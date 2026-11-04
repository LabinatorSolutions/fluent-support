<?php
/**
 * A refusal that no controller catches still reaches the agent with its own
 * message and status. WPFluent 2.12 answers "An internal error occurred." for
 * any plain exception that reaches the router, so the controller guards throw
 * ApiException, which the framework renders as {message} with its status.
 * The widgets endpoint (filter ticket_sidebar) calls ensureCanAccessTicket()
 * outside any try block.
 * The viewer user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();

    $login = FsTest::uniq('fs-err-viewer');
    $viewerUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($viewerUserId)) {
        throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
    }

    try {
        $viewer = FsFactory::agent();
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        PermissionManager::attachPermissions($viewerUserId, ['fst_manage_own_tickets']);

        // Assigned to someone else, so a Manage Own Tickets agent may not open it.
        $ticket = FsFactory::ticket(['agent_id' => FsFactory::agent()->id]);

        FsTest::case('uncaught_ticket_refusal_keeps_its_message_and_status', function () use ($viewerUserId, $ticket) {
            wp_set_current_user($viewerUserId);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();

            $result = FsTest::rest('GET', 'widgets', [
                'filter' => 'ticket_sidebar',
                'data'   => ['ticket_id' => $ticket->id],
            ]);

            FsTest::assertSame(403, (int) $result['status'], 'refused widget request status');
            FsTest::assertSame(
                'Sorry, You do not have permission to this ticket',
                $result['message'],
                'refused widget request message'
            );
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($viewerUserId);
    }
};
