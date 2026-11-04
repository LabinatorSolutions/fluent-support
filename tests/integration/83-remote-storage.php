<?php
/**
 * Remote storage in the free plugin: StorageManager routing, delete queue, shared
 * files, email attachments, expired download links and driver validation.
 *
 * Uses a fake driver registered through `fluent_support/storage_drivers`, so it
 * doesn't need Pro or any network. Plan: dev-works/remote-storage-plan.md.
 */

use FluentSupport\App\Hooks\Handlers\ExternalPages;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\App\Services\Storage\MailAttachments;
use FluentSupport\App\Services\Storage\StorageDriverInterface;
use FluentSupport\App\Services\Storage\StorageManager;

return function () {
    // Records what the fake driver was asked to do.
    $log = new \ArrayObject(['uploads' => [], 'deletes' => [], 'copies' => [], 'fail_upload' => false, 'fail_delete' => false, 'delete_row_during_upload' => false, 'max_bytes' => []]);

    $fakeDriver = new class($log) implements StorageDriverInterface {
        private $log;

        public function __construct($log)
        {
            $this->log = $log;
        }

        public function getKey()
        {
            return 'fstest_remote';
        }

        public function isConfigured()
        {
            return true;
        }

        public function upload($attachment, $ticketId)
        {
            if ($this->log['fail_upload']) {
                return new \WP_Error('fstest', 'fake upload refused');
            }

            // Simulates the ticket being deleted while the upload runs.
            if ($this->log['delete_row_during_upload']) {
                Attachment::where('id', $attachment->id)->delete();
            }

            $uploads = $this->log['uploads'];
            $uploads[] = (int) $attachment->id;
            $this->log['uploads'] = $uploads;

            $settings = (array) $attachment->settings;
            $settings['object_key'] = 'k/' . $attachment->id;
            $attachment->settings = $settings;
            $attachment->file_path = 'fstest://k/' . $attachment->id;
            $attachment->driver = 'fstest_remote';
            $attachment->save();

            return true;
        }

        public function delete($attachment)
        {
            if ($this->log['fail_delete']) {
                return new \WP_Error('fstest', 'fake delete refused');
            }

            $deletes = $this->log['deletes'];
            $deletes[] = (string) $attachment->file_path;
            $this->log['deletes'] = $deletes;

            return true;
        }

        public function getDownloadUrl($attachment)
        {
            return 'https://storage.invalid/' . rawurlencode((string) $attachment->file_path);
        }

        public function stream($attachment)
        {
        }

        public function getLocalCopy($attachment, $maxBytes = 0)
        {
            $maxList = $this->log['max_bytes'];
            $maxList[] = (int) $maxBytes;
            $this->log['max_bytes'] = $maxList;

            // The timeout a real driver's HTTP download would get.
            $args = apply_filters('http_request_args', ['timeout' => 300], 'https://fstest.invalid/object');
            $timeouts = isset($this->log['timeouts']) ? $this->log['timeouts'] : [];
            $timeouts[] = $args['timeout'];
            $this->log['timeouts'] = $timeouts;

            $path = trailingslashit(get_temp_dir()) . 'fstest-copy-' . wp_generate_password(8, false) . '.txt';
            file_put_contents($path, 'remote bytes');
            $copies = $this->log['copies'];
            $copies[] = $path;
            $this->log['copies'] = $copies;

            return $path;
        }

        public function getStatus()
        {
            return ['last_error' => '', 'failed_uploads' => 0];
        }
    };

    // Runs $fn with the fake driver registered and selected, restoring everything after.
    $withFakeDriver = function (callable $fn) use ($fakeDriver, $log) {
        $register = function ($drivers) use ($fakeDriver) {
            $drivers['fstest_remote'] = $fakeDriver;
            return $drivers;
        };
        $previousDriver = Meta::where('object_type', 'option')->where('key', 'file_upload_driver')->value('value');
        $previousFailures = Meta::where('object_type', 'option')->where('key', '_storage_failures')->value('value');
        $previousDeleteFailures = Meta::where('object_type', 'option')->where('key', '_storage_delete_failures')->value('value');

        add_filter('fluent_support/storage_drivers', $register);
        Helper::updateOption('file_upload_driver', 'fstest_remote');
        $log['uploads'] = [];
        $log['deletes'] = [];
        $log['copies'] = [];
        $log['timeouts'] = [];
        $log['fail_upload'] = false;
        $log['fail_delete'] = false;
        $log['delete_row_during_upload'] = false;
        $log['max_bytes'] = [];

        try {
            return $fn();
        } finally {
            remove_filter('fluent_support/storage_drivers', $register);
            foreach (['file_upload_driver' => $previousDriver, '_storage_failures' => $previousFailures, '_storage_delete_failures' => $previousDeleteFailures] as $key => $value) {
                if ($value === null) {
                    Meta::where('object_type', 'option')->where('key', $key)->delete();
                } else {
                    Meta::where('object_type', 'option')->where('key', $key)->update(['value' => $value]);
                }
            }
            Attachment::where('status', StorageManager::STATUS_DELETING)->where('driver', 'fstest_remote')->delete();
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions(StorageManager::DELETE_HOOK);
            }
        }
    };

    // A real file in the plugin's upload folder.
    $makeFile = function ($subDir = 'temp_files', $content = 'hello') {
        $name = FsTest::uniq('file') . '.txt';
        $info = FileSystem::setSubDir($subDir)->putAsContent($name, $content);
        return $info['file'];
    };

    $makeAttachment = function ($ticket, array $attributes) {
        return Attachment::create(array_merge([
            'ticket_id' => $ticket ? $ticket->id : null,
            'person_id' => $ticket ? $ticket->customer_id : null,
            'file_type' => 'text/plain',
            'title'     => 'note.txt',
            'driver'    => 'local',
            'status'    => 'active',
        ], $attributes));
    };

    FsTest::case('deleting a ticket marks its remote files and the delete job removes them', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, [
                'driver'    => 'fstest_remote',
                'file_path' => 'fstest://k/' . FsTest::uniq('obj'),
            ]);
            $path = $attachment->file_path;

            $ticket->deleteTicket();

            $marked = Attachment::find($attachment->id);
            FsTest::assertSame(StorageManager::STATUS_DELETING, $marked ? $marked->status : null, 'row kept and marked for the job');
            FsTest::assert($marked && !$marked->ticket_id && !$marked->conversation_id && !$marked->person_id, 'row detached');
            FsTest::assertSame([], $log['deletes'], 'remote object not deleted during the request');

            StorageManager::processDeleteQueue();

            FsTest::assert(in_array($path, $log['deletes'], true), 'driver deleted the remote object');
            FsTest::assertSame(0, Attachment::where('id', $attachment->id)->count(), 'row removed after the remote delete');
        });
    });

    FsTest::case('a deleted reply\'s remote file is no longer listed on its ticket', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $reply = FsFactory::conversation($ticket);
            $attachment = $makeAttachment($ticket, [
                'conversation_id' => $reply->id,
                'driver'          => 'fstest_remote',
                'file_path'       => 'fstest://k/' . FsTest::uniq('obj'),
            ]);

            $reply->delete();

            FsTest::assertSame(0, Attachment::where('ticket_id', $ticket->id)->count(), 'not listed on the ticket');
            FsTest::assertSame(StorageManager::STATUS_DELETING, Attachment::find($attachment->id)->status ?? null, 'waiting for the job');

            StorageManager::processDeleteQueue();
            FsTest::assertSame(1, count($log['deletes']), 'remote object deleted once');
        });
    });

    FsTest::case('an abandoned upload that already went remote is left for the delete job', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            // An agent upload on an open ticket is pushed at once, then never sent.
            $attachment = $makeAttachment($ticket, [
                'driver'    => 'fstest_remote',
                'status'    => 'in-active',
                'file_path' => 'fstest://k/' . FsTest::uniq('obj'),
            ]);
            Attachment::where('id', $attachment->id)->update(['created_at' => gmdate('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS)]);

            $method = new \ReflectionMethod(\FluentSupport\App\Hooks\Handlers\CleanupHandler::class, 'cleanAbandonedTempAttachments');
            $method->setAccessible(true);
            $method->invoke(new \FluentSupport\App\Hooks\Handlers\CleanupHandler());

            FsTest::assertSame(StorageManager::STATUS_DELETING, Attachment::find($attachment->id)->status ?? null, 'row kept for the job');

            StorageManager::processDeleteQueue();
            FsTest::assertSame(1, count($log['deletes']), 'remote object deleted');
            FsTest::assertSame(0, Attachment::where('id', $attachment->id)->count(), 'row gone');
        });
    });

    FsTest::case('the delete job keeps a remote object another row still uses', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $first = FsFactory::ticket();
            $second = FsFactory::ticket(['customer_id' => $first->customer_id]);
            $path = 'fstest://k/' . FsTest::uniq('shared');
            $makeAttachment($first, ['driver' => 'fstest_remote', 'file_path' => $path]);
            $first->deleteTicket();

            // A bulk-reply style copy points at the same object before the job runs.
            $kept = $makeAttachment($second, ['driver' => 'fstest_remote', 'file_path' => $path]);
            StorageManager::processDeleteQueue();

            FsTest::assertSame([], $log['deletes'], 'shared object not deleted');
            FsTest::assertSame(1, Attachment::where('id', $kept->id)->count(), 'other row untouched');

            Attachment::where('id', $kept->id)->delete();
        });
    });

    FsTest::case('a file another ticket still uses is not deleted', function () use ($makeFile, $makeAttachment) {
        $first = FsFactory::ticket();
        $second = FsFactory::ticket(['customer_id' => $first->customer_id]);
        $path = $makeFile();

        // Bulk reply rows share one file.
        $makeAttachment($first, ['file_path' => $path]);
        $kept = $makeAttachment($second, ['file_path' => $path]);

        $first->deleteTicket();

        FsTest::assert(file_exists($path), 'shared file kept');
        FsTest::assertSame(1, Attachment::where('id', $kept->id)->count(), 'other row untouched');

        Attachment::where('id', $kept->id)->delete();
        wp_delete_file($path);
    });

    FsTest::case('a merged ticket\'s files survive deleting the old ticket folder', function () use ($makeFile, $makeAttachment) {
        $old = FsFactory::ticket();
        $target = FsFactory::ticket(['customer_id' => $old->customer_id]);
        $path = $makeFile('ticket_' . $old->id);

        // Pro's merge moves the row to the new ticket; the file stays in ticket_{old}/.
        $moved = $makeAttachment($target, ['file_path' => $path]);

        $old->deleteTicket();

        FsTest::assert(file_exists($path), 'file in the old ticket folder kept');

        Attachment::where('id', $moved->id)->delete();
        wp_delete_file($path);
    });

    FsTest::case('finalize uploads through a registered driver and records nothing on success', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment(null, ['file_path' => $makeFile(), 'status' => 'in-active']);

            StorageManager::finalize($attachment, $ticket->id);

            FsTest::assertSame([(int) $attachment->id], $log['uploads'], 'uploaded once');
            FsTest::assertSame('fstest_remote', Attachment::find($attachment->id)->driver, 'row switched to the driver');
            FsTest::assertSame(0, StorageManager::getHealth('fstest_remote')['failed_uploads'], 'no failure recorded');

            Attachment::where('id', $attachment->id)->delete();
        });
    });

    FsTest::case('a failed upload keeps the file local and is reported', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $log['fail_upload'] = true;
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment(null, ['file_path' => $makeFile(), 'status' => 'in-active']);

            StorageManager::finalize($attachment, $ticket->id);

            $health = StorageManager::getHealth('fstest_remote');
            FsTest::assertSame('local', $attachment->driver, 'still local');
            FsTest::assertSame(1, $health['failed_uploads'], 'failure counted');
            FsTest::assertSame('fake upload refused', $health['last_error'], 'error kept');

            wp_delete_file($attachment->file_path);
            Attachment::where('id', $attachment->id)->delete();
        });
    });

    FsTest::case('an older Pro that leaves the row local is reported as a failure', function () use ($makeFile, $makeAttachment) {
        // No registered driver: free fires the legacy hook. Nobody converts the row.
        $previous = Meta::where('object_type', 'option')->where('key', 'file_upload_driver')->value('value');
        $previousFailures = Meta::where('object_type', 'option')->where('key', '_storage_failures')->value('value');
        Helper::updateOption('file_upload_driver', 'fstest_legacy');
        $fired = 0;
        $listener = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/finalize_file_upload_fstest_legacy', $listener);

        try {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment(null, ['file_path' => $makeFile(), 'status' => 'in-active']);

            StorageManager::finalize($attachment, $ticket->id);

            FsTest::assertSame(1, $fired, 'legacy hook fired');
            FsTest::assertSame(1, StorageManager::getHealth('fstest_legacy')['failed_uploads'], 'failure counted');

            wp_delete_file($attachment->file_path);
            Attachment::where('id', $attachment->id)->delete();
        } finally {
            remove_action('fluent_support/finalize_file_upload_fstest_legacy', $listener);
            foreach (['file_upload_driver' => $previous, '_storage_failures' => $previousFailures] as $key => $value) {
                if ($value === null) {
                    Meta::where('object_type', 'option')->where('key', $key)->delete();
                } else {
                    Meta::where('object_type', 'option')->where('key', $key)->update(['value' => $value]);
                }
            }
        }
    });

    FsTest::case('background mode queues the upload and the job pushes it and removes the local copy', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $previous = Meta::where('object_type', 'option')->where('key', 'storage_async_upload')->value('value');
            Helper::updateOption('storage_async_upload', 'yes');

            try {
                $ticket = FsFactory::ticket();
                $attachment = $makeAttachment(null, ['file_path' => $makeFile(), 'status' => 'in-active']);

                StorageManager::finalize($attachment, $ticket->id);
                FsTest::assertSame([], $log['uploads'], 'not uploaded during the request');
                FsTest::assertSame('fstest_remote', $attachment->settings['remote_pending'] ?? null, 'marked pending');

                // What the caller does next: keep it in the ticket folder and activate it.
                $localPath = $makeFile('ticket_' . $ticket->id);
                $attachment->file_path = $localPath;
                $attachment->ticket_id = $ticket->id;
                $attachment->status = 'active';
                $attachment->save();

                StorageManager::pushPending($attachment->id);

                $fresh = Attachment::find($attachment->id);
                FsTest::assertSame('fstest_remote', $fresh->driver, 'pushed to the driver');
                FsTest::assert(empty($fresh->settings['remote_pending']), 'pending flag cleared');
                FsTest::assert(!file_exists($localPath), 'local copy removed');

                Attachment::where('id', $attachment->id)->delete();
            } finally {
                if ($previous === null) {
                    Meta::where('object_type', 'option')->where('key', 'storage_async_upload')->delete();
                } else {
                    Meta::where('object_type', 'option')->where('key', 'storage_async_upload')->update(['value' => $previous]);
                }
                if (function_exists('as_unschedule_all_actions')) {
                    as_unschedule_all_actions(StorageManager::PUSH_HOOK);
                }
            }
        });
    });

    FsTest::case('email attachments fetch a remote file whose temp copy is gone, then delete the copy', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, [
                'driver'    => 'fstest_remote',
                'file_path' => 'fstest://k/1',
                'settings'  => ['local_temp_path' => '/nonexistent/fstest.txt'],
            ]);

            try {
                $mail = new MailAttachments([$attachment]);
                $paths = $mail->getPaths();

                FsTest::assertSame(1, count($paths), 'one file attached');
                FsTest::assertSame($log['copies'], $paths, 'the driver\'s copy is attached');
                FsTest::assert(file_exists($paths[0]), 'copy exists before sending');

                $mail->cleanup();
                MailAttachments::deleteFetched();
                FsTest::assert(!file_exists($paths[0]), 'copy deleted at the end of the request');
            } finally {
                MailAttachments::deleteFetched();
                Attachment::where('id', $attachment->id)->delete();
            }
        });
    });

    FsTest::case('several emails in one request fetch a remote file once', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);

            try {
                $customerMail = new MailAttachments([$attachment], true);
                $customerMail->cleanup();
                $agentMail = new MailAttachments([$attachment]);
                $agentMail->cleanup();

                FsTest::assertSame(1, count($log['copies']), 'downloaded once');
                FsTest::assertSame($customerMail->getPaths(), $agentMail->getPaths(), 'both emails attach the same copy');
                FsTest::assert(file_exists($agentMail->getPaths()[0]), 'copy kept until the request ends');
            } finally {
                MailAttachments::deleteFetched();
                Attachment::where('id', $attachment->id)->delete();
            }
        });
    });

    FsTest::case('an email download cannot wait longer than the fetch time left', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);

            try {
                $mail = new MailAttachments([$attachment]);
                $mail->cleanup();

                FsTest::assert($log['timeouts'][0] <= 15, 'download timeout capped to the 15 s budget, got ' . $log['timeouts'][0]);
                $after = apply_filters('http_request_args', ['timeout' => 300], 'https://fstest.invalid/object');
                FsTest::assertSame(300, $after['timeout'], 'other requests keep their timeout');
            } finally {
                MailAttachments::deleteFetched();
                Attachment::where('id', $attachment->id)->delete();
            }
        });
    });

    FsTest::case('each Action Scheduler action gets its own fetched copies and time budget', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);
            $budget = function () {
                return 0.000001;
            };
            add_filter('fluent_support/mail_attachments_fetch_seconds', $budget);

            try {
                $first = new MailAttachments([$attachment]);
                $first->cleanup();
                $copy = $first->getPaths()[0];

                // The action ends; the next one in the same PHP process starts fresh.
                do_action('action_scheduler_after_execute', 0, null, 'fstest');
                FsTest::assert(!file_exists($copy), 'copy deleted after the action');

                $second = new MailAttachments([$attachment]);
                $second->cleanup();
                FsTest::assertSame(2, count($log['copies']), 'fetched again with a new budget');
                FsTest::assertSame(1, count($second->getPaths()), 'attached, not linked');
            } finally {
                remove_filter('fluent_support/mail_attachments_fetch_seconds', $budget);
                MailAttachments::deleteFetched();
                Attachment::where('id', $attachment->id)->delete();
            }
        });
    });

    FsTest::case('emails send remote files as links once the fetch time budget is spent', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $first = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);
            $second = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);
            // Any fetch at all spends a budget this small.
            $budget = function () {
                return 0.000001;
            };
            add_filter('fluent_support/mail_attachments_fetch_seconds', $budget);

            try {
                $mail = new MailAttachments([$first, $second]);
                $mail->cleanup();

                FsTest::assertSame(1, count($log['copies']), 'second file not fetched');
                FsTest::assertSame(1, count($mail->getPaths()), 'first file attached');
                FsTest::assertSame(1, count($mail->getLinks()), 'second file sent as a link');
            } finally {
                remove_filter('fluent_support/mail_attachments_fetch_seconds', $budget);
                MailAttachments::deleteFetched();
                Attachment::where('id', $first->id)->delete();
                Attachment::where('id', $second->id)->delete();
            }
        });
    });

    FsTest::case('files over the email size limit are sent as 30-day links', function () use ($makeFile, $makeAttachment) {
        $ticket = FsFactory::ticket();
        $attachment = $makeAttachment($ticket, ['file_path' => $makeFile('ticket_' . $ticket->id, str_repeat('x', 2048)), 'file_size' => 2048]);
        $limit = function () {
            return 1024;
        };
        add_filter('fluent_support/mail_attachments_max_bytes', $limit);

        try {
            $mail = new MailAttachments([$attachment]);
            $body = $mail->addLinksToBody('<html><body><p>Hi</p></body></html>');

            FsTest::assertSame([], $mail->getPaths(), 'not attached');
            FsTest::assert(strpos($body, 'fst_file=' . $attachment->file_hash) !== false, 'link in the body');
            FsTest::assert(strpos($body, '</ul></div></body>') !== false, 'inserted before </body>');

            parse_str((string) wp_parse_url($mail->getLinks()[0]['url'], PHP_URL_QUERY), $query);
            FsTest::assert((int) $query['expires'] >= time() + 29 * DAY_IN_SECONDS, 'valid for about 30 days');
            $ticketHash = (string) \FluentSupport\App\Models\Ticket::where('id', $ticket->id)->value('hash');
            FsTest::assertSame(Attachment::ticketSignature($attachment->id, (int) $query['expires'], $ticketHash), $query['secure_sign'], 'signature bound to the ticket hash');
            FsTest::assert(empty($query['fst_ticket']), 'no ticket hash in a link that may go to agents');
        } finally {
            remove_filter('fluent_support/mail_attachments_max_bytes', $limit);
            wp_delete_file($attachment->file_path);
            Attachment::where('id', $attachment->id)->delete();
        }
    });

    // Business rule (RS-07): an expired link still works for whoever can see the ticket.
    $canView = function ($attachment) {
        $method = new \ReflectionMethod(ExternalPages::class, 'canViewWithoutSignature');
        $method->setAccessible(true);
        return $method->invoke(new ExternalPages(), $attachment);
    };

    FsTest::case('expired link: an agent who can see the ticket gets the file', function () use ($makeAttachment, $canView) {
        $ticket = FsFactory::ticket();
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);

        FsTest::assert($canView($attachment), 'admin agent allowed');

        Attachment::where('id', $attachment->id)->delete();
    });

    FsTest::case('expired link: logged out needs the matching ticket hash', function () use ($makeAttachment, $canView) {
        $ticket = FsFactory::ticket();
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);
        $userId = get_current_user_id();
        wp_set_current_user(0);

        try {
            unset($_REQUEST['fst_ticket']);
            FsTest::assert(!$canView($attachment), 'no hash: refused');

            $_REQUEST['fst_ticket'] = 'wrong' . $ticket->hash;
            FsTest::assert(!$canView($attachment), 'wrong hash: refused');

            $_REQUEST['fst_ticket'] = $ticket->hash;
            FsTest::assertSame(Helper::isPublicSignedTicketEnabled(), $canView($attachment), 'right hash: allowed when signed ticket links are on');
        } finally {
            unset($_REQUEST['fst_ticket']);
            wp_set_current_user($userId);
            Attachment::where('id', $attachment->id)->delete();
        }
    });

    FsTest::case('expired link: a customer only gets files of their own tickets', function () use ($makeAttachment, $canView) {
        $userId = get_current_user_id();
        $ownerUser = wp_insert_user(['user_login' => FsTest::uniq('owner'), 'user_pass' => wp_generate_password(), 'user_email' => FsTest::uniq('owner') . '@example.test']);
        $otherUser = wp_insert_user(['user_login' => FsTest::uniq('other'), 'user_pass' => wp_generate_password(), 'user_email' => FsTest::uniq('other') . '@example.test']);
        $owner = FsFactory::customer(['user_id' => $ownerUser]);
        FsFactory::customer(['user_id' => $otherUser]);
        $ticket = FsFactory::ticket(['customer_id' => $owner->id]);
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);

        try {
            wp_set_current_user($ownerUser);
            FsTest::assert($canView($attachment), 'owner allowed');

            wp_set_current_user($otherUser);
            FsTest::assert(!$canView($attachment), 'other customer refused');
        } finally {
            wp_set_current_user($userId);
            Attachment::where('id', $attachment->id)->delete();
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($ownerUser);
            wp_delete_user($otherUser);
        }
    });

    FsTest::case('bulk reply copies keep file type and storage settings', function () use ($makeFile) {
        $ticket = FsFactory::ticket();
        $agent = Helper::getAgentByUserId();
        $path = $makeFile();
        $source = Attachment::create([
            'person_id' => $agent->id,
            'file_type' => 'text/plain',
            'file_path' => $path,
            'title'     => 'bulk.txt',
            'driver'    => 'local',
            'status'    => 'in-active',
            'settings'  => ['local_temp_path' => $path],
        ]);

        $result = FsTest::rest('POST', '/tickets/bulk-reply', [
            'ticket_ids'  => [$ticket->id],
            'content'     => '<p>Bulk reply ' . FsTest::uniq('c') . '</p>',
            'attachments' => [$source->file_hash],
        ]);
        FsTest::assertHealthy($result, 'bulk reply');

        $copy = Attachment::where('ticket_id', $ticket->id)->where('title', 'bulk.txt')->first();
        FsTest::assert((bool) $copy, 'copy created');
        FsTest::assertSame('text/plain', $copy ? $copy->file_type : null, 'file type kept');
        FsTest::assertSame($path, $copy ? ($copy->settings['local_temp_path'] ?? null) : null, 'settings kept');

        Attachment::where('id', $source->id)->delete();
        wp_delete_file($path);
    });

    FsTest::case('the upload driver must be a known, connected driver', function () {
        $previous = Meta::where('object_type', 'option')->where('key', 'file_upload_driver')->value('value');

        try {
            $bad = FsTest::rest('POST', '/settings/update-remote-upload-driver', ['driver' => 'not_a_driver']);
            FsTest::assert($bad['status'] >= 400, 'unknown driver refused');

            $ok = FsTest::rest('POST', '/settings/update-remote-upload-driver', ['driver' => 'local']);
            FsTest::assertHealthy($ok, 'local accepted');
            FsTest::assertSame('local', Helper::getOption('file_upload_driver'), 'saved');
        } finally {
            if ($previous === null) {
                Meta::where('object_type', 'option')->where('key', 'file_upload_driver')->delete();
            } else {
                Meta::where('object_type', 'option')->where('key', 'file_upload_driver')->update(['value' => $previous]);
            }
        }
    });

    // ---- Review findings (2026-10-22) ----

    FsTest::case('a pasted image already pushed to remote storage is kept when the reply is saved', function () use ($withFakeDriver, $makeAttachment) {
        $withFakeDriver(function () use ($makeAttachment) {
            $ticket = FsFactory::ticket();
            $agent = Helper::getAgentByUserId();
            $image = $makeAttachment($ticket, [
                'person_id' => $agent->id,
                'driver'    => 'fstest_remote',
                'status'    => 'in-active',
                'file_type' => 'image/png',
                'file_path' => 'fstest://k/' . FsTest::uniq('img'),
            ]);
            $reply = FsFactory::conversation($ticket, [
                'content' => '<p>See <img src="' . esc_url($image->secureUrl) . '"> ' . esc_html(FsFactory::marker()) . '</p>',
            ]);

            Helper::tempImageMoveUploadDir($ticket->id, 'conversation', $reply->id, $agent->id);

            $fresh = Attachment::find($image->id);
            FsTest::assertSame('inline', $fresh->status, 'marked inline, so the abandoned-upload cleanup skips it');
            FsTest::assertSame((int) $reply->id, (int) $fresh->conversation_id, 'linked to the reply');
        });
    });

    // Runs view_attachment() with wp_die() turned into an exception; returns the HTTP status.
    $download = function (array $query) {
        $dieHandler = function () {
            return function ($message, $title = '', $args = []) {
                throw new \RuntimeException('wp_die:' . (is_array($args) && isset($args['response']) ? $args['response'] : 500));
            };
        };
        add_filter('wp_die_handler', $dieHandler);
        $savedRequest = $_REQUEST;
        $savedMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_REQUEST = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        delete_transient('fs_rate_limit_' . wp_hash(Helper::getIp()));

        try {
            (new ExternalPages())->view_attachment();
            return 200;
        } catch (\RuntimeException $e) {
            return (int) substr($e->getMessage(), strlen('wp_die:'));
        } finally {
            remove_filter('wp_die_handler', $dieHandler);
            $_REQUEST = $savedRequest;
            $_SERVER['REQUEST_METHOD'] = $savedMethod;
        }
    };

    FsTest::case('a file waiting for the delete job can no longer be downloaded', function () use ($withFakeDriver, $makeAttachment, $download) {
        $withFakeDriver(function () use ($makeAttachment, $download) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);
            parse_str((string) wp_parse_url($attachment->emailUrl(), PHP_URL_QUERY), $query);

            $ticket->deleteTicket();

            FsTest::assertSame(404, $download($query), 'deleted ticket\'s file is gone at once');
        });
    });

    FsTest::case('the delete job keeps going when more rows are waiting', function () use ($withFakeDriver, $makeAttachment, $log) {
        if (!function_exists('as_has_scheduled_action')) {
            FsTest::skip('Action Scheduler not loaded');
            return;
        }
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $rows = [];
            for ($i = 0; $i < 51; $i++) {
                $rows[] = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('bulk')]);
            }
            $ticket->deleteTicket();
            as_unschedule_all_actions(StorageManager::DELETE_HOOK);

            StorageManager::processDeleteQueue();

            FsTest::assertSame(50, count($log['deletes']), 'one batch processed');
            FsTest::assert(as_has_scheduled_action(StorageManager::DELETE_HOOK), 'next batch scheduled');
        });
    });

    FsTest::case('the delete job running from Action Scheduler queues its next batch', function () use ($withFakeDriver, $makeAttachment, $log) {
        if (!class_exists('ActionScheduler_QueueRunner')) {
            FsTest::skip('Action Scheduler not loaded');
            return;
        }
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            for ($i = 0; $i < 51; $i++) {
                $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('bulk')]);
            }
            $ticket->deleteTicket();
            as_unschedule_all_actions(StorageManager::DELETE_HOOK);

            // The running action counts as "scheduled" to as_has_scheduled_action().
            $actionId = as_schedule_single_action(time() - 1, StorageManager::DELETE_HOOK, [], 'fluent-support');
            \ActionScheduler_QueueRunner::instance()->process_action($actionId, 'fstest');

            FsTest::assertSame(50, count($log['deletes']), 'one batch processed');
            $pending = as_get_scheduled_actions([
                'hook'   => StorageManager::DELETE_HOOK,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
            ], 'ids');
            FsTest::assertSame(1, count($pending), 'next batch scheduled from inside the job');
            as_unschedule_all_actions(StorageManager::DELETE_HOOK);
        });
    });

    FsTest::case('a failing remote delete is reported as a delete failure, not an upload failure', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $log['fail_delete'] = true;
            $ticket = FsFactory::ticket();
            $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/' . FsTest::uniq('obj')]);
            $ticket->deleteTicket();

            for ($i = 0; $i < 5; $i++) {
                StorageManager::processDeleteQueue();
            }

            $health = StorageManager::getHealth('fstest_remote');
            FsTest::assertSame(1, $health['failed_deletes'], 'delete failure counted');
            FsTest::assertSame(0, $health['failed_uploads'], 'no upload failure');
        });
    });

    FsTest::case('a background upload that finishes after its row was deleted removes the new object', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, [
                'file_path' => $makeFile('ticket_' . $ticket->id),
                'settings'  => ['remote_pending' => 'fstest_remote'],
            ]);
            $log['delete_row_during_upload'] = true;

            StorageManager::pushPending($attachment->id);

            FsTest::assertSame(1, count($log['uploads']), 'uploaded');
            FsTest::assertSame(['fstest://k/' . $attachment->id], $log['deletes'], 'orphaned object deleted');
        });
    });

    FsTest::case('expired link: a customer cannot open an internal note\'s file, even on their own ticket', function () use ($makeAttachment, $canView) {
        $userId = get_current_user_id();
        $ownerUser = wp_insert_user(['user_login' => FsTest::uniq('owner'), 'user_pass' => wp_generate_password(), 'user_email' => FsTest::uniq('owner') . '@example.test']);
        $owner = FsFactory::customer(['user_id' => $ownerUser]);
        $ticket = FsFactory::ticket(['customer_id' => $owner->id]);
        $note = FsFactory::conversation($ticket, ['conversation_type' => 'note']);
        $attachment = $makeAttachment($ticket, ['conversation_id' => $note->id, 'file_path' => '/nonexistent']);

        try {
            wp_set_current_user($ownerUser);
            FsTest::assert(!$canView($attachment), 'note attachment refused');
        } finally {
            wp_set_current_user($userId);
            Attachment::where('id', $attachment->id)->delete();
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($ownerUser);
        }
    });

    FsTest::case('expired link: a customer cannot open an agent\'s upload that is not saved yet', function () use ($makeAttachment, $canView) {
        $userId = get_current_user_id();
        $ownerUser = wp_insert_user(['user_login' => FsTest::uniq('owner'), 'user_pass' => wp_generate_password(), 'user_email' => FsTest::uniq('owner') . '@example.test']);
        $owner = FsFactory::customer(['user_id' => $ownerUser]);
        $ticket = FsFactory::ticket(['customer_id' => $owner->id]);
        // Files for a reply or note still being written (UploaderController).
        $agentUpload = $makeAttachment($ticket, ['status' => 'in-active', 'person_id' => FsFactory::agent()->id, 'file_path' => '/nonexistent']);
        $ownUpload = $makeAttachment($ticket, ['status' => 'in-active', 'file_path' => '/nonexistent']);

        try {
            wp_set_current_user($ownerUser);
            FsTest::assert(!$canView($agentUpload), 'agent\'s temporary upload refused');
            FsTest::assert($canView($ownUpload), 'own temporary upload allowed');
        } finally {
            wp_set_current_user($userId);
            Attachment::whereIn('id', [$agentUpload->id, $ownUpload->id])->delete();
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($ownerUser);
        }
    });

    FsTest::case('expired link: a blocked customer cannot open their files', function () use ($makeAttachment, $canView) {
        $userId = get_current_user_id();
        $ownerUser = wp_insert_user(['user_login' => FsTest::uniq('owner'), 'user_pass' => wp_generate_password(), 'user_email' => FsTest::uniq('owner') . '@example.test']);
        $owner = FsFactory::customer(['user_id' => $ownerUser, 'status' => 'blocked']);
        $ticket = FsFactory::ticket(['customer_id' => $owner->id]);
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);

        try {
            wp_set_current_user($ownerUser);
            FsTest::assert(!$canView($attachment), 'blocked customer refused');
        } finally {
            wp_set_current_user($userId);
            Attachment::where('id', $attachment->id)->delete();
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($ownerUser);
        }
    });

    FsTest::case('email links stop working when the ticket hash is rotated', function () use ($makeAttachment) {
        $ticket = FsFactory::ticket();
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);
        parse_str((string) wp_parse_url($attachment->emailUrl(), PHP_URL_QUERY), $query);

        $validate = new \ReflectionMethod(ExternalPages::class, 'validateAttachmentSignature');
        $validate->setAccessible(true);
        $saved = $_REQUEST;

        try {
            $_REQUEST = $query;
            FsTest::assert($validate->invoke(new ExternalPages(), $attachment), 'valid before rotation');

            \FluentSupport\App\Models\Ticket::where('id', $ticket->id)->update(['hash' => md5(FsTest::uniq('rotated'))]);
            FsTest::assert(!$validate->invoke(new ExternalPages(), $attachment), 'invalid after rotation');
        } finally {
            $_REQUEST = $saved;
            Attachment::where('id', $attachment->id)->delete();
        }
    });

    FsTest::case('customer emails carry the ticket hash for hash-signed links, agent emails never do', function () use ($makeAttachment) {
        $ticket = FsFactory::ticket();
        $attachment = $makeAttachment($ticket, ['file_path' => '/nonexistent']);

        FsTest::assert(strpos($attachment->emailUrl(false), 'fst_ticket=') === false, 'agent link has no ticket hash');
        if (Helper::isPublicSignedTicketEnabled()) {
            FsTest::assert(strpos($attachment->emailUrl(true), 'fst_ticket=') !== false, 'customer link has it');
        }

        Attachment::where('id', $attachment->id)->delete();
    });

    FsTest::case('bulk reply only takes the replying agent\'s own pending uploads', function () use ($makeFile) {
        $ticket = FsFactory::ticket();
        $other = FsFactory::customer();
        $path = $makeFile();
        $foreign = Attachment::create([
            'person_id' => $other->id,
            'file_type' => 'text/plain',
            'file_path' => $path,
            'title'     => 'foreign.txt',
            'driver'    => 'local',
            'status'    => 'in-active',
        ]);

        FsTest::rest('POST', '/tickets/bulk-reply', [
            'ticket_ids'  => [$ticket->id],
            'content'     => '<p>Bulk ' . FsTest::uniq('c') . '</p>',
            'attachments' => [$foreign->file_hash],
        ]);

        FsTest::assertSame(0, Attachment::where('ticket_id', $ticket->id)->where('title', 'foreign.txt')->count(), 'someone else\'s upload not attached');

        Attachment::where('id', $foreign->id)->delete();
        wp_delete_file($path);
    });

    FsTest::case('emails fetch remote files only up to the size limit', function () use ($withFakeDriver, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, ['driver' => 'fstest_remote', 'file_path' => 'fstest://k/1']);
            $limit = function () {
                return 5000;
            };
            add_filter('fluent_support/mail_attachments_max_bytes', $limit);

            try {
                $mail = new MailAttachments([$attachment]);
                $mail->cleanup();
                FsTest::assertSame([5000], $log['max_bytes'], 'download limited to the remaining size');
            } finally {
                MailAttachments::deleteFetched();
                remove_filter('fluent_support/mail_attachments_max_bytes', $limit);
                Attachment::where('id', $attachment->id)->delete();
            }
        });
    });

    FsTest::case('an older Pro\'s delete hook still gets the ticket the file belonged to', function () use ($makeAttachment) {
        $ticket = FsFactory::ticket();
        $seen = [];
        $listener = function ($attachment, $ticketId) use (&$seen) {
            $seen[] = [(int) $ticketId, (int) $attachment->ticket_id];
        };
        add_action('fluent_support/delete_remote_attachment_fstest_legacy', $listener, 10, 2);

        try {
            $makeAttachment($ticket, ['driver' => 'fstest_legacy', 'file_path' => 'fstest://legacy/' . FsTest::uniq('obj')]);
            $ticket->deleteTicket();
            StorageManager::processDeleteQueue();

            FsTest::assertSame([[(int) $ticket->id, (int) $ticket->id]], $seen, 'ticket id passed and set on the model');
        } finally {
            remove_action('fluent_support/delete_remote_attachment_fstest_legacy', $listener, 10);
            Attachment::where('status', StorageManager::STATUS_DELETING)->where('driver', 'fstest_legacy')->delete();
        }
    });

    FsTest::case('a failed upload is not retried automatically; it waits for Retry now', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $log['fail_upload'] = true;
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment(null, ['file_path' => $makeFile(), 'status' => 'in-active']);

            StorageManager::finalize($attachment, $ticket->id);

            FsTest::assertSame('fstest_remote', $attachment->settings['remote_failed'] ?? null, 'remembered as failed');
            FsTest::assert(empty($attachment->settings['remote_pending']), 'nothing queued');
            if (function_exists('as_has_scheduled_action')) {
                FsTest::assert(!as_has_scheduled_action(StorageManager::PUSH_HOOK), 'no background retry scheduled');
            }

            wp_delete_file($attachment->file_path);
            Attachment::where('id', $attachment->id)->delete();
        });
    });

    FsTest::case('Retry now queues failed files and clears the warning', function () use ($withFakeDriver, $makeFile, $makeAttachment, $log) {
        $withFakeDriver(function () use ($makeFile, $makeAttachment, $log) {
            $ticket = FsFactory::ticket();
            $attachment = $makeAttachment($ticket, [
                'file_path' => $makeFile('ticket_' . $ticket->id),
                'settings'  => ['remote_failed' => 'fstest_remote'],
            ]);
            StorageManager::recordFailure('fstest_remote', 'earlier failure');

            $result = FsTest::rest('POST', '/settings/remote-upload-retry', ['driver' => 'fstest_remote']);
            FsTest::assertHealthy($result, 'retry endpoint');
            FsTest::assertSame(1, (int) ($result['data']['queued'] ?? 0), 'one file queued');
            FsTest::assertSame(0, StorageManager::getHealth('fstest_remote')['failed_uploads'], 'warning cleared');

            StorageManager::pushPending($attachment->id);
            FsTest::assertSame('fstest_remote', Attachment::find($attachment->id)->driver, 'uploaded');
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions(StorageManager::PUSH_HOOK);
            }
        });
    });
};
