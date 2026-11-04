<?php
/**
 * Advanced filter groups must not widen an agent's ticket scope.
 * Each filter group is tested against the viewer's visibility and mailbox restrictions.
 * Viewer users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $viewerUserIds = [];

    $makeViewer = function (array $permissions) use (&$viewerUserIds) {
        $login = FsTest::uniq('fs-adv-viewer');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create viewer user: ' . $userId->get_error_message());
        }

        $viewerUserIds[] = $userId;

        $agent = FsFactory::agent();
        $agent->user_id = $userId;
        $agent->save();
        PermissionManager::attachPermissions($userId, $permissions);

        return [$agent, $userId];
    };

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    try {
        $box = FsFactory::mailbox();
        $restrictedBox = FsFactory::mailbox();
        $customer = FsFactory::customer();
        $otherAgent = FsFactory::agent();

        // Viewer A: Manage Own Tickets, barred from $restrictedBox.
        [$viewerA, $viewerAUserId] = $makeViewer(['fst_manage_own_tickets']);
        $viewerA->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        // Viewer B: Manage Unassigned Tickets, no restrictions.
        [$viewerB, $viewerBUserId] = $makeViewer(['fst_manage_unassigned_tickets']);

        $ticket = function (array $attributes) use ($customer) {
            return FsFactory::ticket(array_merge([
                'customer_id' => $customer->id,
                'status'      => 'active',
            ], $attributes));
        };

        $needles = [
            'otherBox'   => FsTest::uniq('adv-other-box'),
            'ownBox'     => FsTest::uniq('adv-own-box'),
            'restricted' => FsTest::uniq('adv-restricted'),
            'unassigned' => FsTest::uniq('adv-unassigned'),
        ];

        $fixtures = [
            'ownBox' => $ticket([
                'mailbox_id' => $box->id,
                'agent_id'   => $viewerA->id,
                'title'      => 'Adv own ' . $needles['ownBox'],
            ]),
            'otherBox' => $ticket([
                'mailbox_id' => $box->id,
                'agent_id'   => $otherAgent->id,
                'title'      => 'Adv other ' . $needles['otherBox'],
            ]),
            // Assigned to viewer A, so only the mailbox restriction can hide it.
            'restricted' => $ticket([
                'mailbox_id' => $restrictedBox->id,
                'agent_id'   => $viewerA->id,
                'title'      => 'Adv restricted ' . $needles['restricted'],
            ]),
            'unassigned' => $ticket([
                'mailbox_id' => $box->id,
                'agent_id'   => null,
                'title'      => 'Adv unassigned ' . $needles['unassigned'],
            ]),
        ];

        $ids = [];
        foreach ($fixtures as $key => $fixture) {
            $ids[$key] = (int) $fixture->id;
        }

        // Returns the fixture IDs in a GET /tickets payload, sorted.
        $fixtureIdsIn = function ($result) use ($ids) {
            $payload = isset($result['data']['tickets']) ? $result['data']['tickets'] : null;
            if (is_object($payload)) {
                $payload = json_decode(json_encode($payload), true);
            }
            if (!isset($payload['data']) || !is_array($payload['data'])) {
                return null;
            }

            $found = [];
            foreach ($payload['data'] as $row) {
                $row = (array) $row;
                if (in_array((int) $row['id'], $ids, true)) {
                    $found[] = (int) $row['id'];
                }
            }
            sort($found);

            return $found;
        };

        $expect = function (array $keys) use ($ids) {
            $expected = [];
            foreach ($keys as $key) {
                $expected[] = $ids[$key];
            }
            sort($expected);

            return $expected;
        };

        // One AND-ed group per needle, sent the way the admin UI does.
        $advancedQuery = function (array $titleNeedles) {
            $groups = [];
            foreach ($titleNeedles as $needle) {
                $groups[] = [[
                    'source'   => ['tickets', 'title'],
                    'operator' => 'contains',
                    'value'    => $needle,
                ]];
            }

            return [
                'per_page'         => 100,
                'filter_type'      => 'advanced',
                'advanced_filters' => wp_json_encode($groups),
            ];
        };

        FsTest::case('assigned_only_agent_second_group_does_not_reveal_other_agents_ticket', function () use (
            $runAs, $viewerAUserId, $advancedQuery, $fixtureIdsIn, $expect, $needles
        ) {
            $runAs($viewerAUserId);
            $result = FsTest::rest('GET', '/tickets', $advancedQuery([$needles['otherBox'], $needles['ownBox']]));
            FsTest::assertHealthy($result, 'GET /tickets (assigned_only, two groups)');

            FsTest::assertSame(
                $expect(['ownBox']),
                $fixtureIdsIn($result),
                'only the viewer\'s own ticket; the other agent\'s ticket must stay hidden'
            );
        });

        FsTest::case('assigned_only_agent_single_group_for_other_agents_ticket_returns_nothing', function () use (
            $runAs, $viewerAUserId, $advancedQuery, $fixtureIdsIn, $expect, $needles
        ) {
            $runAs($viewerAUserId);
            $result = FsTest::rest('GET', '/tickets', $advancedQuery([$needles['otherBox']]));
            FsTest::assertHealthy($result, 'GET /tickets (assigned_only, one group)');

            FsTest::assertSame(
                $expect([]),
                $fixtureIdsIn($result),
                'a single group matching only another agent\'s ticket returns no fixtures'
            );
        });

        FsTest::case('mailbox_restriction_holds_when_first_group_matches_restricted_box', function () use (
            $runAs, $viewerAUserId, $advancedQuery, $fixtureIdsIn, $expect, $needles
        ) {
            $runAs($viewerAUserId);
            $result = FsTest::rest('GET', '/tickets', $advancedQuery([$needles['restricted'], $needles['ownBox']]));
            FsTest::assertHealthy($result, 'GET /tickets (mailbox restricted, two groups)');

            FsTest::assertSame(
                $expect(['ownBox']),
                $fixtureIdsIn($result),
                'the restricted-mailbox ticket must stay hidden even though group 1 matches it'
            );
        });

        FsTest::case('assigned_and_unassigned_agent_second_group_does_not_reveal_other_agents_ticket', function () use (
            $runAs, $viewerBUserId, $advancedQuery, $fixtureIdsIn, $expect, $needles
        ) {
            $runAs($viewerBUserId);
            $result = FsTest::rest('GET', '/tickets', $advancedQuery([$needles['otherBox'], $needles['unassigned']]));
            FsTest::assertHealthy($result, 'GET /tickets (assigned_and_unassigned, two groups)');

            FsTest::assertSame(
                $expect(['unassigned']),
                $fixtureIdsIn($result),
                'unassigned ticket only; the other agent\'s ticket must stay hidden'
            );
        });

        FsTest::case('assigned_and_unassigned_agent_single_group_for_other_agents_ticket_returns_nothing', function () use (
            $runAs, $viewerBUserId, $advancedQuery, $fixtureIdsIn, $expect, $needles
        ) {
            $runAs($viewerBUserId);
            $result = FsTest::rest('GET', '/tickets', $advancedQuery([$needles['otherBox']]));
            FsTest::assertHealthy($result, 'GET /tickets (assigned_and_unassigned, one group)');

            FsTest::assertSame(
                $expect([]),
                $fixtureIdsIn($result),
                'a single group matching only another agent\'s assigned ticket returns no fixtures'
            );
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
        foreach ($viewerUserIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
