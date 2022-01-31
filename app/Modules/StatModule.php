<?php

namespace FluentSupport\App\Modules;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
/**
 * StatModule class is responsible for getting data related to report
 * @package FluentSupport\App\Modules
 *
 * @version 1.0.0
 */
class StatModule
{
    /**
     * getAgentStat method will return ticket statistics by an agent id
     * This method will get agent id, start date and end date as parameter and fetch the ticket information and return
     * @param $agentId
     * @param false $startDate
     * @param false $endDate
     * @return array[]
     */
    public static function getAgentStat($agentId, $startDate = false, $endDate = false)
    {
        if (!$startDate) {
            $currentDate = current_time('mysql');
            $startDate = $currentDate;
            $endDate = $currentDate;
        }

        $startDate = date('Y-m-d 00:00.01', strtotime($startDate));
        $endDate = date('Y-m-d 23:59.59', strtotime($endDate));

        //Get list of new ticket by agent
        $newTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'new')
            ->count();

        //Get list of active ticket by agent
        $activeTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'active')
            ->count();

        //Get list of closed ticket by agent within a date range(default today)
        $closedTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'closed')
            ->whereBetween('resolved_at', [$startDate, $endDate])
            ->count();

        //Get list of response by agent id within a date range(default today)
        $responses = Conversation::where('conversation_type', 'response')
            ->where('person_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        //Count the response in tickets within a date range(default today) for agent
        $interactions = Conversation::where('person_id', $agentId)
            ->where('conversation_type', 'response')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('ticket_id')
            ->get()
            ->count();

        return [
            'new_tickets'    => [
                'title' => __('New Tickets', 'fluent-support'),
                'count' => $newTickets
            ],
            'active_tickets' => [
                'title' => __('Active Tickets', 'fluent-support'),
                'count' => $activeTickets
            ],
            'closed_tickets' => [
                'title' => __('Closed Tickets', 'fluent-support'),
                'count' => $closedTickets
            ],
            'responses'      => [
                'title' => __('Responses', 'fluent-support'),
                'count' => $responses
            ],
            'interactions'  =>[
                'title' => __('Interactions', 'fluent-support'),
                'count' => $interactions
            ]
        ];
    }

    /**
     * getOverAllStats method will return the overall statistics for all tickets
     * This method will count the ticket number by ticket status and return the array
     * @return array[]
     */
    public static function getOverAllStats()
    {
        $newTickets = Ticket::where('status', 'new')
            ->count();

        $activeTickets = Ticket::where('status', 'active')
            ->count();

        $closedTickets = Ticket::where('status', 'closed')
            ->count();

        $responses = Conversation::where('conversation_type', 'response')
            ->count();

        return [
            'new_tickets'    => [
                'title' => __('New Tickets', 'fluent-support'),
                'count' => $newTickets
            ],
            'active_tickets' => [
                'title' => __('Active Tickets', 'fluent-support'),
                'count' => $activeTickets
            ],
            'closed_tickets' => [
                'title' => __('Closed Tickets', 'fluent-support'),
                'count' => $closedTickets
            ],
            'responses'      => [
                'title' => __('Responses', 'fluent-support'),
                'count' => $responses
            ]
        ];
    }

    /**
     * getAgentOverallStats method will produce overall statistics by agent
     * @param $agentId
     * @return array[]
     */
    public static function getAgentOverallStats($agentId)
    {
        //Get count of response by the agent
        $replies_count = Conversation::where('person_id', $agentId)->count();

        //Get the number of interactions/responses by agent with tickets
        $interactions_count = Conversation::where('person_id', $agentId)
                                ->where('conversation_type', 'response')
                                ->groupBy('ticket_id')
                                ->get()
                                ->count();

        //Get the number of tickets that are closed by this agent
        $total_closed = Ticket::where('agent_id', $agentId)->where('status', 'closed')->count();

        return [
            'replies_count' =>  [
                'title' => __('Total Replies', 'fluent-support'),
                'count' => $replies_count
            ],
            'interactions_count' => [
                'title' => __('Total Interactions', 'fluent-support'),
                'count' => $interactions_count
            ],
            'total_closed' => [
                'title' => __('Total Closed', 'fluent-support'),
                'count' => $total_closed
            ]
        ];
    }

}
