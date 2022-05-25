<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\TagPivot;

class TicketService
{
    /**
     * this function will set the ticket status as closed
     * @param $ticket
     * @param $person
     * @param string $internalNote
     * @return mixed
     */
    public function close($ticket, $person, $internalNote = '')
    {
        if ($ticket->status != 'closed') {
            $ticket->status = 'closed';
            $ticket->resolved_at = current_time('mysql');
            $ticket->closed_by = $person->id;
            $ticket->total_close_time = current_time('timestamp') - strtotime($ticket->created_at);
            $ticket->save();
            do_action('fluent_support/ticket_closed', $ticket, $person);
            do_action('fluent_support/ticket_closed_by_' . $person->person_type, $ticket, $person);

            if(!$internalNote) {
                $internalNote = __('Ticket has been closed', 'fluent-support');
            }

            //Keep track in conversation
            Conversation::create([
                'ticket_id' => $ticket->id,
                'person_id' => $person->id,
                'conversation_type' => 'internal_info',
                'content' => $internalNote
            ]);

        }

        return $ticket;
    }

    public function reopen($ticket, $person)
    {
        if ($ticket->status == 'closed') {
            $ticket->status = 'active';
            $ticket->waiting_since = current_time('mysql');
            $ticket->save();

            /*
             * Action on ticket reopen
             *
             * @since v1.0.0
             * @param object $ticket
             * @param object $person
             */
            do_action('fluent_support/ticket_reopen', $ticket, $person);
            do_action('fluent_support/ticket_reopen_by_' . $person->person_type, $ticket, $person);
            Conversation::create([
                'ticket_id' => $ticket->id,
                'person_id' => $person->id,
                'conversation_type' => 'internal_info',
                'content' => __('Ticket has been reopened', 'fluent-support')
            ]);
        }

        return $person;
    }

    public function onAgentChange($ticket, $person){
        do_action('fluent_support/ticket_agent_change', $ticket, $person);
        Conversation::create([
            'ticket_id' => $ticket->id,
            'person_id' => $person->id,
            'conversation_type' => 'internal_info',
            'content' => $ticket->agent->user_id !== $person->user_id ?
                         __($person->full_name .' assign '. $ticket->agent->full_name .' in this ticket' , 'fluent-support') :
                         __($person->full_name. ' assign this ticket to self', 'fluent-support')
        ]);

        return $person;
    }

    public function syncTicketWatchers($watchers, $ticketId)
    {
        $addWatchers = []; //array of watchers to add

        // Existing watchers
        $existingWatchers = TagPivot::where('source_type', 'ticket_watcher')
            ->where('source_id', $ticketId)
            ->get(['tag_id']);

        // Remove watchers that are not in the list
        // and add watchers that are not in the existing watchers
        foreach ($existingWatchers as $watcher) {
            if (!in_array($watcher->tag_id, $watchers)) {
                TagPivot::where('source_type', 'ticket_watcher')
                    ->where('source_id', $ticketId)
                    ->where('tag_id', $watcher->tag_id)
                    ->delete();
            } else {
                $getExWatcherIds = array_column($existingWatchers->toArray(), 'tag_id');
                $addWatchers = array_diff($watchers, $getExWatcherIds);
            }
        }

        // Add watchers that are not in the existing watchers
        if ($addWatchers) {
            foreach ($addWatchers as $watcherId) {
                TagPivot::create([
                    'tag_id' => $watcherId,
                    'source_id' => $ticketId,
                    'source_type' => 'ticket_watcher'
                ]);
            }
        }

        return [
            'message' => __('Watchers has been updated', 'fluent-support')
        ];
    }
}
