<?php
/**
 * Agent-initiated (outreach) tickets: the ticket is marked with
 * source = 'agent_initiated', the agent's message is its first response, the
 * outreach email reads like a real email, and the "ticket created" email
 * suppression doesn't leak to other tickets in the same request.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    $outreach = function (array $attributes = []) {
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        $ticket = (new TicketService())->storeTicket(array_merge([
            'customer_id'     => $customer->id,
            'title'           => 'Your invoice ' . FsFactory::marker(),
            'content'         => '<p>Hi there, your invoice is ready. ' . FsFactory::marker() . '</p>',
            'mailbox_id'      => $mailbox->id,
            'agent_initiated' => 'yes',
        ], $attributes), $customer);

        FsFactory::adoptRestTicket($ticket);

        return [$ticket, $customer];
    };

    FsTest::case('agent_initiated_ticket_is_flagged_and_message_is_first_response', function () use ($outreach) {
        FsTest::interceptMail();
        [$ticket] = $outreach();

        $fresh = Ticket::find($ticket->id);
        $first = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();

        FsTest::assertSame(Ticket::SOURCE_AGENT_INITIATED, $fresh->source, 'ticket source');
        FsTest::assert($fresh->isAgentInitiated(), 'isAgentInitiated()');
        FsTest::assert(strpos((string) $fresh->content, 'started this conversation') !== false,
            'ticket body is the placeholder, got: ' . $fresh->content);
        FsTest::assert($first && strpos((string) $first->content, 'your invoice is ready') !== false,
            'the message is the first response');
        FsTest::assertSame('web', $first ? $first->source : null, 'the channel moves to the response');
    });

    FsTest::case('agent_initiated_ticket_counts_the_message_as_an_answered_first_response', function () use ($outreach) {
        FsTest::interceptMail();
        [$ticket] = $outreach();

        $fresh = Ticket::find($ticket->id);
        $first = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();

        // Same ticket state an agent reply through ResponseService leaves behind.
        FsTest::assertSame('active', $fresh->status, 'an answered ticket is not new');
        FsTest::assertSame(1, (int) $fresh->response_count, 'response_count');
        FsTest::assertSame(0, (int) $fresh->first_response_time, 'the opening message is the first response');
        FsTest::assert(!empty($fresh->last_agent_response), 'last_agent_response is set');
        FsTest::assert(!empty($fresh->waiting_since), 'waiting_since is set');
        FsTest::assertSame($first ? (int) $first->person_id : null, (int) $fresh->agent_id, 'the sending agent owns the ticket');
    });

    FsTest::case('mcp_agent_initiated_ticket_keeps_mcp_on_the_response', function () {
        FsTest::interceptMail();
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();
        $title = 'MCP outreach ' . FsFactory::marker();

        TicketTools::createTicket([
            'title'           => $title,
            'content'         => 'Checking in on your setup. ' . FsFactory::marker(),
            'customer_email'  => $customer->email,
            'mailbox_id'      => $mailbox->id,
            'agent_initiated' => true,
        ]);

        $ticket = Ticket::where('title', $title)->first();
        if (!$ticket) {
            FsTest::fail('MCP did not create the ticket');
            return;
        }
        FsFactory::adoptRestTicket($ticket);

        FsTest::assertSame(Ticket::SOURCE_AGENT_INITIATED, $ticket->source, 'ticket source');
        FsTest::assertSame('mcp', Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->value('source'), 'response source');
    });

    FsTest::case('outreach_message_fills_smartcodes_once', function () use ($outreach) {
        FsTest::interceptMail();
        $customer = FsFactory::customer(['first_name' => '{{agent.first_name}}']);
        $mailbox = FsFactory::mailbox();
        $ticket = (new TicketService())->storeTicket([
            'customer_id'     => $customer->id,
            'title'           => 'Smartcode once ' . FsFactory::marker(),
            'content'         => '<p>Hi {{customer.first_name}}, about {{ticket.title}}</p>',
            'mailbox_id'      => $mailbox->id,
            'agent_initiated' => 'yes',
        ], $customer);
        FsFactory::adoptRestTicket($ticket);

        $first = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();
        $content = $first ? (string) $first->content : '';
        FsTest::assert(strpos($content, 'Hi {{agent.first_name}}') !== false, 'a customer name is not parsed again: ' . $content);
        FsTest::assert(strpos($content, 'about Smartcode once') !== false, 'ticket smartcodes still fill: ' . $content);
    });

    FsTest::case('outreach_email_uses_ticket_subject_and_message', function () use ($outreach) {
        FsTest::interceptMail();
        [$ticket, $customer] = $outreach();

        $toCustomer = array_values(array_filter(FsTest::sentMails(), function ($mail) use ($customer) {
            return in_array($customer->email, (array) ($mail['to'] ?? []), true) || ($mail['to'] ?? '') === $customer->email;
        }));

        FsTest::assertSame(1, count($toCustomer), 'one email to the customer');
        if (!$toCustomer) {
            return;
        }

        FsTest::assertSame($ticket->title, $toCustomer[0]['subject'] ?? null, 'subject is the ticket title');
        FsTest::assert(strpos((string) ($toCustomer[0]['message'] ?? ''), 'your invoice is ready') !== false, 'body carries the message');
        FsTest::assert(strpos((string) ($toCustomer[0]['message'] ?? ''), 'created a new ticket on your behalf') === false, 'no notification wrapper');
    });

    FsTest::case('created_email_suppression_does_not_leak_to_later_tickets', function () use ($outreach) {
        FsTest::interceptMail();
        $outreach();

        $other = FsFactory::ticket();

        FsTest::assertSame(true, apply_filters('fluent_support/should_send_notification', true, 'email', 'ticket_created_email_to_customer', $other, null),
            'a later ticket in the same request still gets its created email');
    });

    FsTest::case('legacy_placeholder_ticket_is_still_agent_initiated', function () {
        $agent = FsFactory::agent();
        $ticket = FsFactory::ticket([
            'created_by' => $agent->id,
            'content'    => $agent->full_name . ' initialized this ticket',
        ]);

        FsTest::assert(Ticket::find($ticket->id)->isAgentInitiated(), 'legacy tickets are recognised without a backfill');

        $loggedOnBehalf = FsFactory::ticket(['created_by' => $agent->id]);
        FsTest::assert(!Ticket::find($loggedOnBehalf->id)->isAgentInitiated(), 'a ticket logged on behalf is not outreach');
    });
};
