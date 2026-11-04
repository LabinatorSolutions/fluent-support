<?php

namespace FluentSupport\App\Services\Storage;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

/**
 * One place for every remote storage call: upload, delete, serve, local copy.
 *
 * A driver registered through `fluent_support/storage_drivers` (StorageDriverInterface)
 * is called directly. Anything else goes through the older per-driver hooks, which
 * an older Pro listens to:
 *   fluent_support/attachment_uploaded_as_temp_{driver}
 *   fluent_support/finalize_file_upload_{driver}
 *   fluent_support/delete_remote_attachment_{driver}
 *   fluent_support/external_attachment_url
 * Those hooks must stay; a site can run this free plugin with an older Pro.
 */
class StorageManager
{
    /**
     * Status of a row whose remote file is waiting to be deleted. The row is detached
     * (no ticket, reply or person), so nothing lists it; the delete job removes the
     * remote object and then the row.
     */
    const STATUS_DELETING = 'deleting';
    const PUSH_HOOK = 'fluent_support/storage_push_attachment';
    const DELETE_HOOK = 'fluent_support/storage_process_delete_queue';
    const AS_GROUP = 'fluent-support';

    /**
     * @return StorageDriverInterface[] keyed by driver key
     */
    public static function getDrivers()
    {
        $drivers = [];

        foreach ((array) apply_filters('fluent_support/storage_drivers', []) as $key => $driver) {
            if ($driver instanceof StorageDriverInterface) {
                $drivers[$driver->getKey()] = $driver;
            }
        }

        return $drivers;
    }

    /**
     * @param string $key
     * @return StorageDriverInterface|null
     */
    public static function getDriver($key)
    {
        if (!$key || $key === 'local') {
            return null;
        }

        $drivers = self::getDrivers();

        return isset($drivers[$key]) ? $drivers[$key] : null;
    }

    /**
     * Driver cards for the storage settings page.
     *
     * @return array
     */
    public static function getDriversInfo()
    {
        $hasPro = defined('FLUENTSUPPORTPRO');
        $configured = [
            'dropbox'       => false,
            'google_drive'  => false,
            'cloudflare_r2' => false,
            'amazon_s3'     => false,
        ];

        // An older Pro does not report its drivers, so read their settings here.
        if ($hasPro) {
            $dropbox = Helper::getIntegrationOption('dropbox_settings');
            $configured['dropbox'] = $dropbox && !empty($dropbox['access_token']) && Arr::get($dropbox, 'status') == 'yes';

            $drive = Helper::getIntegrationOption('google_drive_settings');
            $configured['google_drive'] = $drive && !empty($drive['access_token']) && Arr::get($drive, 'status') == 'yes';

            $r2 = Helper::getIntegrationOption('cloudflare_r2_settings');
            $configured['cloudflare_r2'] = $r2 && !empty($r2['secret_access_key']) && Arr::get($r2, 'status') == 'yes';

            $s3 = Helper::getIntegrationOption('amazon_s3_settings');
            $configured['amazon_s3'] = $s3 && !empty($s3['secret_access_key']) && Arr::get($s3, 'status') == 'yes';
        }

        $drivers = [
            'local'         => [
                'title'         => __('Default WordPress Storage', 'fluent-support'),
                'is_disabled'   => false,
                'is_configured' => true,
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/folder.svg',
                'description'   => __('Upload and store the files to your WordPress File System Storage.', 'fluent-support')
            ],
            'dropbox'       => [
                'meta_key'      => 'dropbox_settings',
                'title'         => 'Dropbox',
                'has_config'    => true,
                'is_configured' => $configured['dropbox'],
                'require_pro'   => !$hasPro,
                'upgrade_url'   => Helper::getUpgradeUrl('feature_lock_dropbox'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/dbox.svg',
                'description'   => __('Upload and store the files to your Dropbox Storage.', 'fluent-support')
            ],
            'google_drive'  => [
                'meta_key'      => 'google_drive_settings',
                'title'         => 'Google Drive',
                'has_config'    => true,
                'is_configured' => $configured['google_drive'],
                'require_pro'   => !$hasPro,
                'upgrade_url'   => Helper::getUpgradeUrl('feature_lock_google_drive'),
                'description'   => __('Upload and store the files to your Google Drive Storage.', 'fluent-support'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/drive.svg',
            ],
            'cloudflare_r2' => [
                'meta_key'      => 'cloudflare_r2_settings',
                'title'         => 'Cloudflare R2',
                'has_config'    => true,
                'is_configured' => $configured['cloudflare_r2'],
                'require_pro'   => !$hasPro,
                'upgrade_url'   => Helper::getUpgradeUrl('feature_lock_cloudflare_r2'),
                'description'   => __('Upload and store the files to Cloudflare R2 Storage with zero egress fees.', 'fluent-support'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/cloudflare-r2.svg',
            ],
            'amazon_s3'     => [
                'meta_key'      => 'amazon_s3_settings',
                'title'         => 'Amazon S3',
                'has_config'    => true,
                'is_configured' => $configured['amazon_s3'],
                'require_pro'   => !$hasPro,
                'upgrade_url'   => Helper::getUpgradeUrl('feature_lock_amazon_s3'),
                'description'   => __('Upload and store the files to Amazon S3 cloud storage.', 'fluent-support'),
                'icon'          => FLUENT_SUPPORT_PLUGIN_URL . 'assets/images/icons/amazon-s3.svg',
            ]
        ];

        $drivers = apply_filters('fluent_support/storage_drivers_info', $drivers);

        foreach ($drivers as $key => $info) {
            if ($key === 'local' || !empty($info['require_pro'])) {
                continue;
            }

            $drivers[$key]['health'] = self::getHealth($key);
        }

        return $drivers;
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function isValidDriverKey($key)
    {
        if (!is_string($key) || !preg_match('/^[a-z0-9_]+$/', $key)) {
            return false;
        }

        return array_key_exists($key, self::getDriversInfo()) || (bool) self::getDriver($key);
    }

    /**
     * Uploads run in the background (Action Scheduler) instead of during the request.
     *
     * @return bool
     */
    public static function isAsyncEnabled()
    {
        return Helper::getOption('storage_async_upload') === 'yes'
            && function_exists('as_schedule_single_action');
    }

    /**
     * Called right after a file is stored as a temporary upload.
     *
     * @param Attachment $attachment
     * @param int|null $ticketId
     */
    public static function afterTempUpload(&$attachment, $ticketId)
    {
        $key = Helper::getUploadDriverKey();

        if ($key === 'local') {
            return;
        }

        $driver = self::getDriver($key);

        if (!$driver) {
            $wasLocal = $attachment->driver === 'local' && file_exists((string) $attachment->file_path);
            do_action_ref_array('fluent_support/attachment_uploaded_as_temp_' . $key, [&$attachment, $ticketId]);

            if ($ticketId && $wasLocal) {
                self::recordLegacyResult($key, $attachment);
            }

            return;
        }

        // Without a ticket the driver has no folder yet; finalize() uploads it.
        // In background mode finalize() queues it instead.
        if (!$ticketId || self::isAsyncEnabled()) {
            return;
        }

        self::uploadNow($driver, $attachment, $ticketId);
    }

    /**
     * Called when a ticket or reply is saved with its uploads. Leaves the row
     * on driver = local when the remote upload fails or is queued; the caller then
     * keeps the file in the ticket folder.
     *
     * @param Attachment $attachment
     * @param int $ticketId
     */
    public static function finalize(&$attachment, $ticketId)
    {
        $key = Helper::getUploadDriverKey();

        if ($key === 'local' || $attachment->driver !== 'local') {
            return;
        }

        $driver = self::getDriver($key);

        if (!$driver) {
            $wasLocal = file_exists((string) $attachment->file_path);
            do_action_ref_array('fluent_support/finalize_file_upload_' . $key, [&$attachment, $ticketId]);

            if ($wasLocal) {
                self::recordLegacyResult($key, $attachment);
            }

            return;
        }

        if (self::isAsyncEnabled()) {
            self::queuePush($attachment, $key, 10);
            return;
        }

        // Failed: the file stays local until an admin retries (Retry now, or a reconnect).
        if (!self::uploadNow($driver, $attachment, $ticketId)) {
            self::markFailed($attachment, $key);
        }
    }

    /**
     * Remember that this local row should have gone to $key, for retryFailed().
     */
    private static function markFailed($attachment, $key)
    {
        $settings = (array) $attachment->settings;
        unset($settings['remote_pending'], $settings['remote_attempts']);
        $settings['remote_failed'] = $key;
        $attachment->settings = $settings;
        $attachment->save();
    }

    /**
     * Mark a local row for one background upload.
     */
    private static function queuePush($attachment, $key, $delay)
    {
        $settings = (array) $attachment->settings;
        $settings['remote_pending'] = $key;
        $settings['remote_attempts'] = 0;
        unset($settings['remote_failed']);

        $attachment->settings = $settings;
        $attachment->save();

        self::schedulePush($attachment->id, $delay);
    }

    /**
     * Queue every file that couldn't be uploaded to this driver again, e.g. after
     * the storage was reconnected. Clears the warning; a new failure brings it back.
     *
     * @param string $key
     * @return int Files queued.
     */
    public static function retryFailed($key)
    {
        if (!self::getDriver($key) || !function_exists('as_schedule_single_action')) {
            return 0;
        }

        self::clearFailure($key);

        $rows = Attachment::where('driver', 'local')
            ->whereNotNull('ticket_id')
            ->whereIn('status', ['active', 'inline'])
            ->where('settings', 'LIKE', '%remote_failed%')
            ->limit(500)
            ->get();

        $queued = 0;
        foreach ($rows as $index => $attachment) {
            if (Arr::get((array) $attachment->settings, 'remote_failed') !== $key) {
                continue;
            }

            self::queuePush($attachment, $key, 5 + $index);
            $queued++;
        }

        return $queued;
    }

    private static function uploadNow(StorageDriverInterface $driver, &$attachment, $ticketId)
    {
        $result = $driver->upload($attachment, $ticketId);

        if (is_wp_error($result)) {
            self::recordFailure($driver->getKey(), $result->get_error_message());
            return false;
        }

        self::clearFailure($driver->getKey());
        return true;
    }

    /**
     * An older Pro gives no result, so judge by the row: still local means it failed.
     */
    private static function recordLegacyResult($key, $attachment)
    {
        if ($attachment->driver === 'local') {
            self::recordFailure($key, __('The file could not be uploaded and was kept on this server.', 'fluent-support'));
        } else {
            self::clearFailure($key);
        }
    }

    private static function schedulePush($attachmentId, $delay)
    {
        if (!function_exists('as_schedule_single_action')) {
            return;
        }

        as_schedule_single_action(time() + $delay, self::PUSH_HOOK, [(int) $attachmentId], self::AS_GROUP);
    }

    /**
     * Background upload of one attachment (Action Scheduler).
     *
     * @param int $attachmentId
     */
    public static function pushPending($attachmentId)
    {
        $attachment = Attachment::find((int) $attachmentId);

        if (!$attachment || $attachment->driver !== 'local') {
            return;
        }

        $settings = (array) $attachment->settings;
        $key = Arr::get($settings, 'remote_pending');

        if (!$key) {
            return;
        }

        $attempts = (int) Arr::get($settings, 'remote_attempts', 0) + 1;

        // The ticket or reply hasn't been saved yet.
        if ($attachment->status === 'in-active' && $attempts < 5) {
            $settings['remote_attempts'] = $attempts;
            $attachment->settings = $settings;
            $attachment->save();
            self::schedulePush($attachment->id, 60);
            return;
        }

        $driver = self::getDriver($key);
        $localPath = (string) $attachment->file_path;

        if (!$driver || !$attachment->ticket_id || !file_exists($localPath)) {
            unset($settings['remote_pending'], $settings['remote_attempts']);
            $attachment->settings = $settings;
            $attachment->save();
            return;
        }

        if (self::uploadNow($driver, $attachment, $attachment->ticket_id)) {
            $fresh = Attachment::find($attachment->id);

            // The ticket or reply was deleted while the upload ran: remove the new object.
            if (!$fresh || $fresh->status === self::STATUS_DELETING) {
                $driver->delete($attachment);
                return;
            }

            $settings = (array) $fresh->settings;
            unset($settings['remote_pending'], $settings['remote_attempts'], $settings['remote_failed']);
            $fresh->settings = $settings;
            $fresh->save();

            if (!self::isPathShared($localPath, [$fresh->id])) {
                wp_delete_file($localPath);
            }
            return;
        }

        // No automatic retries (each is a full upload): keep it local until an admin retries.
        self::markFailed($attachment, $key);
    }

    /**
     * Delete the stored files of these rows. Local files go now. A remote file's row
     * is marked `deleting` and detached, and the delete job removes the remote object
     * and the row in the background. A file another row still uses is kept.
     *
     * The caller deletes the other rows. Delete by ticket, reply or id with
     * `status != deleting`, so marked rows stay for the job.
     *
     * @param iterable $attachments
     * @param int|null $ticketId When given, also removes the ticket_{id} upload folder.
     */
    public static function purge($attachments, $ticketId = null)
    {
        $ids = [];
        foreach ($attachments as $attachment) {
            $ids[] = (int) $attachment->id;
        }

        // One query for the whole batch: file_path is checked against every other row.
        $paths = [];
        foreach ($attachments as $attachment) {
            if ($attachment->file_path) {
                $paths[] = (string) $attachment->file_path;
            }
        }
        $paths = array_values(array_unique($paths));
        $shared = [];
        if ($paths) {
            $query = Attachment::whereIn('file_path', $paths);
            if ($ids) {
                $query->whereNotIn('id', $ids);
            }
            $shared = array_flip($query->pluck('file_path')->toArray());
        }

        $seen = [];
        foreach ($attachments as $attachment) {
            $path = (string) $attachment->file_path;

            // Rows in this batch that share a file: handle the file once.
            if (!$path || isset($shared[$path]) || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            if ($attachment->driver && $attachment->driver !== 'local') {
                self::markForDelete($attachment);
            } elseif (file_exists($attachment->file_path)) {
                wp_delete_file($attachment->file_path);
            }
        }

        if ($ticketId) {
            self::removeTicketFolder($ticketId, $ids);
        }
    }

    /**
     * Rows created by a bulk reply, or moved by a ticket merge, can point at the
     * same file or at a file in another ticket's folder.
     *
     * @param string $filePath
     * @param int[] $excludeIds Rows being deleted together.
     * @return bool
     */
    public static function isPathShared($filePath, $excludeIds = [])
    {
        $query = Attachment::where('file_path', $filePath);

        if ($excludeIds) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->exists();
    }

    private static function removeTicketFolder($ticketId, $excludeIds)
    {
        $uploadDir = wp_upload_dir();
        $ticketDir = $uploadDir['basedir'] . '/' . FLUENT_SUPPORT_UPLOAD_DIR . '/ticket_' . (int) $ticketId;

        if (!is_dir($ticketDir)) {
            return;
        }

        // A merged ticket's attachments keep their files in the old ticket's folder.
        global $wpdb;
        $stillUsed = Attachment::where(function ($query) use ($wpdb, $ticketDir) {
            $query->where('file_path', 'LIKE', $wpdb->esc_like($ticketDir . '/') . '%')
                ->orWhere('file_path', 'LIKE', $wpdb->esc_like($ticketDir . DIRECTORY_SEPARATOR) . '%')
                ->orWhere('file_path', 'LIKE', $wpdb->esc_like(str_replace('/', '\\', $ticketDir) . '\\') . '%');
        });
        if ($excludeIds) {
            $stillUsed->whereNotIn('id', $excludeIds);
        }

        if ($stillUsed->exists()) {
            return;
        }

        if (!class_exists('\WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        (new \WP_Filesystem_Direct(false))->rmdir($ticketDir, true);
    }

    /**
     * @param Attachment $attachment A saved row with a remote driver.
     */
    public static function markForDelete($attachment)
    {
        $settings = (array) $attachment->settings;
        $settings['delete_attempts'] = 0;
        // Third-party drivers may build object paths from these; restored for the delete.
        $settings['deleted_from'] = [
            'ticket_id'       => $attachment->ticket_id ? (int) $attachment->ticket_id : null,
            'conversation_id' => $attachment->conversation_id ? (int) $attachment->conversation_id : null,
            'person_id'       => $attachment->person_id ? (int) $attachment->person_id : null,
        ];

        Attachment::where('id', (int) $attachment->id)->update([
            'status'          => self::STATUS_DELETING,
            'ticket_id'       => null,
            'conversation_id' => null,
            'person_id'       => null,
            'settings'        => maybe_serialize($settings),
            'updated_at'      => current_time('mysql'),
        ]);

        $attachment->status = self::STATUS_DELETING;

        self::scheduleDeleteJob(MINUTE_IN_SECONDS);
    }

    private static function scheduleDeleteJob($delay)
    {
        if (!function_exists('as_schedule_single_action') || !function_exists('as_has_scheduled_action')) {
            return;
        }

        // as_has_scheduled_action() counts the running action too, so from inside the
        // job look for a pending one only, or the next batch would never be queued.
        if (doing_action(self::DELETE_HOOK)) {
            $queued = (bool) as_get_scheduled_actions([
                'hook'     => self::DELETE_HOOK,
                'group'    => self::AS_GROUP,
                'status'   => 'pending', // ActionScheduler_Store::STATUS_PENDING
                'per_page' => 1,
            ], 'ids');
        } else {
            $queued = as_has_scheduled_action(self::DELETE_HOOK, [], self::AS_GROUP);
        }

        if (!$queued) {
            as_schedule_single_action(time() + $delay, self::DELETE_HOOK, [], self::AS_GROUP);
        }
    }

    /**
     * Delete remote files of rows marked `deleting`, within a time budget. Runs from
     * Action Scheduler and the hourly cron.
     *
     * @param int $timeBudget Seconds.
     */
    public static function processDeleteQueue($timeBudget = 20)
    {
        $started = time();

        // Only drivers that can delete right now, so rows for a missing driver (Pro
        // inactive or too old) never block the rest. Those rows go after 30 days.
        $keys = Attachment::where('status', self::STATUS_DELETING)->distinct()->pluck('driver')->toArray();
        $handled = array_values(array_filter($keys, function ($key) {
            return self::getDriver($key) || has_action('fluent_support/delete_remote_attachment_' . $key);
        }));

        $unhandled = array_values(array_diff($keys, $handled));
        if ($unhandled) {
            Attachment::where('status', self::STATUS_DELETING)
                ->whereIn('driver', $unhandled)
                ->where('updated_at', '<', gmdate('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS))
                ->delete();
        }

        if (!$handled) {
            return;
        }

        $rows = Attachment::where('status', self::STATUS_DELETING)
            ->whereIn('driver', $handled)
            ->orderBy('id', 'ASC')
            ->limit(50)
            ->get();

        // Objects another (live) row started using since: keep them. One query per batch.
        $paths = array_values(array_unique(array_filter($rows->pluck('file_path')->toArray())));
        $inUse = [];
        if ($paths) {
            $inUse = array_flip(Attachment::whereIn('file_path', $paths)
                ->where('status', '!=', self::STATUS_DELETING)
                ->pluck('file_path')
                ->toArray());
        }

        $finished = true;
        foreach ($rows as $attachment) {
            if ((time() - $started) >= $timeBudget) {
                $finished = false;
                break;
            }

            // The ids the row had, for drivers that build object paths from them.
            $origin = (array) Arr::get((array) $attachment->settings, 'deleted_from', []);
            $attachment->ticket_id = Arr::get($origin, 'ticket_id');
            $attachment->conversation_id = Arr::get($origin, 'conversation_id');

            $result = true;
            if (!isset($inUse[(string) $attachment->file_path])) {
                $driver = self::getDriver($attachment->driver);

                if ($driver) {
                    $result = $driver->delete($attachment);
                } else {
                    do_action('fluent_support/delete_remote_attachment_' . $attachment->driver, $attachment, $attachment->ticket_id);
                }
            }

            if (!is_wp_error($result)) {
                Attachment::where('id', $attachment->id)->delete();
                continue;
            }

            $settings = (array) $attachment->settings;
            $settings['delete_attempts'] = (int) Arr::get($settings, 'delete_attempts', 0) + 1;

            if ($settings['delete_attempts'] >= 5) {
                self::recordFailure($attachment->driver, $result->get_error_message(), 'delete');
                Attachment::where('id', $attachment->id)->delete();
                continue;
            }

            Attachment::where('id', $attachment->id)->update(['settings' => maybe_serialize($settings)]);
        }

        // More waiting: keep draining instead of waiting for the hourly cron.
        if (!$finished || $rows->count() >= 50) {
            self::scheduleDeleteJob(30);
        }
    }

    /**
     * Redirect to, or stream, a remote attachment. Exits.
     *
     * @param Attachment $attachment
     */
    public static function serve($attachment)
    {
        $driver = self::getDriver($attachment->driver);

        if ($driver) {
            $url = $driver->getDownloadUrl($attachment);

            if ($url === null) {
                $driver->stream($attachment);
                $url = new \WP_Error('stream_failed', 'The file could not be read from storage.');
            }
        } else {
            $url = apply_filters('fluent_support/external_attachment_url', $attachment->full_url, $attachment);
        }

        if (is_wp_error($url) || empty($url)) {
            wp_die(esc_html__('File could not be found', 'fluent-support'), esc_html__('Not Found', 'fluent-support'), ['response' => 404]);
        }

        // Not wp_safe_redirect: remote attachments live on other hosts.
        wp_redirect($url, 307);
        exit();
    }

    /**
     * A local file for this attachment, e.g. to attach to an email.
     *
     * @param Attachment $attachment
     * @param int $maxBytes Don't fetch a remote file larger than this (0 = no limit).
     * @param bool $fetch False returns only files already on this server.
     * @return array|null ['path' => string, 'temporary' => bool] Delete temporary files after use.
     */
    public static function getLocalFile($attachment, $maxBytes = 0, $fetch = true)
    {
        if ($attachment->driver === 'local') {
            return $attachment->file_path && file_exists($attachment->file_path)
                ? ['path' => $attachment->file_path, 'temporary' => false]
                : null;
        }

        $tempPath = Arr::get((array) $attachment->settings, 'local_temp_path');
        if ($tempPath && file_exists($tempPath)) {
            return ['path' => $tempPath, 'temporary' => false];
        }

        if (!$fetch) {
            return null;
        }

        $driver = self::getDriver($attachment->driver);

        if ($driver) {
            $path = $driver->getLocalCopy($attachment, $maxBytes);
        } else {
            $path = apply_filters('fluent_support/attachment_local_copy', null, $attachment, $maxBytes);
        }

        if (!$path || is_wp_error($path) || !file_exists($path)) {
            return null;
        }

        if ($maxBytes && filesize($path) > $maxBytes) {
            self::deleteTempFile($path);
            return null;
        }

        return ['path' => $path, 'temporary' => true];
    }

    /**
     * Delete a temporary copy and the folder drivers create for it.
     *
     * @param string $path
     */
    public static function deleteTempFile($path)
    {
        if ($path && file_exists($path)) {
            wp_delete_file($path);
        }

        $dir = dirname((string) $path);
        if (strpos(basename($dir), 'fluent-support-') === 0 && is_dir($dir)) {
            @rmdir($dir);
        }
    }

    /**
     * @param string $key
     * @param string $message
     * @param string $type 'upload' (shown in the admin notice) or 'delete'.
     */
    public static function recordFailure($key, $message, $type = 'upload')
    {
        $option = $type === 'delete' ? '_storage_delete_failures' : '_storage_failures';
        $failures = (array) Helper::getOption($option, []);
        $current = Arr::get($failures, $key, []);

        $failures[$key] = [
            'count'      => (int) Arr::get($current, 'count', 0) + 1,
            'last_error' => sanitize_text_field((string) $message),
            'at'         => time(),
        ];

        Helper::updateOption($option, $failures);
    }

    public static function clearFailure($key)
    {
        $failures = (array) Helper::getOption('_storage_failures', []);

        if (!empty($failures[$key])) {
            unset($failures[$key]);
            Helper::updateOption('_storage_failures', $failures);
        }
    }

    /**
     * @param string $key
     * @return array ['last_error' => string, 'failed_uploads' => int, 'failed_at' => int|null]
     */
    public static function getHealth($key)
    {
        $failure = Arr::get((array) Helper::getOption('_storage_failures', []), $key, []);

        $deleteFailure = Arr::get((array) Helper::getOption('_storage_delete_failures', []), $key, []);

        $health = [
            'last_error'        => (string) Arr::get($failure, 'last_error', ''),
            'failed_uploads'    => (int) Arr::get($failure, 'count', 0),
            'failed_at'         => Arr::get($failure, 'at'),
            'failed_deletes'    => (int) Arr::get($deleteFailure, 'count', 0),
            'last_delete_error' => (string) Arr::get($deleteFailure, 'last_error', ''),
        ];

        $driver = self::getDriver($key);
        if ($driver) {
            $status = (array) $driver->getStatus();
            if (!$health['last_error'] && !empty($status['last_error'])) {
                $health['last_error'] = (string) $status['last_error'];
            }
        }

        return $health;
    }
}
