<?php

namespace FluentSupport\App\Modules\Reports;

use FluentSupport\App\App;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

/**
 * Every report number. Tickets and replies are counted only inside the
 * current agent's mailboxes (AgentTicketAccess). The words mean one thing:
 *
 * - new / active / closed: by ticket status; "closed in a range" uses resolved_at.
 * - open: not closed. unassigned: open with no agent.
 * - replies: responses written by agents (customer replies only where named).
 * - interactions: distinct tickets an agent replied to.
 * - waiting: open tickets whose turn is the agent's (Ticket::waitingOnly());
 *   waiting on the customer: open tickets we answered last.
 *
 * Dates are site time, like the stored ones. A range is whole days, both ends included.
 */
class Reports
{
    /**
     * Tickets the viewer may count, narrowed by mailbox_id, product_id,
     * customer_id, agent_id and agent_ids (an empty list narrows nothing).
     */
    public static function tickets(array $filters = [])
    {
        $query = (new AgentTicketAccess())->applyMailboxRestrictionScope(Ticket::query());

        foreach (['mailbox_id', 'product_id', 'customer_id', 'agent_id'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }
        if (!empty($filters['agent_ids'])) {
            $query->whereIn('agent_id', array_map('intval', (array) $filters['agent_ids']));
        }

        return $query;
    }

    /**
     * Responses the viewer may count. $by: 'agent' (default), 'customer' or null
     * for both. agent_id / agent_ids pick the authors; mailbox_id, product_id and
     * customer_id narrow through the ticket.
     */
    public static function replies(array $filters = [], $by = 'agent')
    {
        $query = (new AgentTicketAccess())->applyMailboxRestrictionScopeViaTicket(
            Conversation::query()->where('conversation_type', 'response')
        );

        if ($by) {
            $query->whereIn('person_id', function ($q) use ($by) {
                $q->select('id')->from('fs_persons')->where('person_type', $by);
            });
        }
        if (!empty($filters['agent_id'])) {
            $query->where('person_id', (int) $filters['agent_id']);
        }
        if (!empty($filters['agent_ids'])) {
            $query->whereIn('person_id', array_map('intval', (array) $filters['agent_ids']));
        }

        $ticketFilters = array_filter(array_intersect_key($filters, array_flip(['mailbox_id', 'product_id', 'customer_id'])));
        if ($ticketFilters) {
            $query->whereHas('ticket', function ($q) use ($ticketFilters) {
                foreach ($ticketFilters as $field => $value) {
                    $q->where($field, (int) $value);
                }
            });
        }

        return $query;
    }

    /**
     * Counts per hour, day, week or month for a chart.
     *
     * @param string $metric 'tickets' (created), 'closed' (resolved) or 'replies' (agent replies)
     * @return array<string,int> every bucket of the range, in order
     */
    public static function chart($metric, $from, $to, array $filters = [])
    {
        list($start, $end) = self::range($from, $to, '-30 days');
        $days = (int) $start->diff($end)->days;
        $unit = $days === 0 ? 'hour' : ($days <= 62 ? 'day' : ($days <= 181 ? 'week' : 'month'));

        $column = $metric === 'closed' ? 'resolved_at' : 'created_at';
        $query = $metric === 'replies' ? self::replies($filters) : self::tickets($filters);
        if ($metric === 'closed') {
            $query->where('status', 'closed');
        }

        $rows = $query->whereBetween($column, [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')])
            ->selectRaw(($unit === 'hour' ? "HOUR($column)" : "DATE($column)") . ' AS bucket, COUNT(*) AS total')
            ->groupBy('bucket')
            ->get();

        $label = function ($bucket) use ($unit, $start) {
            if ($unit === 'hour') {
                return sprintf('%02d:00 - %02d:59', $bucket, $bucket);
            }
            $date = new \DateTime($bucket);
            if ($unit === 'week') {
                return (clone $start)->modify('+' . (intdiv((int) $start->diff($date)->days, 7) * 7) . ' days')->format('Y-m-d');
            }
            return $date->format($unit === 'month' ? 'M Y' : 'Y-m-d');
        };

        $chart = [];
        if ($unit === 'hour') {
            foreach (range(0, 23) as $hour) {
                $chart[$label($hour)] = 0;
            }
        } else {
            $step = ['day' => '+1 day', 'week' => '+7 days', 'month' => 'first day of next month'][$unit];
            for ($date = clone $start; $date <= $end; $date->modify($step)) {
                $chart[$label($date->format('Y-m-d'))] = 0;
            }
        }

        foreach ($rows as $row) {
            $key = $label($row->bucket);
            $chart[$key] = ($chart[$key] ?? 0) + (int) $row->total;
        }

        return $chart;
    }

    /**
     * Ticket counts for the Reports overview. Without dates every ticket counts.
     *
     * @return array<string,int> new, active (not with agent_id), closed, replies, open
     */
    public static function overview($from, $to, array $filters = [])
    {
        $inRange = function ($query, $column = 'created_at') use ($from, $to) {
            if ($from) {
                $query->where($column, '>=', $from . ' 00:00:00');
            }
            if ($to) {
                $query->where($column, '<=', $to . ' 23:59:59');
            }
            return $query;
        };

        $counts = [
            'new_tickets'    => $inRange(self::tickets($filters)->where('status', 'new'))->count(),
            'active_tickets' => $inRange(self::tickets($filters)->where('status', 'active'))->count(),
            'closed_tickets' => $inRange(self::tickets($filters)->where('status', 'closed'), 'resolved_at')->count(),
            // Every reply, customers' too; for one agent, the replies they wrote.
            'responses'      => $inRange(self::replies($filters, empty($filters['agent_id']) ? null : 'agent'))->count(),
            'open_tickets'   => $inRange(self::tickets($filters)->where('status', '!=', 'closed'))->count(),
        ];

        if (!empty($filters['agent_id'])) {
            unset($counts['active_tickets']);
        }

        return $counts;
    }

    /**
     * One row per agent (all agents, or $agentIds): closed, replies and
     * interactions in the range (default today), open tickets now, likes and
     * dislikes when feedback is on, and waiting time.
     *
     * @param int[]|null $agentIds
     * @return \FluentSupport\Framework\Support\Collection agents with ->stats and ->active_stat
     */
    public static function agentSummary($from, $to, $agentIds = null)
    {
        list($start, $end) = self::range($from, $to, '+0 days');
        $between = [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')];

        $agentsQuery = Agent::select(['id', 'first_name', 'last_name', 'email']);
        if ($agentIds) {
            $agentsQuery->whereIn('id', array_map('intval', $agentIds));
        }
        $agents = $agentsQuery->get();
        $ids = $agents->pluck('id')->all();

        $byAgent = function ($query, $column = 'agent_id', $count = 'COUNT(*)') use ($ids) {
            return $query->whereIn($column, $ids ?: [0])
                ->selectRaw("$column AS agent_id, $count AS total")
                ->groupBy($column)
                ->pluck('total', 'agent_id')
                ->all();
        };

        $stats = [
            'closed'       => $byAgent(self::tickets()->where('status', 'closed')->whereBetween('resolved_at', $between)),
            'opens'        => $byAgent(self::tickets()->where('status', '!=', 'closed')),
            'responses'    => $byAgent(self::replies()->whereBetween('created_at', $between), 'person_id'),
            'interactions' => $byAgent(self::replies()->whereBetween('created_at', $between), 'person_id', 'COUNT(DISTINCT ticket_id)'),
        ];

        $feedback = defined('FLUENTSUPPORTPRO_PLUGIN_VERSION') && Helper::isAgentFeedbackEnabled();
        if ($feedback) {
            $p = App::getInstance('db')->getTablePrefix();
            $ratings = self::replies()
                ->whereIn('person_id', $ids ?: [0])
                ->join('fs_meta', 'fs_meta.object_id', '=', 'fs_conversations.id')
                ->where('fs_meta.object_type', 'conversation_meta')
                ->where('fs_meta.key', 'agent_feedback_ratings')
                ->whereBetween('fs_meta.created_at', $between)
                ->selectRaw("{$p}fs_conversations.person_id AS agent_id, SUM({$p}fs_meta.value = 'like') AS likes, SUM({$p}fs_meta.value = 'dislike') AS dislikes")
                ->groupBy('fs_conversations.person_id')
                ->get()
                ->keyBy('agent_id');
        }

        $waiting = self::waitStatsBy('agent_id', ['agent_ids' => $ids]);

        foreach ($agents as $agent) {
            $row = ['interactions' => 0, 'responses' => 0, 'opens' => 0, 'closed' => 0, 'waiting_tickets' => 0];
            foreach ($stats as $key => $counts) {
                $row[$key] = (int) ($counts[$agent->id] ?? 0);
            }
            if ($feedback) {
                $row['likes'] = (int) ($ratings[$agent->id]->likes ?? 0);
                $row['dislikes'] = (int) ($ratings[$agent->id]->dislikes ?? 0);
            }
            $agent->stats = $row;
            $agent->active_stat = $waiting[$agent->id] ?? self::formatWaiting(null);
        }

        return $agents;
    }

    const WAIT_COLUMNS = 'AVG(UNIX_TIMESTAMP(waiting_since)) AS avg_since, MIN(UNIX_TIMESTAMP(waiting_since)) AS oldest_since, COUNT(*) AS total';

    /**
     * Open tickets waiting on an agent, from $tickets.
     */
    public static function waiting($tickets)
    {
        return $tickets->waitingOnly()->where('status', '!=', 'closed')->whereNotNull('waiting_since');
    }

    /**
     * Open tickets we answered last, waiting on the customer, from $tickets.
     */
    public static function waitingOnCustomer($tickets)
    {
        return $tickets->where('status', '!=', 'closed')->whereNotNull('waiting_since')->whereColumn('last_customer_response', '<', 'last_agent_response');
    }

    /**
     * Count, average, median, max and min (seconds) of an SQL duration over a
     * query's rows. The median uses the latest 500 rows.
     *
     * @param string $expression a column such as first_response_time, or SQL
     *                           with ? placeholders for $bindings
     */
    public static function durations($query, $expression, array $bindings = [])
    {
        $row = (clone $query)->selectRaw("COUNT(*) AS total, AVG($expression) AS avg_val, MAX($expression) AS max_val, MIN($expression) AS min_val", array_merge($bindings, $bindings, $bindings))->first();
        $count = $row ? (int) $row->total : 0;

        $sample = $count ? (clone $query)->selectRaw("$expression AS seconds", $bindings)->orderByDesc('id')->limit(500)->pluck('seconds')->all() : [];
        sort($sample);
        $middle = intdiv(count($sample), 2);
        $median = !$sample ? 0 : (count($sample) % 2 ? $sample[$middle] : ($sample[$middle - 1] + $sample[$middle]) / 2);

        return [
            'count'   => $count,
            'average' => $count ? (float) $row->avg_val : 0,
            'median'  => (float) $median,
            'max'     => $count ? (float) $row->max_val : 0,
            'min'     => $count ? (float) $row->min_val : 0,
        ];
    }

    /**
     * Average and longest wait and the number of waiting tickets.
     *
     * @return array{average_waiting: string|int, max_waiting: string|int, waiting_tickets: int}
     */
    public static function waitStats(array $filters = [])
    {
        return self::formatWaiting(self::waiting(self::tickets($filters))->selectRaw(self::WAIT_COLUMNS)->first());
    }

    /**
     * waitStats() per value of $column (agent_id, mailbox_id, product_id).
     * Values with no waiting ticket have no entry.
     *
     * @return array<int, array> keyed by the column's value
     */
    public static function waitStatsBy($column, array $filters = [])
    {
        $rows = self::waiting(self::tickets($filters))
            ->whereNotNull($column)
            ->selectRaw("$column AS group_id, " . self::WAIT_COLUMNS)
            ->groupBy($column)
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->group_id] = self::formatWaiting($row);
        }

        return $stats;
    }

    /**
     * Open tickets with no agent.
     */
    public static function unassigned(array $filters = [])
    {
        return self::tickets($filters)->whereNull('agent_id')->where('status', '!=', 'closed');
    }

    private static function formatWaiting($row)
    {
        $avg = $row ? (int) $row->avg_since : 0;
        $oldest = $row ? (int) $row->oldest_since : 0;
        $seconds = time() - $avg;

        return [
            // Between 2 and 48 hours the average reads better in hours.
            'average_waiting' => $avg ? ($seconds > 7200 && $seconds < 172800 ? ceil($seconds / 3600) . ' hours' : human_time_diff($avg, time())) : 0,
            'max_waiting'     => $oldest ? human_time_diff($oldest, time()) : 0,
            'waiting_tickets' => $row ? (int) $row->total : 0,
        ];
    }

    /**
     * Counts by weekday and hour: ['Mon' => ['0:00' => n, ... '23:00' => n], ...].
     *
     * @param string $type 'ticket', 'agent_response' or 'customer_response'
     */
    public static function weekdayHours($type, $from, $to, array $filters = [])
    {
        if ($type === 'ticket') {
            $query = self::tickets($filters);
        } else {
            $query = self::replies($filters, $type === 'customer_response' ? 'customer' : 'agent');
        }
        if ($from && $to) {
            $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        }

        $hours = array_fill_keys(array_map(fn($hour) => $hour . ':00', range(0, 23)), 0);
        $grid = array_fill_keys(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $hours);

        $rows = $query->selectRaw('DAYOFWEEK(created_at) AS weekday, HOUR(created_at) AS hour, COUNT(*) AS total')
            ->groupBy('weekday', 'hour')
            ->get();
        foreach ($rows as $row) {
            // DAYOFWEEK: 1 = Sunday.
            $day = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $row->weekday - 1];
            $grid[$day][$row->hour . ':00'] = (int) $row->total;
        }

        return $grid;
    }

    /**
     * The dashboard cards for one agent: their new and active tickets, and
     * today's closed tickets, replies and tickets replied to.
     *
     * @return array<string,array{title:string,count:int}>
     */
    public static function agentToday($agentId)
    {
        $today = [current_time('Y-m-d') . ' 00:00:00', current_time('Y-m-d') . ' 23:59:59'];
        $mine = ['agent_id' => $agentId];

        return [
            'new_tickets'    => ['title' => __('New Tickets', 'fluent-support'), 'count' => self::tickets($mine)->where('status', 'new')->count()],
            'active_tickets' => ['title' => __('Active Tickets', 'fluent-support'), 'count' => self::tickets($mine)->where('status', 'active')->count()],
            'closed_tickets' => ['title' => __('Closed Tickets', 'fluent-support'), 'count' => self::tickets($mine)->where('status', 'closed')->whereBetween('resolved_at', $today)->count()],
            'responses'      => ['title' => __('Total Responses', 'fluent-support'), 'count' => self::replies($mine)->whereBetween('created_at', $today)->count()],
            'interactions'   => ['title' => __('Interactions', 'fluent-support'), 'count' => (int) self::replies($mine)->whereBetween('created_at', $today)->selectRaw('COUNT(DISTINCT ticket_id) AS total')->value('total')],
        ];
    }

    /**
     * Today's waiting, new, closed and reply counts for every agent who replied
     * today or has tickets waiting (the dashboard's Agent Performance).
     */
    public static function agentsToday()
    {
        $today = [current_time('Y-m-d') . ' 00:00:00', current_time('Y-m-d') . ' 23:59:59'];
        $agents = Agent::get();
        $ids = $agents->pluck('id')->all();
        if (!$ids) {
            return [];
        }

        $count = function ($query, $column = 'agent_id') {
            return $query->selectRaw("$column AS grouped_id, COUNT(*) AS total")->groupBy($column)->pluck('total', 'grouped_id')->all();
        };
        $waiting = $count(self::awaiting(['agent_ids' => $ids]));
        $new = $count(self::tickets(['agent_ids' => $ids])->whereBetween('created_at', $today));
        $closed = $count(self::tickets(['agent_ids' => $ids])->where('status', 'closed')->whereBetween('resolved_at', $today));
        $replies = $count(self::replies(['agent_ids' => $ids])->whereBetween('created_at', $today), 'person_id');

        $rows = [];
        foreach ($agents as $agent) {
            $waitingCount = (int) ($waiting[$agent->id] ?? 0);
            $replyCount = (int) ($replies[$agent->id] ?? 0);
            if (!$waitingCount && !$replyCount) {
                continue;
            }

            $rows[] = [
                'agent_id'    => $agent->id,
                'agent_name'  => $agent->full_name,
                'agent_email' => $agent->email,
                'avatar'      => $agent->photo,
                'stats'       => [
                    'waiting_today'  => ['title' => __('Waiting Today', 'fluent-support'), 'count' => $waitingCount],
                    'new_tickets'    => ['title' => __('New Tickets', 'fluent-support'), 'count' => (int) ($new[$agent->id] ?? 0)],
                    'closed_tickets' => ['title' => __('Closed Tickets', 'fluent-support'), 'count' => (int) ($closed[$agent->id] ?? 0)],
                    'responses'      => ['title' => __('Responses', 'fluent-support'), 'count' => $replyCount],
                ]
            ];
        }

        return $rows;
    }

    /**
     * Open tickets awaiting an agent reply (no reply yet, the customer wrote
     * last, or still new).
     */
    public static function awaiting(array $filters = [])
    {
        return self::tickets($filters)
            ->where('status', '!=', 'closed')
            ->where(function ($query) {
                $query->whereColumn('last_agent_response', '<', 'last_customer_response')
                    ->orWhereNull('last_agent_response')
                    ->orWhere('status', 'new');
            });
    }

    /**
     * Awaiting tickets per product: [product_id => ['title' => ..., 'count' => n]].
     */
    public static function awaitingByProduct()
    {
        $products = Product::select(['id', 'title'])->get();
        $counts = self::awaiting()->whereIn('product_id', $products->pluck('id')->all() ?: [0])
            ->selectRaw('product_id, COUNT(*) AS total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->all();

        $result = [];
        foreach ($products as $product) {
            $result[$product->id] = ['title' => $product->title, 'count' => (int) ($counts[$product->id] ?? 0)];
        }

        return $result;
    }

    /**
     * [start, end] DateTime days for a range; $default fills a missing start,
     * today a missing end.
     */
    private static function range($from, $to, $default)
    {
        $start = new \DateTime($from ?: current_time('Y-m-d') . ' ' . $default);
        $end = new \DateTime($to ?: current_time('Y-m-d'));
        $start->setTime(0, 0);
        $end->setTime(0, 0);

        return [$start, max($start, $end)];
    }
}
