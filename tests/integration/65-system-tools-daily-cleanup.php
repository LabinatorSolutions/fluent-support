<?php
/**
 * Daily log cleanup (CleanupHandler::initDailyTasks): locks in which rows the cron deletes,
 * so moving the cron onto DataCleanup must pass this file unchanged.
 *
 * Activity and AI retention are set to 7000 days and seeded rows are dated 2005/2010, so real
 * rows are never deleted. Notifications use the cron's fixed 15 days, so the file skips when the
 * site has its own notifications that old. Tables this file creates are dropped in finally.
 */

use FluentSupport\App\Hooks\Handlers\CleanupHandler;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Database\Migrations\AIActivityLogsMigrator;
use FluentSupport\Database\Migrations\NotificationsMigrator;
use FluentSupport\Database\Migrations\NotificationUsersMigrator;

return function () {
    global $wpdb;

    $activities = $wpdb->prefix . 'fs_activities';
    $aiLogs = $wpdb->prefix . AIActivityLogsMigrator::$tableName;
    $notifications = $wpdb->prefix . NotificationsMigrator::$tableName;
    $recipients = $wpdb->prefix . NotificationUsersMigrator::$tableName;
    $marker = FsTest::uniq('fs-daily-cleanup');
    $optionKeys = ['_activity_settings', '_ai_activity_settings'];

    $runDaily = function () {
        (new CleanupHandler())->initDailyTasks();
    };

    $daysAgo = function ($days) {
        return gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
    };

    $seedActivity = function ($createdAt, $count = 1) use ($wpdb, $activities, $marker) {
        for ($offset = 0; $offset < $count; $offset += 1000) {
            $values = [];
            for ($i = 0; $i < min(1000, $count - $offset); $i++) {
                $values[] = $wpdb->prepare('(%s, %s, %s, %s)', 'fstest_seed', $marker, $createdAt, $createdAt);
            }
            $wpdb->query("INSERT INTO {$activities} (event_type, description, created_at, updated_at) VALUES " . implode(',', $values));
        }
    };

    $countActivity = function ($createdAt) use ($wpdb, $activities, $marker) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$activities} WHERE description = %s AND created_at = %s",
            $marker,
            $createdAt
        ));
    };

    $seedAILog = function ($createdAt) use ($wpdb, $aiLogs, $marker) {
        $wpdb->insert($aiLogs, ['model_name' => 'fstest', 'prompt' => $marker, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
    };

    $countAILogs = function ($createdAt) use ($wpdb, $aiLogs, $marker) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$aiLogs} WHERE prompt = %s AND created_at = %s",
            $marker,
            $createdAt
        ));
    };

    $tableExists = function ($table) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    };

    // Every daily run deletes notifications older than 15 days, so never run against real ones.
    if ($tableExists($notifications) && $tableExists($recipients)) {
        $realOld = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$notifications} WHERE created_at < %s", $daysAgo(15)));
        if ($realOld) {
            FsTest::case('daily cleanup', function () use ($realOld) {
                FsTest::skip("{$realOld} notifications on this site are older than 15 days and would be deleted");
            });
            return;
        }
    }

    $savedOptions = [];
    foreach ($optionKeys as $key) {
        $row = Meta::where('object_type', 'option')->where('key', $key)->first();
        $savedOptions[$key] = $row ? $row->value : null;
    }

    $createdAITable = !$tableExists($aiLogs);
    AIActivityLogsMigrator::migrate();

    Helper::updateOption('_activity_settings', ['delete_days' => 7000]);
    Helper::updateOption('_ai_activity_settings', ['delete_days' => 7000]);

    try {
        FsTest::case('daily cleanup: deletes activity older than the retention days and keeps newer rows', function () use ($seedActivity, $countActivity, $runDaily) {
            $seedActivity('2005-01-01 00:00:00');
            $seedActivity('2010-01-01 00:00:00');

            $runDaily();

            FsTest::assertSame(0, $countActivity('2005-01-01 00:00:00'), 'activity older than 7000 days is deleted');
            FsTest::assertSame(1, $countActivity('2010-01-01 00:00:00'), 'activity inside the retention period is kept');
        });

        FsTest::case('daily cleanup: one run removes every old activity row, past the 10,000-row API chunk', function () use ($seedActivity, $countActivity, $runDaily) {
            $seedActivity('2005-01-02 00:00:00', 10050);

            $runDaily();

            FsTest::assertSame(0, $countActivity('2005-01-02 00:00:00'), 'all 10,050 old rows are gone after one daily run');
        });

        FsTest::case('daily cleanup: activity retention defaults to 14 days', function () use ($wpdb, $activities, $marker, $seedActivity, $countActivity, $runDaily, $daysAgo) {
            $others = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$activities} WHERE created_at < %s AND (description IS NULL OR description != %s)",
                $daysAgo(14),
                $marker
            ));
            if ($others) {
                FsTest::skip("{$others} lab activity rows are older than 14 days and would be deleted");
                return;
            }

            $old = $daysAgo(15);
            $recent = $daysAgo(13);
            $seedActivity($old);
            $seedActivity($recent);
            Helper::deleteOption('_activity_settings');

            try {
                $runDaily();
            } finally {
                Helper::updateOption('_activity_settings', ['delete_days' => 7000]);
            }

            FsTest::assertSame(0, $countActivity($old), 'activity older than 14 days is deleted');
            FsTest::assertSame(1, $countActivity($recent), 'activity newer than 14 days is kept');
        });

        FsTest::case('daily cleanup: leaves AI activity logs alone when no AI integration is enabled', function () use ($seedAILog, $countAILogs, $runDaily) {
            // Same condition CleanupHandler::cleanAIActivityLogs() checks.
            if (defined('FLUENT_SUPPORT_PRO_DIR_FILE') && Helper::openAIIntegrationStatus()) {
                FsTest::skip('an AI integration is enabled on this site');
                return;
            }

            $seedAILog('2005-01-01 00:00:00');

            $runDaily();

            FsTest::assertSame(1, $countAILogs('2005-01-01 00:00:00'), 'AI logs are only cleaned while an AI integration is enabled');
        });

        FsTest::case('daily cleanup: deletes AI activity logs older than their retention days when an AI integration is enabled', function () use ($wpdb, $seedAILog, $countAILogs, $runDaily) {
            if (!defined('FLUENT_SUPPORT_PRO_DIR_FILE')) {
                FsTest::skip('AI logs are only cleaned with Pro active');
                return;
            }

            // Seed a provider only when the site has none, so a real configuration is never touched.
            $providerMetaId = 0;
            if (!Helper::getAIProviderSettings()) {
                $wpdb->insert($wpdb->prefix . 'fs_meta', [
                    'object_type' => '_fs_ai_provider_settings',
                    'object_id'   => 0,
                    'key'         => '_fs_ai_provider_settings',
                    'value'       => maybe_serialize(['enabled' => 'yes', 'api_key' => 'fstest-key']),
                ]);
                $providerMetaId = (int) $wpdb->insert_id;
            }

            try {
                if (!Helper::openAIIntegrationStatus()) {
                    FsTest::skip('the site has an AI provider configured but disabled');
                    return;
                }

                $seedAILog('2005-01-03 00:00:00');
                $seedAILog('2010-01-03 00:00:00');

                $runDaily();

                FsTest::assertSame(0, $countAILogs('2005-01-03 00:00:00'), 'AI logs older than 7000 days are deleted');
                FsTest::assertSame(1, $countAILogs('2010-01-03 00:00:00'), 'AI logs inside the retention period are kept');
            } finally {
                if ($providerMetaId) {
                    $wpdb->delete($wpdb->prefix . 'fs_meta', ['id' => $providerMetaId]);
                }
            }
        });

        FsTest::case('daily cleanup: deletes notifications older than 15 days with their recipients', function () use ($wpdb, $notifications, $recipients, $runDaily, $daysAgo, $tableExists) {
            $createdNotifications = !$tableExists($notifications);
            $createdRecipients = !$tableExists($recipients);
            NotificationsMigrator::migrate();
            NotificationUsersMigrator::migrate();

            $insertNotification = function ($createdAt, array $userIds) use ($wpdb, $notifications, $recipients) {
                $wpdb->insert($notifications, ['event_type' => 'fstest_seed', 'category' => 'fstest', 'created_at' => $createdAt, 'updated_at' => $createdAt]);
                $id = (int) $wpdb->insert_id;
                foreach ($userIds as $userId) {
                    $wpdb->insert($recipients, ['notification_id' => $id, 'user_id' => $userId, 'created_at' => $createdAt]);
                }

                return $id;
            };

            $countRows = function ($table, $column, $id) use ($wpdb) {
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $id));
            };

            $oldId = $insertNotification($daysAgo(16), [1, 2]);
            $recentId = $insertNotification($daysAgo(14), [1]);

            try {
                $runDaily();

                FsTest::assertSame(0, $countRows($notifications, 'id', $oldId), 'notification older than 15 days is deleted');
                FsTest::assertSame(0, $countRows($recipients, 'notification_id', $oldId), 'its recipient rows are deleted too');
                FsTest::assertSame(1, $countRows($notifications, 'id', $recentId), 'notification newer than 15 days is kept');
                FsTest::assertSame(1, $countRows($recipients, 'notification_id', $recentId), 'its recipient row is kept');
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
    } finally {
        $wpdb->query($wpdb->prepare("DELETE FROM {$activities} WHERE description = %s", $marker));

        if ($createdAITable) {
            $wpdb->query("DROP TABLE IF EXISTS {$aiLogs}");
        } else {
            $wpdb->query($wpdb->prepare("DELETE FROM {$aiLogs} WHERE prompt = %s", $marker));
        }

        foreach ($savedOptions as $key => $value) {
            if ($value === null) {
                Helper::deleteOption($key);
            } else {
                Meta::where('object_type', 'option')->where('key', $key)->update(['value' => $value]);
            }
        }
    }
};
