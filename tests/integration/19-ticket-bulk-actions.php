<?php
/**
 * Ticket bulk actions: change priority, change product, reopen, remove tags,
 * and the per-request ticket cap. Each action changes only the tickets the
 * caller may access, the same scope as the older bulk actions.
 * The restricted user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $restrictedUserId = null;

    $bulk = function (array $params) {
        return FsTest::rest('POST', '/tickets/bulk-actions', $params);
    };

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // Collects the first argument of an action while $fn runs.
    $captured = function ($hook, callable $fn) {
        $seen = [];
        $listener = function ($first) use (&$seen) {
            $seen[] = $first;
        };
        add_action($hook, $listener, 10, 1);
        try {
            $fn();
        } finally {
            remove_action($hook, $listener, 10);
        }
        return $seen;
    };

    try {
        $customer = FsFactory::customer();
        $box = FsFactory::mailbox();
        $restrictedBox = FsFactory::mailbox();

        // An agent who manages other agents' tickets but is barred from $restrictedBox.
        $login = FsTest::uniq('fs-bulk-agent');
        $restrictedUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($restrictedUserId)) {
            throw new RuntimeException('Could not create agent user: ' . $restrictedUserId->get_error_message());
        }
        $restrictedAgent = FsFactory::agent();
        $restrictedAgent->user_id = $restrictedUserId;
        $restrictedAgent->save();
        PermissionManager::attachPermissions($restrictedUserId, [
            'fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets',
        ]);
        $restrictedAgent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        $ticket = function (array $attributes = []) use ($customer, $box) {
            return FsFactory::ticket(array_merge(['customer_id' => $customer->id, 'mailbox_id' => $box->id], $attributes));
        };

        FsTest::case('bulk change_priority sets the priority on the selected tickets', function () use ($bulk, $ticket) {
            $a = $ticket(['priority' => 'normal']);
            $b = $ticket(['priority' => 'normal']);

            $result = $bulk(['bulk_action' => 'change_priority', 'ticket_ids' => [$a->id, $b->id], 'priority' => 'critical']);

            FsTest::assertHealthy($result, 'change_priority');
            FsTest::assertSame(['critical', 'critical'], Ticket::whereIn('id', [$a->id, $b->id])->orderBy('id')->pluck('priority')->all(), 'priorities');
        });

        FsTest::case('bulk change_priority rejects an unknown priority', function () use ($bulk, $ticket) {
            $a = $ticket(['priority' => 'normal']);

            $result = $bulk(['bulk_action' => 'change_priority', 'ticket_ids' => [$a->id], 'priority' => 'not-a-priority']);

            FsTest::assertSame(422, $result['status'], 'status');
            FsTest::assertSame('normal', Ticket::where('id', $a->id)->value('priority'), 'priority unchanged');
        });

        FsTest::case('bulk change_product sets the product and rejects a missing one', function () use ($bulk, $ticket) {
            $product = FsFactory::product();
            $a = $ticket();

            $result = $bulk(['bulk_action' => 'change_product', 'ticket_ids' => [$a->id], 'product_id' => $product->id]);
            FsTest::assertHealthy($result, 'change_product');
            FsTest::assertSame((int) $product->id, (int) Ticket::where('id', $a->id)->value('product_id'), 'product set');

            $missing = $bulk(['bulk_action' => 'change_product', 'ticket_ids' => [$a->id], 'product_id' => 999999999]);
            FsTest::assertSame(422, $missing['status'], 'missing product rejected');
        });

        FsTest::case('bulk reopen_tickets reopens closed tickets only, firing ticket_reopen for each', function () use ($bulk, $ticket, $captured) {
            $closedA = $ticket(['status' => 'closed']);
            $closedB = $ticket(['status' => 'closed']);
            $open = $ticket(['status' => 'active']);

            $result = null;
            $reopened = $captured('fluent_support/ticket_reopen', function () use ($bulk, $closedA, $closedB, $open, &$result) {
                $result = $bulk(['bulk_action' => 'reopen_tickets', 'ticket_ids' => [$closedA->id, $closedB->id, $open->id]]);
            });

            FsTest::assertHealthy($result, 'reopen_tickets');
            $reopenedIds = array_map(function ($t) { return (int) $t->id; }, $reopened);
            sort($reopenedIds);
            FsTest::assertSame([(int) $closedA->id, (int) $closedB->id], $reopenedIds, 'ticket_reopen fired for the closed tickets only');
            FsTest::assertSame(0, Ticket::whereIn('id', [$closedA->id, $closedB->id])->where('status', 'closed')->count(), 'closed tickets reopened');
            FsTest::assertSame('active', Ticket::where('id', $open->id)->value('status'), 'open ticket untouched');
        });

        FsTest::case('bulk remove_tags detaches the tags and fires ticket_tag_removed', function () use ($bulk, $ticket, $captured) {
            $keep = FsFactory::tag(['tag_type' => 'ticket_tag']);
            $drop = FsFactory::tag(['tag_type' => 'ticket_tag']);
            $a = $ticket();
            FsFactory::attachTag($a, $keep);
            FsFactory::attachTag($a, $drop);

            $result = null;
            $removed = $captured('fluent_support/ticket_tag_removed', function () use ($bulk, $a, $drop, &$result) {
                $result = $bulk(['bulk_action' => 'remove_tags', 'ticket_ids' => [$a->id], 'tag_ids' => [$drop->id]]);
            });

            FsTest::assertHealthy($result, 'remove_tags');
            FsTest::assertSame([(int) $drop->id], array_map('intval', $removed), 'ticket_tag_removed fired for the removed tag');
            $tagIds = Ticket::find($a->id)->tags()->pluck('fs_taggables.id')->map(function ($id) { return (int) $id; })->all();
            FsTest::assertSame([(int) $keep->id], $tagIds, 'only the other tag remains');
        });

        FsTest::case('bulk actions change nothing in a mailbox the agent is barred from', function () use ($bulk, $ticket, $runAs, $restrictedUserId, $restrictedBox, $adminId) {
            $allowed = $ticket(['priority' => 'normal', 'status' => 'closed']);
            $barred = $ticket(['mailbox_id' => $restrictedBox->id, 'priority' => 'normal', 'status' => 'closed']);

            $runAs($restrictedUserId);
            try {
                $priority = $bulk(['bulk_action' => 'change_priority', 'ticket_ids' => [$allowed->id, $barred->id], 'priority' => 'critical']);
                $reopen = $bulk(['bulk_action' => 'reopen_tickets', 'ticket_ids' => [$allowed->id, $barred->id]]);
            } finally {
                $runAs($adminId);
            }

            FsTest::assertHealthy($priority, 'change_priority as restricted agent');
            FsTest::assertHealthy($reopen, 'reopen_tickets as restricted agent');
            FsTest::assertSame('critical', Ticket::where('id', $allowed->id)->value('priority'), 'allowed ticket changed');
            FsTest::assertSame('normal', Ticket::where('id', $barred->id)->value('priority'), 'barred ticket priority unchanged');
            FsTest::assertSame('closed', Ticket::where('id', $barred->id)->value('status'), 'barred ticket stays closed');
        });

        FsTest::case('bulk reply keeps the reply\'s HTML', function () use ($ticket) {
            $a = $ticket(['status' => 'active']);
            FsTest::rest('POST', '/tickets/bulk-reply', ['ticket_ids' => [$a->id], 'content' => '<p>Hi</p><p><a href="https://example.com">link</a></p>']);
            $content = \FluentSupport\App\Models\Conversation::where('ticket_id', $a->id)->where('conversation_type', 'response')->value('content');
            FsTest::assert(strpos((string) $content, '<a href="https://example.com">link</a>') !== false, 'link kept: ' . $content);
        });

        FsTest::case('bulk actions refuse more than 200 tickets', function () use ($bulk, $ticket) {
            $a = $ticket(['priority' => 'normal']);
            $ids = array_merge([$a->id], range(900000001, 900000200));

            $result = $bulk(['bulk_action' => 'change_priority', 'ticket_ids' => $ids, 'priority' => 'critical']);

            FsTest::assertSame(422, $result['status'], 'status');
            FsTest::assertSame('normal', Ticket::where('id', $a->id)->value('priority'), 'nothing changed');
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
        if ($restrictedUserId && !is_wp_error($restrictedUserId)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($restrictedUserId);
        }
    }
};
