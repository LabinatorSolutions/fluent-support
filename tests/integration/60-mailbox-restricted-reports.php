<?php
/**
 * Mailbox restrictions in stats and reports.
 *
 * A viewer with `agent_restrictions` on a mailbox must not see its tickets or
 * replies in any aggregate. One fixture agent works one ticket (plus a reply)
 * in an allowed and one in a restricted mailbox; assertions only use its rows.
 *
 * Restriction resolves from the current user, so the viewer is a real WP user
 * linked to a fixture agent. The user and meta row aren't factory-tracked and
 * are removed in this file's finally.
 *
 * Pro cases skip by name when fluent-support-pro is not active.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Modules\Reports\Reports;

return function () {

    $adminId = get_current_user_id();
    $proActive = defined('FLUENTSUPPORTPRO')
        && class_exists('\FluentSupportPro\App\Modules\Reporting\AdvancedReportService')
        && class_exists('\FluentSupportPro\App\Modules\Reporting\SnapshotService');

    // ---- fixtures ---------------------------------------------------------

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $customer = FsFactory::customer();

    // The agent whose activity is measured.
    $worker = FsFactory::agent();

    // The agent who views the reports, restricted from $restrictedBox.
    $viewer = FsFactory::agent();

    $login = FsTest::uniq('fs-restricted-viewer');
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

    $asNobody = function () {
        wp_set_current_user(0);
        PermissionManager::currentUserPermissions(false);
    };

    $asAdmin = function () use ($adminId) {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
    };

    try {
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        $viewer->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        $allowedTicket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'mailbox_id'  => $allowedBox->id,
            'agent_id'    => $worker->id,
            'status'      => 'active',
            'title'       => 'Restricted-report allowed',
        ]);
        $restrictedTicket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'mailbox_id'  => $restrictedBox->id,
            'agent_id'    => $worker->id,
            'status'      => 'active',
            'title'       => 'Restricted-report restricted',
        ]);

        foreach ([$allowedTicket, $restrictedTicket] as $ticket) {
            FsFactory::conversation($ticket, [
                'person_id'         => $worker->id,
                'conversation_type' => 'response',
            ]);
        }

        $today = current_time('Y-m-d');

        // Without a real restriction every case below would pass vacuously.
        FsTest::case('restricted viewer: fixture agent resolves its mailbox restriction', function () use ($asViewer, $asAdmin, $restrictedBox) {
            $asViewer();
            try {
                FsTest::assertSame(
                    [(int) $restrictedBox->id],
                    PermissionManager::getRestrictedMailboxIds(),
                    'viewer restriction resolves from the current user'
                );
            } finally {
                $asAdmin();
            }
        });

        // ---- core: Reports::overview() ---------------------------------------

        FsTest::case('restricted viewer: Reports::overview excludes the restricted mailbox', function () use ($asNobody, $asViewer, $asAdmin, $restrictedBox) {
            // Baseline with no user: no agent, no restriction, site-wide.
            $asNobody();
            $unscoped = array_map(fn($count) => ['count' => $count], Reports::overview('', ''));

            // Exactly what the restricted mailbox contributes to each figure.
            $activeInBox = Ticket::where('mailbox_id', $restrictedBox->id)->where('status', 'active')->count();
            $responsesInBox = \FluentSupport\App\Models\Conversation::where('conversation_type', 'response')
                ->whereHas('ticket', function ($q) use ($restrictedBox) {
                    $q->where('mailbox_id', $restrictedBox->id);
                })->count();

            $asViewer();
            try {
                $scoped = array_map(fn($count) => ['count' => $count], Reports::overview('', ''));
            } finally {
                $asAdmin();
            }

            FsTest::assertSame(1, $activeInBox, 'fixture: one active ticket in the restricted mailbox');
            FsTest::assertSame(1, $responsesInBox, 'fixture: one response in the restricted mailbox');

            FsTest::assertSame(
                $unscoped['active_tickets']['count'] - $activeInBox,
                $scoped['active_tickets']['count'],
                'active_tickets drops the restricted mailbox'
            );
            FsTest::assertSame(
                $unscoped['responses']['count'] - $responsesInBox,
                $scoped['responses']['count'],
                'responses drops replies on restricted-mailbox tickets'
            );
        });

        // ---- core: ticket counts (admin bar, dashboard widget, MCP) --------

        FsTest::case('restricted viewer: quick links and MCP stats overview exclude the restricted mailbox', function () use ($asViewer, $asAdmin, $restrictedBox, $viewerUserId) {
            // MCP resources need ticket access; the viewer user is deleted at the end.
            PermissionManager::attachPermissions($viewerUserId, ['fst_view_tickets']);

            $unscoped = [
                'total'      => Ticket::count(),
                'new'        => Ticket::where('status', 'new')->count(),
                'active'     => Ticket::where('status', 'active')->count(),
                'closed'     => Ticket::where('status', 'closed')->count(),
                'unassigned' => Ticket::whereNull('agent_id')->count(),
            ];

            // Exactly what the restricted mailbox contributes to each figure.
            $inBox = function () use ($restrictedBox) {
                return Ticket::where('mailbox_id', $restrictedBox->id);
            };
            $expected = [
                'total'      => $unscoped['total'] - $inBox()->count(),
                'new'        => $unscoped['new'] - $inBox()->where('status', 'new')->count(),
                'active'     => $unscoped['active'] - $inBox()->where('status', 'active')->count(),
                'closed'     => $unscoped['closed'] - $inBox()->where('status', 'closed')->count(),
                'unassigned' => $unscoped['unassigned'] - $inBox()->whereNull('agent_id')->count(),
            ];

            $asViewer();
            try {
                $links = (new \FluentSupport\App\Services\Tickets\TicketStats())->getQuickLinks();
                $overview = \FluentSupport\App\Modules\MCP\Resources\ResourceProvider::readResource('fluentsupport://stats/overview');
            } finally {
                $asAdmin();
            }

            FsTest::assert($inBox()->count() > 0, 'fixture: the restricted mailbox has a ticket');
            FsTest::assertSame($expected, $overview, 'MCP stats overview');
            FsTest::assertSame($expected['total'], (int) $links[0]['number'], 'quick link: total');
            FsTest::assertSame($expected['active'], (int) $links[1]['number'], 'quick link: active');
        });

        // ---- core: Reports::agentSummary() ----------------------------------

        FsTest::case('restricted viewer: Reports::agentSummary excludes the restricted mailbox', function () use ($asViewer, $asAdmin, $worker) {
            $asViewer();
            try {
                $agents = Reports::agentSummary('', '');
            } finally {
                $asAdmin();
            }

            $row = null;
            foreach ($agents as $agent) {
                if ((int) $agent->id === (int) $worker->id) {
                    $row = $agent;
                    break;
                }
            }

            FsTest::assert($row !== null, 'worker agent is listed in the summary');
            if (!$row) {
                return;
            }

            FsTest::assertSame(1, (int) $row->stats['opens'], 'opens counts only the allowed-mailbox ticket');
            FsTest::assertSame(1, (int) $row->stats['responses'], 'responses counts only the allowed-mailbox reply');
            FsTest::assertSame(1, (int) $row->stats['interactions'], 'interactions counts only the allowed-mailbox ticket');
        });

        // ---- pro: AdvancedReportService::getPerformanceOverview() ----------

        FsTest::case('pro restricted viewer: getPerformanceOverview excludes the restricted mailbox', function () use ($proActive, $asViewer, $asAdmin, $worker, $today) {
            if (!$proActive) {
                FsTest::skip('fluent-support-pro is not active');
                return;
            }

            $asViewer();
            try {
                $overview = \FluentSupportPro\App\Modules\Reporting\AdvancedReportService::getPerformanceOverview(
                    $today, $today, [(int) $worker->id]
                );
            } finally {
                $asAdmin();
            }

            $agentRow = null;
            foreach ($overview['agents'] as $candidate) {
                if ((int) $candidate['id'] === (int) $worker->id) {
                    $agentRow = $candidate;
                    break;
                }
            }

            FsTest::assert($agentRow !== null, 'worker agent is listed in the performance overview');
            if ($agentRow) {
                FsTest::assertSame(1, (int) $agentRow['responses'], 'agent responses counts only the allowed-mailbox reply');
            }

            FsTest::assertSame(1, (int) $overview['totals']['open'], 'totals.open counts only the allowed-mailbox ticket');
            FsTest::assertSame(1, (int) $overview['tickets']['total'], 'ticket rows list only the allowed-mailbox ticket');
        });

        // ---- pro: SnapshotService::takeSnapshot() under a restricted user --

        FsTest::case('pro: takeSnapshot stores site-wide figures even when a restricted user runs cron', function () use ($proActive, $asNobody, $asViewer, $asAdmin, $viewerUserId) {
            if (!$proActive) {
                FsTest::skip('fluent-support-pro is not active');
                return;
            }

            $snapshotService = '\FluentSupportPro\App\Modules\Reporting\SnapshotService';
            $snapshotModel = '\FluentSupportPro\App\Models\ReportSnapshot';

            // Unscoped figures over the snapshot's cumulative range.
            $asNobody();
            $expected = \FluentSupportPro\App\Modules\Reporting\AdvancedReportService::getTicketOverview(
                $snapshotService::CUMULATIVE_START_DATE,
                current_time('Y-m-d')
            );

            $maxIdBefore = (int) $snapshotModel::max('id');

            // Cron can run with a user set (ALTERNATE_WP_CRON, `wp cron event run --user`).
            $asViewer();
            try {
                $snapshotService::takeSnapshot();
                $userAfter = get_current_user_id();
            } finally {
                $asAdmin();
            }

            try {
                $stored = $snapshotModel::ofType('ticket_overview')
                    ->where('id', '>', $maxIdBefore)
                    ->orderBy('id', 'desc')
                    ->first();

                FsTest::assert($stored !== null, 'a ticket_overview snapshot row was written');
                if (!$stored) {
                    return;
                }

                FsTest::assertSame($expected['tickets'], $stored->data['tickets'], 'snapshot ticket counts are site-wide, not the viewer\'s scope');
                FsTest::assertSame($expected['workload'], $stored->data['workload'], 'snapshot workload is site-wide, not the viewer\'s scope');
                FsTest::assertSame($viewerUserId, $userAfter, 'the caller\'s user is restored after capture');
            } finally {
                // Snapshot rows are not factory-tracked.
                $snapshotModel::where('id', '>', $maxIdBefore)->delete();
            }
        });

        // ---- core (pro on): Reports::agentSummary() feedback counts --------

        // Last on purpose: it adds a ticket to $restrictedBox.
        FsTest::case('restricted viewer: agentSummary counts every like, none from a restricted mailbox', function () use ($asViewer, $asAdmin, $customer, $allowedBox, $restrictedBox, $worker) {
            if (!defined('FLUENTSUPPORTPRO_PLUGIN_VERSION')) {
                FsTest::skip('fluent-support-pro is not active');
                return;
            }
            if (\FluentSupport\App\Services\Helper::getBusinessSettings('agent_feedback_rating') !== 'yes') {
                FsTest::skip('Agent feedback rating is off on this site');
                return;
            }

            // Agent::getAgentIds() is cached per request, so rate the agent
            // created at the top of this file rather than a new one.
            $rater = $worker;
            $allowed = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $allowedBox->id, 'agent_id' => $rater->id]);
            $hidden = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id, 'agent_id' => $rater->id]);

            // More responses than GROUP_CONCAT's 1024-byte default can list.
            $rate = function ($ticket, $value) use ($rater) {
                $response = FsFactory::conversation($ticket, ['person_id' => $rater->id, 'conversation_type' => 'response']);
                Meta::create([
                    'object_type' => 'conversation_meta',
                    'object_id'   => $response->id,
                    'key'         => 'agent_feedback_ratings',
                    'value'       => $value,
                ]);
                return $response->id;
            };
            $ratedIds = [];
            for ($i = 0; $i < 250; $i++) {
                $ratedIds[] = $rate($allowed, 'like');
            }
            $ratedIds[] = $rate($allowed, 'dislike');
            $ratedIds[] = $rate($hidden, 'like');

            // MariaDB defaults group_concat_max_len to 1MB; MySQL to 1024 bytes.
            // Run at MySQL's default so a GROUP_CONCAT shortcut is caught.
            global $wpdb;
            $previousConcatLen = $wpdb->get_var('SELECT @@SESSION.group_concat_max_len');
            $wpdb->query('SET SESSION group_concat_max_len = 1024');

            try {
                $asViewer();
                try {
                    $agents = Reports::agentSummary('', '', [$rater->id]);
                } finally {
                    $asAdmin();
                    $wpdb->query($wpdb->prepare('SET SESSION group_concat_max_len = %d', $previousConcatLen));
                }

                $row = null;
                foreach ($agents as $agent) {
                    if ((int) $agent->id === (int) $rater->id) {
                        $row = $agent;
                    }
                }

                FsTest::assert($row !== null, 'rater agent is listed in the summary');
                if (!$row) {
                    return;
                }
                FsTest::assertSame(250, (int) $row->stats['likes'], 'every like on the allowed mailbox counts; the restricted one does not');
                FsTest::assertSame(1, (int) $row->stats['dislikes'], 'dislikes counted');
            } finally {
                Meta::where('object_type', 'conversation_meta')
                    ->where('key', 'agent_feedback_ratings')
                    ->whereIn('object_id', $ratedIds)
                    ->delete();
            }
        });
    } finally {
        $asAdmin();

        Meta::where('object_id', $viewer->id)
            ->where('object_type', 'person_meta')
            ->where('key', 'agent_restrictions')
            ->delete();

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if (get_user_by('ID', $viewerUserId)) {
            wp_delete_user($viewerUserId);
        }
    }
};
