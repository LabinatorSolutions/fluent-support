<?php

namespace FluentSupport\App\Modules\MCP\Support;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Includes\UploadService;
use FluentSupport\App\Services\Storage\StorageManager;

/**
 * Files an agent sends through MCP as base64. They become in-active attachments
 * on the ticket, the same state the ticket view's uploader leaves them in, and
 * ResponseService activates them when the reply or note is saved with their hashes.
 *
 * The site's upload settings apply: max files, max size and accepted file types.
 * Every file is checked before any is written, so one bad file adds nothing.
 */
class AttachmentStager
{
    /** Upper bound even when the site allows unlimited files per upload. */
    const MAX_FILES = 10;

    /**
     * @param mixed  $files  List of ['filename' => string, 'content_base64' => string].
     * @param Ticket $ticket
     * @param object $person The agent the attachments belong to.
     * @return string[]|\WP_Error File hashes to pass as the response's 'attachments'.
     */
    public static function stage($files, Ticket $ticket, $person)
    {
        if ($files === null || $files === []) {
            return [];
        }

        if (!is_array($files) || array_values($files) !== $files) {
            return MCPHelper::error('invalid_param', 'attachments must be a list of files', ['fields' => ['attachments']]);
        }

        $settings = (new Settings())->globalBusinessSettings();
        $maxMb    = floatval($settings['max_file_size'] ?? 0);
        $limit    = self::maxFiles();

        if (count($files) > $limit) {
            return MCPHelper::error(
                'invalid_param',
                sprintf('You can attach at most %d files.', $limit),
                ['fields' => ['attachments']]
            );
        }

        $accepted = self::acceptedMimes();

        $checked = [];
        foreach ($files as $index => $file) {
            $field = 'attachments[' . $index . ']';
            $name  = is_array($file) ? sanitize_file_name((string) ($file['filename'] ?? '')) : '';
            $data  = is_array($file) ? preg_replace('/\s+/', '', (string) ($file['content_base64'] ?? '')) : '';
            $raw   = $data !== '' ? base64_decode($data, true) : false;

            if ($name === '' || $raw === false || $raw === '') {
                return MCPHelper::error(
                    'invalid_param',
                    sprintf('%s needs a filename and base64 content_base64', $field),
                    ['fields' => [$field]]
                );
            }

            if ($maxMb > 0 && strlen($raw) > $maxMb * 1024 * 1024) {
                return MCPHelper::error(
                    'file_too_large',
                    sprintf('%1$s is larger than the %2$sMB limit.', $name, $maxMb),
                    ['fields' => [$field]]
                );
            }

            $type = wp_check_filetype($name, FileSystem::ticketMimeTypes());
            if (!$type['type'] || !in_array($type['type'], $accepted, true)) {
                return MCPHelper::error(
                    'file_type_not_allowed',
                    sprintf('%1$s is not an allowed file type. Allowed extensions: %2$s', $name, implode(', ', self::allowedExtensions())),
                    ['fields' => [$field]]
                );
            }

            $checked[] = [$name, $raw];
        }

        UploadService::ensureProtectedUploadDir();

        $written = [];
        $hashes  = [];
        foreach ($checked as $index => [$name, $raw]) {
            $put = (new FileSystem())->_setSubDir('temp_files')->_putAsContent($name, $raw);

            if (!is_array($put) || !empty($put['error']) || empty($put['file'])) {
                self::discard($written);
                return MCPHelper::error('upload_failed', 'Could not store the attachment. Try again.', ['retryable' => true]);
            }

            // The extension passed above; the content must match it too. Core checks the
            // result against get_allowed_mime_types(), so the plugin's types apply here too.
            add_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
            $real = wp_check_filetype_and_ext($put['file'], $name);
            remove_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
            if (empty($real['type']) || !in_array($real['type'], $accepted, true)) {
                wp_delete_file($put['file']);
                self::discard($written);
                return MCPHelper::error(
                    'file_type_not_allowed',
                    sprintf('The content of %s does not match its file type.', $name),
                    ['fields' => ['attachments[' . $index . ']']]
                );
            }

            $attachment = Attachment::create([
                'ticket_id' => (int) $ticket->id,
                'person_id' => (int) $person->id,
                'file_type' => $real['type'],
                'file_path' => $put['file'],
                'full_url'  => esc_url($put['url']),
                'title'     => $name,
                'file_size' => (int) @filesize($put['file']),
                'driver'    => 'local',
                'status'    => 'in-active',
                'settings'  => [
                    'local_temp_path' => $put['file'],
                ],
            ]);

            $written[] = $attachment;
            $hashes[]  = $attachment->file_hash;

            // The same hooks the ticket view's uploader fires, so cloud drivers take over.
            do_action('fluent_support/attachment_uploaded_as_temp', $attachment, $ticket->id);
            StorageManager::afterTempUpload($attachment, $ticket->id);
        }

        return $hashes;
    }

    /** Files allowed per reply or note: the site's setting, capped at MAX_FILES. */
    public static function maxFiles()
    {
        $maxCount = intval((new Settings())->globalBusinessSettings()['max_file_upload'] ?? 0);

        return $maxCount > 0 ? min($maxCount, self::MAX_FILES) : self::MAX_FILES;
    }

    /** MIME types a file may have: the site's accepted types, else WordPress's. */
    public static function acceptedMimes()
    {
        $accepted = Helper::ticketAcceptedFileMiles();

        return $accepted ?: array_values(FileSystem::ticketMimeTypes());
    }

    /**
     * File extensions stage() accepts, worked out from the same rule: an extension
     * WordPress allows for this user whose type the site accepts.
     *
     * @return string[]
     */
    public static function allowedExtensions()
    {
        $accepted   = self::acceptedMimes();
        $extensions = [];

        foreach (FileSystem::ticketMimeTypes() as $pattern => $mime) {
            if (in_array($mime, $accepted, true)) {
                foreach (explode('|', $pattern) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        $extensions = array_values(array_unique($extensions));
        sort($extensions);

        return $extensions;
    }

    /**
     * A fingerprint of the files for the duplicate-reply check.
     *
     * @param mixed $files
     * @return string
     */
    public static function fingerprint($files)
    {
        if (!is_array($files) || !$files) {
            return '';
        }

        return md5(wp_json_encode(array_map(function ($file) {
            return is_array($file)
                ? [(string) ($file['filename'] ?? ''), md5((string) ($file['content_base64'] ?? ''))]
                : null;
        }, $files)));
    }

    /**
     * Removes files staged earlier in a failed batch, wherever they are stored: a cloud
     * driver may already have moved one off the server. Rows are read again because a
     * driver hook gets the attachment by reference and may have replaced it.
     *
     * @param Attachment[] $attachments
     */
    private static function discard(array $attachments)
    {
        $ids = array_map(function ($attachment) {
            return (int) $attachment->id;
        }, $attachments);

        if (!$ids) {
            return;
        }

        $stored = Attachment::whereIn('id', $ids)->get();

        Attachment::purgeAttachments($stored);
        // Rows of remote files stay marked `deleting` for the delete job.
        Attachment::whereIn('id', $ids)
            ->where('status', '!=', StorageManager::STATUS_DELETING)
            ->delete();
    }
}
