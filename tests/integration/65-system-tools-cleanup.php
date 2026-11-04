<?php
/**
 * Data Cleanup API: counts, chunked delete, validation and permissions.
 *
 * Seeded activity rows are dated 2005 and the requests use a ~19-year cutoff, so only
 * this file's rows match; real lab activity is never deleted. Seeded rows and users are
 * removed in this file's finally.
 */

use FluentSupport\App\Modules\PermissionManager;

return function () {
    global $wpdb;

    $adminId = get_current_user_id();
    $table = $wpdb->prefix . 'fs_activities';
    $marker = FsTest::uniq('fs-cleanup-seed');
    $seeded = 25000;
    $daysBefore = 7000;
    $params = ['days_before' => $daysBefore, 'selected_logs' => ['activity_logs']];

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

    $seedOld = function ($count) use ($wpdb, $table, $marker) {
        for ($offset = 0; $offset < $count; $offset += 1000) {
            $values = [];
            for ($i = 0; $i < min(1000, $count - $offset); $i++) {
                $values[] = $wpdb->prepare('(%s, %s, %s, %s)', 'fstest_seed', $marker, '2005-01-01 00:00:00', '2005-01-01 00:00:00');
            }
            $wpdb->query("INSERT INTO {$table} (event_type, description, created_at, updated_at) VALUES " . implode(',', $values));
        }
    };

    $countMarked = function () use ($wpdb, $table, $marker) {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE description = %s", $marker));
    };

    $subscriberId = $makeUser([]);
    $managerId = $makeUser(['fst_manage_settings']);

    // One recent row that must survive every delete below.
    $wpdb->insert($table, [
        'event_type'  => 'fstest_seed',
        'description' => $marker,
        'created_at'  => current_time('mysql'),
        'updated_at'  => current_time('mysql'),
    ]);
    $seedOld($seeded);

    try {
        FsTest::case('cleanup api: counts only rows older than the cutoff', function () use ($loginAs, $adminId, $params, $seeded) {
            $loginAs($adminId);
            $result = FsTest::rest('GET', '/system-tools/cleanup', $params);
            FsTest::assertHealthy($result, 'GET /system-tools/cleanup as admin');

            FsTest::assertSame(true, $result['data']['can_manage'], 'admin can delete');
            FsTest::assertSame('activity_logs', $result['data']['log_counts'][0]['key'] ?? null, 'one row per selected type');
            FsTest::assertSame($seeded, (int) ($result['data']['log_counts'][0]['count'] ?? -1), 'counts the seeded old rows, not the recent one');
        });

        FsTest::case('cleanup api: settings manager can preview but not delete', function () use ($loginAs, $managerId, $params, $countMarked, $seeded) {
            $loginAs($managerId);
            $read = FsTest::rest('GET', '/system-tools/cleanup', $params);
            FsTest::assertHealthy($read, 'GET /system-tools/cleanup as settings manager');
            FsTest::assertSame(false, $read['data']['can_manage'], 'settings managers cannot delete');

            FsTest::assertSame(403, FsTest::rest('DELETE', '/system-tools/cleanup', $params)['status'], 'delete needs a site administrator');
            FsTest::assertSame($seeded + 1, $countMarked(), 'the denied delete removed nothing');
        });

        FsTest::case('cleanup api: subscriber cannot preview', function () use ($loginAs, $subscriberId, $params) {
            $loginAs($subscriberId);
            FsTest::assertSame(403, FsTest::rest('GET', '/system-tools/cleanup', $params)['status'], 'subscribers are denied');
        });

        FsTest::case('cleanup api: invalid input is rejected', function () use ($loginAs, $adminId) {
            $loginAs($adminId);
            $tooRecent = FsTest::rest('GET', '/system-tools/cleanup', ['days_before' => 5, 'selected_logs' => ['activity_logs']]);
            FsTest::assertSame(422, $tooRecent['status'], 'fewer than 7 days is rejected');

            $unknownType = FsTest::rest('DELETE', '/system-tools/cleanup', ['days_before' => 30, 'selected_logs' => ['fs_tickets']]);
            FsTest::assertSame(422, $unknownType['status'], 'unknown log types are rejected');
        });

        FsTest::case('cleanup api: deletes in chunks until done and keeps newer rows', function () use ($loginAs, $adminId, $params, $countMarked, $seeded) {
            $loginAs($adminId);
            $calls = 0;
            $total = 0;

            do {
                $result = FsTest::rest('DELETE', '/system-tools/cleanup', $params);
                FsTest::assertHealthy($result, 'DELETE /system-tools/cleanup call ' . ($calls + 1));
                $total += (int) $result['data']['deleted'];
                $calls++;
            } while (!empty($result['data']['has_more']) && $calls < 10);

            FsTest::assertSame(3, $calls, '25,000 rows take three 10,000-row calls');
            FsTest::assertSame($seeded, $total, 'every seeded old row was deleted');
            FsTest::assertSame(1, $countMarked(), 'the recent row survived');
        });

        FsTest::case('cleanup api: nothing to delete stops the loop', function () use ($loginAs, $adminId, $params) {
            $loginAs($adminId);
            $result = FsTest::rest('DELETE', '/system-tools/cleanup', $params);
            FsTest::assertHealthy($result, 'DELETE with nothing left');
            FsTest::assertSame(0, (int) $result['data']['deleted'], 'nothing deleted');
            FsTest::assertSame(false, $result['data']['has_more'], 'has_more is false so the page stops');
        });

        FsTest::case('cleanup api: notification recipients stay within the chunk limit', function () use ($wpdb, $loginAs, $adminId, $daysBefore) {
            $loginAs($adminId);
            $notifications = $wpdb->prefix . 'fs_notifications';
            $recipients = $wpdb->prefix . 'fs_notification_users';
            $tableExists = function ($table) use ($wpdb) {
                return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            };
            $createdNotifications = !$tableExists($notifications);
            $createdRecipients = !$tableExists($recipients);

            \FluentSupport\Database\Migrations\NotificationsMigrator::migrate();
            \FluentSupport\Database\Migrations\NotificationUsersMigrator::migrate();

            $insertNotification = function ($createdAt, $recipientCount) use ($wpdb, $notifications, $recipients) {
                $wpdb->insert($notifications, [
                    'event_type' => 'fstest_seed',
                    'category'   => 'fstest',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $id = (int) $wpdb->insert_id;

                for ($offset = 0; $offset < $recipientCount; $offset += 1000) {
                    $values = [];
                    for ($userId = $offset + 1; $userId <= min($offset + 1000, $recipientCount); $userId++) {
                        $values[] = $wpdb->prepare('(%d, %d, %s)', $id, $userId, $createdAt);
                    }
                    $wpdb->query("INSERT INTO {$recipients} (notification_id, user_id, created_at) VALUES " . implode(',', $values));
                }

                return $id;
            };

            $countRecipients = function ($id) use ($wpdb, $recipients) {
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$recipients} WHERE notification_id = %d", $id));
            };

            $oldId = $insertNotification('2005-01-01 00:00:00', 10005);
            $recentId = $insertNotification(current_time('mysql'), 1);
            $notificationParams = ['days_before' => $daysBefore, 'selected_logs' => ['internal_notifications']];

            try {
                $first = FsTest::rest('DELETE', '/system-tools/cleanup', $notificationParams);
                FsTest::assertHealthy($first, 'DELETE notifications call 1');
                FsTest::assertSame(10000, (int) $first['data']['deleted'], 'one call deletes no more than the chunk limit');
                FsTest::assertSame(true, $first['data']['has_more'], 'remaining recipients keep the loop going');
                FsTest::assertSame(5, $countRecipients($oldId), 'the rest of the recipients wait for the next call');
                FsTest::assertSame(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$notifications} WHERE id = %d", $oldId)), 'the notification is kept while it still has recipients');

                $second = FsTest::rest('DELETE', '/system-tools/cleanup', $notificationParams);
                FsTest::assertHealthy($second, 'DELETE notifications call 2');
                FsTest::assertSame(6, (int) $second['data']['deleted'], 'last recipients and their notification are deleted');
                FsTest::assertSame(false, $second['data']['has_more'], 'the loop stops');
                FsTest::assertSame(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$notifications} WHERE id = %d", $oldId)), 'old notification deleted');
                FsTest::assertSame(1, $countRecipients($recentId), 'the recent notification keeps its recipient');
            } finally {
                // Drop only the tables this case created; a pre-existing one keeps its own rows.
                if ($createdRecipients) {
                    $wpdb->query("DROP TABLE IF EXISTS {$recipients}");
                } else {
                    $wpdb->query($wpdb->prepare("DELETE FROM {$recipients} WHERE notification_id IN (%d, %d)", $oldId, $recentId));
                }

                if ($createdNotifications) {
                    $wpdb->query("DROP TABLE IF EXISTS {$notifications}");
                } else {
                    $wpdb->query($wpdb->prepare("DELETE FROM {$notifications} WHERE id IN (%d, %d)", $oldId, $recentId));
                }
            }
        });

        FsTest::case('cleanup api: filter-added types count and delete through their callbacks', function () use ($loginAs, $adminId, $daysBefore) {
            $loginAs($adminId);
            $deleteCalls = [];
            $onceCalls = 0;
            $register = function ($types) use (&$deleteCalls, &$onceCalls) {
                $types[] = [
                    'key'    => 'fstest_extra',
                    'title'  => 'Test Extra Logs',
                    'count'  => function ($cutoff) { return 42; },
                    'delete' => function ($cutoff, $limit) use (&$deleteCalls) {
                        $deleteCalls[] = $limit;
                        return 7;
                    },
                ];
                $types[] = ['key' => 'fstest_broken', 'title' => 'No callbacks'];
                $types[] = [
                    'key'    => 'FSTEST_EXTRA',
                    'title'  => 'Duplicate key',
                    'count'  => function () { return 0; },
                    'delete' => function () { return 0; },
                ];
                $types[] = [
                    'key'    => 'fstest_greedy',
                    'title'  => 'Over-reporting',
                    'count'  => function () { return 0; },
                    'delete' => function () { return 50000; },
                ];
                // Registered only on the first filter run of a request, i.e. gone before the delete runs.
                if (++$onceCalls === 1) {
                    $types[] = [
                        'key'    => 'fstest_once',
                        'title'  => 'Registered once',
                        'count'  => function () { return 0; },
                        'delete' => function () { return 0; },
                    ];
                }
                $types[] = [
                    'key'    => 'activity_logs',
                    'title'  => 'Hijacked',
                    'count'  => function () { return 999; },
                    'delete' => function () { return 999; },
                ];

                return $types;
            };
            add_filter('fluent_support/system_tools_cleanup_types', $register);

            try {
                $read = FsTest::rest('GET', '/system-tools/cleanup', ['days_before' => $daysBefore, 'selected_logs' => ['fstest_extra', 'activity_logs']]);
                FsTest::assertHealthy($read, 'GET with a filter-added type');
                $counts = array_column($read['data']['log_counts'], null, 'key');
                FsTest::assertSame('Test Extra Logs', $counts['fstest_extra']['title'] ?? null, 'the added type is listed with its title');
                FsTest::assertSame(42, (int) ($counts['fstest_extra']['count'] ?? -1), 'its count comes from its callback');
                FsTest::assertSame('Activity Logs', $counts['activity_logs']['title'] ?? null, 'a filter entry cannot replace a built-in type');
                FsTest::assert(999 !== (int) ($counts['activity_logs']['count'] ?? 999), 'built-in counts never use a filter callback');

                $delete = FsTest::rest('DELETE', '/system-tools/cleanup', ['days_before' => $daysBefore, 'selected_logs' => ['fstest_extra']]);
                FsTest::assertHealthy($delete, 'DELETE a filter-added type');
                FsTest::assertSame(7, (int) $delete['data']['deleted'], 'rows deleted come from its callback');
                FsTest::assertSame([\FluentSupport\App\Modules\SystemTools\DataCleanup::CHUNK_SIZE], $deleteCalls, 'its delete callback gets the chunk limit');

                $broken = FsTest::rest('GET', '/system-tools/cleanup', ['days_before' => $daysBefore, 'selected_logs' => ['fstest_broken']]);
                FsTest::assertSame(422, $broken['status'], 'an entry without callbacks is not a valid type');

                $greedy = FsTest::rest('DELETE', '/system-tools/cleanup', ['days_before' => $daysBefore, 'selected_logs' => ['fstest_greedy']]);
                FsTest::assertHealthy($greedy, 'DELETE an over-reporting type');
                FsTest::assertSame(\FluentSupport\App\Modules\SystemTools\DataCleanup::CHUNK_SIZE, (int) $greedy['data']['deleted'], 'a callback cannot report more than the chunk limit');

                $onceCalls = 0;
                $gone = FsTest::rest('DELETE', '/system-tools/cleanup', ['days_before' => $daysBefore, 'selected_logs' => ['fstest_once']]);
                FsTest::assertHealthy($gone, 'DELETE a type that unregistered after validation');
                FsTest::assertSame(0, (int) $gone['data']['deleted'], 'a type gone by delete time deletes nothing');
            } finally {
                remove_filter('fluent_support/system_tools_cleanup_types', $register);
            }
        });
    } finally {
        $loginAs($adminId);

        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE description = %s", $marker));

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
