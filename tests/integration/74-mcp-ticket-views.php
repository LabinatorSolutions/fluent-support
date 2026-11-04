<?php
/**
 * MCP ticket views: get-ticket-views and get-support-context's views and stats,
 * plus the stats/overview resource and the admin bar's quick links.
 *
 * Each view is a ticket list sidebar queue. Its count must equal the total
 * list-tickets returns for its list_tickets arguments, and both must respect
 * the agent's ticket visibility and mailbox restrictions.
 *
 * The own-only agent is a real WP user linked to a fixture agent (visibility
 * comes from the current user). The user isn't factory-tracked and is removed
 * in this file's finally.
 */

use FluentSupport\App\Modules\MCP\Support\AbilityGuard;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;

return function () {

    $adminId = get_current_user_id();

    $plain = function ($value) {
        return json_decode(wp_json_encode($value), true);
    };

    $views = function () use ($plain) {
        return $plain(ManagementTools::getTicketViews([]))['data']['views'];
    };

    $context = function () use ($plain) {
        ManagementTools::invalidateSupportContextCache();
        return $plain(ManagementTools::getSupportContext([]))['data'];
    };

    $listTotal = function ($args) use ($plain) {
        $result = $plain(TicketTools::listTickets(array_merge($args, ['per_page' => 1])));
        FsTest::assert(isset($result['meta']['paging']['total']), 'list-tickets returned a total for ' . wp_json_encode($args));
        return (int) $result['meta']['paging']['total'];
    };

    $assertParity = function ($views, $who) use ($listTotal) {
        FsTest::assert(count($views) > 0, $who . ': views are returned');
        foreach ($views as $view) {
            FsTest::assertSame($listTotal($view['list_tickets']), (int) $view['count'], $who . ': ' . $view['key']);
        }
    };

    $ownOnly = FsFactory::agent();
    $login = FsTest::uniq('fs-mcp-views-own-only');
    $ownOnlyUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($ownOnlyUserId)) {
        throw new RuntimeException('Could not create own-only user: ' . $ownOnlyUserId->get_error_message());
    }

    $asOwnOnly = function () use ($ownOnlyUserId) {
        wp_set_current_user($ownOnlyUserId);
        PermissionManager::currentUserPermissions(false);
    };

    $asAdmin = function () use ($adminId) {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
    };

    try {
        $ownOnly->user_id = $ownOnlyUserId;
        $ownOnly->save();
        // Sees only the tickets assigned to them.
        PermissionManager::attachPermissions($ownOnlyUserId, ['fst_view_tickets', 'fst_manage_own_tickets']);

        // One open ticket for the own-only agent, plus team tickets they must not count.
        FsFactory::ticket(['status' => 'active', 'priority' => 'critical', 'agent_id' => $ownOnly->id]);
        FsFactory::ticket(['status' => 'new', 'priority' => 'critical']);
        FsFactory::ticket(['status' => 'active', 'priority' => 'medium', 'agent_id' => FsFactory::agent()->id]);

        FsTest::case('mcp views: every view count matches list-tickets (admin)', function () use ($views, $assertParity) {
            if (!Helper::getAgentByUserId()) {
                FsTest::skip('the admin user has no agent row');
                return;
            }

            $assertParity($views(), 'admin');
        });

        FsTest::case('mcp views: an assigned-only agent gets only their own tickets', function () use ($asOwnOnly, $asAdmin, $views, $assertParity) {
            $asOwnOnly();
            try {
                FsTest::assertSame(PermissionManager::VISIBILITY_ASSIGNED_ONLY, PermissionManager::getAgentTicketVisibility(), 'fixture: own-only visibility');

                $own = $views();
                $byKey = array_column($own, 'count', 'key');

                FsTest::assert(!isset($byKey['unassigned']), 'no unassigned view for an assigned-only agent');
                FsTest::assertSame(
                    ['assigned_to_me' => 1, 'priority_critical' => 1, 'priority_medium' => 0, 'status_all' => 1],
                    [
                        'assigned_to_me'    => $byKey['assigned_to_me'],
                        'priority_critical' => $byKey['priority_critical'],
                        'priority_medium'   => $byKey['priority_medium'],
                        'status_all'        => $byKey['status_all'],
                    ],
                    'own-only counts'
                );
                $assertParity($own, 'own-only');
            } finally {
                $asAdmin();
            }
        });

        // Before, stats only applied mailbox restrictions and showed team-wide totals.
        FsTest::case('mcp views: get-support-context stats follow visibility and match the views', function () use ($asOwnOnly, $asAdmin, $context) {
            $asOwnOnly();
            try {
                $data = $context();
            } finally {
                $asAdmin();
            }

            $byKey = array_column($data['views'], 'count', 'key');

            FsTest::assertSame(1, (int) $data['stats']['total'], 'stats.total counts only the own ticket');
            FsTest::assertSame(0, (int) $data['stats']['unassigned'], 'stats.unassigned is 0 for an assigned-only agent');
            FsTest::assertSame($byKey['status_all'], (int) $data['stats']['total'], 'stats.total matches the all view');
            FsTest::assertSame($byKey['status_closed'], (int) $data['stats']['closed'], 'stats.closed matches the closed view');
        });

        // The stats resource and the admin bar's quick links read the same scope as the list.
        FsTest::case('mcp views: the stats resource and quick links count only what the agent can see', function () use ($asOwnOnly, $asAdmin) {
            $asOwnOnly();
            try {
                $overview = \FluentSupport\App\Modules\MCP\Resources\ResourceProvider::readResource('fluentsupport://stats/overview');
                $links = (new \FluentSupport\App\Services\Tickets\TicketStats())->getQuickLinks();
            } finally {
                $asAdmin();
            }

            FsTest::assertSame(
                ['total' => 1, 'new' => 0, 'active' => 1, 'closed' => 0, 'unassigned' => 0],
                $overview,
                'stats/overview counts only the own ticket'
            );

            $numbers = array_column($links, 'number', 'title');
            FsTest::assertSame(1, (int) $numbers[__('Total Tickets', 'fluent-support')], 'quick link: total');
            FsTest::assertSame(0, (int) $numbers[__('Un-Assigned Tickets', 'fluent-support')], 'quick link: unassigned');
            // The own ticket has no agent reply yet, so it waits on an agent.
            FsTest::assertSame(1, (int) $numbers[__('Awaiting Replies', 'fluent-support')], 'quick link: awaiting replies');
        });

        FsTest::case('mcp views: get-ticket-views needs ticket access', function () use ($asAdmin) {
            wp_set_current_user(0);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assert(!AbilityGuard::check('fluent-support/get-ticket-views'), 'a visitor is refused');
            } finally {
                $asAdmin();
            }

            FsTest::assert(AbilityGuard::check('fluent-support/get-ticket-views'), 'an admin is allowed');
        });
    } finally {
        $asAdmin();
        \FluentSupport\App\Models\Meta::where('object_type', 'person_meta')
            ->where('object_id', $ownOnly->id)
            ->delete();
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($ownOnlyUserId);
        ManagementTools::invalidateSupportContextCache();
    }
};
