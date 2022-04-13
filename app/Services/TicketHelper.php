<?php

namespace FluentSupport\App\Services;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

class TicketHelper
{
    /**
     * getActivity method will return the activity in a ticket by agent
     * @param $ticketId
     * @param false $currentAgentId
     * @return array
     */
    public static function getActivity($ticketId, $currentAgentId = false)
    {
        //Get the ticket meta information
        $meta = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_live_activity')
            ->where('object_id', $ticketId)
            ->first();

        $activities = [];
        if ($meta) {
            $activities = maybe_unserialize($meta->value);
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

    public static function getAgentsInfoFromActivities($activities)
    {
        if (!$activities) {
            return [];
        }

        return Agent::select(['email', 'first_name', 'last_name'])
            ->whereIn('id', array_keys($activities))
            ->get();
    }

    /**
     * removeFromActivities method will remove the old live activity by agent and ticket id
     * @param $ticketId
     * @param $agentId
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
            $activities = maybe_unserialize($meta->value);
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
     * getSuggestedTickets method will return the list of Suggested tickets
     * This method will get the agent id as parameter and fetch ticket information that are waiting to reply or unassigned
     * @param $agentId
     * @param int $limit
     * @return mixed
     */
    public static function getSuggestedTickets($agentId, $limit = 5)
    {
        //Get lis of tickets which are waiting for reply
        $tickets = Ticket::where('agent_id', $agentId)
            ->where('status', '!=', 'closed')
            ->applyFilters([
                'waiting_for_reply' => 'yes'
            ])
            ->orderBy('last_customer_response', 'ASC')
            ->limit($limit)
            ->with('customer')
            ->get();

        //If no ticket is available for reply and logged-in user has permission to manage unassigned tickets
        if($tickets->isEmpty() && PermissionManager::currentUserCan('fst_manage_unassigned_tickets')) {
            //Get the ticket list which status is not closed and agent id is null or 0
            $tickets = Ticket::where('status', '!=', 'closed')
                ->orderBy('id', 'ASC')
                ->where(function ($q) {
                    $q->whereNull('agent_id');
                    $q->orWhere('agent_id', '0');
                })
                ->with('customer')
                ->limit($limit)
                ->get();
        }


        return $tickets;

    }

    public static function getMentionedAgents($ticketId){
        $mentioned  = TagPivot::select('source_id')->where('tag_id', $ticketId)
            ->where('source_type', '_mentioned_agent_to_ticket')
            ->orderBy('id', 'DESC')
            ->get();

        $agentIds = [];

        if($mentioned){
            foreach ($mentioned as $id){
                $agentIds[] = $id->source_id;
            }
        }

        return Agent::select(['id','email', 'first_name', 'last_name'])
            ->whereIn('id', $agentIds)
            ->get();
    }

    public static function getMentionedTicketIds($agentId){
        $mentioned  = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_mentioned_agent_to_ticket')
            ->orderBy('id', 'DESC')
            ->get();

        $ticketIds = [];

        if(!empty($mentioned)){
            foreach ($mentioned as $row){
                if(!empty($row->value) && !empty($row->object_id)){
                    $val = maybe_unserialize($row->value);

                    if(is_array($val) && in_array($agentId, $val)){
                        $ticketIds[] = $row->object_id;
                    }
                }
            }
        }

        return $ticketIds;
    }

    public static function getMentionedTickets($agentId, $limit = 5){
        $mentioned  = Meta::where('object_type', 'ticket_meta')
            ->where('key', '_mentioned_agent_to_ticket')
            ->orderBy('id', 'DESC')
            ->get();

        $tickets = [];
        $count  = 0;
        if(!empty($mentioned)){
            foreach ($mentioned as $row){
                if($count < $limit){
                    if(!empty($row->value) && !empty($row->object_id)){
                        $val = maybe_unserialize($row->value);

                        if(is_array($val) && in_array($agentId, $val)){
                            $tickets[] = Ticket::with('customer')->find($row->object_id);
                            $count++;
                        }
                    }
                }else {
                    break;
                }
            }
        }

        return $tickets;
    }
}
