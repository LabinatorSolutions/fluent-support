<?php
/**
 * Cron Job Monitor API: status, manual run and the REST method check.
 *
 * Test jobs are added through the cron-events filter and scheduled under test-only
 * hooks, so no real Fluent Support task runs. Users, filters and schedules are
 * removed in this file's finally.
 */

use FluentSupport\App\Modules\PermissionManager;

return function () {

    $adminId = get_current_user_id();
    $overdueHook = FsTest::uniq('fs_test_overdue_task');
    $missingHook = FsTest::uniq('fs_test_missing_task');
    $runCount = 0;

    $testJobs = function ($jobs) use ($overdueHook, $missingHook) {
        $jobs[] = ['hook' => $overdueHook, 'title' => 'Test overdue task', 'interval' => HOUR_IN_SECONDS];
        $jobs[] = ['hook' => $missingHook, 'title' => 'Test missing task', 'interval' => HOUR_IN_SECONDS];
        // Malformed entries are dropped instead of breaking the page.
        $jobs[] = ['hook' => 'fs_test_malformed_task'];

        return $jobs;
    };
    $countRun = function () use (&$runCount) {
        $runCount++;
    };

    $makeUser = function (array $permissions) {
        $login = FsTest::uniq('fs-system-tools');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }

        if ($permissions) {
            PermissionManager::attachPermissions($userId, $permissions);
        }

        return $userId;
    };

    $loginAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    $findEvent = function (array $events, $hook) {
        foreach ($events as $event) {
            if ($event['hook'] === $hook) {
                return $event;
            }
        }

        return null;
    };

    $subscriberId = $makeUser([]);
    $managerId = $makeUser(['fst_manage_settings']);

    add_filter('fluent_support/system_tools_cron_events', $testJobs);
    add_action($overdueHook, $countRun);
    // Two intervals late, so it counts as overdue.
    wp_schedule_single_event(time() - 2 * HOUR_IN_SECONDS - 60, $overdueHook);

    try {
        FsTest::case('cron api: admin gets server info and the free tasks', function () use ($loginAs, $adminId, $findEvent) {
            $loginAs($adminId);
            $result = FsTest::rest('GET', '/system-tools/cron');
            FsTest::assertHealthy($result, 'GET /system-tools/cron as admin');

            $data = $result['data'];
            FsTest::assertSame(true, $data['can_manage'], 'admin can run tasks');
            foreach (['memory_limit', 'usage_percent', 'max_execution_time', 'has_server_cron'] as $key) {
                FsTest::assert(array_key_exists($key, $data['server']), "server.{$key} is present");
            }
            foreach (['fluent_support_hourly_tasks', 'fluent_support_daily_tasks', 'fluent_support_weekly_tasks'] as $hook) {
                FsTest::assert($findEvent($data['events'], $hook) !== null, "{$hook} is listed");
            }
            FsTest::assert($findEvent($data['events'], 'fs_test_malformed_task') === null, 'malformed filter entries are dropped');
        });

        FsTest::case('cron api: half-hourly row follows its listeners', function () use ($loginAs, $adminId, $findEvent) {
            $loginAs($adminId);
            $events = FsTest::rest('GET', '/system-tools/cron')['data']['events'];
            FsTest::assertSame(
                (bool) has_action('fluent_support_half_hourly'),
                $findEvent($events, 'fluent_support_half_hourly') !== null,
                'half-hourly is listed only when something listens on it'
            );
        });

        FsTest::case('cron api: overdue and not-scheduled statuses', function () use ($loginAs, $adminId, $findEvent, $overdueHook, $missingHook) {
            $loginAs($adminId);
            $events = FsTest::rest('GET', '/system-tools/cron')['data']['events'];

            $overdue = $findEvent($events, $overdueHook);
            FsTest::assertSame('overdue', $overdue['status'] ?? null, 'a run missed by more than its interval is overdue');
            FsTest::assert(($overdue['next_run_human'] ?? '') !== '', 'overdue rows say how long ago the run was due');

            $missing = $findEvent($events, $missingHook);
            FsTest::assertSame('not_scheduled', $missing['status'] ?? null, 'an unscheduled task is not_scheduled');
            FsTest::assertSame('', $missing['next_run_human'] ?? null, 'not_scheduled rows have no next run text');
        });

        FsTest::case('cron api: run fires a registered task', function () use ($loginAs, $adminId, $overdueHook, &$runCount) {
            $loginAs($adminId);
            $before = $runCount;
            $result = FsTest::rest('POST', '/system-tools/cron/run', ['hook' => $overdueHook]);
            FsTest::assertHealthy($result, 'POST /system-tools/cron/run as admin');
            FsTest::assertSame($before + 1, $runCount, 'the task callback ran once');
        });

        FsTest::case('cron api: run rejects a hook that is not a registered task', function () use ($loginAs, $adminId) {
            $loginAs($adminId);
            $result = FsTest::rest('POST', '/system-tools/cron/run', ['hook' => 'wp_version_check']);
            FsTest::assertSame(422, $result['status'], 'unregistered hooks are rejected');
        });

        FsTest::case('cron api: settings manager can read but not run', function () use ($loginAs, $managerId, $overdueHook, &$runCount) {
            $loginAs($managerId);
            $read = FsTest::rest('GET', '/system-tools/cron');
            FsTest::assertHealthy($read, 'GET /system-tools/cron as settings manager');
            FsTest::assertSame(false, $read['data']['can_manage'], 'settings managers cannot run tasks');

            $before = $runCount;
            $run = FsTest::rest('POST', '/system-tools/cron/run', ['hook' => $overdueHook]);
            FsTest::assertSame(403, $run['status'], 'run needs a site administrator');
            FsTest::assertSame($before, $runCount, 'the task did not run');
        });

        FsTest::case('cron api: subscriber cannot read', function () use ($loginAs, $subscriberId) {
            $loginAs($subscriberId);
            FsTest::assertSame(403, FsTest::rest('GET', '/system-tools/cron')['status'], 'subscribers are denied');
        });

        FsTest::case('cron api: rest-test answers every method', function () use ($loginAs, $adminId) {
            $loginAs($adminId);
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
                $result = FsTest::rest($method, '/system-tools/rest-test');
                FsTest::assertHealthy($result, "{$method} /system-tools/rest-test");
            }
        });
    } finally {
        $loginAs($adminId);

        remove_filter('fluent_support/system_tools_cron_events', $testJobs);
        remove_action($overdueHook, $countRun);
        wp_clear_scheduled_hook($overdueHook);

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ([$subscriberId, $managerId] as $userId) {
            if (get_user_by('ID', $userId)) {
                wp_delete_user($userId);
            }
        }
    }
};
