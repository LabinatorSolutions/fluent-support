<?php
/**
 * Site admins can edit what a customer wrote; nobody else can.
 *
 * Business rule (maintainer decision): customers sometimes send sensitive data
 * (passwords, card numbers). A user with manage_options may edit a customer's
 * reply (`PUT tickets/{id}/responses/{id}`) and the ticket's opening message
 * (`PUT tickets/{id}/content`) to remove it. Every such edit leaves an activity
 * line that does not repeat the old text. Agents without manage_options, even
 * with every ticket permission, stay refused.
 *
 * The agent user created here is not factory-tracked and is removed in this file's finally.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $me = Helper::getAgentByUserId();
    if (!$me) {
        FsTest::case('customer message edit: admin has an agent row', function () {
            FsTest::skip('the admin user has no agent row');
        });
        return;
    }

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    $secret = 'card 4111-1111-1111-1111 ' . FsFactory::marker();

    $activityLines = function ($ticketId) {
        return Conversation::where('ticket_id', $ticketId)
            ->where('conversation_type', 'internal_info')
            ->get();
    };

    $agentUserId = 0;

    try {
        $login = FsTest::uniq('fs-edit-customer');
        $agentUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'editor',
        ]);
        if (is_wp_error($agentUserId)) {
            throw new RuntimeException('Could not create agent user: ' . $agentUserId->get_error_message());
        }
        $agent = FsFactory::agent();
        $agent->user_id = $agentUserId;
        $agent->save();
        PermissionManager::attachPermissions($agentUserId, [
            'fst_view_dashboard',
            'fst_manage_own_tickets',
            'fst_manage_unassigned_tickets',
            'fst_manage_other_tickets',
        ]);

        FsTest::case('customer message edit: admin edits a customer reply and an activity line is added', function () use ($me, $secret, $activityLines) {
            $ticket = FsFactory::ticket(['agent_id' => $me->id]);
            $reply = FsFactory::conversation($ticket, ['content' => '<p>My ' . $secret . '</p>']);

            $redacted = '<p>My card [removed] ' . FsFactory::marker() . '</p>';
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $reply->id, ['content' => $redacted]);

            FsTest::assertHealthy($result, 'admin edits customer reply');
            FsTest::assertSame($redacted, Conversation::find($reply->id)->content, 'reply content replaced');
            FsTest::assertSame(md5($redacted), Conversation::find($reply->id)->content_hash, 'hash no longer fingerprints the old text');

            $lines = $activityLines($ticket->id);
            FsTest::assertSame(1, $lines->count(), 'one activity line');
            FsTest::assert(strpos($lines->first()->content, '4111') === false, 'activity line does not repeat the old text');
        });

        FsTest::case('customer message edit: admin edits the opening message and an activity line is added', function () use ($me, $secret, $activityLines) {
            $ticket = FsFactory::ticket(['agent_id' => $me->id, 'content' => '<p>Login: admin / ' . $secret . '</p>']);
            $fired = 0;
            $listener = function () use (&$fired) { $fired++; };
            add_action('fluent_support/ticket_content_updated', $listener);

            $redacted = '<p>Login: [removed] ' . FsFactory::marker() . '</p>';
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/content', ['content' => $redacted]);
            remove_action('fluent_support/ticket_content_updated', $listener);

            FsTest::assertHealthy($result, 'admin edits opening message');
            FsTest::assertSame($redacted, Ticket::find($ticket->id)->content, 'ticket content replaced');
            FsTest::assertSame(md5($redacted), Ticket::find($ticket->id)->content_hash, 'hash no longer fingerprints the old text');
            FsTest::assertSame(1, $fired, 'ticket_content_updated fired once');

            $lines = $activityLines($ticket->id);
            FsTest::assertSame(1, $lines->count(), 'one activity line');
            FsTest::assert(strpos($lines->first()->content, '4111') === false, 'activity line does not repeat the old text');
        });

        FsTest::case('customer message edit: an empty opening message is refused', function () use ($me) {
            $ticket = FsFactory::ticket(['agent_id' => $me->id]);
            $before = $ticket->content;

            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/content', ['content' => '<p> </p>']);

            FsTest::assert($result['status'] >= 400, 'empty content is rejected, got HTTP ' . $result['status']);
            FsTest::assertSame($before, Ticket::find($ticket->id)->content, 'content unchanged');
        });

        FsTest::case('customer message edit: an image-only opening message is accepted', function () use ($me) {
            $ticket = FsFactory::ticket(['agent_id' => $me->id]);
            $imageOnly = '<p><img src="https://example.invalid/' . FsFactory::marker() . '.png" alt="" /></p>';

            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/content', ['content' => $imageOnly]);

            FsTest::assertHealthy($result, 'image-only edit');
            FsTest::assert(strpos(Ticket::find($ticket->id)->content, '<img') !== false, 'image kept');
        });

        FsTest::case('customer message edit: an agent without manage_options cannot edit a customer reply', function () use ($agent, $agentUserId, $adminId, $runAs, $secret) {
            $ticket = FsFactory::ticket(['agent_id' => $agent->id]);
            $original = '<p>My ' . $secret . '</p>';
            $reply = FsFactory::conversation($ticket, ['content' => $original]);

            $runAs($agentUserId);
            try {
                $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/responses/' . $reply->id, ['content' => '<p>changed ' . FsFactory::marker() . '</p>']);
            } finally {
                $runAs($adminId);
            }

            FsTest::assert($result['status'] >= 400, 'edit is rejected, got HTTP ' . $result['status']);
            FsTest::assertSame($original, Conversation::find($reply->id)->content, 'reply unchanged');
        });

        FsTest::case('customer message edit: an agent without manage_options cannot edit the opening message', function () use ($agent, $agentUserId, $adminId, $runAs) {
            $ticket = FsFactory::ticket(['agent_id' => $agent->id]);
            $before = $ticket->content;

            $runAs($agentUserId);
            try {
                $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/content', ['content' => '<p>changed ' . FsFactory::marker() . '</p>']);
            } finally {
                $runAs($adminId);
            }

            FsTest::assertSame(403, $result['status'], 'policy refuses the route');
            FsTest::assertSame($before, Ticket::find($ticket->id)->content, 'content unchanged');
        });
    } finally {
        $runAs($adminId);

        if ($agentUserId && !is_wp_error($agentUserId)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($agentUserId);
        }
    }
};
