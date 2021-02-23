<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\App;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Mailer;

class EmailNotificationHandler
{
    public function ticketCreated($ticket, $customer)
    {
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

    public function ticketClosedByAgent($ticket, $agent)
    {

    }

}
