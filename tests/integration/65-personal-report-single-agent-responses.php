<?php
/**
 * GET /reports/stats — single-agent response counting (agent_id scopes
 * responses by who authored them, not by ticket assignment), the removed
 * total_replies card, and two regression paths around it: agent_id given
 * alone (no mailbox_id), and mailbox restriction on the responses query.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Modules\Reports\Reports;

return function () {

    $mailbox = FsFactory::mailbox();
    $customer = FsFactory::customer();
    $agentA = FsFactory::agent();
    $agentB = FsFactory::agent();

    // Assigned to A, but both replies were actually written by B.
    $ticketAssignedToA = FsFactory::ticket([
        'customer_id' => $customer->id,
        'mailbox_id'  => $mailbox->id,
        'agent_id'    => $agentA->id,
        'status'      => 'active',
        'title'       => 'Single-agent-stats assigned to A',
    ]);
    FsFactory::conversation($ticketAssignedToA, [
        'person_id'         => $agentB->id,
        'conversation_type' => 'response',
    ]);
    FsFactory::conversation($ticketAssignedToA, [
        'person_id'         => $agentB->id,
        'conversation_type' => 'response',
    ]);

    // Assigned to B, but the one reply on it was written by A.
    $ticketAssignedToB = FsFactory::ticket([
        'customer_id' => $customer->id,
        'mailbox_id'  => $mailbox->id,
        'agent_id'    => $agentB->id,
        'status'      => 'active',
        'title'       => 'Single-agent-stats assigned to B',
    ]);
    FsFactory::conversation($ticketAssignedToB, [
        'person_id'         => $agentA->id,
        'conversation_type' => 'response',
    ]);

    FsTest::case('reports/stats?agent_id=A counts replies A authored, not replies on tickets assigned to A', function () use ($mailbox, $agentA) {
        $result = FsTest::rest('GET', '/reports/stats', [
            'agent_id'   => $agentA->id,
            'mailbox_id' => $mailbox->id,
        ]);
        FsTest::assertHealthy($result, 'GET /reports/stats?agent_id=A');

        $reports = $result['data']['overall_reports'] ?? null;
        FsTest::assert(is_array($reports), 'overall_reports is present');
        if (!is_array($reports)) {
            return;
        }

        // Ticket-assignment scoping (the bug) would see only
        // $ticketAssignedToA (the one ticket with ticket.agent_id = A) and
        // count both of B's replies on it: 2. Authorship scoping (the fix)
        // counts A's one reply, written on $ticketAssignedToB: 1.
        FsTest::assertSame(1, (int) ($reports['responses']['count'] ?? null), 'responses counts only replies A personally wrote');

        FsTest::assertSame(
            __('Total Responses', 'fluent-support'),
            $reports['responses']['title'] ?? null,
            'responses is labelled "Total Responses" when scoped to one agent'
        );
        FsTest::assert(!array_key_exists('total_replies', $reports), 'the removed total_replies stat is not returned');
    });

    FsTest::case('reports/stats?agent_id=A with no mailbox_id still counts only replies A authored', function () use ($agentA) {
        // No mailbox_id/product_id/customer_id: $ticketFilters is empty after
        // agent_id is unset, so getStats() skips the ticket whereHas join
        // entirely and relies on the person_id filter alone. Person_id scoping
        // on its own fixture rows is safe to assert exactly because agentA's
        // id is unique to this test.
        $result = FsTest::rest('GET', '/reports/stats', [
            'agent_id' => $agentA->id,
        ]);
        FsTest::assertHealthy($result, 'GET /reports/stats?agent_id=A (no mailbox_id)');

        $reports = $result['data']['overall_reports'] ?? null;
        FsTest::assert(is_array($reports), 'overall_reports is present');
        if (!is_array($reports)) {
            return;
        }

        FsTest::assertSame(1, (int) ($reports['responses']['count'] ?? null), 'responses counts only replies A personally wrote, with no mailbox filter applied');
    });

    FsTest::case('reports/stats without an agent filter counts every reply and keeps the default label', function () use ($mailbox) {
        $result = FsTest::rest('GET', '/reports/stats', [
            'mailbox_id' => $mailbox->id,
        ]);
        FsTest::assertHealthy($result, 'GET /reports/stats (no agent_id)');

        $reports = $result['data']['overall_reports'] ?? null;
        FsTest::assert(is_array($reports), 'overall_reports is present');
        if (!is_array($reports)) {
            return;
        }

        // All three fixture replies, unscoped by author.
        FsTest::assertSame(3, (int) ($reports['responses']['count'] ?? null), 'responses counts every reply across both fixture tickets');

        FsTest::assertSame(
            __('Responses', 'fluent-support'),
            $reports['responses']['title'] ?? null,
            'responses keeps its default label when no agent_id filter is applied'
        );
        FsTest::assert(!array_key_exists('total_replies', $reports), 'the removed total_replies stat is not returned');
    });

    FsTest::case('Reports::agentToday labels its responses stat "Total Responses"', function () use ($agentA) {
        $stat = Reports::agentToday($agentA->id);

        FsTest::assert(isset($stat['responses']['title']), 'responses stat is present');
        FsTest::assertSame(
            __('Total Responses', 'fluent-support'),
            $stat['responses']['title'] ?? null,
            'agent dashboard responses stat uses the renamed label'
        );
    });

    FsTest::case('reports/stats?agent_id=A for a restricted viewer excludes a restricted mailbox from the responses count', function () use ($agentA) {
        // The agent_id/person_id filter sits right next to the
        // applyMailboxRestrictionScopeViaTicket() wrapper on the responses
        // query in getStats() — a change to one is a change easy to make
        // without noticing it drops the other.
        $restrictedBox = FsFactory::mailbox();
        $customer = FsFactory::customer();

        $restrictedTicket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'mailbox_id'  => $restrictedBox->id,
            'agent_id'    => $agentA->id,
            'status'      => 'active',
            'title'       => 'Single-agent-stats restricted mailbox',
        ]);
        FsFactory::conversation($restrictedTicket, [
            'person_id'         => $agentA->id,
            'conversation_type' => 'response',
        ]);

        $adminId = get_current_user_id();
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

        $viewer = FsFactory::agent();

        try {
            $viewer->user_id = $viewerUserId;
            $viewer->save();
            $viewer->updateMeta('agent_restrictions', [
                'businessBoxRestrictions' => true,
                'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
            ]);
            PermissionManager::attachPermissions($viewerUserId, ['fst_view_all_reports']);

            wp_set_current_user($viewerUserId);
            PermissionManager::currentUserPermissions(false);
            $result = FsTest::rest('GET', '/reports/stats', ['agent_id' => $agentA->id]);
            wp_set_current_user($adminId);
            PermissionManager::currentUserPermissions(false);

            FsTest::assertHealthy($result, 'GET /reports/stats?agent_id=A as a restricted viewer');

            $reports = $result['data']['overall_reports'] ?? null;
            FsTest::assert(is_array($reports), 'overall_reports is present');
            if (!is_array($reports)) {
                return;
            }

            // Same 1 reply A wrote in the allowed mailbox (see the first
            // case above); the new restricted-mailbox reply must not add to it.
            FsTest::assertSame(1, (int) ($reports['responses']['count'] ?? null), 'responses excludes replies on a mailbox the viewer is restricted from');
        } finally {
            wp_set_current_user($adminId);
            PermissionManager::currentUserPermissions(false);

            Meta::where('object_id', $viewer->id)
                ->where('object_type', 'person_meta')
                ->where('key', 'agent_restrictions')
                ->delete();
            PermissionManager::detachPermissions($viewerUserId);

            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            if (get_user_by('ID', $viewerUserId)) {
                wp_delete_user($viewerUserId);
            }
        }
    });
};
