<?php

namespace FluentSupport\App\Services\MailerInbox;

use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Support\Arr;

/**
 * Moving tickets between mailboxes: the mailbox screen, the ticket view's
 * mailbox switcher, MCP and Pro workflows. The callers check the current user's
 * own access to the new mailbox; this checks the tickets' agents.
 */
class MailBoxService
{
    /**
     * Moves all tickets (move_type All) or the given ticket_ids (any other move_type) of mailbox
     * $mailBoxId to $data['new_box_id']. Only tickets in $mailBoxId move, and none
     * moves into a mailbox its agent is restricted from.
     *
     * Fires fluent_support/before_move_tickets and fluent_support/tickets_moved
     * ($data, $oldBox, $newBox).
     *
     * @param array $data new_box_id, move_type, ticket_ids
     * @param int $mailBoxId The mailbox the tickets are in.
     * @return array
     * @throws ApiException 422 for an invalid move, 404 for an unknown mailbox
     */
    public function moveTickets($data, $mailBoxId)
    {
        $newBoxId = (int) Arr::get($data, 'new_box_id');
        $moveAll = Arr::get($data, 'move_type') === 'All';
        $ticketIds = array_filter(array_map('intval', (array) Arr::get($data, 'ticket_ids', [])));

        if ($newBoxId === (int) $mailBoxId) {
            throw ApiException::invalid(__('New Box cannot be the same as MailBox ID', 'fluent-support'));
        }
        if (!$moveAll && !$ticketIds) {
            throw ApiException::invalid(__('Invalid request submitted. Please select a ticket first', 'fluent-support'));
        }

        $oldBox = MailBox::find($mailBoxId);
        $newBox = MailBox::find($newBoxId);
        if (!$oldBox || !$newBox) {
            throw ApiException::notFound();
        }

        $tickets = Ticket::where('mailbox_id', $oldBox->id);
        if (!$moveAll) {
            $tickets->whereIn('id', $ticketIds);
        }

        $access = new AgentTicketAccess();
        $agentIds = (clone $tickets)->whereNotNull('agent_id')->distinct()->pluck('agent_id')->all();
        foreach (Agent::whereIn('id', $agentIds ?: [0])->get() as $agent) {
            if (in_array((int) $newBox->id, $access->getRestrictedMailboxIds($agent), true)) {
                throw ApiException::invalid(__('Agent is restricted for this mailbox ticket', 'fluent-support'));
            }
        }

        /**
         * Fires before tickets move to another mailbox.
         * @since v1.5.7
         * @param array $data
         * @param object $oldBox Current mailbox
         * @param object $newBox Mailbox where selected tickets will be moved
         */
        do_action('fluent_support/before_move_tickets', $data, $oldBox, $newBox);

        $tickets->update(['mailbox_id' => $newBox->id]);

        /**
         * Fires after tickets move to another mailbox.
         * @since v1.5.7
         * @param array $data
         * @param object $oldBox Mailbox where selected tickets were moved from
         * @param object $newBox Mailbox where selected tickets were moved to
         */
        do_action('fluent_support/tickets_moved', $data, $oldBox, $newBox);

        return [
            'message' => __('All ticket moves to the selected Business', 'fluent-support')
        ];
    }
}
