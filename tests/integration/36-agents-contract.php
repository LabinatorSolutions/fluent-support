<?php
/**
 * Agents (support staff): the agents/* endpoints the Support Staff settings
 * page calls (resources/admin/Modules/Settings/AgentMembers.vue), and the
 * tickets/ping keep-alive.
 *
 * - The list: each agent's groups, permissions, reply counts, chat ids,
 *   mailbox restrictions and signature; the permission groups and mailboxes
 *   to choose from.
 * - Create needs a WordPress user with the email and no other person with it;
 *   it saves permissions and settings and joins the default agent group.
 * - Update saves names, settings, permissions and a sanitized signature.
 * - Delete moves tickets, replies and products to a fallback agent.
 * - Add, edit and delete need manage_options; the list needs fst_sensitive_data.
 * - Agents are sent from an allow list: never the portal hash or IPs.
 */

use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {
    $adminId = get_current_user_id();
    $marker = FsFactory::marker();
    $userIds = [];
    $agentIds = [];
    $groupIds = [];

    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };
    $wpUser = function ($slug) use (&$userIds, $marker) {
        $email = $slug . '-' . strtolower(wp_generate_password(6, false)) . '@' . $marker . '.example.test';
        $userId = wp_insert_user(['user_login' => FsTest::uniq('fs-agent-' . $slug), 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $email, 'role' => 'subscriber', 'first_name' => 'Wp', 'last_name' => 'Agent']);
        $userIds[] = $userId;
        return [$userId, $email];
    };
    $agentRow = function ($id, $search) use ($rest) {
        foreach ((array) ($rest('GET', '/agents', ['search' => $search])['data']['agents']['data'] ?? []) as $row) {
            if ((int) $row['id'] === (int) $id) {
                return $row;
            }
        }
        return null;
    };

    $originalDefaults = [];
    foreach (AgentGroup::whereNotNull('settings')->get() as $group) {
        if (!empty($group->settings['is_default'])) {
            $originalDefaults[] = $group->id;
            AgentGroup::where('id', $group->id)->update(['settings' => maybe_serialize(['is_default' => false])]);
        }
    }
    $defaultGroup = AgentGroup::create(['title' => 'Default ' . $marker, 'settings' => ['is_default' => true]]);
    $groupIds[] = (int) $defaultGroup->id;

    try {
        FsTest::case('index lists an agent with groups, permissions, counts and settings', function () use ($rest, $wpUser, $agentRow, $marker, &$agentIds, $defaultGroup) {
            list($userId) = $wpUser('listed');
            $agent = FsFactory::agent(['user_id' => $userId, 'first_name' => 'Listed' . $marker]);
            $agentIds[] = (int) $agent->id;
            PermissionManager::attachPermissions($userId, ['fst_view_tickets']);
            $agent->updateMeta('slack_user_id', 'U123');
            TagPivot::create(['tag_id' => $defaultGroup->id, 'source_id' => $agent->id, 'source_type' => 'agent_group']);
            $ticket = FsFactory::ticket();
            FsFactory::conversation($ticket, ['person_id' => $agent->id]);
            FsFactory::conversation($ticket, ['person_id' => $agent->id]);

            $result = $rest('GET', '/agents', ['search' => 'Listed' . $marker]);
            FsTest::assertHealthy($result, 'GET agents');
            FsTest::assert(!empty($result['data']['permissions']) && array_key_exists('businessBoxes', $result['data']), 'permission groups and mailboxes');
            $row = $agentRow($agent->id, 'Listed' . $marker);
            FsTest::assert((bool) $row, 'listed');
            FsTest::assertSame(['replies_count' => 2, 'interactions_count' => 1, 'slack_user_id' => 'U123', 'permissions' => ['fst_view_tickets']], ['replies_count' => $row['replies_count'] ?? null, 'interactions_count' => $row['interactions_count'] ?? null, 'slack_user_id' => $row['slack_user_id'] ?? null, 'permissions' => $row['permissions'] ?? null], 'row values');
            FsTest::assertSame([(int) $defaultGroup->id], array_map(fn($g) => (int) $g['id'], $row['groups'] ?? []), 'groups');
            FsTest::assertSame(['restrictedBusinessBoxes' => [], 'businessBoxRestrictions' => false], $row['restrictions'] ?? null, 'default restrictions');
            foreach (['first_name', 'last_name', 'full_name', 'email', 'title', 'photo', 'avatar', 'user_profile', 'telegram_chat_id', 'whatsapp_number', 'agent_signature', 'agent_signature_enabled'] as $key) {
                FsTest::assert(array_key_exists($key, $row), "row has no '$key'");
            }
            foreach (['hash', 'ip_address', 'last_ip_address', 'remote_uid'] as $key) {
                FsTest::assert(!array_key_exists($key, $row), "row carries '$key'");
            }
        });

        FsTest::case('create: a WordPress user is required, the email must be free; settings saved, default group joined', function () use ($rest, $wpUser, $marker, &$agentIds, $defaultGroup) {
            $none = $rest('POST', '/agents', ['email' => 'nobody-' . $marker . '@example.test', 'first_name' => 'Nobody']);
            FsTest::assert($none['status'] >= 400 && $none['message'] !== '', 'no WordPress user');

            $customer = FsFactory::customer();
            $taken = $rest('POST', '/agents', ['email' => $customer->email, 'first_name' => 'Taken']);
            FsTest::assert($taken['status'] >= 400, 'email of another person');

            list($userId, $email) = $wpUser('created');
            $result = $rest('POST', '/agents', [
                'email'        => $email,
                'first_name'   => 'Created' . $marker,
                'title'        => 'Lead',
                'permissions'  => ['fst_view_tickets', 'fst_manage_own_tickets', 'not_a_permission'],
                'telegram_chat_id' => 'T1',
                'restrictions' => ['restrictedBusinessBoxes' => [7], 'businessBoxRestrictions' => 'true'],
            ]);
            FsTest::assertHealthy($result, 'POST agents');
            FsTest::assertSame('Support Staff has been added', $result['message'], 'message');
            $agent = Agent::where('email', $email)->first();
            $agentIds[] = (int) ($agent->id ?? 0);
            FsTest::assertSame(['user_id' => (int) $userId, 'last_name' => 'Agent', 'title' => 'Lead'], ['user_id' => (int) $agent->user_id, 'last_name' => $agent->last_name, 'title' => $agent->title], 'saved (last name from the user)');
            $permissions = PermissionManager::getUserPermissions($userId);
            sort($permissions);
            FsTest::assertSame(['fst_manage_own_tickets', 'fst_view_tickets'], $permissions, 'permissions (unknown ones dropped)');
            FsTest::assertSame('T1', $agent->getMeta('telegram_chat_id'), 'telegram id');
            FsTest::assertSame(['restrictedBusinessBoxes' => [7], 'businessBoxRestrictions' => true], $agent->getMeta('agent_restrictions'), 'restrictions');
            FsTest::assert(TagPivot::where('tag_id', $defaultGroup->id)->where('source_id', $agent->id)->where('source_type', 'agent_group')->exists(), 'joined the default group');
            FsTest::assert(!array_key_exists('hash', $result['data']['agent'] ?? []), 'agent carries hash');
        });

        FsTest::case('update: names, settings, permissions and a sanitized signature; the email stays the user\'s', function () use ($rest, $wpUser, $marker, &$agentIds) {
            list($userId, $email) = $wpUser('updated');
            $agent = FsFactory::agent(['user_id' => $userId, 'email' => $email, 'first_name' => 'Before']);
            $agentIds[] = (int) $agent->id;
            PermissionManager::attachPermissions($userId, ['fst_sensitive_data']);

            $result = $rest('PUT', '/agents/' . $agent->id, [
                'email'                   => 'changed-' . $email,
                'first_name'              => 'After',
                'last_name'               => 'Edit',
                'permissions'             => ['fst_manage_own_tickets'],
                'whatsapp_number'         => '+880',
                'agent_signature'         => '<p>Cheers</p><script>alert(1)</script>',
                'agent_signature_enabled' => 'yes',
            ]);
            FsTest::assertHealthy($result, 'PUT agents/{id}');
            FsTest::assertSame('Support Staff has been updated', $result['message'], 'message');
            $saved = Agent::find($agent->id);
            FsTest::assertSame(['first_name' => 'After', 'last_name' => 'Edit', 'email' => $email], ['first_name' => $saved->first_name, 'last_name' => $saved->last_name, 'email' => $saved->email], 'saved, email kept');
            $permissions = PermissionManager::getUserPermissions($userId);
            sort($permissions);
            FsTest::assertSame(['fst_manage_own_tickets', 'fst_view_tickets'], $permissions, 'permissions replaced (managing implies viewing)');
            FsTest::assertSame('+880', $saved->getMeta('whatsapp_number'), 'whatsapp');
            FsTest::assert(strpos($saved->getMeta('agent_signature'), '<script>') === false && strpos($saved->getMeta('agent_signature'), 'Cheers') !== false, 'signature sanitized');
            FsTest::assertSame('yes', $saved->getMeta('agent_signature_enabled'), 'signature on');
            FsTest::assert($rest('PUT', '/agents/99999999', ['email' => $email, 'first_name' => 'X'])['status'] >= 400, 'unknown agent');

            // A save without some fields keeps them.
            $rest('PUT', '/agents/' . $agent->id, ['email' => $email, 'first_name' => 'Again']);
            $kept = Agent::find($agent->id);
            $permissions = PermissionManager::getUserPermissions($userId);
            sort($permissions);
            FsTest::assertSame(['last_name' => 'Edit', 'permissions' => ['fst_manage_own_tickets', 'fst_view_tickets']], ['last_name' => $kept->last_name, 'permissions' => $permissions], 'fields left out are kept');
        });

        FsTest::case('delete moves tickets and replies to the fallback agent and removes the agent', function () use ($rest, $wpUser, &$agentIds, $defaultGroup) {
            list($userId) = $wpUser('deleted');
            $agent = FsFactory::agent(['user_id' => $userId]);
            $fallback = FsFactory::agent();
            $agentIds[] = (int) $fallback->id;
            PermissionManager::attachPermissions($userId, ['fst_view_tickets']);
            $agent->updateMeta('slack_user_id', 'gone');
            TagPivot::create(['tag_id' => $defaultGroup->id, 'source_id' => $agent->id, 'source_type' => 'agent_group']);
            $ticket = FsFactory::ticket(['agent_id' => $agent->id]);
            $reply = FsFactory::conversation($ticket, ['person_id' => $agent->id]);

            FsTest::assert($rest('DELETE', '/agents/' . $agent->id, ['fallback_agent_id' => $agent->id])['status'] >= 400, 'fallback is the same agent');
            FsTest::assert($rest('DELETE', '/agents/' . $agent->id, ['fallback_agent_id' => 99999999])['status'] >= 400, 'unknown fallback');
            FsTest::assert((bool) Agent::find($agent->id), 'a refused delete removed the agent');

            $result = $rest('DELETE', '/agents/' . $agent->id, ['fallback_agent_id' => $fallback->id]);
            FsTest::assertHealthy($result, 'DELETE agents/{id}');
            FsTest::assertSame('Support Staff has been deleted', $result['message'], 'message');
            FsTest::assert(!Person::find($agent->id), 'agent row gone');
            FsTest::assertSame((int) $fallback->id, (int) Ticket::find($ticket->id)->agent_id, 'ticket moved');
            FsTest::assertSame((int) $fallback->id, (int) Conversation::find($reply->id)->person_id, 'reply moved');
            FsTest::assertSame(0, TagPivot::where('source_id', $agent->id)->where('source_type', 'agent_group')->count(), 'group membership gone');
            FsTest::assertSame(0, Meta::where('object_type', 'person_meta')->where('object_id', $agent->id)->count(), 'settings gone');
            FsTest::assertSame([], PermissionManager::getUserPermissions($userId), 'permissions gone');
        });

        FsTest::case('avatar reset and ping', function () use ($rest, &$agentIds) {
            $agent = FsFactory::agent();
            $agentIds[] = (int) $agent->id;
            $result = $rest('POST', '/agents/reset_avatar/' . $agent->id);
            FsTest::assertHealthy($result, 'POST agents/reset_avatar/{id}');
            FsTest::assertSame('Support Staff avatar reset to gravatar default', $result['message'], 'message');
            FsTest::assertSame(404, $rest('POST', '/agents/reset_avatar/99999999')['status'], 'unknown agent');
            FsTest::assertSame(['ping' => 'pong'], $rest('GET', '/tickets/ping')['data'], 'ping');
        });

        FsTest::case('the list needs fst_sensitive_data; add, edit and delete need an administrator', function () use ($rest, $wpUser, &$userIds, $adminId, &$agentIds) {
            list($userId) = $wpUser('viewer');
            $agentIds[] = (int) FsFactory::agent(['user_id' => $userId])->id;
            $target = FsFactory::agent();
            $agentIds[] = (int) $target->id;

            wp_set_current_user($userId);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets']);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assertSame(403, $rest('GET', '/agents')['status'], 'list without fst_sensitive_data');
                PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_sensitive_data']);
                PermissionManager::currentUserPermissions(false);
                FsTest::assertSame(200, $rest('GET', '/agents')['status'], 'list with fst_sensitive_data');
                FsTest::assertSame(403, $rest('PUT', '/agents/' . $target->id, ['email' => $target->email, 'first_name' => 'X'])['status'], 'edit without manage_options');
                FsTest::assertSame(403, $rest('DELETE', '/agents/' . $target->id, ['fallback_agent_id' => 1])['status'], 'delete without manage_options');
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
        });
    } finally {
        foreach (array_filter($agentIds) as $id) {
            TagPivot::where('source_id', $id)->where('source_type', 'agent_group')->delete();
            Meta::where('object_type', 'person_meta')->where('object_id', $id)->delete();
            Agent::where('id', $id)->delete();
        }
        foreach ($groupIds as $id) {
            TagPivot::where('tag_id', $id)->delete();
            AgentGroup::where('id', $id)->delete();
        }
        foreach ($originalDefaults as $id) {
            AgentGroup::where('id', $id)->update(['settings' => maybe_serialize(['is_default' => true])]);
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            PermissionManager::detachPermissions($userId);
            wp_delete_user($userId);
        }
    }
};
