<?php
/**
 * Agent replies: what lands in fs_conversations, and what goes out by mail.
 *
 * The runner intercepts mail through `pre_wp_mail`. These cases check the
 * recipient and subject, and that an internal note doesn't notify the customer.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    $marker = FsFactory::marker();
    $agent = Helper::getAgentByUserId();

    /**
     * Mails captured since a recorded offset, as plain arrays.
     *
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    $mailsSince = function ($offset) {
        $all = FsTest::sentMails();

        return array_values(array_slice($all, $offset));
    };

    /**
     * Recipients of one captured mail, normalised to a flat list.
     *
     * @param array<string,mixed> $mail
     * @return array<int,string>
     */
    $recipients = function ($mail) {
        $to = isset($mail['to']) ? $mail['to'] : [];

        return array_map('strval', is_array($to) ? $to : [$to]);
    };

    FsTest::case('agent_response_is_stored_as_a_conversation_by_that_agent', function () use ($marker, $agent) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Convo storage', 'status' => 'new']);

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => '<p>Stored reply ' . $marker . '</p>',
            'conversation_type' => 'response',
        ]);
        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

        $conversations = Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'response')
            ->get();

        FsTest::assertSame(1, count($conversations), 'exactly one response conversation on the ticket');

        $conversation = $conversations[0];
        FsTest::assertSame((int) $agent->id, (int) $conversation->person_id, 'conversation person_id is the agent');
        // The author's kind comes from the fs_persons row person_id points at.
        FsTest::assertSame('agent', $conversation->person->person_type, 'conversation author is an agent person');
        FsTest::assert(strpos((string) $conversation->content, $marker) !== false,
            'the stored content must be what we posted');
    });

    FsTest::case('agent_response_emails_the_ticket_customer', function () use (
        $marker, $mailsSince, $recipients
    ) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Convo mail', 'status' => 'new']);

        $before = count(FsTest::sentMails());

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => '<p>Notify reply ' . $marker . '</p>',
            'conversation_type' => 'response',
        ]);
        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

        $mails = $mailsSince($before);
        if (!$mails) {
            FsTest::fail('an agent response must attempt a customer notification, none was intercepted');
            return;
        }

        $matching = [];
        foreach ($mails as $mail) {
            if (in_array($customer->email, $recipients($mail), true)) {
                $matching[] = $mail;
            }
        }

        FsTest::assertSame(1, count($matching),
            'exactly one intercepted mail addressed to the ticket customer (' . $customer->email . '), got '
            . count($mails) . ' mail(s) total');

        $subject = isset($matching[0]['subject']) ? (string) $matching[0]['subject'] : '';
        $serial = (string) Ticket::find($ticket->id)->serial_number;
        FsTest::assert(strpos($subject, $serial) !== false,
            'the notification subject must carry the ticket serial #' . $serial . ', got: ' . $subject);
    });

    FsTest::case('mail_is_intercepted_and_never_handed_to_the_mailer', function () use (
        $marker, $mailsSince
    ) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Convo intercept', 'status' => 'new']);

        $before = count(FsTest::sentMails());

        FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => '<p>Intercept reply ' . $marker . '</p>',
            'conversation_type' => 'response',
        ]);

        $mails = $mailsSince($before);
        FsTest::assert(count($mails) > 0, 'the interceptor must have captured the outbound mail');

        foreach ($mails as $mail) {
            FsTest::assert(isset($mail['subject']) && $mail['subject'] !== '',
                'captured mail must carry a subject: ' . wp_json_encode($mail));
        }
    });

    FsTest::case('internal_note_does_not_email_the_customer', function () use (
        $marker, $mailsSince, $recipients
    ) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Convo note', 'status' => 'new']);

        $before = count(FsTest::sentMails());

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => '<p>Internal note ' . $marker . '</p>',
            'conversation_type' => 'note',
        ]);
        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses (note)');

        $note = Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'note')
            ->first();
        if (!$note) {
            FsTest::fail('an internal note must still be stored as a conversation');
            return;
        }

        foreach ($mailsSince($before) as $mail) {
            if (in_array($customer->email, $recipients($mail), true)) {
                FsTest::fail('an internal note must never be emailed to the customer (' . $customer->email . ')');
                return;
            }
        }

        FsTest::assertSame(0, (int) Ticket::find($ticket->id)->response_count,
            'an internal note must not increment response_count');
    });

    /**
     * Agent-created ticket in a mailbox with all three creation emails on;
     * returns [customer recipients, admin recipients] counts.
     *
     * @param string $customerStatus
     * @return array{0:int,1:int}
     */
    $createdEmailCounts = function ($customerStatus) use ($marker, $mailsSince, $recipients) {
        $adminEmail = $marker . '-admin@example.invalid';
        $box = FsFactory::mailbox(['settings' => ['admin_email_address' => $adminEmail]]);
        $settings = new Settings();

        try {
            foreach (['ticket_created_email_to_customer', 'ticket_created_by_agent_email_to_customer', 'ticket_created_email_to_admin'] as $key) {
                $emailSettings = $settings->getBoxEmailSettings($box, $key);
                $emailSettings['status'] = 'yes';
                $settings->saveBoxEmailSettings($box, $key, $emailSettings);
            }

            $customer = FsFactory::customer(['status' => $customerStatus]);
            $before = count(FsTest::sentMails());

            $ticket = (new TicketService())->storeTicket([
                'customer_id' => $customer->id,
                'mailbox_id'  => $box->id,
                'title'       => 'Created mail ' . $marker,
                'content'     => '<p>Created mail ' . $marker . '</p>',
            ], $customer);
            FsFactory::adoptRestTicket($ticket);

            $toCustomer = 0;
            $toAdmin = 0;
            foreach ($mailsSince($before) as $mail) {
                $to = $recipients($mail);
                $toCustomer += in_array($customer->email, $to, true) ? 1 : 0;
                $toAdmin += in_array($adminEmail, $to, true) ? 1 : 0;
            }

            return [$toCustomer, $toAdmin];
        } finally {
            $box->deleteAllMeta();
        }
    };

    FsTest::case('active_customer_gets_ticket_created_emails', function () use ($createdEmailCounts) {
        FsTest::interceptMail();
        [$toCustomer, $toAdmin] = $createdEmailCounts('active');

        // Control for the blocked case: "Ticket Created" + "Ticket Logged by Agent".
        FsTest::assertSame(2, $toCustomer, 'creation emails to an active customer');
        FsTest::assertSame(1, $toAdmin, 'creation email to the mailbox admin');
    });

    FsTest::case('blocked_customer_gets_no_ticket_created_emails_but_admin_does', function () use ($createdEmailCounts) {
        FsTest::interceptMail();
        [$toCustomer, $toAdmin] = $createdEmailCounts('inactive');

        // Same rule as replies and closes: a blocked customer receives no email.
        FsTest::assertSame(0, $toCustomer, 'creation emails to a blocked customer');
        FsTest::assertSame(1, $toAdmin, 'the admin is still told about the new ticket');
    });

    FsTest::case('customer_response_is_attributed_to_the_customer_not_the_agent', function () use ($marker) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Convo customer side']);

        $conversation = FsFactory::conversation($ticket, [
            'person_id' => $customer->id,
            'content'   => '<p>Customer says ' . $marker . '</p>',
        ]);

        $stored = Conversation::find($conversation->id);
        FsTest::assertSame((int) $customer->id, (int) $stored->person_id, 'conversation person_id');

        // Must resolve to the customer, never an agent in the shared table.
        $author = $stored->person;
        if (!$author) {
            FsTest::fail('conversation->person did not resolve to the customer who wrote it');
            return;
        }

        FsTest::assertSame($customer->email, $author->email, 'conversation author email');
        FsTest::assertSame('customer', $author->person_type, 'conversation author is a customer person');
    });

    FsTest::case('default email subjects show the public ticket number', function () {
        $box = FsFactory::mailbox();
        $customer = FsFactory::customer();
        $number = 'FS-' . wp_rand(100000, 999999);
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $box->id, 'ticket_number' => $number]);

        $settings = (new \FluentSupport\App\Services\EmailNotification\Settings())->getBoxEmailSettings($box, 'ticket_created_email_to_admin');
        $subject = apply_filters('fluent_support/parse_smartcode_data', $settings['email_subject'], [
            'customer' => $customer,
            'business' => $box,
            'ticket'   => Ticket::find($ticket->id),
        ]);

        FsTest::assert(strpos($subject, '#' . $number) !== false, 'subject: ' . $subject);
    });
};
