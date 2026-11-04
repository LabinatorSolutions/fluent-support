<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

class TicketHelper
{
    /**
     * Above this many matches, suggested tickets are sorted in SQL. See firstIdsBy().
     */
    const SORT_IN_PHP_MAX = 1000;

    /**
     * Agents seen on the ticket in the last 60 seconds. Passing $currentAgentId also
     * records that agent as active now.
     *
     * @param int $ticketId
     * @param int|false $currentAgentId
     * @return array|\FluentSupport\Framework\Support\Collection
     */
    public static function getActivity($ticketId, $currentAgentId = false)
    {
        $meta = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->where('object_id', $ticketId)
            ->first();

        $activities = [];
        if ($meta) {
            $activities = Helper::safeUnserialize($meta->value);
        }

        foreach ($activities as $index => $activity) {
            if ((time() - $activity) > 60) {
                unset($activities[$index]);
            }
        }

        if (!$currentAgentId) {
            return self::getAgentsInfoFromActivities($activities);
        }

        $activities[$currentAgentId] = time();

        if ($meta) {
            $meta->value = maybe_serialize($activities);
            $meta->save();
        } else {
            Meta::insert([
                'object_type' => 'ticket_meta',
                'key' => '_live_activity',
                'object_id' => $ticketId,
                'value' => maybe_serialize($activities)
            ]);
        }

        return self::getAgentsInfoFromActivities($activities);
    }

    private static function getAgentsInfoFromActivities($activities)
    {
        if (!$activities) {
            return [];
        }

        return Agent::select(['id', 'email', 'first_name', 'last_name'])
            ->whereIn('id', array_keys($activities))
            ->get();
    }

    /**
     * Batch version of getActivity() for a list page: one query for metas, one for agents.
     *
     * @param \FluentSupport\Framework\Support\Collection $tickets Collection of Ticket models
     * @return void
     */
    public static function loadBatchLiveActivities($tickets)
    {
        $activeTicketIds = $tickets->where('status', '!=', 'closed')->pluck('id')->toArray();

        if (!$activeTicketIds) {
            foreach ($tickets as $ticket) {
                $ticket->live_activity = [];
            }
            return;
        }

        $activityMetas = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->whereIn('object_id', $activeTicketIds)
            ->get()
            ->keyBy('object_id');

        $allAgentIds = [];
        $ticketActivities = [];

        foreach ($activityMetas as $ticketId => $meta) {
            $activities = Helper::safeUnserialize($meta->value);
            if (!is_array($activities)) {
                continue;
            }

            $activities = array_filter($activities, function ($time) {
                return (time() - $time) <= 60;
            });

            $ticketActivities[$ticketId] = $activities;
            $allAgentIds = array_merge($allAgentIds, array_keys($activities));
        }

        $allAgentIds = array_unique(array_filter($allAgentIds));
        $agents = $allAgentIds
            ? Agent::select(['id', 'email', 'first_name', 'last_name'])
                ->whereIn('id', $allAgentIds)
                ->get()
                ->keyBy('id')
            : [];

        $activitiesByTicket = [];
        foreach ($ticketActivities as $ticketId => $activities) {
            $ticketAgents = [];
            foreach (array_keys($activities) as $agentId) {
                if (isset($agents[$agentId])) {
                    $ticketAgents[] = $agents[$agentId];
                }
            }
            $activitiesByTicket[$ticketId] = $ticketAgents;
        }

        foreach ($tickets as $ticket) {
            $ticket->live_activity = $activitiesByTicket[$ticket->id] ?? [];
        }
    }

    /**
     * Drop the agent's live activity on the ticket, plus any expired entries.
     *
     * @param int $ticketId
     * @param int $agentId
     * @return bool
     */
    public static function removeFromActivities($ticketId, $agentId)
    {
        $meta = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->where('object_id', $ticketId)
            ->first();

        $activities = [];
        if ($meta) {
            $activities = Helper::safeUnserialize($meta->value);
        }

        if (!$activities) {
            return false;
        }

        unset($activities[$agentId]);
        foreach ($activities as $index => $activity) {
            if ((time() - $activity) > 60) {
                unset($activities[$index]);
            }
        }

        $meta->value = maybe_serialize($activities);
        $meta->save();

        return true;
    }

    /**
     * The agent's tickets waiting for a reply, or unassigned open tickets when there are none
     * and the user can manage unassigned tickets.
     *
     * @param int $agentId
     * @param int $limit
     * @return mixed
     */
    public static function getSuggestedTickets($agentId, $limit = 5)
    {
        $restrictedBusinessBoxes = PermissionManager::getRestrictedMailboxIds();

        $inAllowedMailbox = function ($q) use ($restrictedBusinessBoxes) {
            $q->whereNotIn('mailbox_id', $restrictedBusinessBoxes);
            $q->orWhereNull('mailbox_id');
        };

        $waitingQuery = Ticket::where('agent_id', $agentId)
            ->where($inAllowedMailbox)
            ->where('status', '!=', 'closed')
            ->waitingOnly();

        // NULL sorts first, as with ORDER BY ... ASC.
        $waiting = static::firstIdsBy($waitingQuery, ['last_customer_response', 'id'], $limit);

        if ($waiting) {
            return static::showCardFields(static::ticketsInOrder($waiting, ['customer', 'mailbox', 'tags', 'agent']));
        }

        if (!PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            return new \FluentSupport\Framework\Support\Collection([]);
        }

        $unassignedQuery = Ticket::where('status', '!=', 'closed')
            ->where($inAllowedMailbox)
            ->where(function ($q) {
                $q->whereNull('agent_id');
                $q->orWhere('agent_id', '0');
            });

        $unassigned = static::firstIdsBy($unassignedQuery, ['id'], $limit);

        return static::showCardFields(static::ticketsInOrder($unassigned, ['customer', 'mailbox', 'tags']));
    }

    /**
     * Ids of the first $limit rows of $query in ascending $columns order.
     *
     * When few rows match, ORDER BY ... LIMIT makes MySQL walk the whole table
     * in sort order, so up to SORT_IN_PHP_MAX matches are sorted here. When many
     * match, that walk ends quickly and loading them all would not, so SQL sorts.
     *
     * @param \FluentSupport\Framework\Database\Orm\Builder $query
     * @param string[] $columns
     * @param int $limit
     * @return int[]
     */
    protected static function firstIdsBy($query, array $columns, $limit)
    {
        $matches = (clone $query)->count();

        if (!$matches) {
            return [];
        }

        if ($matches > static::SORT_IN_PHP_MAX) {
            foreach ($columns as $column) {
                $query->orderBy($column, 'ASC');
            }

            return array_map('intval', $query->limit($limit)->pluck('id')->all());
        }

        $rows = $query->get(array_unique(array_merge(['id'], $columns)))->all();

        usort($rows, function ($a, $b) use ($columns) {
            foreach ($columns as $column) {
                $left = $column === 'id' ? (int) $a->id : (string) $a->{$column};
                $right = $column === 'id' ? (int) $b->id : (string) $b->{$column};
                if ($left != $right) {
                    return $left < $right ? -1 : 1;
                }
            }
            return 0;
        });

        return array_map(function ($row) {
            return (int) $row->id;
        }, array_slice($rows, 0, $limit));
    }

    /**
     * Tickets by id with relations, in the order of $ids.
     *
     * @param int[] $ids
     * @param array $with
     * @return \FluentSupport\Framework\Support\Collection
     */
    protected static function ticketsInOrder(array $ids, array $with)
    {
        if (!$ids) {
            return new \FluentSupport\Framework\Support\Collection([]);
        }

        $position = array_flip(array_map('intval', array_values($ids)));

        return Ticket::whereIn('id', $ids)
            ->with($with)
            ->get()
            ->sortBy(function ($ticket) use ($position) {
                return $position[(int) $ticket->id];
            })
            ->values();
    }

    /**
     * Limit the dashboard cards' people and inbox to what the cards show. The
     * customer keeps their email, which the card shows when there is no name and
     * the ticket view shows anyway; the IP, address and the agents' note stay out.
     * The assigned agent may be a colleague, so it gets display fields only.
     *
     * @param \FluentSupport\Framework\Support\Collection $tickets
     * @return \FluentSupport\Framework\Support\Collection
     */
    protected static function showCardFields($tickets)
    {
        foreach ($tickets as $ticket) {
            if ($ticket->customer) {
                $ticket->customer->setVisible(['id', 'first_name', 'last_name', 'full_name', 'photo', 'email']);
            }
            if ($ticket->relationLoaded('agent') && $ticket->agent) {
                $ticket->agent->setVisible(['id', 'first_name', 'last_name', 'full_name', 'photo']);
            }
            if ($ticket->mailbox) {
                $ticket->mailbox->setVisible(['id', 'name']);
            }
        }

        return $tickets;
    }

    public static function getWatcherTicketIds($agentId)
    {
        $mentioned  = TagPivot::where('source_type', 'ticket_watcher')
            ->where('tag_id', $agentId)
            ->latest('id')
            ->get(['source_id']);

        $ticketIds = array_column($mentioned->toArray(), 'source_id');
        return $ticketIds;
    }

    // Up to 5 tickets the current agent is watching.
    public static function getTicketsToWatch()
    {
        $agent = Helper::getCurrentAgent();
        $restrictedBusinessBoxes = PermissionManager::getRestrictedMailboxIds();

        $tickets = Ticket::with(
            [
                'customer', 
                'mailbox',
                'tags',
                'agent'
            ])
            ->limit(5)
            ->where(function ($q) use ($restrictedBusinessBoxes) {
                $q->whereNotIn('mailbox_id', $restrictedBusinessBoxes);
                $q->orWhereNull('mailbox_id');
            })
            ->join('fs_tag_pivot', 'fs_tag_pivot.source_id', '=', 'fs_tickets.id')
            ->where('fs_tag_pivot.source_type', '=', 'ticket_watcher')
            ->where('fs_tag_pivot.tag_id', '=', $agent->id)
            ->select(['fs_tickets.*'])
            ->get();

        return static::showCardFields($tickets);
    }

    public static function getWatchers($watchers)
    {
        $watcherAgents = [];
        $agentIds = [];

        foreach ($watchers as $watcher) {
            $agentIds[] = absint($watcher->tag_id);
        }

        if (!$agentIds) {
            return $watcherAgents;
        }

        // email and avatar only build the photo URL; neither is sent to the browser.
        $agents = Agent::whereIn('id', $agentIds)
            ->select(['id', 'first_name', 'last_name', 'email', 'avatar'])
            ->get()
            ->keyBy('id');

        foreach ($agentIds as $agentId) {
            if (isset($agents[$agentId])) {
                $watcherAgents[] = $agents[$agentId]->makeHidden(['email', 'avatar']);
            }
        }

        return $watcherAgents;
    }

    /** @internal Not called from core controllers — available for Pro/hook usage. */
    public static function getCarbonCopyCustomerInfo($ticketId){
        $existing = Meta::where('object_type', 'beginning_cc_info')->where('object_id', $ticketId)->first();
        if($existing){
            return Helper::safeUnserialize($existing->value, []);
        }

        return [];
    }

    /**
     * Ticket counts by status for the current user, among the tickets they can
     * see in the list (visibility and mailbox restrictions).
     *
     * @return array{total:int, new:int, active:int, closed:int, unassigned:int}
     */
    public static function getStatusCounts()
    {
        $access = new AgentTicketAccess();

        $byStatus = $access->applyListScope(
            Ticket::query()->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')
        )->get()->pluck('total', 'status')->toArray();

        $unassigned = $access->applyListScope(Ticket::query()->whereNull('agent_id'))->count();

        return [
            'total'      => (int) array_sum($byStatus),
            'new'        => (int) ($byStatus['new'] ?? 0),
            'active'     => (int) ($byStatus['active'] ?? 0),
            'closed'     => (int) ($byStatus['closed'] ?? 0),
            'unassigned' => (int) $unassigned,
        ];
    }

    public static function getLabelSearch($agentId)
    {
        $lists = Meta::where('object_id', $agentId)
                        ->where('object_type', 'search_meta')
                        ->where('key', 'label_search')
                        ->first();
        $unserialize = [];
        if ($lists) {
            $unserialize = Helper::safeUnserialize($lists->value);
        }

        return $unserialize;
    }

    public static function saveSearchLabel($agent_id, $searchData, $filterType) {
        
        $agent_id = get_current_user_id();
        $existingRecord = Meta::where('object_id', $agent_id)
                                ->where('object_type', 'search_meta')
                                ->where('key', 'label_search')
                                ->first();

        $existingData = $existingRecord ? Helper::safeUnserialize($existingRecord->value) ?: [] : [];

        $isUpdate = isset($searchData['id']) && array_filter($existingData, function ($item) use ($searchData) {
            return isset($item['id']) && $item['id'] == $searchData['id'];
        });

        $updatedData = self::addOrUpdateSearchData($existingData, $searchData);

        if ($existingRecord) {
            $existingRecord->update([
                'value' => maybe_serialize($updatedData)
            ]);
        } else {
            Meta::insert([
                'object_type' => 'search_meta',
                'key'         => 'label_search',
                'object_id'   => $agent_id,
                'value'       => maybe_serialize($updatedData)
            ]);
        }

        $message = $isUpdate 
        ? __('Your saved search has been updated successfully!', 'fluent-support') 
        : __('Your search has been saved successfully!', 'fluent-support');

        return [
            'message' => $message,
        ];
    }

    private static function addOrUpdateSearchData(array $existingData, array $searchData): array
    {
        if (isset($searchData['id'])) {
            $updated = false;

            foreach ($existingData as &$record) {
                if (isset($record['id']) && $record['id'] == $searchData['id']) {
                    $record = array_merge($record, $searchData);
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $existingData[] = $searchData;
            }
        } else {
            $newId = self::generateNewId($existingData);
            $searchData['id'] = $newId;
            $existingData[] = $searchData;
        }

        return $existingData;
    }

    public static function deleteSavedSearch($search_id) {
        $agent_id = get_current_user_id();
        $existingRecord = Meta::where('object_id', $agent_id)
                                ->where('object_type', 'search_meta')
                                ->where('key', 'label_search')
                                ->first();

        if ($existingRecord) {
            $existingData = Helper::safeUnserialize($existingRecord->value) ?: [];

            $updatedData = array_filter($existingData, function ($item) use ($search_id) {
                return isset($item['id']) && $item['id'] != $search_id;
            });

            if (count($existingData) !== count($updatedData)) {
                $existingRecord->update([
                    'value' => maybe_serialize(array_values($updatedData))
                ]);
                return [
                    'message' => __('Search deleted successfully.', 'fluent-support'),
                ];
            }

            return [
                'message' => __('Search ID not found.', 'fluent-support'),
            ];
        }
    }

    private static function generateNewId(array $existingData): int
    {
        if (empty($existingData)) {
            return 1;
        }

        $maxId = max(array_column($existingData, 'id'));
        return $maxId + 1;
    }
}
