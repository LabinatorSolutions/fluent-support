<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Response;

class TicketService
{
    public function close($ticket, $person)
    {
        if ($ticket->status != 'closed') {
            $ticket->status = 'closed';
            $ticket->resolved_at = current_time('mysql');
            $ticket->closed_by = $person->id;
            $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
            $ticket->save();
            do_action('fluent_support/ticket_closed', $ticket, $person);
            do_action('fluent_support/ticket_closed_by_' . $person->person_type, $ticket, $person);

            Response::create([
                'ticket_id' => $ticket->id,
                'person_id' => $person->id,
                'conversation_type' => 'internal_info',
                'content' => __('Ticket has been closed', 'fluent-support')
            ]);

        }

        return $ticket;
    }

    public function reopen($ticket, $person)
    {
        if ($ticket->status == 'closed') {
            $ticket->status = 'active';
            $ticket->save();
            do_action('fluent_support/ticket_reopen', $ticket, $person);
            do_action('fluent_support/ticket_reopen_by_' . $person->person_type, $ticket, $person);
            Response::create([
                'ticket_id' => $ticket->id,
                'person_id' => $person->id,
                'conversation_type' => 'internal_info',
                'content' => __('Ticket has been reopened', 'fluent-support')
            ]);
        }

        return $person;
    }
}
