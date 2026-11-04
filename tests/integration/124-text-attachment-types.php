<?php
/**
 * Text and Markdown attachments (the "Text/Markdown" accepted file type).
 *
 * - WordPress core has no .md entry and refuses the file on every write path.
 *   FileSystem adds it as text/plain (upload_mimes) only around the plugin's own
 *   writes and the upload endpoint's type validation, because
 *   wp_check_filetype_and_ext() reads a Markdown file's real type as text/plain and
 *   refuses any other mapping. The rest of the site keeps core's list.
 * - An emailed .md arrives with the type the mail client declared (text/markdown),
 *   usually with a charset. The email download matches the type without parameters.
 * - The group is opt-in like every other: a site that has not ticked it still
 *   refuses Markdown by email.
 *
 * The attachment download is faked through pre_http_request.
 */

use FluentSupport\App\Http\Controllers\UploaderController;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;
use FluentSupport\App\Modules\MCP\Support\AttachmentStager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Includes\UploadService;
use FluentSupport\Framework\Validator\ValidationException;

return function () {
    $savedSettings = Meta::where('object_type', 'option')->where('key', 'global_business_settings')->value('value');
    $files = [];

    $setAcceptedTypes = function (array $types) {
        $settings = Helper::getOption('global_business_settings', []);
        $settings['accepted_file_types'] = $types;
        Helper::updateOption('global_business_settings', $settings);
        (new Settings())->globalBusinessSettings(false);
    };

    // Runs the email attachment download against a faked response with $contentType.
    $emailAttachment = function ($filename, $contentType, $body) use (&$files) {
        $fake = function ($pre, $args, $url) use ($contentType, $body) {
            return [
                'headers'  => ['content-type' => $contentType],
                'body'     => $body,
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        };
        add_filter('pre_http_request', $fake, 10, 3);
        try {
            $upload = UploadService::handleEmailAttachments([
                'url'         => 'https://pipe.invalid/att/' . rawurlencode($filename),
                'filename'    => $filename,
                'contentType' => $contentType,
            ], null, Helper::ticketAcceptedFileMiles());
        } finally {
            remove_filter('pre_http_request', $fake, 10);
        }
        if ($upload && !empty($upload['file'])) {
            $files[] = $upload['file'];
        }
        return $upload;
    };

    try {
        FsTest::case('text attachments: a real .md file passes only inside the plugin\'s writes', function () use (&$files) {
            $path = wp_tempnam('fs-markdown');
            $files[] = $path;
            file_put_contents($path, "# Report\n\n- one\n- two\n");
            $name = FsTest::uniq('report') . '.md';

            if (!function_exists('wp_check_filetype_and_ext')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $outside = wp_check_filetype_and_ext($path, $name);
            FsTest::assertSame(false, $outside['type'], 'core list untouched: refused outside the plugin');
            FsTest::assert(!in_array('md|markdown', array_keys(get_allowed_mime_types()), true), 'Media Library list has no md');

            add_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
            try {
                $inside = wp_check_filetype_and_ext($path, $name);
            } finally {
                remove_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
            }
            FsTest::assertSame('md', $inside['ext'], 'extension kept');
            FsTest::assertSame('text/plain', $inside['type'], 'stored as text/plain');
        });

        FsTest::case('text attachments: an emailed .md with a charset is stored when Text/Markdown is enabled', function () use ($setAcceptedTypes, $emailAttachment) {
            $setAcceptedTypes(['images', 'text']);
            $name = FsTest::uniq('report') . '.md';

            $upload = $emailAttachment($name, 'text/markdown; charset=utf-8', "# Report\n");

            FsTest::assert(is_array($upload) && !empty($upload['file']) && file_exists($upload['file']), 'attachment stored');
            FsTest::assertSame("# Report\n", $upload ? file_get_contents($upload['file']) : null, 'content kept');
            FsTest::assertSame('text/plain', $upload ? $upload['type'] : null, 'stored type');
            FsTest::assertSame(false, has_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']), 'filter removed after the write');
        });

        FsTest::case('text attachments: an emailed .txt is stored when Text/Markdown is enabled', function () use ($setAcceptedTypes, $emailAttachment) {
            $setAcceptedTypes(['images', 'text']);

            $upload = $emailAttachment(FsTest::uniq('notes') . '.txt', 'text/plain; charset=UTF-8', 'plain notes');

            FsTest::assert(is_array($upload) && !empty($upload['file']), 'attachment stored');
        });

        FsTest::case('text attachments: an emailed .json is stored when JSON is enabled', function () use ($setAcceptedTypes, $emailAttachment) {
            $setAcceptedTypes(['json']);

            $upload = $emailAttachment(FsTest::uniq('data') . '.json', 'application/json', '{"a":1}');

            FsTest::assert(is_array($upload) && !empty($upload['file']), 'attachment stored');
            FsTest::assert(!in_array('json', array_keys(get_allowed_mime_types()), true), 'Media Library list has no json');
        });

        FsTest::case('text attachments: MCP accepts .md and .json and lists both extensions', function () use ($setAcceptedTypes) {
            $setAcceptedTypes(['text', 'json']);
            $ticket = FsFactory::ticket();

            try {
                $extensions = AttachmentStager::allowedExtensions();
                FsTest::assert(in_array('md', $extensions, true) && in_array('json', $extensions, true), 'md and json listed, got: ' . implode(',', $extensions));

                $staged = AttachmentStager::stage([
                    ['filename' => 'notes.md', 'content_base64' => base64_encode("# Notes\n")],
                    ['filename' => 'data.json', 'content_base64' => base64_encode('{"a":1}')],
                ], $ticket, MCPHelper::resolveAgent());
                FsTest::assert(is_array($staged) && count($staged) === 2, 'both staged, got: ' . (is_wp_error($staged) ? $staged->get_error_message() : wp_json_encode($staged)));
                FsTest::assertSame(false, has_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']), 'filter removed after staging');
            } finally {
                foreach (Attachment::where('ticket_id', $ticket->id)->get() as $attachment) {
                    if ($attachment->file_path && file_exists($attachment->file_path)) {
                        wp_delete_file($attachment->file_path);
                    }
                    $attachment->delete();
                }
            }
        });

        FsTest::case('text attachments: the upload endpoint accepts .md and .json and still refuses other types', function () use ($setAcceptedTypes, &$files) {
            $setAcceptedTypes(['text', 'json']);
            FsTest::assert((bool) Helper::getCurrentAgent(), 'runs as an agent');

            $app = \FluentSupport\App\App::getInstance();
            $hook = $app->config->get('app.hook_prefix') . 'handle_exception';
            $rethrow = function ($e) {
                throw $e;
            };
            // File::isValid() accepts a non-HTTP upload only in a test env. The write after
            // validation still needs a real HTTP upload (wp_handle_upload), so this checks
            // validation only; the write's own filter is covered by the email cases above.
            $savedEnv = isset($_ENV['WPF_ENV']) ? $_ENV['WPF_ENV'] : null;
            $_ENV['WPF_ENV'] = 'testing';
            add_action($hook, $rethrow, 1);

            // Returns 'passed' when the file got past validation, or the validation errors.
            $upload = function ($name, $body) use ($app, &$files) {
                if ($app->bound('wprestrequest')) {
                    $app->forgetInstance('wprestrequest');
                }
                $tmp = wp_tempnam('fs-text-upload');
                file_put_contents($tmp, $body);
                $files[] = $tmp;
                $_FILES = ['file' => [
                    'name'     => $name,
                    'type'     => 'application/octet-stream',
                    'tmp_name' => $tmp,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => strlen($body),
                ]];

                // The failed write leaves FileSystem's upload_dir override registered (a real
                // request would end there). Restore the hooks so later suites keep core's dir.
                global $wp_filter;
                $hooks = [];
                foreach (['upload_dir', 'wp_handle_upload_prefilter', 'wp_handle_upload'] as $tag) {
                    $hooks[$tag] = isset($wp_filter[$tag]) ? clone $wp_filter[$tag] : null;
                }

                try {
                    $request = new \FluentSupport\Framework\Http\Request\Request($app, [], []);
                    (new UploaderController())->uploadTicketFiles($request);
                } catch (ValidationException $e) {
                    return wp_json_encode($e->errors());
                } finally {
                    $_FILES = [];
                    foreach ($hooks as $tag => $saved) {
                        if ($saved) {
                            $wp_filter[$tag] = $saved;
                        } else {
                            unset($wp_filter[$tag]);
                        }
                    }
                }

                return 'passed';
            };

            try {
                FsTest::assertSame('passed', $upload(FsTest::uniq('notes') . '.md', "# Notes\n"), '.md passes validation');
                FsTest::assertSame('passed', $upload(FsTest::uniq('data') . '.json', '{"a":1}'), '.json passes validation');
                FsTest::assertSame(false, has_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']), 'filter removed after a passing upload');

                $refused = $upload(FsTest::uniq('page') . '.html', '<p>hi</p>');
                FsTest::assert(strpos($refused, 'mimetypes') !== false, 'a type outside the setting is refused, got: ' . $refused);
                FsTest::assertSame(false, has_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']), 'filter removed after a refused upload');

                // A .md reads as text/plain, which belongs to Text/Markdown only, so CSV alone must not admit it.
                $setAcceptedTypes(['images', 'csv']);
                FsTest::assert(strpos($upload(FsTest::uniq('notes') . '.md', "# Notes\n"), 'mimetypes') !== false, '.md is refused when only CSV is accepted');
                FsTest::assertSame('passed', $upload(FsTest::uniq('rows') . '.csv', "a,b\n1,2\n"), '.csv still passes');
            } finally {
                remove_action($hook, $rethrow, 1);
                if ($savedEnv === null) {
                    unset($_ENV['WPF_ENV']);
                } else {
                    $_ENV['WPF_ENV'] = $savedEnv;
                }
            }
        });

        FsTest::case('text attachments: Markdown by email is refused when the group is not enabled', function () use ($setAcceptedTypes, $emailAttachment) {
            $setAcceptedTypes(['images', 'csv', 'documents', 'zip', 'json']);

            $upload = $emailAttachment(FsTest::uniq('report') . '.md', 'text/markdown', "# Report\n");

            FsTest::assertSame(null, $upload, 'refused');
        });

        // Mail clients often declare a CSV as text/plain. The email path judges such a file
        // by its extension, so CSV alone takes the .csv and still refuses a .md or .txt.
        FsTest::case('text attachments: a CSV emailed as text/plain is stored when only CSV is enabled', function () use ($setAcceptedTypes, $emailAttachment) {
            $setAcceptedTypes(['images', 'csv']);

            $csv = $emailAttachment(FsTest::uniq('rows') . '.csv', 'text/plain; charset=UTF-8', "a,b\n1,2\n");
            FsTest::assert(is_array($csv) && !empty($csv['file']), 'the .csv is stored');

            FsTest::assertSame(null, $emailAttachment(FsTest::uniq('notes') . '.md', 'text/plain', "# Notes\n"), 'a .md sent as text/plain is refused');
            FsTest::assertSame(null, $emailAttachment(FsTest::uniq('notes') . '.txt', 'text/plain', "notes\n"), 'a .txt sent as text/plain is refused');
        });

        FsTest::case('text attachments: MCP lists no text extensions when only CSV is enabled', function () use ($setAcceptedTypes) {
            $setAcceptedTypes(['images', 'csv']);

            $extensions = AttachmentStager::allowedExtensions();

            FsTest::assert(in_array('csv', $extensions, true), 'csv listed, got: ' . implode(',', $extensions));
            FsTest::assertSame([], array_values(array_intersect(['md', 'txt', 'asc', 'c', 'h', 'srt'], $extensions)), 'no text extensions listed');
        });
    } finally {
        if ($savedSettings === null) {
            Meta::where('object_type', 'option')->where('key', 'global_business_settings')->delete();
        } else {
            Meta::where('object_type', 'option')->where('key', 'global_business_settings')->update(['value' => $savedSettings]);
        }
        (new Settings())->globalBusinessSettings(false);

        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
};
