<?php
/**
 * "Save as an internal note": an agent logs a call or visit as a ticket. The
 * text is saved as an internal note, the body is a placeholder, files move to
 * the note, and no customer email goes out on create. Hiding the ticket in the
 * portal is covered by 127-logged-ticket-hidden.
 * The portal WP user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {
    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $userId = wp_insert_user([
        'user_login' => substr($marker . '-logged', 0, 60),
        'user_email' => $marker . '-logged@example.invalid',
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);
    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
    }

    $asCustomer = function () use ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };
    $asAdmin = function () use ($adminId) {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
    };

    try {
        // Registering the user may already have made its customer.
        $customer = Customer::where('user_id', $userId)->first();
        if ($customer) {
            FsFactory::adoptRestCustomer($customer);
        } else {
            $customer = FsFactory::customer(['email' => $marker . '-logged@example.invalid', 'user_id' => $userId]);
        }
        // Every email this suite expects to see blocked (or sent) is switched on.
        $adminEmail = $marker . '-logged-admin@example.invalid';
        $mailbox = FsFactory::mailbox(['settings' => ['admin_email_address' => $adminEmail]]);
        $emailSettings = new Settings();
        foreach (['ticket_created_email_to_customer', 'ticket_created_by_agent_email_to_customer', 'ticket_created_email_to_admin', 'ticket_closed_by_agent_email_to_customer', 'ticket_replied_by_agent_email_to_customer'] as $key) {
            $setting = $emailSettings->getBoxEmailSettings($mailbox, $key);
            $setting['status'] = 'yes';
            $emailSettings->saveBoxEmailSettings($mailbox, $key, $setting);
        }

        $create = function (array $attributes = []) use ($customer, $mailbox) {
            $ticket = (new TicketService())->storeTicket(array_merge([
                'customer_id'     => $customer->id,
                'title'           => 'Phone call ' . FsFactory::marker(),
                'content'         => '<p>Called about the license error. Sounded upset. ' . FsFactory::marker() . '</p>',
                'mailbox_id'      => $mailbox->id,
                'start_with_note' => 'yes',
            ], $attributes), $customer);
            FsFactory::adoptRestTicket($ticket);

            return Ticket::find($ticket->id);
        };

        $mailsTo = function ($email) {
            return array_values(array_filter(FsTest::sentMails(), function ($mail) use ($email) {
                return in_array($email, (array) ($mail['to'] ?? []), true) || ($mail['to'] ?? '') === $email;
            }));
        };

        $serial = function (Ticket $ticket) {
            return (int) Ticket::find($ticket->id)->serial_number;
        };

        FsTest::case('logged_ticket_keeps_the_text_as_a_note_and_a_placeholder_body', function () use ($create) {
            FsTest::interceptMail();
            $ticket = $create();

            $notes = Conversation::where('ticket_id', $ticket->id)->get();

            FsTest::assertSame(Ticket::SOURCE_AGENT_LOGGED, $ticket->source, 'ticket source');
            FsTest::assertSame('new', $ticket->status, 'still new');
            FsTest::assertSame(0, (int) $ticket->response_count, 'no response yet');
            FsTest::assertSame(1, $notes->count(), 'one thread row');
            FsTest::assertSame('note', $notes->count() ? $notes[0]->conversation_type : null, 'the row is a note');
            FsTest::assert($notes->count() && strpos((string) $notes[0]->content, 'Sounded upset') !== false, 'the note holds the text');
            FsTest::assert(strpos((string) $ticket->content, 'Sounded upset') === false, 'the body does not hold the text: ' . $ticket->content);
            FsTest::assert(strpos((string) $ticket->content, 'logged this ticket') !== false, 'the body is the placeholder: ' . $ticket->content);
            FsTest::assertSame(md5($ticket->content), $ticket->content_hash, 'content_hash matches the placeholder');
        });

        FsTest::case('logged_ticket_moves_ticket_level_files_to_the_note', function () use ($create, $marker) {
            FsTest::interceptMail();
            $title = 'Files ' . $marker;

            // A file on the ticket before the branch runs, like an upload or a pasted image.
            $seed = function ($ticket) use ($title, $marker) {
                if ($ticket->title === $title) {
                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'person_id' => (int) $ticket->customer_id,
                        'file_type' => 'image/png',
                        'file_path' => '/nonexistent/' . $marker . '.png',
                        'full_url'  => '',
                        'title'     => 'pasted ' . $marker,
                        'driver'    => 'local',
                        'file_size' => 1,
                        'status'    => 'inline',
                    ]);
                }
            };
            Ticket::created($seed);

            $ticket = $create(['title' => $title]);
            $agent = Helper::getAgentByUserId();
            $note = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'note')->first();
            $file = Attachment::where('ticket_id', $ticket->id)->first();

            FsTest::assert((bool) $file, 'the seeded file exists');
            if (!$file || !$note) {
                return;
            }
            FsTest::assertSame((int) $note->id, (int) $file->conversation_id, 'the file is on the note');
            FsTest::assertSame((int) $agent->id, (int) $file->person_id, 'the agent owns the file');
        });

        FsTest::case('logged_ticket_sends_the_customer_no_email_on_create', function () use ($create, $customer, $mailbox, $mailsTo, $adminEmail) {
            // Control: a plain agent-created ticket does email this customer.
            FsTest::interceptMail();
            $plain = (new TicketService())->storeTicket([
                'customer_id' => $customer->id,
                'title'       => 'Plain ' . FsFactory::marker(),
                'content'     => '<p>Plain</p>',
                'mailbox_id'  => $mailbox->id,
            ], $customer);
            FsFactory::adoptRestTicket($plain);
            FsTest::assert(count($mailsTo($customer->email)) > 0, 'control: a plain ticket emails the customer');

            FsTest::interceptMail();
            $create();
            FsTest::assertSame(0, count($mailsTo($customer->email)), 'no email to the customer');
            FsTest::assert(count($mailsTo($adminEmail)) > 0, 'the admin email still goes');
        });

        FsTest::case('logged_ticket_waits_on_the_agent', function () use ($create) {
            FsTest::interceptMail();
            $ticket = $create();

            FsTest::assert(Ticket::waitingOnly()->where('id', $ticket->id)->exists(), 'in the waiting list');
        });

        FsTest::case('first_reply_reveals_the_ticket_but_not_the_note', function () use ($create, $customer, $mailsTo, $asCustomer, $asAdmin, $serial) {
            FsTest::interceptMail();
            $ticket = $create();
            $agent = Helper::getAgentByUserId();

            FsTest::interceptMail();
            (new ResponseService())->createResponse(['content' => '<p>Fixed your license.</p>', 'conversation_type' => 'response'], $agent, $ticket);
            FsTest::assertSame(1, count($mailsTo($customer->email)), 'the customer gets the reply email');

            $asCustomer();
            try {
                $result = FsTest::rest('GET', '/customer-portal/tickets/' . $serial($ticket));
                FsTest::assertHealthy($result, 'portal ticket page after the reply');
                $json = wp_json_encode($result['data']);
                FsTest::assert(strpos($json, 'Fixed your license') !== false, 'the reply is shown');
                FsTest::assert(strpos($json, 'Sounded upset') === false, 'the note is not shown');
                FsTest::assertSame(true, $result['data']['ticket']['created_by_agent']['agent_initiated'] ?? null, 'the placeholder body is skipped');
            } finally {
                $asAdmin();
            }
        });

        FsTest::case('a_user_without_an_agent_record_cannot_log_a_ticket', function () use ($create, $marker, $asAdmin) {
            FsTest::interceptMail();
            // A site admin can have no agent row; the note would otherwise become a visible body.
            $adminUser = wp_insert_user([
                'user_login' => substr($marker . '-noagent', 0, 60),
                'user_email' => $marker . '-noagent@example.invalid',
                'user_pass'  => wp_generate_password(20),
                'role'       => 'administrator',
            ]);
            $title = 'No agent ' . $marker;
            try {
                wp_set_current_user($adminUser);
                PermissionManager::currentUserPermissions(false);
                FsTest::assert(!Helper::getAgentByUserId(), 'fixture: the user has no agent record');

                $thrown = false;
                try {
                    $create(['title' => $title]);
                } catch (\Exception $e) {
                    $thrown = true;
                }
                FsTest::assert($thrown, 'refused');
                FsTest::assertSame(0, Ticket::where('title', $title)->count(), 'no ticket');

                // Through the admin form with a new email: refused before the customer is made.
                $email = $marker . '-noagent-new@example.invalid';
                $result = FsTest::rest('POST', 'tickets', [
                    'ticket'      => ['title' => $title, 'content' => '<p>Body</p>', 'create_customer' => 'yes', 'start_with_note' => 'yes'],
                    'newCustomer' => ['email' => $email, 'first_name' => 'New'],
                ]);
                FsTest::assertSame(403, $result['status'], 'REST refuses');
                FsTest::assertSame(0, Customer::where('email', $email)->count(), 'no customer created');
                FsTest::assertSame(0, Ticket::where('title', $title)->count(), 'still no ticket');
            } finally {
                $asAdmin();
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($adminUser);
            }
        });

        FsTest::case('a_failure_after_the_insert_leaves_the_ticket_hidden', function () use ($create, $customer, $marker) {
            FsTest::interceptMail();
            $title = 'Fails mid-create ' . $marker;
            // Throws while the note is written, after the ticket row exists.
            $boom = function () {
                throw new \RuntimeException('note step failed');
            };
            add_filter('fluent_support/parse_smartcode_data', $boom);
            try {
                $create(['title' => $title]);
                FsTest::assert(false, 'fixture: the note step should throw');
            } catch (\RuntimeException $e) {
                FsTest::assertSame('note step failed', $e->getMessage(), 'the injected failure');
            } finally {
                remove_filter('fluent_support/parse_smartcode_data', $boom);
            }

            $ticket = Ticket::where('title', $title)->first();
            FsTest::assert((bool) $ticket, 'fixture: the row was inserted before the failure');
            if (!$ticket) {
                return;
            }
            FsFactory::adoptRestTicket($ticket);

            FsTest::assertSame(Ticket::SOURCE_AGENT_LOGGED, $ticket->source, 'hidden source from the first insert');
            FsTest::assert(
                !Ticket::where('customer_id', $customer->id)->visibleToCustomer()->where('id', $ticket->id)->exists(),
                'the portal does not list it'
            );
        });

        FsTest::case('content_that_sanitizes_to_nothing_still_gets_its_note', function () use ($create) {
            FsTest::interceptMail();
            $ticket = $create(['content' => '<iframe src="https://example.invalid"></iframe>']);

            FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'note')->count(), 'the note exists');
            FsTest::assertSame(Ticket::SOURCE_AGENT_LOGGED, $ticket->source, 'still a logged ticket');
        });

        FsTest::case('both_flags_are_rejected_before_a_customer_is_created', function () use ($marker) {
            FsTest::interceptMail();
            $email = $marker . '-both@example.invalid';

            $result = FsTest::rest('POST', 'tickets', [
                'ticket'      => ['title' => 'Both ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes', 'agent_initiated' => 'yes', 'start_with_note' => 'yes'],
                'newCustomer' => ['email' => $email, 'first_name' => 'Both'],
            ]);

            FsTest::assert($result['status'] >= 400, 'rejected, got ' . $result['status']);
            FsTest::assertSame(0, Ticket::where('title', 'Both ' . $marker)->count(), 'no ticket');
            FsTest::assertSame(0, Customer::where('email', $email)->count(), 'no customer');
        });
    } finally {
        $asAdmin();
        if (isset($mailbox)) {
            $mailbox->deleteAllMeta();
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
