<?php
/**
 * A ticket logged as an internal note (source agent_logged) stays hidden from the
 * customer until its first response row: no portal access, no customer email.
 * The rule is Ticket::isHiddenFromCustomer() / scopeVisibleToCustomer().
 * The portal WP user isn't factory-tracked; the finally removes it.
 */

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
        'user_login' => substr($marker . '-hidden', 0, 60),
        'user_email' => $marker . '-hidden@example.invalid',
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
            $customer = FsFactory::customer(['email' => $marker . '-hidden@example.invalid', 'user_id' => $userId]);
        }

        // Every customer email this suite expects to see blocked (or sent) is switched on.
        $mailbox = FsFactory::mailbox();
        $emailSettings = new Settings();
        foreach (['ticket_closed_by_agent_email_to_customer', 'ticket_replied_by_agent_email_to_customer'] as $key) {
            $setting = $emailSettings->getBoxEmailSettings($mailbox, $key);
            $setting['status'] = 'yes';
            $emailSettings->saveBoxEmailSettings($mailbox, $key, $setting);
        }

        $hiddenTicket = function (array $attributes = []) use ($customer, $mailbox) {
            return FsFactory::ticket($attributes + [
                'customer_id'    => $customer->id,
                'mailbox_id'     => $mailbox->id,
                'status'         => 'new',
                'source'         => Ticket::SOURCE_AGENT_LOGGED,
                'response_count' => 0,
            ]);
        };

        $mailsTo = function ($email) {
            return array_values(array_filter(FsTest::sentMails(), function ($mail) use ($email) {
                return in_array($email, (array) ($mail['to'] ?? []), true) || ($mail['to'] ?? '') === $email;
            }));
        };

        $serial = function (Ticket $ticket) {
            return (int) Ticket::find($ticket->id)->serial_number;
        };

        FsTest::case('the_php_rule_and_the_sql_scope_agree', function () use ($hiddenTicket, $customer, $mailbox) {
            $tickets = [
                'hidden'   => $hiddenTicket(),
                'answered' => $hiddenTicket(['response_count' => 1]),
                'web'      => FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $mailbox->id, 'source' => 'web']),
                'no source' => FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $mailbox->id, 'source' => null]),
            ];
            $ids = array_map(fn($ticket) => (int) $ticket->id, $tickets);
            $visible = array_map('intval', Ticket::whereIn('id', $ids)->visibleToCustomer()->pluck('id')->toArray());

            foreach ($tickets as $label => $ticket) {
                $hidden = Ticket::find($ticket->id)->isHiddenFromCustomer();
                FsTest::assertSame($label === 'hidden', $hidden, $label . ': isHiddenFromCustomer()');
                FsTest::assertSame(!$hidden, in_array((int) $ticket->id, $visible, true), $label . ': visibleToCustomer()');
            }
        });

        FsTest::case('a_hidden_ticket_is_not_found_anywhere_in_the_portal', function () use ($hiddenTicket, $asCustomer, $asAdmin, $serial) {
            FsTest::interceptMail();
            $ticket = $hiddenTicket();
            $number = $serial($ticket);

            $asCustomer();
            try {
                $list = FsTest::rest('GET', '/customer-portal/tickets', ['per_page' => 100, 'filter_type' => 'all']);
                FsTest::assertHealthy($list, 'portal list');
                $payload = json_decode(wp_json_encode($list['data']['tickets'] ?? []), true);
                $ids = array_map('intval', array_column((array) ($payload['data'] ?? []), 'id'));
                FsTest::assert(!in_array((int) $ticket->id, $ids, true), 'not in the portal list');

                $calls = [
                    ['GET', '/customer-portal/tickets/' . $number, []],
                    ['POST', '/customer-portal/tickets/' . $number . '/responses', ['content' => '<p>Hi</p>']],
                    ['POST', '/customer-portal/tickets/' . $number . '/close', []],
                    ['POST', '/customer-portal/tickets/' . $number . '/re-open', []],
                ];
                foreach ($calls as $call) {
                    $result = FsTest::rest($call[0], $call[1], $call[2]);
                    FsTest::assertSame(404, $result['status'], $call[0] . ' ' . $call[1]);
                }
            } finally {
                $asAdmin();
            }

            FsTest::assertSame(0, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count(), 'the customer could not reply');
        });

        FsTest::case('closing_a_hidden_ticket_emails_the_customer_nothing', function () use ($hiddenTicket, $customer, $mailbox, $mailsTo) {
            $agent = Helper::getAgentByUserId();

            // Control: closing a normal ticket emails the customer.
            $plain = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $mailbox->id, 'status' => 'active']);
            FsTest::interceptMail();
            (new TicketService())->close(Ticket::find($plain->id), $agent);
            FsTest::assert(count($mailsTo($customer->email)) > 0, 'control: closing a normal ticket emails the customer');

            $ticket = $hiddenTicket(['status' => 'active']);
            FsTest::interceptMail();
            (new TicketService())->close(Ticket::find($ticket->id), $agent);
            FsTest::assertSame(0, count($mailsTo($customer->email)), 'no closed email to the customer');
        });

        FsTest::case('the_first_reply_reveals_the_ticket_but_not_its_note', function () use ($hiddenTicket, $customer, $mailsTo, $asCustomer, $asAdmin, $serial) {
            $agent = Helper::getAgentByUserId();
            $ticket = $hiddenTicket();
            FsFactory::conversation($ticket, ['conversation_type' => 'note', 'person_id' => $agent->id, 'content' => '<p>Sounded upset. ' . FsFactory::marker() . '</p>']);

            FsTest::interceptMail();
            (new ResponseService())->createResponse(['content' => '<p>Fixed your license.</p>', 'conversation_type' => 'response'], $agent, Ticket::find($ticket->id));
            FsTest::assertSame(1, count($mailsTo($customer->email)), 'the customer gets the reply email');

            $asCustomer();
            try {
                $result = FsTest::rest('GET', '/customer-portal/tickets/' . $serial($ticket));
                FsTest::assertHealthy($result, 'portal ticket page after the reply');
                $json = wp_json_encode($result['data']);
                FsTest::assert(strpos($json, 'Fixed your license') !== false, 'the reply is shown');
                FsTest::assert(strpos($json, 'Sounded upset') === false, 'the note is not shown');
            } finally {
                $asAdmin();
            }
        });

        FsTest::case('the_email_filter_leaves_other_calls_alone', function () {
            FsTest::assertSame(true, apply_filters('fluent_support/should_send_notification', true, 'email', 'ticket_closed_by_agent_email_to_customer', null, null), 'no ticket');
            FsTest::assertSame(true, apply_filters('fluent_support/should_send_notification', true, 'slack', 'ticket_created', null, null), 'another channel');
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
