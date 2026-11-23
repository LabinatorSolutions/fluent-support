<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Integrations\UsageStats;
use FluentSupport\App\Modules\SystemTools\DataCleanup;
use FluentSupport\App\Services\Storage\StorageManager;

class CleanupHandler
{
    public function initHourlyTasks()
    {
        $this->cleanLiveActivities();
        $this->maybeDeleteOldTempFiles();
        $this->cleanAbandonedTempAttachments();
        $this->cleanExpiredAuthChallenges();
        StorageManager::processDeleteQueue();
    }

    public function initDailyTasks()
    {
        $this->cleanActivityLogs();

        $this->cleanAIActivityLogs();

        $this->cleanInternalNotifications();
    }

    protected function cleanLiveActivities()
    {
        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - 86400);

        Meta::where('key', '_live_activity')
            ->where('object_type', 'ticket_meta')
            ->where('updated_at', '<', $oldDateTime)
            ->delete();
    }

    protected function cleanExpiredAuthChallenges()
    {
        // Codes live for minutes (see the CODE_TTL_SECONDS constants), so an hour
        // never races a still-valid code.
        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - HOUR_IN_SECONDS);

        // Older versions wrote these rows without created_at. They are long expired.
        Meta::whereIn('object_type', ['fs_login_hashes', 'fs_2fa'])
            ->where(function ($query) use ($oldDateTime) {
                $query->where('created_at', '<', $oldDateTime)
                    ->orWhereNull('created_at');
            })
            ->delete();
    }

    protected function cleanActivityLogs()
    {
        $settings = Helper::getOption('_activity_settings', []);

        if (empty($settings['delete_days'])) {
            $settings['delete_days'] = 14;
        }

        $this->deleteLogsOlderThan('activity_logs', $settings['delete_days']);
    }

    protected function cleanAIActivityLogs()
    {
        if (!defined('FLUENT_SUPPORT_PRO_DIR_FILE') || !Helper::openAIIntegrationStatus()) {
            return;
        }

        $settings = Helper::getOption('_ai_activity_settings', []);

        $defaultDays = 14;

        if (!empty($settings['delete_days'])) {
            $defaultDays = (int)$settings['delete_days'];
        }

        $this->deleteLogsOlderThan('ai_activity_logs', $defaultDays);
    }

    protected function cleanInternalNotifications()
    {
        $this->deleteLogsOlderThan('internal_notifications', 15);
    }

    /**
     * DataCleanup deletes one chunk per call; the cron keeps going until the type is clean
     * or the 20s budget is spent (the next run continues).
     *
     * @param string $type
     * @param int    $days
     */
    protected function deleteLogsOlderThan($type, $days)
    {
        $cleanup = new DataCleanup();

        $started = time();
        do {
            $result = $cleanup->deleteOlderThan([$type], $days);
        } while ($result['has_more'] && (time() - $started) < 20);
    }

    public function maybeDeleteAttachmentsOnClose($ticket)
    {
        $settings = (new Settings())->globalBusinessSettings();
        if ($settings['del_files_on_close'] == 'yes') {
            $this->deleteTicketAttachments($ticket);
        }
    }

    public function deleteTicketAttachments($ticket)
    {
        $attachments = Attachment::where('ticket_id', $ticket->id)->get();

        if (!$attachments->isEmpty()) {
            Attachment::purgeAttachments($attachments, $ticket->id);
            Attachment::where('ticket_id', $ticket->id)->delete();
        }
    }

    public function maybeMaintanceTask()
    {
        (new UsageStats())->maybeSend();

        $this->maybeRemoveOldScheuledActionLogs();
    }

    private function maybeDeleteOldTempFiles()
    {
        $dir = FileSystem::getDir();

        foreach (glob($dir . '/temp_files/*') as $filename) {
            if (time() - filectime($filename) >= 7200) { // 2 hours
                wp_delete_file($filename);
            }
        }
    }

    /**
     * Uploads stay 'in-active' until submitted with a ticket or reply. Abandoned ones
     * count against the upload quota, so drop them after the same 2 hours that
     * maybeDeleteOldTempFiles() uses for the files.
     *
     * NULL created_at rows are left alone: bulk reply writes them without a timestamp
     * and activates them within the same request.
     */
    protected function cleanAbandonedTempAttachments()
    {
        $oldDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - 7200); // 2 hours

        // Each row hits the filesystem or remote storage, so drain a backlog over several runs.
        $attachments = Attachment::where('status', 'in-active')
            ->where('created_at', '<', $oldDateTime)
            ->limit(500)
            ->get();

        if ($attachments->isEmpty()) {
            return;
        }

        Attachment::purgeAttachments($attachments);

        // Rows of remote files stay marked `deleting` for the delete job.
        Attachment::whereIn('id', $attachments->pluck('id')->toArray())
            ->where('status', '!=', StorageManager::STATUS_DELETING)
            ->delete();
    }


    function maybeRemoveOldScheuledActionLogs($group_slug = 'fluent-support', $days_old = 7)
    {
        global $wpdb;

        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        $group_id = $wpdb->get_var($wpdb->prepare(
            "SELECT group_id FROM {$wpdb->prefix}actionscheduler_groups WHERE slug = %s",
            $group_slug
        ));

        if (!$group_id) {
            return false;
        }

        $actionsTable = $wpdb->prefix . 'actionscheduler_actions';
        $logsTable = $wpdb->prefix . 'actionscheduler_logs';
        $claimsTable = $wpdb->prefix . 'actionscheduler_claims';

        $deleted = 0;
        $started = time();

        // A batch at a time, and only our own actions, logs and claims.
        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT action_id, claim_id FROM {$actionsTable}
                WHERE group_id = %d AND status IN ('complete', 'failed') AND scheduled_date_gmt < %s
                ORDER BY action_id ASC LIMIT 1000",
                $group_id, $cutoff_date
            ));
            if (!$rows) {
                break;
            }

            $actionIds = implode(',', array_map('intval', wp_list_pluck($rows, 'action_id')));
            $claimIds = array_filter(array_map('intval', wp_list_pluck($rows, 'claim_id')));

            $wpdb->query("DELETE FROM {$logsTable} WHERE action_id IN ({$actionIds})"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer ids
            $deleted += (int) $wpdb->query("DELETE FROM {$actionsTable} WHERE action_id IN ({$actionIds})"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer ids

            if ($claimIds) {
                // Claims those actions held, once no action uses them.
                $claimList = implode(',', array_unique($claimIds));
                $wpdb->query(
                    "DELETE c FROM {$claimsTable} c
                    LEFT JOIN {$actionsTable} a ON a.claim_id = c.claim_id
                    WHERE c.claim_id IN ({$claimList}) AND a.action_id IS NULL"
                ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer ids
            }
        } while (count($rows) === 1000 && (time() - $started) < 20);

        return $deleted;
    }
}
