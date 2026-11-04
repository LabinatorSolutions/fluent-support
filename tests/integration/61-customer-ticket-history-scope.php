<?php
/**
 * Customer profile ticket list and customers-list counts honour the viewer's ticket access.
 * The viewer user and its restriction meta aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();

    // ---- fixtures ---------------------------------------------------------

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $customer = FsFactory::customer();
    $otherAgent = FsFactory::agent();

    // Can open customer profiles, but only its own tickets outside $restrictedBox.
    $viewer = FsFactory::agent();

    $login = FsTest::uniq('fs-history-viewer');
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

    $historyIds = function () use ($customer) {
        $result = FsTest::rest('GET', '/customers/' . $customer->id, ['with' => ['tickets']]);
        FsTest::assertHealthy($result, 'GET customers/{id}?with[]=tickets');

        $data = json_decode(wp_json_encode($result['data']), true);
        $ids = array_map('intval', array_column(isset($data['tickets']) ? $data['tickets'] : [], 'id'));
        sort($ids);

        return $ids;
    };

    // [total_tickets, total_responses] for the fixture customer on the customers list.
    $listCounts = function () use ($customer) {
        $result = FsTest::rest('GET', '/customers', ['search' => $customer->email]);
        FsTest::assertHealthy($result, 'GET customers?search=');

        $data = json_decode(wp_json_encode($result['data']), true);
        $rows = isset($data['customers']['data']) ? $data['customers']['data'] : [];

        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $customer->id) {
                return [(int) $row['total_tickets'], (int) $row['total_responses']];
            }
        }

        FsTest::fail('fixture customer is missing from the customers list');
        return null;
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
        PermissionManager::attachPermissions($viewerUserId, ['fst_sensitive_data', 'fst_manage_own_tickets']);
        $viewer->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        $ticket = function ($boxId, $agentId, $title) use ($customer) {
            return FsFactory::ticket([
                'customer_id' => $customer->id,
                'mailbox_id'  => $boxId,
                'agent_id'    => $agentId,
                'status'      => 'active',
                'title'       => $title,
            ]);
        };

        $allowed = $ticket($allowedBox->id, $viewer->id, 'History allowed');
        $mailboxDenied = $ticket($restrictedBox->id, $viewer->id, 'History restricted mailbox');
        $unassigned = $ticket($allowedBox->id, null, 'History unassigned');
        $othersTicket = $ticket($allowedBox->id, $otherAgent->id, 'History other agent');

        // No mailbox is never restricted. The factory always sets one, so clear it.
        $noMailbox = $ticket($allowedBox->id, $viewer->id, 'History no mailbox');
        Ticket::where('id', $noMailbox->id)->update(['mailbox_id' => null]);

        // Customer replies on three tickets with different access.
        foreach ([$allowed, $mailboxDenied, $unassigned] as $repliedTicket) {
            FsFactory::conversation($repliedTicket, ['person_id' => $customer->id]);
        }

        // Without real restrictions the scoped cases would pass vacuously.
        FsTest::case('customer history: viewer resolves assigned-only visibility and its mailbox restriction', function () use ($switchTo, $viewerUserId, $adminId, $restrictedBox) {
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

        FsTest::case('customer history: assigned-only viewer only gets tickets it can open', function () use ($switchTo, $viewerUserId, $adminId, $historyIds, $sortedIds, $allowed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox) {
            $switchTo($viewerUserId);
            try {
                // Controls: direct reads apply both rules.
                FsTest::assertSame(200, FsTest::rest('GET', '/tickets/' . $allowed->id)['status'], 'direct read of the allowed ticket');
                FsTest::assertSame(200, FsTest::rest('GET', '/tickets/' . $noMailbox->id)['status'], 'direct read of the no-mailbox ticket');
                FsTest::assertSame(403, FsTest::rest('GET', '/tickets/' . $mailboxDenied->id)['status'], 'direct read of the restricted-mailbox ticket');
                FsTest::assertSame(403, FsTest::rest('GET', '/tickets/' . $unassigned->id)['status'], 'direct read of the unassigned ticket');
                FsTest::assertSame(403, FsTest::rest('GET', '/tickets/' . $othersTicket->id)['status'], 'direct read of another agent\'s ticket');

                FsTest::assertSame($sortedIds([$allowed, $noMailbox]), $historyIds(), 'history lists only the allowed and no-mailbox tickets');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('customers list: assigned-only viewer counts only tickets and replies it can open', function () use ($switchTo, $viewerUserId, $adminId, $listCounts) {
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame([2, 1], $listCounts(), 'total_tickets = allowed + no-mailbox; total_responses = the reply on the allowed ticket');
            } finally {
                $switchTo($adminId);
            }
        });

        FsTest::case('customer history: assigned + unassigned viewer also gets unassigned tickets', function () use ($switchTo, $viewerUserId, $adminId, $historyIds, $listCounts, $sortedIds, $allowed, $unassigned, $noMailbox) {
            PermissionManager::attachPermissions($viewerUserId, ['fst_sensitive_data', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets']);
            $switchTo($viewerUserId);
            try {
                FsTest::assertSame(
                    PermissionManager::VISIBILITY_ASSIGNED_AND_UNASSIGNED,
                    PermissionManager::getAgentTicketVisibility($viewerUserId),
                    'viewer visibility is assigned + unassigned'
                );
                FsTest::assertSame($sortedIds([$allowed, $unassigned, $noMailbox]), $historyIds(), 'history adds the unassigned ticket, still not the restricted-mailbox or other agent\'s');
                FsTest::assertSame([3, 2], $listCounts(), 'counts add the unassigned ticket and its reply');
            } finally {
                PermissionManager::attachPermissions($viewerUserId, ['fst_sensitive_data', 'fst_manage_own_tickets']);
                $switchTo($adminId);
            }
        });

        FsTest::case('customer history: admin still gets every ticket and count', function () use ($switchTo, $adminId, $historyIds, $listCounts, $sortedIds, $allowed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox) {
            $switchTo($adminId);
            FsTest::assertSame($sortedIds([$allowed, $mailboxDenied, $unassigned, $othersTicket, $noMailbox]), $historyIds(), 'admin history lists all five tickets');
            FsTest::assertSame([5, 3], $listCounts(), 'admin counts every ticket and reply');
        });

        // Profile header counts (ticket_stats) and the list's open_tickets use the same access scope.
        FsTest::case('customer profile: ticket_stats and open_tickets count only tickets the viewer can open', function () use ($switchTo, $viewerUserId, $adminId, $customer, $othersTicket, $allowed, $noMailbox) {
            $stats = function () use ($customer) {
                $result = FsTest::rest('GET', '/customers/' . $customer->id, ['with' => ['ticket_stats']]);
                FsTest::assertHealthy($result, 'GET customers/{id}?with[]=ticket_stats');

                $data = json_decode(wp_json_encode($result['data']), true);
                $stats = isset($data['ticket_stats']) ? $data['ticket_stats'] : [];
                ksort($stats);

                return $stats;
            };

            $openCount = function () use ($customer) {
                $result = FsTest::rest('GET', '/customers', ['search' => $customer->email]);
                FsTest::assertHealthy($result, 'GET customers?search=');

                $data = json_decode(wp_json_encode($result['data']), true);
                foreach ($data['customers']['data'] as $row) {
                    if ((int) $row['id'] === (int) $customer->id) {
                        return (int) $row['open_tickets'];
                    }
                }

                FsTest::fail('fixture customer is missing from the customers list');
                return null;
            };

            Ticket::where('id', $othersTicket->id)->update(['status' => 'closed']);

            // "Has open tickets" view: listed only when the viewer can open one of the open tickets.
            $inOpenView = function () use ($customer) {
                $result = FsTest::rest('GET', '/customers', ['search' => $customer->email, 'has_open_tickets' => 'yes']);
                FsTest::assertHealthy($result, 'GET customers?has_open_tickets=yes');

                $data = json_decode(wp_json_encode($result['data']), true);

                return in_array((int) $customer->id, array_map('intval', array_column($data['customers']['data'], 'id')), true);
            };

            try {
                $switchTo($adminId);
                FsTest::assertSame(['active' => 4, 'closed' => 1], $stats(), 'admin stats count every ticket by status');
                FsTest::assertSame(4, $openCount(), 'admin open_tickets leaves out the closed ticket');

                $switchTo($viewerUserId);
                FsTest::assertSame(['active' => 2], $stats(), 'viewer stats count only the allowed and no-mailbox tickets');
                FsTest::assertSame(2, $openCount(), 'viewer open_tickets counts only tickets it can open');
                FsTest::assert($inOpenView(), 'viewer sees the customer under Has open tickets');

                // Smart search ticket counts use the same scope: the viewer can open 2 of the 5.
                $smartMatches = function ($term) use ($customer) {
                    $result = FsTest::rest('GET', '/customers', ['search' => 'id:' . $customer->id . ' ' . $term, 'smart_search' => 'yes', 'import_contacts' => 'no']);
                    FsTest::assertHealthy($result, 'GET customers smart search ' . $term);

                    $data = json_decode(wp_json_encode($result['data']), true);

                    return (int) $data['customers']['total'] === 1;
                };
                FsTest::assert($smartMatches('tickets:2'), 'viewer: tickets:2 matches');
                FsTest::assert(!$smartMatches('tickets:5'), 'viewer: tickets:5 does not');

                // Close the viewer's two tickets: the open ones left belong to tickets it can't open.
                Ticket::whereIn('id', [$allowed->id, $noMailbox->id])->update(['status' => 'closed']);
                FsTest::assert(!$inOpenView(), 'viewer no longer sees the customer: its remaining open tickets are hidden from it');

                $switchTo($adminId);
                FsTest::assert($inOpenView(), 'admin still sees the customer under Has open tickets');
            } finally {
                Ticket::whereIn('id', [$allowed->id, $noMailbox->id])->update(['status' => 'active']);
                Ticket::where('id', $othersTicket->id)->update(['status' => 'active']);
                $switchTo($adminId);
            }
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
