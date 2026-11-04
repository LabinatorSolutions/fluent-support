<?php
/**
 * The agent list (GET agents) returns each agent's counts, meta,
 * groups and permissions, with a fixed number of queries however many agents
 * are on the page. Values were pinned against the per-agent version first.
 * The WP user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $marker = FsFactory::marker();
    $userId = null;
    $group = null;
    $agentIds = [];

    try {
        $login = FsTest::uniq('fs-agent-list');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }
        PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets']);

        // Busy agent: user, 4 conversations on 2 tickets, every meta key, one group.
        // A name of its own: the list is one page, newest first, and other suites in
        // the run add agents with the shared fixture marker in the same second.
        $listName = FsTest::uniq('agentlist');
        $busy = FsFactory::agent(['user_id' => $userId, 'first_name' => $listName]);
        $ticketOne = FsFactory::ticket();
        $ticketTwo = FsFactory::ticket();
        foreach ([$ticketOne, $ticketOne, $ticketOne] as $ticket) {
            FsFactory::conversation($ticket, ['person_id' => $busy->id, 'conversation_type' => 'response']);
        }
        FsFactory::conversation($ticketTwo, ['person_id' => $busy->id, 'conversation_type' => 'note']);

        $restrictions = ['businessBoxRestrictions' => true, 'restrictedBusinessBoxes' => [7]];
        $busy->updateMeta('telegram_chat_id', '123456');
        $busy->updateMeta('slack_user_id', 'U0FIXTURE');
        $busy->updateMeta('whatsapp_number', '+15550001');
        $busy->updateMeta('agent_restrictions', $restrictions);
        $busy->updateMeta('agent_signature', '<p>Thanks, Busy</p>');
        $busy->updateMeta('agent_signature_enabled', 'yes');

        $group = AgentGroup::create(['title' => 'Fixture Agent List Group ' . $marker]);
        TagPivot::create(['tag_id' => $group->id, 'source_id' => $busy->id, 'source_type' => 'agent_group']);

        // Quiet agent: nothing at all.
        $quiet = FsFactory::agent(['first_name' => $listName]);
        $agentIds = [$busy->id, $quiet->id];

        $listed = function () use ($listName) {
            $rows = [];
            foreach ((array) (json_decode(wp_json_encode(FsTest::rest('GET', '/agents', ['search' => $listName])['data']), true)['agents']['data'] ?? []) as $row) {
                $rows[$row['id']] = $row;
            }
            return $rows;
        };

        FsTest::case('agent list returns each agent\'s counts, meta, groups and permissions', function () use ($listed, $busy, $quiet, $group, $restrictions, $userId) {
            $rows = $listed();
            $b = $rows[$busy->id] ?? null;
            $q = $rows[$quiet->id] ?? null;
            FsTest::assert($b && $q, 'both fixture agents listed');
            if (!$b || !$q) {
                return;
            }

            FsTest::assertSame(4, (int) $b['replies_count'], 'busy replies_count');
            FsTest::assertSame(2, (int) $b['interactions_count'], 'busy interactions_count');
            FsTest::assertSame('123456', $b['telegram_chat_id'], 'telegram_chat_id');
            FsTest::assertSame('U0FIXTURE', $b['slack_user_id'], 'slack_user_id');
            FsTest::assertSame('+15550001', $b['whatsapp_number'], 'whatsapp_number');
            FsTest::assertSame($restrictions, $b['restrictions'], 'restrictions');
            FsTest::assertSame('<p>Thanks, Busy</p>', $b['agent_signature'], 'agent_signature');
            FsTest::assertSame('yes', $b['agent_signature_enabled'], 'agent_signature_enabled');
            FsTest::assertSame([(int) $group->id], array_map('intval', array_column($b['groups'], 'id')), 'groups');
            FsTest::assertSame(admin_url('user-edit.php?user_id=' . $userId), $b['user_profile'] ?? null, 'user_profile');
            FsTest::assert(in_array('fst_manage_own_tickets', (array) $b['permissions'], true), 'permissions');

            FsTest::assertSame(0, (int) $q['replies_count'], 'quiet replies_count');
            FsTest::assertSame(0, (int) $q['interactions_count'], 'quiet interactions_count');
            FsTest::assertSame('', $q['telegram_chat_id'], 'quiet telegram_chat_id default');
            FsTest::assertSame(['restrictedBusinessBoxes' => [], 'businessBoxRestrictions' => false], $q['restrictions'], 'quiet restrictions default');
            FsTest::assertSame('', $q['agent_signature'], 'quiet agent_signature default');
            FsTest::assertSame('no', $q['agent_signature_enabled'], 'quiet agent_signature_enabled default');
            FsTest::assertSame([], $q['groups'], 'quiet groups');
            FsTest::assertSame([], $q['permissions'], 'quiet permissions');
        });

        FsTest::case('agent list runs a fixed number of queries per page', function () use ($listName) {
            $queries = FsTest::countQueries(function () use ($listName) {
                FsTest::rest('GET', '/agents', ['search' => $listName]);
            });
            // Was 9+ per agent; now one query per kind of data for the page (plus the
            // permission groups and mailbox list the endpoint also sends).
            FsTest::assert($queries <= 16, 'queries for the page: ' . $queries);
        });
    } finally {
        if ($group) {
            TagPivot::where('tag_id', $group->id)->where('source_type', 'agent_group')->delete();
            AgentGroup::where('id', $group->id)->delete();
        }
        if ($agentIds) {
            Meta::where('object_type', 'person_meta')->whereIn('object_id', $agentIds)->delete();
        }
        if ($userId && !is_wp_error($userId)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($userId);
        }
    }
};
