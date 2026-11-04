<?php
/**
 * Database Health API: table check and repair.
 *
 * The missing-table case renames a real table aside and restores it in finally, so
 * no data is lost. Test users are removed in finally too.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\Database\Migrations\DataMetrixMigrator;

return function () {
    global $wpdb;

    $adminId = get_current_user_id();
    $table = $wpdb->prefix . DataMetrixMigrator::$tableName;
    $parkedTable = $table . '_fstest_parked';

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

    $tableExists = function ($name) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)) === $name;
    };

    $findTable = function (array $tables, $name) {
        foreach ($tables as $item) {
            if ($item['name'] === $name) {
                return $item;
            }
        }

        return null;
    };

    $getHealth = function () {
        return FsTest::rest('GET', '/system-tools/database');
    };

    $subscriberId = $makeUser([]);
    $managerId = $makeUser(['fst_manage_settings']);

    try {
        FsTest::case('database api: admin sees every core table', function () use ($loginAs, $adminId, $getHealth, $findTable) {
            $loginAs($adminId);
            $result = $getHealth();
            FsTest::assertHealthy($result, 'GET /system-tools/database as admin');

            $data = $result['data'];
            FsTest::assertSame(true, $data['can_manage'], 'admin can repair');
            foreach (['fs_tickets', 'fs_conversations', 'fs_persons', 'fs_meta'] as $name) {
                $row = $findTable($data['tables'], $name);
                FsTest::assertSame('ok', $row['status'] ?? null, "{$name} is reported present");
                FsTest::assert(!empty($row['title']), "{$name} has a readable title");
            }
        });

        FsTest::case('database api: lazy tables are listed only when they exist', function () use ($loginAs, $adminId, $getHealth, $findTable, $tableExists, $wpdb) {
            $loginAs($adminId);
            $tables = $getHealth()['data']['tables'];
            foreach (['fs_ai_activity_logs', 'fs_notifications', 'fs_notification_users'] as $name) {
                FsTest::assertSame(
                    $tableExists($wpdb->prefix . $name),
                    $findTable($tables, $name) !== null,
                    "{$name} is listed only when the table exists"
                );
            }
        });

        FsTest::case('database api: a missing table is reported first and repair recreates it', function () use ($loginAs, $adminId, $getHealth, $tableExists, $wpdb, $table, $parkedTable) {
            $loginAs($adminId);
            $wpdb->query("RENAME TABLE `{$table}` TO `{$parkedTable}`");
            FsTest::assert(!$tableExists($table), 'fixture: table moved aside');

            $tables = $getHealth()['data']['tables'];
            FsTest::assertSame(DataMetrixMigrator::$tableName, $tables[0]['name'] ?? null, 'the missing table is listed first');
            FsTest::assertSame('missing', $tables[0]['status'] ?? null, 'the missing table is reported missing');

            $repair = FsTest::rest('POST', '/system-tools/database/repair');
            FsTest::assertHealthy($repair, 'POST /system-tools/database/repair as admin');
            FsTest::assert($tableExists($table), 'repair recreated the table');
        });

        FsTest::case('database api: repair lets modules repair the tables they add', function () use ($loginAs, $adminId) {
            $loginAs($adminId);
            $fired = 0;
            $callback = function () use (&$fired) {
                $fired++;
            };

            add_action('fluent_support/system_tools_database_repair', $callback);
            try {
                FsTest::assertHealthy(FsTest::rest('POST', '/system-tools/database/repair'), 'repair as admin');
            } finally {
                remove_action('fluent_support/system_tools_database_repair', $callback);
            }

            FsTest::assertSame(1, $fired, 'the repair action fired once');
        });

        FsTest::case('database api: settings manager can read but not repair', function () use ($loginAs, $managerId, $getHealth) {
            $loginAs($managerId);
            $read = $getHealth();
            FsTest::assertHealthy($read, 'GET /system-tools/database as settings manager');
            FsTest::assertSame(false, $read['data']['can_manage'], 'settings managers cannot repair');

            FsTest::assertSame(403, FsTest::rest('POST', '/system-tools/database/repair')['status'], 'repair needs a site administrator');
        });

        FsTest::case('database api: subscriber cannot read', function () use ($loginAs, $subscriberId, $getHealth) {
            $loginAs($subscriberId);
            FsTest::assertSame(403, $getHealth()['status'], 'subscribers are denied');
        });
    } finally {
        $loginAs($adminId);

        if ($tableExists($parkedTable)) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
            $wpdb->query("RENAME TABLE `{$parkedTable}` TO `{$table}`");
        }

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
