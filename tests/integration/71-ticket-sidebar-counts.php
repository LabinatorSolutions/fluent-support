<?php
/**
 * Ticket list sidebar counts (`GET tickets/sidebar-counts`).
 *
 * Each sidebar view is a preset of list filters, so its count must equal the
 * total the list returns for those filters, and both must honour the agent's
 * ticket visibility and mailbox restrictions.
 *
 * The viewer is a real WP user linked to a fixture agent (visibility comes
 * from the current user). The user and its meta row aren't factory-tracked
 * and are removed in this file's finally.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\TicketStats;

return function () {

    $adminId = get_current_user_id();

    // Sidebar view => the list filters the sidebar sends for it.
    $viewFilters = function ($agentId) {
        $views = [
            'open.mine'       => ['status_type' => 'open', 'agent_id' => [$agentId]],
            'open.unassigned' => ['status_type' => 'open', 'agent_id' => ['unassigned']],
            'open.waiting'    => ['status_type' => 'open', 'waiting_for_reply' => 'yes'],
        ];
        foreach (array_keys(Helper::adminTicketPriorities()) as $priority) {
            $views['priorities.' . $priority] = ['status_type' => 'open', 'priority' => [$priority]];
        }
        foreach (array_keys(Helper::ticketStatusGroups()) as $group) {
            $views['statuses.' . $group] = ['status_type' => $group];
        }
        return $views;
    };

    $countFor = function ($counts, $key) {
        list($section, $name) = explode('.', $key, 2);
        return (int) $counts[$section][$name];
    };

    $listTotal = function ($filters) {
        $result = FsTest::rest('GET', '/tickets', ['filters' => $filters, 'per_page' => 1, 'filter_type' => 'simple']);
        FsTest::assertHealthy($result, 'ticket list ' . wp_json_encode($filters));
        return (int) $result['data']['tickets']->total();
    };

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $customer = FsFactory::customer();
    $worker = FsFactory::agent();
    $viewer = FsFactory::agent();

    $ownOnly = FsFactory::agent();
    $ownOnlyLogin = FsTest::uniq('fs-sidebar-own-only');
    $ownOnlyUserId = wp_insert_user([
        'user_login' => $ownOnlyLogin,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $ownOnlyLogin . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($ownOnlyUserId)) {
        throw new RuntimeException('Could not create own-only user: ' . $ownOnlyUserId->get_error_message());
    }

    $asOwnOnly = function () use ($ownOnlyUserId) {
        wp_set_current_user($ownOnlyUserId);
        PermissionManager::currentUserPermissions(false);
    };

    $login = FsTest::uniq('fs-sidebar-viewer');
    $viewerUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($viewerUserId)) {
        throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
    }

    $asViewer = function () use ($viewerUserId) {
        wp_set_current_user($viewerUserId);
        PermissionManager::currentUserPermissions(false);
    };

    $asAdmin = function () use ($adminId) {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
    };

    try {
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        // Sees tickets assigned to them plus unassigned ones, never others'.
        PermissionManager::attachPermissions($viewerUserId, ['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets']);
        $viewer->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        // Sees only the tickets assigned to them.
        $ownOnly->user_id = $ownOnlyUserId;
        $ownOnly->save();
        PermissionManager::attachPermissions($ownOnlyUserId, ['fst_manage_own_tickets']);

        $asViewer();
        try {
            $before = (new TicketStats())->getSidebarCounts($viewer);
        } finally {
            $asAdmin();
        }

        $base = ['customer_id' => $customer->id, 'mailbox_id' => $allowedBox->id];
        $earlier = date('Y-m-d H:i:s', current_time('timestamp') - 3600);
        $later = current_time('mysql');

        // New and unassigned: waiting by definition.
        $newTicket = FsFactory::ticket($base + ['status' => 'new', 'priority' => 'critical']);
        // Mine, the customer wrote last: waiting.
        FsFactory::ticket($base + [
            'status' => 'active', 'priority' => 'normal', 'agent_id' => $viewer->id,
            'last_agent_response' => $earlier, 'last_customer_response' => $later,
        ]);
        // Another agent's ticket: outside the viewer's visibility.
        FsFactory::ticket($base + [
            'status' => 'active', 'priority' => 'medium', 'agent_id' => $worker->id,
            'last_agent_response' => $later, 'last_customer_response' => $earlier,
        ]);
        // Mine but closed: only in the closed and all counts.
        FsFactory::ticket($base + ['status' => 'closed', 'priority' => 'normal', 'agent_id' => $viewer->id]);
        // The own-only agent's single ticket.
        FsFactory::ticket($base + ['status' => 'active', 'priority' => 'normal', 'agent_id' => $ownOnly->id]);
        // Mine but in a restricted mailbox: never counted.
        FsFactory::ticket([
            'customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id,
            'status' => 'active', 'priority' => 'critical', 'agent_id' => $viewer->id,
        ]);

        FsTest::case('sidebar counts: fixtures move each count by what the viewer may see', function () use ($asViewer, $asAdmin, $viewer, $before) {
            $asViewer();
            try {
                $after = (new TicketStats())->getSidebarCounts($viewer);
            } finally {
                $asAdmin();
            }

            $delta = function ($section, $name) use ($before, $after) {
                return (int) $after[$section][$name] - (int) $before[$section][$name];
            };

            FsTest::assertSame(
                ['new' => 1, 'active' => 1, 'closed' => 1, 'open' => 2, 'all' => 3],
                [
                    'new'    => $delta('statuses', 'new'),
                    'active' => $delta('statuses', 'active'),
                    'closed' => $delta('statuses', 'closed'),
                    'open'   => $delta('statuses', 'open'),
                    'all'    => $delta('statuses', 'all'),
                ],
                'status counts skip the other agent\'s and the restricted-mailbox tickets'
            );
            FsTest::assertSame(
                ['mine' => 1, 'unassigned' => 1, 'waiting' => 2],
                ['mine' => $delta('open', 'mine'), 'unassigned' => $delta('open', 'unassigned'), 'waiting' => $delta('open', 'waiting')],
                'open view counts'
            );
            FsTest::assertSame(
                ['critical' => 1, 'normal' => 1, 'medium' => 0],
                ['critical' => $delta('priorities', 'critical'), 'normal' => $delta('priorities', 'normal'), 'medium' => $delta('priorities', 'medium')],
                'priority counts cover open tickets only'
            );
        });

        FsTest::case('sidebar counts: every view count matches the ticket list total (restricted viewer)', function () use ($asViewer, $asAdmin, $viewer, $viewFilters, $countFor, $listTotal) {
            $asViewer();
            try {
                $result = FsTest::rest('GET', '/tickets/sidebar-counts');
                FsTest::assertHealthy($result, 'sidebar-counts as viewer');
                $counts = $result['data']['counts'];

                foreach ($viewFilters($viewer->id) as $key => $filters) {
                    FsTest::assertSame($listTotal($filters), $countFor($counts, $key), 'viewer: ' . $key);
                }
            } finally {
                $asAdmin();
            }
        });

        FsTest::case('sidebar counts: an assigned-only agent counts just their own tickets', function () use ($asOwnOnly, $asAdmin, $ownOnly, $viewFilters, $countFor, $listTotal) {
            $asOwnOnly();
            try {
                FsTest::assertSame(PermissionManager::VISIBILITY_ASSIGNED_ONLY, PermissionManager::getAgentTicketVisibility(), 'fixture: own-only visibility');

                $result = FsTest::rest('GET', '/tickets/sidebar-counts');
                FsTest::assertHealthy($result, 'sidebar-counts as own-only agent');
                $counts = $result['data']['counts'];

                FsTest::assertSame(['mine' => 1, 'unassigned' => 0], ['mine' => (int) $counts['open']['mine'], 'unassigned' => (int) $counts['open']['unassigned']], 'own-only open counts');
                FsTest::assertSame(1, (int) $counts['statuses']['all'], 'own-only sees one ticket in total');

                foreach ($viewFilters($ownOnly->id) as $key => $filters) {
                    FsTest::assertSame($listTotal($filters), $countFor($counts, $key), 'own-only: ' . $key);
                }
            } finally {
                $asAdmin();
            }
        });

        // Add-ons narrow the list through this hook; the counts must follow.
        FsTest::case('sidebar counts: a visibility hook that hides a ticket hides it from the counts too', function () use ($asViewer, $asAdmin, $viewer, $newTicket, $viewFilters, $countFor, $listTotal) {
            $hide = function ($query) use ($newTicket) {
                $query->where('id', '!=', $newTicket->id);
            };

            $asViewer();
            try {
                $before = (new TicketStats())->getSidebarCounts($viewer);

                add_action('fluent_support/tickets_query_by_permission_ref', $hide, 20);
                try {
                    $after = (new TicketStats())->getSidebarCounts($viewer);
                    foreach ($viewFilters($viewer->id) as $key => $filters) {
                        FsTest::assertSame($listTotal($filters), $countFor($after, $key), 'hooked: ' . $key);
                    }
                } finally {
                    remove_action('fluent_support/tickets_query_by_permission_ref', $hide, 20);
                }
            } finally {
                $asAdmin();
            }

            FsTest::assertSame(
                ['all' => 1, 'unassigned' => 1, 'critical' => 1],
                [
                    'all'        => (int) $before['statuses']['all'] - (int) $after['statuses']['all'],
                    'unassigned' => (int) $before['open']['unassigned'] - (int) $after['open']['unassigned'],
                    'critical'   => (int) $before['priorities']['critical'] - (int) $after['priorities']['critical'],
                ],
                'the hidden new, unassigned, critical ticket drops out'
            );
        });

        FsTest::case('sidebar counts: every view count matches the ticket list total (admin)', function () use ($viewFilters, $countFor, $listTotal) {
            $me = Helper::getAgentByUserId();
            if (!$me) {
                FsTest::skip('the admin user has no agent row');
                return;
            }

            $result = FsTest::rest('GET', '/tickets/sidebar-counts');
            FsTest::assertHealthy($result, 'sidebar-counts as admin');
            $counts = $result['data']['counts'];

            foreach ($viewFilters($me->id) as $key => $filters) {
                FsTest::assertSame($listTotal($filters), $countFor($counts, $key), 'admin: ' . $key);
            }
        });
    } finally {
        $asAdmin();
        \FluentSupport\App\Models\Meta::where('object_type', 'person_meta')
            ->where('object_id', $viewer->id)
            ->delete();
        \FluentSupport\App\Models\Meta::where('object_type', 'person_meta')
            ->where('object_id', $ownOnly->id)
            ->delete();
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($viewerUserId);
        wp_delete_user($ownOnlyUserId);
    }
};
