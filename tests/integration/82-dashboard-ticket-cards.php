<?php
/**
 * The dashboard's Suggested Tickets and Tickets to Watch cards (GET /tickets/my_stats)
 * show a ticket's title and the customer's name, or email when there is no name.
 * The rows must not carry the customer's IP, address or the agents' note, another
 * agent's email, IP or note, or the inbox's addresses.
 * Viewer users and the watcher pivot aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $viewerUserId = 0;
    $watchedTicketId = 0;
    $marker = FsFactory::marker();

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    try {
        $login = FsTest::uniq('fs-dash-viewer');
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

        $otherAgent = FsFactory::agent(['ip_address' => '10.82.0.1', 'note' => 'Agent note ' . $marker]);
        $customer = FsFactory::customer([
            'ip_address'     => '10.82.0.2',
            'note'           => 'Customer note ' . $marker,
            'address_line_1' => 'Street ' . $marker,
        ]);
        $mailbox = FsFactory::mailbox(['mapped_email' => FsTest::uniq('mapped') . '@example.invalid']);

        // New and assigned to the viewer, so it is waiting on them.
        $suggested = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $viewer->id,
            'mailbox_id'  => $mailbox->id,
            'status'      => 'new',
        ]);

        // Assigned to another agent; the viewer watches it.
        $watched = FsFactory::ticket([
            'customer_id' => $customer->id,
            'agent_id'    => $otherAgent->id,
            'mailbox_id'  => $mailbox->id,
            'status'      => 'active',
        ]);
        $watchedTicketId = (int) $watched->id;
        TagPivot::create(['tag_id' => $viewer->id, 'source_id' => $watched->id, 'source_type' => 'ticket_watcher']);

        $cards = function () use ($runAs, $viewerUserId) {
            $runAs($viewerUserId);
            $result = FsTest::rest('GET', '/tickets/my_stats', ['with' => ['suggested_tickets', 'ticket_to_watch']]);
            FsTest::assertHealthy($result, 'GET /tickets/my_stats');

            return json_decode(wp_json_encode($result['data']), true);
        };

        $findTicket = function ($rows, $ticketId) {
            foreach ((array) $rows as $row) {
                if ((int) ($row['id'] ?? 0) === (int) $ticketId) {
                    return $row;
                }
            }
            return null;
        };

        $assertNoPrivateData = function ($row, $label) use ($otherAgent, $mailbox, $marker) {
            $json = wp_json_encode($row);

            FsTest::assert(strpos($json, '10.82.0.2') === false, $label . ': the customer IP must not be sent');
            FsTest::assert(strpos($json, 'Customer note ' . $marker) === false, $label . ': the customer note must not be sent');
            FsTest::assert(strpos($json, 'Street ' . $marker) === false, $label . ': the customer address must not be sent');
            FsTest::assert(strpos($json, $otherAgent->email) === false, $label . ': the other agent\'s email must not be sent');
            FsTest::assert(strpos($json, '10.82.0.1') === false, $label . ': the other agent\'s IP must not be sent');
            FsTest::assert(strpos($json, 'Agent note ' . $marker) === false, $label . ': the other agent\'s note must not be sent');
            FsTest::assert(strpos($json, $mailbox->email) === false, $label . ': the inbox address must not be sent');
            FsTest::assert(strpos($json, $mailbox->mapped_email) === false, $label . ': the mapped inbox address must not be sent');
        };

        FsTest::case('suggested_ticket_card_carries_display_fields_only', function () use (
            $cards, $findTicket, $suggested, $customer, $assertNoPrivateData
        ) {
            $row = $findTicket($cards()['suggested_tickets'] ?? [], $suggested->id);
            if (!$row) {
                FsTest::fail('the fixture ticket is not among the suggested tickets');
                return;
            }

            $assertNoPrivateData($row, 'suggested');
            // The card falls back to the email for a customer with no name.
            FsTest::assertSame($customer->first_name, $row['customer']['first_name'] ?? null, 'customer first_name');
            FsTest::assertSame($customer->email, $row['customer']['email'] ?? null, 'customer email');
        });

        FsTest::case('ticket_to_watch_card_carries_display_fields_only', function () use (
            $cards, $findTicket, $watched, $otherAgent, $assertNoPrivateData
        ) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Tickets to Watch is a Pro card');
                return;
            }

            $row = $findTicket($cards()['ticket_to_watch'] ?? [], $watched->id);
            if (!$row) {
                FsTest::fail('the watched fixture ticket is not among the tickets to watch');
                return;
            }

            $assertNoPrivateData($row, 'watched');
            FsTest::assertSame($otherAgent->full_name, $row['agent']['full_name'] ?? null, 'assigned agent name');
        });
    } finally {
        if ($watchedTicketId) {
            TagPivot::where('source_type', 'ticket_watcher')->where('source_id', $watchedTicketId)->delete();
        }
        $runAs($adminId);
        if ($viewerUserId && !is_wp_error($viewerUserId)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($viewerUserId);
        }
    }
};
