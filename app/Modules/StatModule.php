<?php

namespace FluentSupport\App\Modules;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;

class StatModule
{
    public static function getAgentStat($agentId, $startDate = false, $endDate = false)
    {
        if (!$startDate) {
            $currentDate = current_time('mysql');
            $startDate = $currentDate;
            $endDate = $currentDate;
        }

        $startDate = date('Y-m-d 00:00.01', strtotime($startDate));
        $endDate = date('Y-m-d 23:59.59', strtotime($endDate));

        $newTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'new')
            ->count();

        $activeTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'active')
            ->count();

        $closedTickets = Ticket::where('agent_id', $agentId)
            ->where('status', 'closed')
            ->whereBetween('resolved_at', [$startDate, $endDate])
            ->count();

        $responses = Conversation::where('conversation_type', 'response')
            ->where('person_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

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

    public static function getAgentOverallStats($agentId)
    {
        $replies_count = Conversation::where('person_id', $agentId)->count();

        $interactions_count = Conversation::where('person_id', $agentId)
                                ->where('conversation_type', 'response')
                                ->groupBy('ticket_id')
                                ->get()
                                ->count();

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
