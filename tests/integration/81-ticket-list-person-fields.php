<?php
/**
 * GET /tickets is paginated in bulk, so whatever a row carries can be collected for
 * every ticket an agent can see. Rows show names and photos; customer and agent
 * emails only for agents with fst_sensitive_data (who can open the Customers list
 * anyway). Everyone else sees a customer's email on the single-ticket view only.
 * Inbox addresses never appear in rows.
 * per_page is capped so one request can't return a whole table.
 * Viewer users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $viewerUserId = 0;
    $sensitiveUserId = 0;
    $requestBackup = $_REQUEST;

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    try {
        $login = FsTest::uniq('fs-list-viewer');
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
        $viewer->user_id = $viewerUserId;
        $viewer->save();
        PermissionManager::attachPermissions($viewerUserId, ['fst_manage_other_tickets']);

        $sensitiveLogin = FsTest::uniq('fs-list-sensitive');
        $sensitiveUserId = wp_insert_user([
            'user_login' => $sensitiveLogin,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $sensitiveLogin . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($sensitiveUserId)) {
            throw new RuntimeException('Could not create viewer user: ' . $sensitiveUserId->get_error_message());
        }

        $sensitiveViewer = FsFactory::agent();
        $sensitiveViewer->user_id = $sensitiveUserId;
        $sensitiveViewer->save();
        PermissionManager::attachPermissions($sensitiveUserId, ['fst_manage_other_tickets', 'fst_sensitive_data']);

        $agent = FsFactory::agent();
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox(['mapped_email' => FsTest::uniq('mapped') . '@example.invalid']);
        $ticket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $agent->id,
            'mailbox_id'  => $mailbox->id,
            'status'      => 'active',
        ]);

        $fetchRow = function ($asUserId = null) use ($runAs, $viewerUserId, $customer, $ticket) {
            $runAs($asUserId ?: $viewerUserId);
            $result = FsTest::rest('GET', '/tickets', ['customer_id' => $customer->id]);
            FsTest::assertHealthy($result, 'GET /tickets');

            $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
            foreach ((array) ($payload['data'] ?? []) as $row) {
                if ((int) $row['id'] === (int) $ticket->id) {
                    return $row;
                }
            }

            FsTest::fail('the fixture ticket is not in the list');
            return [];
        };

        FsTest::case('ticket_list_rows_carry_no_customer_agent_or_inbox_email', function () use ($fetchRow, $customer, $agent, $mailbox) {
            $json = wp_json_encode($fetchRow());

            FsTest::assert(strpos($json, $customer->email) === false, 'the customer email must not be in a list row');
            FsTest::assert(strpos($json, $agent->email) === false, 'the agent email must not be in a list row');
            FsTest::assert(strpos($json, $mailbox->email) === false, 'the inbox address must not be in a list row');
            FsTest::assert(strpos($json, $mailbox->mapped_email) === false, 'the mapped inbox address must not be in a list row');
        });

        FsTest::case('ticket_list_rows_keep_what_the_list_shows', function () use ($fetchRow, $customer, $agent, $mailbox) {
            $row = $fetchRow();

            FsTest::assertSame((int) $customer->id, (int) ($row['customer']['id'] ?? 0), 'customer id');
            FsTest::assertSame($customer->full_name, $row['customer']['full_name'] ?? null, 'customer full_name');
            FsTest::assert(!empty($row['customer']['photo']), 'customer photo');
            FsTest::assertSame($agent->full_name, $row['agent']['full_name'] ?? null, 'agent full_name');
            FsTest::assert(!empty($row['agent']['photo']), 'agent photo');
            FsTest::assertSame($mailbox->name, $row['mailbox']['name'] ?? null, 'mailbox name');
        });

        FsTest::case('agent_with_sensitive_data_sees_customer_and_agent_emails_in_the_list', function () use (
            $fetchRow, $sensitiveUserId, $customer, $agent, $mailbox
        ) {
            $row = $fetchRow($sensitiveUserId);

            FsTest::assertSame($customer->email, $row['customer']['email'] ?? null, 'customer email');
            FsTest::assertSame($agent->email, $row['agent']['email'] ?? null, 'agent email');
            FsTest::assert(strpos(wp_json_encode($row), $mailbox->email) === false, 'the inbox address stays out');
        });

        FsTest::case('the_single_ticket_view_still_shows_the_customer_email', function () use ($runAs, $viewerUserId, $ticket, $customer) {
            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets/' . $ticket->id);
            FsTest::assertHealthy($result, 'GET /tickets/{id}');

            $payload = json_decode(wp_json_encode($result['data']['ticket'] ?? []), true);
            FsTest::assertSame($customer->email, $payload['customer']['email'] ?? null, 'ticket.customer.email');
        });

        FsTest::case('per_page_is_capped', function () use ($runAs, $viewerUserId) {
            // Model::getPerPage() reads the raw request, which FsTest::rest() doesn't set.
            $_REQUEST['per_page'] = '100000';

            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets', ['per_page' => 100000]);
            FsTest::assertHealthy($result, 'GET /tickets?per_page=100000');

            $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
            FsTest::assertSame(150, (int) ($payload['per_page'] ?? 0), 'per_page is capped at the largest UI page size');
        });

        FsTest::case('per_page_below_one_falls_back_to_the_default', function () use ($runAs, $viewerUserId) {
            $_REQUEST['per_page'] = '0';

            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets', ['per_page' => 0]);
            FsTest::assertHealthy($result, 'GET /tickets?per_page=0');

            $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
            FsTest::assertSame(15, (int) ($payload['per_page'] ?? 0), 'per_page=0 uses the default page size');
        });
    } finally {
        $_REQUEST = $requestBackup;
        $runAs($adminId);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ([$viewerUserId, $sensitiveUserId] as $userId) {
            if ($userId && !is_wp_error($userId)) {
                wp_delete_user($userId);
            }
        }
    }
};
