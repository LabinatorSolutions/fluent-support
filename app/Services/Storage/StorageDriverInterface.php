<?php

namespace FluentSupport\App\Services\Storage;

/**
 * A remote file storage driver (Amazon S3, Cloudflare R2, Dropbox, ...).
 *
 * Drivers are registered with the `fluent_support/storage_drivers` filter, keyed by
 * the value they write to `fs_attachments.driver`. A driver that is not registered
 * still works through the older per-driver hooks (see StorageManager), so an older
 * Pro keeps working with this version of the free plugin.
 */
interface StorageDriverInterface
{
    /**
     * The driver key, as stored in fs_attachments.driver (e.g. 'amazon_s3').
     *
     * @return string
     */
    public function getKey();

    /**
     * @return bool
     */
    public function isConfigured();

    /**
     * Copy the attachment's local file to the remote storage. On success the driver
     * updates and saves the row (driver, file_path, full_url, settings).
     *
     * @param \FluentSupport\App\Models\Attachment $attachment A row with driver = local.
     * @param int $ticketId
     * @return true|\WP_Error
     */
    public function upload($attachment, $ticketId);

    /**
     * Delete the remote object. Gets an unsaved model built from the row as it was,
     * because the row itself is usually deleted by then.
     *
     * @param \FluentSupport\App\Models\Attachment $attachment
     * @return true|\WP_Error
     */
    public function delete($attachment);

    /**
     * URL that returns the file bytes, valid for a short time. Return null when the
     * driver can't give a URL; StorageManager then calls stream().
     *
     * @param \FluentSupport\App\Models\Attachment $attachment
     * @return string|null|\WP_Error
     */
    public function getDownloadUrl($attachment);

    /**
     * Send the file to the browser and exit. Only called when getDownloadUrl() returns null.
     *
     * @param \FluentSupport\App\Models\Attachment $attachment
     * @return void|\WP_Error
     */
    public function stream($attachment);

    /**
     * Download the remote file to a new temporary local file (for email attachments).
     * The caller deletes the file when done.
     *
     * @param \FluentSupport\App\Models\Attachment $attachment
     * @param int $maxBytes Stop and fail when the file is larger (0 = no limit).
     * @return string|\WP_Error Absolute path.
     */
    public function getLocalCopy($attachment, $maxBytes = 0);

    /**
     * Health shown on the storage settings page and in the admin notice.
     *
     * @return array ['last_error' => string, 'failed_uploads' => int]
     */
    public function getStatus();
}
