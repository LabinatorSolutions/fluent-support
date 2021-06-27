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

        return [
            'new_tickets'    => [
                'title' => 'New Tickets',
                'count' => $newTickets
            ],
            'active_tickets' => [
                'title' => 'Active Tickets',
                'count' => $activeTickets
            ],
            'closed_tickets' => [
                'title' => 'Closed Tickets',
                'count' => $closedTickets
            ],
            'responses'      => [
                'title' => 'Responses',
                'count' => $responses
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
                'title' => 'New Tickets',
                'count' => $newTickets
            ],
            'active_tickets' => [
                'title' => 'Active Tickets',
                'count' => $activeTickets
            ],
            'closed_tickets' => [
                'title' => 'Closed Tickets',
                'count' => $closedTickets
            ],
            'responses'      => [
                'title' => 'Responses',
                'count' => $responses
            ]
        ];
    }

}
