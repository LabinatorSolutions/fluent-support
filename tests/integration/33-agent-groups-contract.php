<?php
/**
 * Agent groups: the five agent-groups/* endpoints the settings page reads
 * (resources/admin/Modules/Settings/AgentGroups.vue).
 *
 * - The list pages, searches and counts members; one group opens with its
 *   member ids and the agent picker.
 * - Create and update save the title, description, members and the default
 *   flag (only one group is the default); update keeps the members when
 *   agent_ids is left out.
 * - Delete needs another existing group and moves the members there.
 * - Members are agents only; other person ids are dropped.
 * - Only fst_sensitive_data may use the endpoints.
 *
 * Groups aren't factory-tracked; the finally removes them and restores the
 * site's default group.
 */

use FluentSupport\App\Models\AgentGroup;
use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $originalDefaults = [];
    foreach (AgentGroup::whereNotNull('settings')->get() as $group) {
        if (!empty($group->settings['is_default'])) {
            $originalDefaults[] = (int) $group->id;
        }
    }

    $groupIds = [];
    $userIds = [];

    $membersOf = function ($groupId) {
        $ids = array_map('intval', TagPivot::where('tag_id', $groupId)->where('source_type', 'agent_group')->pluck('source_id')->toArray());
        sort($ids);
        return $ids;
    };

    $isDefault = function ($groupId) {
        $group = AgentGroup::find($groupId);
        return $group && !empty($group->settings['is_default']);
    };

    // The REST answer as the browser gets it (in process it holds models and paginators).
    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };

    $create = function (array $params) use (&$groupIds, $rest) {
        $result = $rest('POST', '/agent-groups', $params);
        if (!empty($result['data']['group']['id'])) {
            $groupIds[] = (int) $result['data']['group']['id'];
        }
        return $result;
    };

    $ids = function (array $agents) {
        $ids = array_map(fn($agent) => (int) $agent->id, $agents);
        sort($ids);
        return $ids;
    };

    try {
        $agentA = FsFactory::agent();
        $agentB = FsFactory::agent();
        $agentC = FsFactory::agent();
        $customer = FsFactory::customer();

        FsTest::case('create saves the title, description and members, and answers the group', function () use ($create, $membersOf, $marker, $agentA, $agentB, $ids, $isDefault, $rest) {
            $result = $create([
                'title'       => 'Group One ' . $marker,
                'description' => 'First fixture group',
                'agent_ids'   => [$agentA->id, $agentB->id],
            ]);

            FsTest::assertHealthy($result, 'POST /agent-groups');
            FsTest::assertSame('Agent group has been created', $result['message'], 'create message');
            $groupId = (int) ($result['data']['group']['id'] ?? 0);
            FsTest::assert($groupId > 0, 'create answered no group id');
            FsTest::assertSame('Group One ' . $marker, $result['data']['group']['title'] ?? null, 'created title');
            FsTest::assertSame('First fixture group', AgentGroup::find($groupId)->description, 'saved description');
            FsTest::assertSame($ids([$agentA, $agentB]), $membersOf($groupId), 'saved members');
            FsTest::assert(!$isDefault($groupId), 'a group saved without is_default became the default');
        });

        FsTest::case('create without a title answers 422 and saves nothing', function () use ($create, $marker, $rest) {
            $before = AgentGroup::count();
            $result = $create(['description' => 'no title ' . $marker]);

            FsTest::assertSame(422, $result['status'], 'status without a title');
            FsTest::assertSame($before, AgentGroup::count(), 'group count after a refused create');
        });

        FsTest::case('members are agents only: a customer id is dropped', function () use ($create, $membersOf, $marker, $agentA, $customer, $ids, $rest) {
            $result = $create([
                'title'     => 'Group Mixed ' . $marker,
                'agent_ids' => [$agentA->id, $customer->id, 99999999],
            ]);

            FsTest::assertHealthy($result, 'POST /agent-groups (mixed ids)');
            FsTest::assertSame($ids([$agentA]), $membersOf($result['data']['group']['id']), 'members after a create with a customer id and an unknown id');
        });

        FsTest::case('only one group is the default', function () use ($create, $isDefault, $marker, $agentA, $rest) {
            $first = $create(['title' => 'Default One ' . $marker, 'agent_ids' => [$agentA->id], 'is_default' => 'true']);
            $second = $create(['title' => 'Default Two ' . $marker, 'agent_ids' => [$agentA->id], 'is_default' => 'true']);

            FsTest::assertHealthy($second, 'POST /agent-groups (second default)');
            FsTest::assert($isDefault($second['data']['group']['id']), 'the newest default group is not the default');
            FsTest::assert(!$isDefault($first['data']['group']['id']), 'the earlier default group kept the flag');

            $defaults = 0;
            foreach (AgentGroup::whereNotNull('settings')->get() as $group) {
                $defaults += !empty($group->settings['is_default']) ? 1 : 0;
            }
            FsTest::assertSame(1, $defaults, 'number of default groups');
        });

        FsTest::case('index pages, searches and counts members', function () use ($create, $marker, $agentA, $agentB, $agentC, $rest) {
            $created = $create(['title' => 'Searchable ' . $marker, 'agent_ids' => [$agentA->id, $agentB->id, $agentC->id]]);
            $groupId = (int) $created['data']['group']['id'];

            $result = $rest('GET', '/agent-groups', ['search' => 'Searchable ' . $marker, 'per_page' => 10, 'page' => 1]);
            FsTest::assertHealthy($result, 'GET /agent-groups');

            $page = $result['data']['groups'] ?? [];
            foreach (['data', 'total', 'per_page', 'current_page'] as $key) {
                FsTest::assert(array_key_exists($key, $page), "groups page has no '$key'");
            }
            FsTest::assertSame(1, (int) $page['total'], 'search total');

            $row = $page['data'][0] ?? [];
            FsTest::assertSame($groupId, (int) ($row['id'] ?? 0), 'searched group id');
            FsTest::assertSame(3, (int) ($row['agents_count'] ?? -1), 'agents_count');
            foreach (['title', 'description', 'settings'] as $key) {
                FsTest::assert(array_key_exists($key, $row), "group row has no '$key'");
            }
        });

        FsTest::case('get answers the group with member ids and the agent picker', function () use ($create, $marker, $agentA, $agentB, $customer, $ids, $rest) {
            $created = $create(['title' => 'Openable ' . $marker, 'agent_ids' => [$agentA->id, $agentB->id]]);
            $groupId = (int) $created['data']['group']['id'];

            $result = $rest('GET', '/agent-groups/' . $groupId);
            FsTest::assertHealthy($result, 'GET /agent-groups/{id}');

            $group = $result['data']['group'] ?? [];
            FsTest::assertSame($groupId, (int) ($group['id'] ?? 0), 'opened group id');
            $memberIds = array_map('intval', (array) ($group['agent_ids'] ?? []));
            sort($memberIds);
            FsTest::assertSame($ids([$agentA, $agentB]), $memberIds, 'agent_ids');

            $agents = (array) ($result['data']['agents'] ?? []);
            $pickerIds = array_map(fn($agent) => (int) $agent['id'], $agents);
            FsTest::assert(in_array((int) $agentA->id, $pickerIds, true), 'the picker has no agent A');
            FsTest::assert(!in_array((int) $customer->id, $pickerIds, true), 'the picker lists a customer');
            foreach (['id', 'first_name', 'last_name'] as $key) {
                FsTest::assert(array_key_exists($key, $agents[0] ?? []), "picker agent has no '$key'");
            }
        });

        FsTest::case('get and update answer 404 for an unknown group', function () use ($rest) {
            FsTest::assertSame(404, $rest('GET', '/agent-groups/99999999')['status'], 'GET unknown group');
            FsTest::assertSame(404, $rest('PUT', '/agent-groups/99999999', ['title' => 'x'])['status'], 'PUT unknown group');
        });

        FsTest::case('update replaces the members, and keeps them when agent_ids is left out', function () use ($create, $membersOf, $isDefault, $marker, $agentA, $agentB, $agentC, $ids, $rest) {
            $created = $create(['title' => 'Editable ' . $marker, 'agent_ids' => [$agentA->id, $agentB->id], 'is_default' => 'true']);
            $groupId = (int) $created['data']['group']['id'];

            $result = $rest('PUT', '/agent-groups/' . $groupId, ['title' => 'Edited ' . $marker, 'description' => 'changed', 'agent_ids' => [$agentC->id]]);
            FsTest::assertHealthy($result, 'PUT /agent-groups/{id}');
            FsTest::assertSame('Agent group has been updated', $result['message'], 'update message');
            $group = AgentGroup::find($groupId);
            FsTest::assertSame('Edited ' . $marker, $group->title, 'updated title');
            FsTest::assertSame('changed', $group->description, 'updated description');
            FsTest::assertSame($ids([$agentC]), $membersOf($groupId), 'members after update');
            FsTest::assert($isDefault($groupId), 'update without is_default kept the default flag');

            $result = $rest('PUT', '/agent-groups/' . $groupId, ['title' => 'Edited again ' . $marker]);
            FsTest::assertHealthy($result, 'PUT /agent-groups/{id} (no agent_ids)');
            FsTest::assertSame($ids([$agentC]), $membersOf($groupId), 'members after an update without agent_ids');
            FsTest::assertSame('changed', AgentGroup::find($groupId)->description, 'description kept when left out');
            FsTest::assert($isDefault($groupId), 'still the default');
        });

        FsTest::case('delete needs another existing group', function () use ($create, $marker, $agentA, $rest) {
            $created = $create(['title' => 'Doomed ' . $marker, 'agent_ids' => [$agentA->id]]);
            $groupId = (int) $created['data']['group']['id'];

            foreach ([[], ['fallback_group_id' => $groupId], ['fallback_group_id' => 99999999]] as $params) {
                $result = $rest('DELETE', '/agent-groups/' . $groupId, $params);
                FsTest::assertSame(422, $result['status'], 'delete status with ' . wp_json_encode($params));
                FsTest::assert($result['message'] !== '', 'delete refused without a message');
            }
            FsTest::assert((bool) AgentGroup::find($groupId), 'a refused delete removed the group');
            FsTest::assertSame(404, $rest('DELETE', '/agent-groups/99999999', ['fallback_group_id' => $groupId])['status'], 'DELETE unknown group');
        });

        FsTest::case('delete moves the members to the fallback group without duplicates', function () use ($create, $membersOf, $marker, $agentA, $agentB, $agentC, $ids, $rest) {
            $doomed = (int) $create(['title' => 'Leaving ' . $marker, 'agent_ids' => [$agentA->id, $agentB->id]])['data']['group']['id'];
            $fallback = (int) $create(['title' => 'Staying ' . $marker, 'agent_ids' => [$agentB->id, $agentC->id]])['data']['group']['id'];

            $result = $rest('DELETE', '/agent-groups/' . $doomed, ['fallback_group_id' => $fallback]);
            FsTest::assertHealthy($result, 'DELETE /agent-groups/{id}');
            FsTest::assertSame('Agent group has been deleted and agents have been reassigned', $result['message'], 'delete message');
            FsTest::assert(!AgentGroup::find($doomed), 'the deleted group still exists');
            FsTest::assertSame([], $membersOf($doomed), 'member rows of the deleted group');
            FsTest::assertSame($ids([$agentA, $agentB, $agentC]), $membersOf($fallback), 'fallback members');
        });

        FsTest::case('an agent without fst_sensitive_data is refused', function () use ($create, $marker, &$userIds, $adminId, $rest) {
            $login = FsTest::uniq('fs-groups-agent');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
            $userIds[] = $userId;
            FsFactory::agent(['user_id' => $userId]);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets']);

            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assertSame(403, $rest('GET', '/agent-groups')['status'], 'GET as a plain agent');
                $before = AgentGroup::count();
                FsTest::assertSame(403, $create(['title' => 'Sneaky ' . $marker])['status'], 'POST as a plain agent');
                FsTest::assertSame($before, AgentGroup::count(), 'group count after a refused create');
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
        });
    } finally {
        foreach ($groupIds as $groupId) {
            TagPivot::where('tag_id', $groupId)->where('source_type', 'agent_group')->delete();
            AgentGroup::where('id', $groupId)->delete();
        }
        foreach (AgentGroup::whereNotNull('settings')->get() as $group) {
            $settings = $group->settings ?: [];
            $shouldBe = in_array((int) $group->id, $originalDefaults, true);
            if (!empty($settings['is_default']) !== $shouldBe) {
                $settings['is_default'] = $shouldBe;
                $group->settings = $settings;
                $group->save();
            }
        }
        foreach ($userIds as $userId) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    }
};
