<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Mailer;
use FluentSupport\Framework\Support\Arr;

class EmailNotificationHandler
{
    public function ticketCreated($ticket, $customer)
    {
        $emailSettings = Helper::getEmailSettings();
        if(!in_array('ticket_created', Arr::get($emailSettings, 'notification_events', []))) {
            return;
        }

        $app = App::getInstance();
        $ticket->view_url = Helper::getTicketViewUrl($ticket);

        $emailBody = $app->view->make('emails.ticket_created', [
            'ticket'   => $ticket,
            'customer' => $customer,
            'settings' => Helper::getBusinessSettings()
        ]);

        $subject = 'Request Received: ' . $ticket->title;

        Mailer::send($customer->email, $subject, $emailBody);
    }

    public function agentReplied($response, $ticket, $agent)
    {
        $emailSettings = Helper::getEmailSettings();
        if(!in_array('response_added_by_agent', Arr::get($emailSettings, 'notification_events', []))) {
            return;
        }

        $app = App::getInstance();
        $ticket->view_url = Helper::getTicketViewUrl($ticket);

        $ticket->load('customer');

        $customer = $ticket->customer;

        $emailBody = $app->view->make('emails.response_by_agent', [
            'ticket'   => $ticket,
            'agent'    => $agent,
            'customer' => $customer,
            'response' => $response,
            'settings' => Helper::getBusinessSettings()
        ]);

        $subject = 'Re: ' . $ticket->title;

        Mailer::send($customer->email, $subject, $emailBody);
    }

    public function closedByAgent($ticket, $agent)
    {
        $emailSettings = Helper::getEmailSettings();
        if(!in_array('ticket_closed_by_agent', Arr::get($emailSettings, 'notification_events', []))) {
            return;
        }

        $settings = Helper::getBusinessSettings();

        $app = App::getInstance();
        $ticket->view_url = Helper::getTicketViewUrl($ticket);

        $ticket->load('customer');

        $customer = $ticket->customer;

        $emailBody = $app->view->make('emails.ticket_closed', [
            'ticket'   => $ticket,
            'customer' => $customer,
            'settings' => $settings
        ]);

        $subject = 'Request Received: ' . $ticket->title;

        Mailer::send($customer->email, $subject, $emailBody);
    }

}
