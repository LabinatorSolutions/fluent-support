<?php

namespace FluentSupport\App\Services\Tickets;

use FluentSupport\App\Modules\PermissionManager;

/**
 * What the admin ticket screens send about the people and mailbox on a ticket:
 * allow lists, so a column added later is not sent by default.
 */
class TicketFormatter
{
    /**
     * A person in the ticket view and thread. A customer keeps their email for the
     * "From" line; an agent's email, IP and notes need fst_sensitive_data
     * elsewhere and are not sent.
     *
     * @param \FluentSupport\App\Models\Person|null $person
     * @return void
     */
    public static function person($person)
    {
        if (!$person) {
            return;
        }

        $fields = ['id', 'first_name', 'last_name', 'full_name', 'photo', 'person_type', 'title'];
        if ($person->person_type === 'customer') {
            $fields[] = 'email';
        }

        $person->setVisible($fields);
    }

    /**
     * The ticket view's "Closed Ticket" bar names who closed it.
     *
     * @param \FluentSupport\App\Models\Ticket|null $ticket
     * @return void
     */
    public static function closedBy($ticket)
    {
        if (!$ticket || $ticket->status !== 'closed') {
            return;
        }

        $ticket->load('closed_by_person');
        self::person($ticket->closed_by_person);
    }

    /**
     * Rows of the ticket list. Pages can be walked to the end, so the customer and
     * agent carry names and photos; their emails need fst_sensitive_data, as the
     * Customers list does (the single-ticket view shows the customer's). Email and
     * avatar stay loaded because the photo URL is built from them.
     *
     * @param iterable $tickets with customer, agent and mailbox loaded
     * @return void
     */
    public static function listRows($tickets)
    {
        $personFields = ['id', 'first_name', 'last_name', 'full_name', 'photo'];
        if (PermissionManager::currentUserCan('fst_sensitive_data')) {
            $personFields[] = 'email';
        }

        foreach ($tickets as $ticket) {
            if ($ticket->customer) {
                $ticket->customer->setVisible($personFields);
            }
            if ($ticket->agent) {
                $ticket->agent->setVisible($personFields);
            }
            if ($ticket->mailbox) {
                $ticket->mailbox->setVisible(['id', 'name']);
            }
        }
    }
}
