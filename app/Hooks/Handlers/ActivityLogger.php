<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Activity;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class ActivityLogger
{
    public function init()
    {
        // Ticket Related activities
        add_action('fluent_support/ticket_created', function ($ticket, $customer) {

            $description = sprintf('%1$s created a %2$s via %3$s', $this->getPersonMarkup($customer), $this->getTicketMarkup($ticket), $ticket->source);

            $log = [
                'event_type' => 'fluent_support/ticket_created',
                'person_id' => $customer->id,
                'person_type' => $customer->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);

        }, 20, 2);

        add_action('fluent_support/response_added_by_customer', function ($response, $ticket, $person) {
            $description = sprintf('Customer %1$s added a %2$s via %3$s', $this->getPersonMarkup($person), $this->getTicketMarkup($ticket, 'response'), $response->source);

            $log = [
                'event_type' => 'fluent_support/response_added_by_customer',
                'person_id' => $person->id,
                'person_type' => $person->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);
        }, 20, 3);

        add_action('fluent_support/response_added_by_agent', function ($response, $ticket, $person) {
            $description = sprintf('%1$s added a %2$s via %3$s', $this->getPersonMarkup($person), $this->getTicketMarkup($ticket, 'response'), $response->source);

            $log = [
                'event_type' => 'fluent_support/response_added_by_agent',
                'person_id' => $person->id,
                'person_type' => $person->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);
        }, 20, 3);

        add_action('fluent_support/note_added_by_agent', function ($response, $ticket, $person) {
            $description = sprintf('%1$s added a %2$s via %3$s', $this->getPersonMarkup($person), $this->getTicketMarkup($ticket, 'note'), $response->source);

            $log = [
                'event_type' => 'fluent_support/note_added_by_agent',
                'person_id' => $person->id,
                'person_type' => $person->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);
        }, 20, 3);

        add_action('fluent_support/ticket_closed', function ($ticket, $person) {
            $description = sprintf('%1$s closed a %2$s', $this->getPersonMarkup($person), $this->getTicketMarkup($ticket, 'ticket'));

            $log = [
                'event_type' => 'fluent_support/ticket_closed',
                'person_id' => $person->id,
                'person_type' => $person->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);
        }, 20, 2);

        add_action('fluent_support/ticket_reopen', function ($ticket, $person) {
            $description = sprintf('%1$s reopened a %2$s', $this->getPersonMarkup($person), $this->getTicketMarkup($ticket, 'response'));

            $log = [
                'event_type' => 'fluent_support/ticket_reopen',
                'person_id' => $person->id,
                'person_type' => $person->person_type,
                'object_id' => $ticket->id,
                'object_type' => 'ticket',
                'description' => $description
            ];

            Activity::create($log);
        }, 20, 2);

    }

    public function getTicketMarkup($ticket, $ticketText = false)
    {
        if(!$ticketText) {
            $ticketText = sprintf(__('Ticket #%d', 'fluent-support'), $ticket->id);
        }

        return '<a class="fs_link_trans fs_tk" href="#view_ticket">'.$ticketText.'</a>';
    }

    public function getPersonMarkup($person)
    {
        $route = 'view_agent';
        if($person->person_type == 'customer') {
            $route = 'view_customer';
        }
        return '<a class="fs_link_trans fs_pr" href="#'.$route.'">'.$person->full_name.'</a>';
    }
}
