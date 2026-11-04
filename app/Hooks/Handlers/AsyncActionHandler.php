<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\TicketService;

class AsyncActionHandler
{
    public function resolveAgentAssigned($agentId, $ticketId, $assignerId, $previousAgentId = 0)
    {
        $agent = Agent::find($agentId);
        $ticket = Ticket::find($ticketId);
        $assigner = Agent::find($assignerId);

        // Skip when the ticket moved on to someone else before the job ran.
        if ($agent && $ticket && (int) $ticket->agent_id === (int) $agent->id) {
            (new TicketService())->afterAgentAssigned($ticket, $assigner ?: $agent, $previousAgentId);
        }
    }
}
