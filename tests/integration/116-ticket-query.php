<?php
/**
 * TicketQuery, the one ticket list query (dev-works/tickets-architecture.md 3.3):
 * - The access scope is part of the query, not a listener someone can unhook.
 * - The mailbox screen is limited to the agent's mailboxes like every list.
 * - Sorting only uses the columns the list offers.
 * - The MCP search treats % and _ as text.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {
    $adminId = get_current_user_id();
    $userIds = [];
    $marker = FsFactory::marker();

    $restrictedAgent = function (array $mailboxIds, array $permissions = []) use (&$userIds) {
        $login = FsTest::uniq('fs-tq');
        $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
        $userIds[] = $userId;
        $agent = FsFactory::agent(['user_id' => $userId]);
        PermissionManager::attachPermissions($userId, array_merge(['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets'], $permissions));
        $agent->updateMeta('agent_restrictions', ['businessBoxRestrictions' => 'yes', 'restrictedBusinessBoxes' => $mailboxIds]);
        return $userId;
    };
    $as = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };
    $listIds = function (array $params) {
        $data = json_decode(wp_json_encode(FsTest::rest('GET', 'tickets', $params + ['per_page' => 50])['data']), true);
        return array_map('intval', array_column($data['tickets']['data'] ?? [], 'id'));
    };

    $customer = FsFactory::customer();
    // $open is created first, so the first ticket has the higher mailbox id:
    // sorting by mailbox_id gives the opposite order to sorting by id.
    $open = FsFactory::mailbox();
    $hidden = FsFactory::mailbox();
    $inHidden = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $hidden->id, 'title' => 'TQ hidden ' . $marker]);
    $inOpen = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $open->id, 'title' => 'TQ open ' . $marker]);

    try {
        FsTest::case('ticket query: the list keeps the mailbox restriction without the old listener', function () use ($restrictedAgent, $as, $adminId, $listIds, $hidden, $customer, $inHidden, $inOpen) {
            $userId = $restrictedAgent([$hidden->id]);
            global $wp_filter;
            $saved = $wp_filter['fluent_support\main_tickets_query'] ?? null;
            unset($wp_filter['fluent_support\main_tickets_query']);
            $as($userId);
            try {
                $ids = $listIds(['customer_id' => $customer->id]);
            } finally {
                $as($adminId);
                if ($saved) {
                    $wp_filter['fluent_support\main_tickets_query'] = $saved;
                }
            }
            FsTest::assert(in_array((int) $inOpen->id, $ids, true), 'the allowed mailbox is listed');
            FsTest::assert(!in_array((int) $inHidden->id, $ids, true), 'the restricted mailbox is not');
        });

        FsTest::case('ticket query: the mailbox screen follows the mailbox restriction', function () use ($restrictedAgent, $as, $adminId, $hidden, $inHidden) {
            $userId = $restrictedAgent([$hidden->id], ['fst_manage_settings']);
            $as($userId);
            try {
                $data = json_decode(wp_json_encode(FsTest::rest('GET', 'mailboxes/' . $hidden->id . '/tickets')['data']), true);
            } finally {
                $as($adminId);
            }
            FsTest::assert(!in_array((int) $inHidden->id, array_map('intval', array_column($data['tickets']['data'] ?? [], 'id')), true), 'restricted tickets not listed');
        });

        FsTest::case('ticket query: watcher mode with unassigned lists unassigned or the agent\'s tickets', function () use ($listIds) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $owner = FsFactory::customer();
            $agent = FsFactory::agent();
            $unassigned = FsFactory::ticket(['customer_id' => $owner->id]);
            $assigned = FsFactory::ticket(['customer_id' => $owner->id, 'agent_id' => $agent->id]);
            $ids = $listIds(['filter_type' => 'simple', 'customer_id' => $owner->id, 'filters' => ['status_type' => 'all', 'watcher' => 'watcher', 'agent_id' => ['unassigned', (string) $agent->id]]]);
            sort($ids);
            FsTest::assertSame([(int) $unassigned->id, (int) $assigned->id], $ids, 'unassigned or the agent\'s ticket');
        });

        FsTest::case('ticket query: main_tickets_query listeners get the old argument keys', function () use ($customer) {
            $seen = [];
            $listen = function ($query, $args) use (&$seen) {
                $seen[] = $args;
            };
            add_action('fluent_support/main_tickets_query', $listen, 10, 2);
            try {
                FsTest::rest('GET', 'tickets', ['filter_type' => 'simple', 'filters' => ['status_type' => 'open'], 'customer_id' => $customer->id]);
                FsTest::rest('GET', 'tickets', ['filter_type' => 'advanced', 'advanced_filters' => wp_json_encode([[['source' => ['tickets', 'title'], 'operator' => 'contains', 'value' => 'x']]])]);
            } finally {
                remove_action('fluent_support/main_tickets_query', $listen, 10);
            }
            FsTest::assertSame(['simple', 'open', (int) $customer->id], [$seen[0]['filter_type'] ?? null, $seen[0]['simple_filters']['status_type'] ?? null, (int) ($seen[0]['customer_id'] ?? 0)], 'simple search');
            FsTest::assertSame(['advanced', 'title'], [$seen[1]['filter_type'] ?? null, $seen[1]['filters_groups_raw'][0][0]['source'][1] ?? null], 'advanced search');
        });

        FsTest::case('ticket query: sorting uses only the columns the list offers', function () use ($listIds, $customer) {
            $byId = $listIds(['customer_id' => $customer->id, 'order_by' => 'id', 'order_type' => 'DESC']);
            FsTest::assertSame($byId, $listIds(['customer_id' => $customer->id, 'order_by' => 'mailbox_id', 'order_type' => 'DESC']), 'a column the list does not offer sorts by id');
            FsTest::assertSame(array_reverse($byId), $listIds(['customer_id' => $customer->id, 'order_by' => 'title', 'order_type' => 'ASC']), 'title ascending');
        });

        FsTest::case('ticket query: the MCP search treats % as text', function () use ($customer) {
            $result = json_decode(wp_json_encode(TicketTools::listTickets(['customer_id' => $customer->id, 'search' => '%'])), true);
            FsTest::assertSame(0, count($result['data']['tickets'] ?? [1]), 'no ticket has a % in it');
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
