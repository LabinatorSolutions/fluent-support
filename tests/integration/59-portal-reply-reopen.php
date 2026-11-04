<?php
/**
 * A portal reply on a closed ticket reopens it, as an emailed reply does, but only once
 * the reply is saved.
 *
 * The portal hides the reply box on closed tickets, so this path is a stale page (an
 * agent closed the ticket while the customer was writing) or a direct API call.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();

    $userId = wp_insert_user([
        'user_login' => substr($marker . '-reopen-owner', 0, 60),
        'user_email' => $marker . '-reopen-owner@example.invalid',
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
    }

    try {
        $customer = FsFactory::customer([
            'email'   => $marker . '-reopen-owner@example.invalid',
            'user_id' => $userId,
        ]);

        $closedTicket = function () use ($customer) {
            return FsFactory::ticket([
                'customer_id' => $customer->id,
                'title'       => 'Closed ticket for reply',
                'privacy'     => 'private',
                'status'      => 'closed',
            ]);
        };

        $reply = function (Ticket $ticket, array $params) {
            return FsTest::rest('POST', '/customer-portal/tickets/' . ($ticket->serial_number ?: $ticket->id) . '/responses', $params);
        };

        $responseCount = function (Ticket $ticket) {
            return (int) Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count();
        };

        // Counts reopen hooks and notes, and the status the reply hook sees, for one reply.
        $observe = function (Ticket $ticket, callable $send) {
            $seen = ['reopen_hooks' => 0, 'status_in_reply_hook' => null];
            $onReopen = function () use (&$seen) {
                $seen['reopen_hooks']++;
            };
            $onReply = function ($response, $replyTicket) use (&$seen) {
                $seen['status_in_reply_hook'] = $replyTicket->status;
            };
            $notes = function () use ($ticket) {
                return (int) Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->count();
            };

            $notesBefore = $notes();
            add_action('fluent_support/ticket_reopen', $onReopen);
            add_action('fluent_support/response_added_by_customer', $onReply, 10, 2);
            try {
                $seen['result'] = $send();
            } finally {
                remove_action('fluent_support/ticket_reopen', $onReopen);
                remove_action('fluent_support/response_added_by_customer', $onReply, 10);
            }
            $seen['new_notes'] = $notes() - $notesBefore;

            return $seen;
        };

        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);

        foreach (['empty after sanitizing' => '<script></script>', 'blank markup' => '<p>&nbsp;</p>', 'zero' => '0'] as $label => $content) {
            FsTest::case('portal-reply-reopen: a reply with no content (' . $label . ') changes nothing', function () use ($closedTicket, $reply, $responseCount, $observe, $content) {
                $ticket = $closedTicket();
                $before = $responseCount($ticket);

                $seen = $observe($ticket, function () use ($reply, $ticket, $content) {
                    return $reply($ticket, ['content' => $content]);
                });

                FsTest::assertSame(422, (int) $seen['result']['status'], 'status');
                FsTest::assert(strpos($seen['result']['message'], 'write a reply') !== false, 'message shown in the reply form: ' . $seen['result']['message']);
                FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'ticket status');
                FsTest::assertSame($before, $responseCount($ticket), 'no reply stored');
                FsTest::assertSame(0, $seen['reopen_hooks'], 'no reopen hook');
                FsTest::assertSame(0, $seen['new_notes'], 'no reopened note');
            });
        }

        FsTest::case('portal-reply-reopen: a saved reply reopens the ticket before the reply hooks run', function () use ($closedTicket, $reply, $responseCount, $observe, $marker) {
            $ticket = $closedTicket();
            $before = $responseCount($ticket);

            $seen = $observe($ticket, function () use ($reply, $ticket, $marker) {
                return $reply($ticket, ['content' => '<p>Still broken ' . $marker . '</p>']);
            });

            FsTest::assertHealthy($seen['result'], 'reply to closed ticket');
            FsTest::assertSame($before + 1, $responseCount($ticket), 'reply stored');
            FsTest::assertSame('active', Ticket::find($ticket->id)->status, 'ticket reopened');
            FsTest::assertSame(1, $seen['reopen_hooks'], 'one reopen hook');
            FsTest::assertSame('active', $seen['status_in_reply_hook'], 'reply hook sees an active ticket, as with email piping');
        });

        FsTest::case('portal-reply-reopen: reply and close keeps a closed ticket closed', function () use ($closedTicket, $reply, $responseCount, $observe, $marker) {
            $ticket = $closedTicket();
            $before = $responseCount($ticket);

            $seen = $observe($ticket, function () use ($reply, $ticket, $marker) {
                return $reply($ticket, ['content' => '<p>Thanks, all good ' . $marker . '</p>', 'close_ticket' => 'yes']);
            });

            FsTest::assertHealthy($seen['result'], 'reply and close on closed ticket');
            FsTest::assertSame($before + 1, $responseCount($ticket), 'reply stored');
            FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'ticket stays closed');
            FsTest::assertSame(0, $seen['reopen_hooks'], 'no reopen hook');
            FsTest::assertSame(0, $seen['new_notes'], 'no reopened note');
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
