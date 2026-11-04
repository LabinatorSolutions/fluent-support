<?php
/**
 * FluentCRM "Support Tickets" provider returns only tickets the current user could open; CRM-only users get none.
 * The viewer users and restriction meta aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Integrations\FluentCrm\FluentCRMWidgets;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();

    // ---- fixtures ---------------------------------------------------------

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $customer = FsFactory::customer();
    $otherAgent = FsFactory::agent();

    // Agent viewer: can open only its own tickets outside $restrictedBox.
    $viewer = FsFactory::agent();

    $userIds = [];
    $makeUser = function ($prefix) use (&$userIds) {
        $login = FsTest::uniq($prefix);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }

        $userIds[] = $userId;
        return $userId;
    };

    $viewerUserId = $makeUser('fs-crm-agent');
    // CRM-only user: no agent record, so no Fluent Support ticket access at all.
    $crmOnlyUserId = $makeUser('fs-crm-only');

    // Agent record without any ticket permission (e.g. reports only): ticket routes refuse it.
    $noTicketAgent = FsFactory::agent();
    $noTicketUserId = $makeUser('fs-crm-no-ticket-perm');

    // Merge-only agent: passes the broad ticket-route gate but can't open a ticket.
    $mergeOnlyAgent = FsFactory::agent();
    $mergeOnlyUserId = $makeUser('fs-crm-merge-only');

    $switchTo = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // [total, sorted row ids] from the provider; the subscriber only needs an email.
    $provider = function () use ($customer) {
        $result = (new FluentCRMWidgets())->getSupportTickets(
            ['data' => [], 'total' => 0],
            (object) ['email' => $customer->email]
        );

        $ids = array_map(function ($row) {
            return (int) ltrim($row['id'], '#');
        }, $result['data']);
        sort($ids);

        return [(int) $result['total'], $ids];
    };

    $sortedIds = function (array $tickets) {
        $ids = array_map(function ($ticket) {
            return (int) $ticket->id;
        }, $tickets);
        sort($ids);

        return $ids;
    };

    try {
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        PermissionManager::attachPermissions($viewerUserId, ['fst_manage_own_tickets']);
        $viewer->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        $noTicketAgent->user_id = $noTicketUserId;
        $noTicketAgent->save();
        PermissionManager::attachPermissions($noTicketUserId, ['fst_view_dashboard']);

        $mergeOnlyAgent->user_id = $mergeOnlyUserId;
        $mergeOnlyAgent->save();
        PermissionManager::attachPermissions($mergeOnlyUserId, ['fst_merge_tickets']);

        $ticket = function ($boxId, $agentId, $title) use ($customer) {
            return FsFactory::ticket([
                'customer_id' => $customer->id,
                'mailbox_id'  => $boxId,
                'agent_id'    => $agentId,
                'status'      => 'active',
                'title'       => $title,
            ]);
        };

        $own = $ticket($allowedBox->id, $viewer->id, 'CRM own');
        $mailboxDenied = $ticket($restrictedBox->id, $viewer->id, 'CRM restricted mailbox');
        $unassigned = $ticket($allowedBox->id, null, 'CRM unassigned');
        $othersTicket = $ticket($allowedBox->id, $otherAgent->id, 'CRM other agent');
        $noTicketPermTicket = $ticket($allowedBox->id, $noTicketAgent->id, 'CRM assigned to agent without ticket permission');
        $mergeOnlyTicket = $ticket($allowedBox->id, $mergeOnlyAgent->id, 'CRM assigned to merge-only agent');

        // No mailbox is never restricted. The factory always sets one, so clear it.
        $noMailbox = $ticket($allowedBox->id, $viewer->id, 'CRM no mailbox');
        Ticket::where('id', $noMailbox->id)->update(['mailbox_id' => null]);

        // Without real restrictions the scoped case would pass vacuously.
        FsTest::case('fluentcrm tickets: agent viewer resolves assigned-only visibility and its mailbox restriction', function () use ($switchTo, $viewerUserId, $adminId, $restrictedBox) {
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame(
                    PermissionManager::VISIBILITY_ASSIGNED_ONLY,
                    PermissionManager::getAgentTicketVisibility($viewerUserId),
                    'viewer visibility is assigned-only'
                );
                FsTest::assertSame(
                    [(int) $restrictedBox->id],
                    PermissionManager::getRestrictedMailboxIds(),
                    'viewer restriction resolves from the current user'
                );
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('fluentcrm tickets: CRM-only user gets no tickets', function () use ($switchTo, $crmOnlyUserId, $adminId, $provider) {
            $switchTo($crmOnlyUserId);
            try {
                FsTest::assertSame([0, []], $provider(), 'a user without an agent record sees no tickets');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('fluentcrm tickets: agent without ticket permission gets no tickets', function () use ($switchTo, $noTicketUserId, $adminId, $provider, $noTicketPermTicket) {
            $switchTo($noTicketUserId);
            try {
                // Control: Fluent Support's own ticket routes refuse this agent.
                FsTest::assertSame(
                    403,
                    FsTest::rest('GET', '/tickets/' . $noTicketPermTicket->id)['status'],
                    'ticket route refuses an agent without ticket permission'
                );
                FsTest::assertSame([0, []], $provider(), 'even its own assigned ticket is hidden');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('fluentcrm tickets: merge-only agent gets no tickets', function () use ($switchTo, $mergeOnlyUserId, $adminId, $provider, $mergeOnlyTicket) {
            $switchTo($mergeOnlyUserId);
            try {
                // Control: the View Ticket link would 403 for this agent.
                FsTest::assertSame(
                    403,
                    FsTest::rest('GET', '/tickets/' . $mergeOnlyTicket->id)['status'],
                    'ticket route refuses a merge-only agent'
                );
                FsTest::assertSame([0, []], $provider(), 'even its own assigned ticket is hidden');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('fluentcrm tickets: restricted agent gets only tickets it can open', function () use ($switchTo, $viewerUserId, $adminId, $provider, $sortedIds, $own, $noMailbox) {
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame([2, $sortedIds([$own, $noMailbox])], $provider(), 'own ticket and no-mailbox ticket only');
            } finally {
                $switchTo($adminId);
            }
        });

        // FluentCRM renders these columns with v-html; some importers store raw subjects.
        FsTest::case('fluentcrm tickets: an HTML ticket title is escaped', function () use ($switchTo, $adminId, $customer, $own) {
            $switchTo($adminId);

            $rawTitle = $own->title;
            $payload = '<img src=x onerror="alert(1)">' . $rawTitle;
            Ticket::where('id', $own->id)->update(['title' => $payload]);

            try {
                $result = (new FluentCRMWidgets())->getSupportTickets(['data' => [], 'total' => 0], (object) ['email' => $customer->email]);
                $row = null;
                foreach ($result['data'] as $candidate) {
                    if ($candidate['id'] === '#' . $own->id) {
                        $row = $candidate;
                    }
                }

                FsTest::assert($row !== null, 'the ticket is listed');
                if ($row) {
                    FsTest::assertSame(esc_html($payload), $row['title'], 'title is HTML-escaped');
                }
            } finally {
                Ticket::where('id', $own->id)->update(['title' => $rawTitle]);
            }
        });

        FsTest::case('fluentcrm tickets: admin still gets every ticket', function () use ($switchTo, $adminId, $provider, $sortedIds, $own, $mailboxDenied, $unassigned, $othersTicket, $noMailbox, $noTicketPermTicket, $mergeOnlyTicket) {
            $switchTo($adminId);
            FsTest::assertSame(
                [7, $sortedIds([$own, $mailboxDenied, $unassigned, $othersTicket, $noMailbox, $noTicketPermTicket, $mergeOnlyTicket])],
                $provider(),
                'admin sees all seven tickets'
            );
        });
    } finally {
        $switchTo($adminId);

        Meta::where('object_id', $viewer->id)
            ->where('object_type', 'person_meta')
            ->where('key', 'agent_restrictions')
            ->delete();

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ($userIds as $userId) {
            if (get_user_by('ID', $userId)) {
                wp_delete_user($userId);
            }
        }
    }
};
