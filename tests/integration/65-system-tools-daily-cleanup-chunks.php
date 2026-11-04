<?php
/**
 * Daily log cleanup through DataCleanup: notification backlogs past one 10,000-row chunk, notifications
 * without recipients, and a missing AI log table while an AI integration is enabled.
 *
 * Activity and AI retention are set to 7000 days so real logs are never deleted. The file skips when the site
 * has its own notifications older than 14 days, since the daily run deletes those past 15. Tables and settings
 * this file creates or changes are restored in finally.
 */

use FluentSupport\App\Hooks\Handlers\CleanupHandler;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Database\Migrations\AIActivityLogsMigrator;
use FluentSupport\Database\Migrations\NotificationsMigrator;
use FluentSupport\Database\Migrations\NotificationUsersMigrator;

return function () {
    global $wpdb;

    $aiLogs = $wpdb->prefix . AIActivityLogsMigrator::$tableName;
    $notifications = $wpdb->prefix . NotificationsMigrator::$tableName;
    $recipients = $wpdb->prefix . NotificationUsersMigrator::$tableName;

    $runDaily = function () {
        (new CleanupHandler())->initDailyTasks();
    };

    $daysAgo = function ($days) {
        return gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
    };

    $tableExists = function ($table) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    };

    // A day of margin, so a real notification can't age past the cron's 15-day cutoff while the test seeds.
    if ($tableExists($notifications) && $tableExists($recipients)) {
        $realOld = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$notifications} WHERE created_at < %s", $daysAgo(14)));
        if ($realOld) {
            FsTest::case('daily cleanup chunks', function () use ($realOld) {
                FsTest::skip("{$realOld} notifications on this site are older than 14 days and could be deleted");
            });
            return;
        }
    }

    $insertNotification = function ($createdAt, $recipientCount) use ($wpdb, $notifications, $recipients) {
        // insert_id keeps the last successful insert's id, so a failed insert must stop here.
        if (!$wpdb->insert($notifications, ['event_type' => 'fstest_seed', 'category' => 'fstest', 'created_at' => $createdAt, 'updated_at' => $createdAt])) {
            throw new RuntimeException('Could not seed a notification: ' . $wpdb->last_error);
        }
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

    $countRows = function ($table, $column, $id) use ($wpdb) {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $id));
    };

    // Every daily run also cleans activity and AI logs, so push both retentions out of reach of real rows.
    $savedOptions = [];
    foreach (['_activity_settings', '_ai_activity_settings'] as $key) {
        $row = Meta::where('object_type', 'option')->where('key', $key)->first();
        $savedOptions[$key] = $row ? $row->value : null;
        Helper::updateOption($key, ['delete_days' => 7000]);
    }

    $createdNotifications = !$tableExists($notifications);
    $createdRecipients = !$tableExists($recipients);
    NotificationsMigrator::migrate();
    NotificationUsersMigrator::migrate();
    $seededIds = [];

    try {
        FsTest::case('daily cleanup chunks: one run clears a notification with more recipients than one chunk', function () use ($insertNotification, $countRows, $notifications, $recipients, $runDaily, $daysAgo, &$seededIds) {
            $oldId = $insertNotification($daysAgo(16), 10005);
            $recentId = $insertNotification($daysAgo(14), 1);
            array_push($seededIds, $oldId, $recentId);

            $runDaily();

            FsTest::assertSame(0, $countRows($recipients, 'notification_id', $oldId), 'all 10,005 recipients are gone after one daily run');
            FsTest::assertSame(0, $countRows($notifications, 'id', $oldId), 'the old notification is gone too');
            FsTest::assertSame(1, $countRows($notifications, 'id', $recentId), 'the recent notification is kept');
            FsTest::assertSame(1, $countRows($recipients, 'notification_id', $recentId), 'its recipient is kept');
        });

        FsTest::case('daily cleanup chunks: deletes old notifications that have no recipients', function () use ($insertNotification, $countRows, $notifications, $runDaily, $daysAgo, &$seededIds) {
            $oldId = $insertNotification($daysAgo(16), 0);
            $seededIds[] = $oldId;

            $runDaily();

            FsTest::assertSame(0, $countRows($notifications, 'id', $oldId), 'an old notification without recipients is deleted');
        });

        FsTest::case('daily cleanup chunks: a missing AI log table does not stop the run while an AI integration is enabled', function () use ($wpdb, $aiLogs, $tableExists, $insertNotification, $countRows, $notifications, $runDaily, $daysAgo, &$seededIds) {
            if ($tableExists($aiLogs)) {
                FsTest::skip('the AI log table exists on this site');
                return;
            }

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

            // Notifications are cleaned after AI logs, so this row shows the run got past the AI step.
            $oldId = $insertNotification($daysAgo(16), 1);
            $seededIds[] = $oldId;

            try {
                FsTest::assertSame(true, Helper::openAIIntegrationStatus(), 'an AI integration reads as enabled');

                $runDaily();

                FsTest::assertSame(0, $countRows($notifications, 'id', $oldId), 'notification cleanup still ran after the AI step');
            } finally {
                if ($providerMetaId) {
                    $wpdb->delete($wpdb->prefix . 'fs_meta', ['id' => $providerMetaId]);
                }
            }
        });
    } finally {
        if ($createdRecipients) {
            $wpdb->query("DROP TABLE IF EXISTS {$recipients}");
        } elseif ($seededIds) {
            $wpdb->query("DELETE FROM {$recipients} WHERE notification_id IN (" . implode(',', array_map('intval', $seededIds)) . ')');
        }

        if ($createdNotifications) {
            $wpdb->query("DROP TABLE IF EXISTS {$notifications}");
        } elseif ($seededIds) {
            $wpdb->query("DELETE FROM {$notifications} WHERE id IN (" . implode(',', array_map('intval', $seededIds)) . ')');
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
