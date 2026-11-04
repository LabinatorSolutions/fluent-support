<?php
/**
 * MCP customer ticket data (get-ticket siblings and counts) honours the viewer's ticket access.
 * The viewer user and its restriction meta aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\CustomerTools;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();

    // ---- fixtures ---------------------------------------------------------

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $customer = FsFactory::customer();
    $otherAgent = FsFactory::agent();

    // Can open only its own tickets outside $restrictedBox.
    $viewer = FsFactory::agent();

    $login = FsTest::uniq('fs-mcp-viewer');
    $viewerUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($viewerUserId)) {
        throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
    }

    $switchTo = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // [previous ticket ids (sorted), total_tickets, open_tickets] from get-ticket.
    $getTicket = function ($ticketId) {
        $result = TicketTools::getTicket(['ticket_id' => $ticketId, 'with_integrations' => false]);

        if (is_wp_error($result)) {
            FsTest::fail('get-ticket returned an error: ' . $result->get_error_message());
            return null;
        }

        $data = json_decode(wp_json_encode($result['data']), true);
        $previous = array_map('intval', array_column(isset($data['previous_tickets']) ? $data['previous_tickets'] : [], 'id'));
        sort($previous);

        return [$previous, (int) $data['customer']['total_tickets'], (int) $data['customer']['open_tickets']];
    };

    // [total_tickets, open_tickets] for the fixture customer from search-customers.
    $searchCounts = function () use ($customer) {
        $result = CustomerTools::searchCustomers(['search' => $customer->email]);

        if (is_wp_error($result)) {
            FsTest::fail('search-customers returned an error: ' . $result->get_error_message());
            return null;
        }

        foreach ($result['data']['customers'] as $row) {
            if ((int) $row['id'] === (int) $customer->id) {
                return [(int) $row['total_tickets'], (int) $row['open_tickets']];
            }
        }

        FsTest::fail('fixture customer is missing from search-customers');
        return null;
    };

    // Sorted ticket ids from get-customer-tickets for the fixture customer.
    $customerTicketIds = function () use ($customer) {
        $result = CustomerTools::getCustomerTickets(['customer_id' => $customer->id, 'per_page' => 50]);

        if (is_wp_error($result)) {
            FsTest::fail('get-customer-tickets returned an error: ' . $result->get_error_message());
            return null;
        }

        $ids = array_map('intval', array_column($result['data']['tickets'], 'id'));
        sort($ids);

        return $ids;
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

        $ticket = function ($boxId, $agentId, $status, $title) use ($customer) {
            return FsFactory::ticket([
                'customer_id' => $customer->id,
                'mailbox_id'  => $boxId,
                'agent_id'    => $agentId,
                'status'      => $status,
                'title'       => $title,
            ]);
        };

        // The ticket the viewer opens through MCP.
        $current = $ticket($allowedBox->id, $viewer->id, 'active', 'MCP current');
        $ownClosed = $ticket($allowedBox->id, $viewer->id, 'closed', 'MCP own closed');
        $mailboxDenied = $ticket($restrictedBox->id, $viewer->id, 'active', 'MCP restricted mailbox');
        $unassigned = $ticket($allowedBox->id, null, 'active', 'MCP unassigned');
        $othersTicket = $ticket($allowedBox->id, $otherAgent->id, 'active', 'MCP other agent');

        // No mailbox is never restricted. The factory always sets one, so clear it.
        $noMailbox = $ticket($allowedBox->id, $viewer->id, 'active', 'MCP no mailbox');

        // A second customer whose only ticket has no mailbox.
        $noMailboxCustomer = FsFactory::customer();
        $noMailboxOnly = FsFactory::ticket([
            'customer_id' => $noMailboxCustomer->id,
            'mailbox_id'  => $allowedBox->id,
            'agent_id'    => $viewer->id,
            'status'      => 'active',
            'title'       => 'MCP no-mailbox-only customer',
        ]);

        Ticket::whereIn('id', [$noMailbox->id, $noMailboxOnly->id])->update(['mailbox_id' => null]);

        // Without real restrictions the scoped case would pass vacuously.
        FsTest::case('mcp get-ticket: viewer resolves assigned-only visibility and its mailbox restriction', function () use ($switchTo, $viewerUserId, $adminId, $restrictedBox) {
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

        FsTest::case('mcp get-ticket: restricted viewer only sees sibling tickets it can open', function () use ($switchTo, $viewerUserId, $adminId, $getTicket, $sortedIds, $current, $ownClosed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox) {
            $switchTo($viewerUserId);
            try {
                // Controls: get-ticket itself refuses tickets the viewer can't open.
                foreach ([$mailboxDenied, $unassigned, $othersTicket] as $denied) {
                    FsTest::assert(
                        is_wp_error(TicketTools::getTicket(['ticket_id' => $denied->id, 'with_integrations' => false])),
                        'get-ticket refuses denied ticket ' . $denied->id
                    );
                }

                $result = $getTicket($current->id);
                if (!$result) {
                    return;
                }
                [$previous, $total, $open] = $result;

                FsTest::assertSame($sortedIds([$ownClosed, $noMailbox]), $previous, 'previous_tickets lists only the viewer\'s other openable tickets');
                FsTest::assertSame(3, $total, 'total_tickets = current + own closed + no-mailbox');
                FsTest::assertSame(2, $open, 'open_tickets = current + no-mailbox');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('mcp search-customers: restricted viewer counts only tickets it can open', function () use ($switchTo, $viewerUserId, $adminId, $searchCounts) {
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame([3, 2], $searchCounts(), 'total = current + own closed + no-mailbox; open = current + no-mailbox');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('mcp get-customer-tickets: restricted viewer keeps its no-mailbox tickets', function () use ($switchTo, $viewerUserId, $adminId, $customerTicketIds, $sortedIds, $current, $ownClosed, $noMailbox) {
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame($sortedIds([$current, $ownClosed, $noMailbox]), $customerTicketIds(), 'lists the viewer\'s own tickets outside the restricted mailbox, including no-mailbox');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('mcp get-customer-tickets: restricted viewer cannot read a customer whose tickets are all in a restricted mailbox', function () use ($switchTo, $viewerUserId, $adminId, $restrictedBox, $otherAgent) {
            $hiddenCustomer = FsFactory::customer();
            FsFactory::ticket([
                'customer_id' => $hiddenCustomer->id,
                'mailbox_id'  => $restrictedBox->id,
                'agent_id'    => $otherAgent->id,
                'status'      => 'active',
                'title'       => 'MCP restricted-only customer',
            ]);

            $switchTo($viewerUserId);
            try {
                $result = CustomerTools::getCustomerTickets(['customer_id' => $hiddenCustomer->id]);
                FsTest::assertSame('forbidden', is_wp_error($result) ? $result->get_error_code() : 'customer returned', 'returns forbidden, not the customer profile');
            } finally {
                $switchTo($adminId);
            }

            $result = CustomerTools::getCustomerTickets(['customer_id' => $hiddenCustomer->id]);
            FsTest::assertSame((int) $hiddenCustomer->id, is_wp_error($result) ? null : (int) $result['data']['customer']['id'], 'admin still reads the customer');
        });

        FsTest::case('mcp search-customers: restricted viewer finds a customer whose only ticket has no mailbox',function () use ($switchTo, $viewerUserId, $adminId, $noMailboxCustomer) {
            $switchTo($viewerUserId);
            try {
                $result = CustomerTools::searchCustomers(['search' => $noMailboxCustomer->email]);
                $ids = is_wp_error($result) ? [] : array_map('intval', array_column($result['data']['customers'], 'id'));

                FsTest::assertSame([(int) $noMailboxCustomer->id], $ids, 'customer is not excluded as restricted-only');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('mcp list-tickets: restricted viewer keeps its no-mailbox tickets', function () use ($switchTo, $viewerUserId, $adminId, $sortedIds, $customer, $current, $ownClosed, $noMailbox) {
            $switchTo($viewerUserId);
            try {
                $result = TicketTools::listTickets(['customer_id' => $customer->id, 'status' => 'all', 'per_page' => 50]);
                $ids = is_wp_error($result) ? [] : array_map('intval', array_column($result['data']['tickets'], 'id'));
                sort($ids);

                FsTest::assertSame($sortedIds([$current, $ownClosed, $noMailbox]), $ids, 'lists the viewer\'s own tickets outside the restricted mailbox, including no-mailbox');
            } finally {
                $switchTo($adminId);
            }
        });

        // The viewer is a fresh agent, so its queue and period volume hold only fixture tickets.
        FsTest::case('mcp support context and insights: restricted viewer counts its no-mailbox tickets', function () use ($switchTo, $viewerUserId, $adminId) {
            $switchTo($viewerUserId);
            try {
                $context = ManagementTools::getSupportContext([]);
                $insights = ManagementTools::getSupportInsights(['period' => '7d']);

                // Open, unreplied tickets assigned to the viewer: current, no-mailbox, no-mailbox-only.
                FsTest::assertSame(3, is_wp_error($context) ? null : (int) $context['data']['my_queue']['awaiting_your_reply_total'], 'support-context queue includes no-mailbox tickets');
                // Visible tickets created this period: current, own closed, no-mailbox, no-mailbox-only.
                FsTest::assertSame(4, is_wp_error($insights) ? null : (int) $insights['data']['volume']['created'], 'support-insights volume includes no-mailbox tickets');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('mcp: admin still sees every sibling ticket and count', function () use ($switchTo, $adminId, $getTicket, $searchCounts, $customerTicketIds, $sortedIds, $current, $ownClosed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox) {
            $switchTo($adminId);

            $result = $getTicket($current->id);
            if ($result) {
                [$previous, $total, $open] = $result;

                FsTest::assertSame($sortedIds([$ownClosed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox]), $previous, 'admin previous_tickets lists all five siblings');
                FsTest::assertSame(6, $total, 'admin total_tickets counts every ticket');
                FsTest::assertSame(5, $open, 'admin open_tickets counts every non-closed ticket');
            }

            FsTest::assertSame([6, 5], $searchCounts(), 'admin search-customers counts every ticket');
            FsTest::assertSame(
                $sortedIds([$current, $ownClosed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox]),
                $customerTicketIds(),
                'admin get-customer-tickets lists every ticket'
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
        if (get_user_by('ID', $viewerUserId)) {
            wp_delete_user($viewerUserId);
        }
    }
};
