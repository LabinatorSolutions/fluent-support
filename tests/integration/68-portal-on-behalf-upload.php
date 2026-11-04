<?php
/**
 * A remote portal uploading on_behalf of a customer uploads as that customer: the
 * ticket is addressed by its public number, the customer's ticket access applies, and
 * the attachment quota counts that customer's pending files only. The ticket-create
 * path then claims those files.
 *
 * The controller is called directly: a real upload never passes validation outside
 * PHP's upload handling, so a ValidationException here means every guard let it through.
 */

use FluentSupport\App\Http\Controllers\UploaderController;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\Framework\Validator\ValidationException;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();
    $maxFileUpload = intval((new Settings())->globalBusinessSettings()['max_file_upload']);

    $owner = FsFactory::customer(['email' => $marker . '-upload-owner@example.invalid']);
    $other = FsFactory::customer(['email' => $marker . '-upload-other@example.invalid']);
    $agent = FsFactory::agent();

    // Serials default to the primary key. Force them apart so an id can't pass for a serial.
    $ownerTicket = FsFactory::ticket([
        'customer_id'   => $owner->id,
        'title'         => 'On behalf upload ' . $marker,
        'privacy'       => 'private',
        'serial_number' => 900000 + wp_rand(1, 80000),
    ]);
    $ownerSerial = (int) Ticket::find($ownerTicket->id)->serial_number;

    $login = FsTest::uniq('fs-onbehalf-upload');
    $agentUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($agentUserId)) {
        throw new RuntimeException('Could not create agent user: ' . $agentUserId->get_error_message());
    }

    $switchTo = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    $tempFiles = [];

    // Pending (in-active, no ticket) uploads owned by a person, as the uploader leaves them.
    $seedPending = function ($personId, $count) use (&$tempFiles) {
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $path = wp_tempnam('fs-onbehalf-upload');
            file_put_contents($path, 'fixture');
            $tempFiles[] = $path;

            $hashes[] = Attachment::create([
                'ticket_id' => null,
                'person_id' => $personId,
                'file_type' => 'text/plain',
                'file_path' => $path,
                'full_url'  => 'https://example.invalid/fixture.txt',
                'title'     => 'fixture.txt',
                'driver'    => 'local',
                'status'    => 'in-active',
                'settings'  => ['local_temp_path' => $path],
            ])->file_hash;
        }
        return $hashes;
    };

    // Returns the refusal message, or 'passed' when file validation was reached.
    $upload = function (array $params) use (&$tempFiles) {
        $app = \FluentSupport\App\App::getInstance();
        // A prior REST dispatch leaves its request bound; its params would merge into ours.
        if ($app->bound('wprestrequest')) {
            $app->forgetInstance('wprestrequest');
        }

        $tmp = wp_tempnam('fs-onbehalf-upload');
        file_put_contents($tmp, 'fixture');
        $tempFiles[] = $tmp;

        $_FILES = ['file' => [
            'name'     => 'fixture.txt',
            'type'     => 'text/plain',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 7,
        ]];

        // Outside a REST request validate() hands its exception to the handle_exception
        // action, whose handler sends JSON and exits. Rethrow it first.
        $hook = $app->config->get('app.hook_prefix') . 'handle_exception';
        $rethrow = function ($e) {
            throw $e;
        };
        add_action($hook, $rethrow, 1);

        try {
            $request = new \FluentSupport\Framework\Http\Request\Request($app, [], $params);
            $response = (new UploaderController())->uploadTicketFiles($request);
        } catch (ValidationException $e) {
            // Refusals go out through Helper::getSafeErrorMessage(), which throws one
            // carrying the message; file validation fails on the 'file' field.
            $errors = (array) $e->errors();
            return isset($errors['message']) ? (string) $errors['message'] : 'passed';
        } finally {
            remove_action($hook, $rethrow, 1);
            $_FILES = [];
        }

        $data = $response instanceof \WP_REST_Response ? $response->get_data() : $response;
        return is_array($data) && isset($data['message']) ? (string) $data['message'] : wp_json_encode($data);
    };

    $createdTicketIds = [];
    $draftUserId = 0;

    try {
        $agent->user_id = $agentUserId;
        $agent->save();
        PermissionManager::attachPermissions($agentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets', 'fst_manage_settings']);

        FsTest::case('portal on_behalf upload: ticket_id is the public ticket number', function () use ($switchTo, $agentUserId, $adminId, $upload, $owner, $ownerSerial, $ownerTicket) {
            FsTest::assert($ownerSerial !== (int) $ownerTicket->id, 'fixture serial differs from the row id');

            $switchTo($agentUserId);
            try {
                $result = $upload(['ticket_id' => $ownerSerial, 'on_behalf' => ['email' => $owner->email]]);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertSame('passed', $result, 'upload to the customer\'s own ticket by number');
        });

        FsTest::case('portal on_behalf upload: another customer\'s private ticket is refused', function () use ($switchTo, $agentUserId, $adminId, $upload, $other, $ownerSerial) {
            $switchTo($agentUserId);
            try {
                $result = $upload(['ticket_id' => $ownerSerial, 'on_behalf' => ['email' => $other->email]]);
            } finally {
                $switchTo($adminId);
            }

            // Checked as a reply: the same answer as an unknown ticket number.
            FsTest::assert(strpos($result, 'do not have permission to this support ticket') !== false, 'refused by the customer access check, got: ' . $result);
        });

        FsTest::case('portal on_behalf upload: the quota counts the customer\'s own pending files', function () use ($switchTo, $agentUserId, $adminId, $upload, $seedPending, $owner, $maxFileUpload) {
            if ($maxFileUpload <= 0) {
                FsTest::skip('max_file_upload is unlimited on this site');
                return;
            }

            $seedPending($owner->id, $maxFileUpload);

            $switchTo($agentUserId);
            try {
                $result = $upload(['on_behalf' => ['email' => $owner->email]]);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assert(strpos($result, 'maximum of') !== false, 'the customer\'s pending files hit the quota, got: ' . $result);
        });

        FsTest::case('portal on_behalf upload: the API agent\'s pending files do not block a customer', function () use ($switchTo, $agentUserId, $adminId, $upload, $seedPending, $agent, $other, $maxFileUpload) {
            if ($maxFileUpload <= 0) {
                FsTest::skip('max_file_upload is unlimited on this site');
                return;
            }

            $seedPending($agent->id, $maxFileUpload);

            $switchTo($agentUserId);
            try {
                $result = $upload(['on_behalf' => ['email' => $other->email]]);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertSame('passed', $result, 'another customer\'s upload is not counted against the agent');
        });

        FsTest::case('portal on_behalf ticket: the customer\'s pending upload is attached', function () use ($switchTo, $agentUserId, $adminId, $seedPending, $other, $marker, &$createdTicketIds) {
            [$hash] = $seedPending($other->id, 1);

            $switchTo($agentUserId);
            try {
                $result = FsTest::rest('POST', '/customer-portal/tickets', [
                    'title'       => 'On behalf upload claim ' . $marker,
                    'content'     => '<p>With a file ' . $marker . '</p>',
                    'on_behalf'   => ['email' => $other->email],
                    'attachments' => [$hash],
                ]);
            } finally {
                $switchTo($adminId);
            }

            FsTest::assertHealthy($result, 'on_behalf ticket with an attachment');

            $ticket = Ticket::where('customer_id', $other->id)->where('title', 'like', '%upload claim%')->first();
            if ($ticket) {
                $createdTicketIds[] = $ticket->id;
                FsFactory::adoptRestTicket($ticket);
            }

            $attachment = Attachment::where('file_hash', $hash)->first();
            FsTest::assert($ticket && $attachment && (int) $attachment->ticket_id === (int) $ticket->id, 'the pending file belongs to the new ticket');
            FsTest::assert($attachment && $attachment->status === 'active', 'the file is active');
        });

        FsTest::case('an agent ticket without on_behalf does not take the customer\'s pending upload', function () use ($switchTo, $agentUserId, $adminId, $seedPending, $other, &$createdTicketIds) {
            [$hash] = $seedPending($other->id, 1);
            $ticket = FsFactory::ticket(['customer_id' => $other->id]);
            $createdTicketIds[] = $ticket->id;

            $switchTo($agentUserId);
            try {
                \FluentSupport\App\Services\Tickets\TicketService::addTicketAttachments(['attachments' => [$hash]], [], $ticket, $other);
            } finally {
                $switchTo($adminId);
            }

            $attachment = Attachment::where('file_hash', $hash)->first();
            FsTest::assert($attachment && !$attachment->ticket_id && $attachment->status === 'in-active', 'the customer\'s pending file stays unclaimed');
        });

        FsTest::case('portal on_behalf upload: an agent who may only upload is refused on_behalf', function () use ($switchTo, $adminId, $marker, &$draftUserId) {
            $login = FsTest::uniq('fs-onbehalf-draft');
            $draftUserId = wp_insert_user([
                'user_login' => $login,
                'user_pass'  => wp_generate_password(24, true, true),
                'user_email' => $login . '@example.test',
                'role'       => 'subscriber',
            ]);
            $draftAgent = FsFactory::agent();
            $draftAgent->user_id = $draftUserId;
            $draftAgent->save();
            PermissionManager::attachPermissions($draftUserId, ['fst_draft_reply']);

            $app = \FluentSupport\App\App::getInstance();
            // A prior REST dispatch leaves its request bound; its params would merge into ours.
            if ($app->bound('wprestrequest')) {
                $app->forgetInstance('wprestrequest');
            }
            $policy = new \FluentSupport\App\Http\Policies\PortalPolicy();

            $switchTo($draftUserId);
            try {
                $plain = $policy->uploadTicketFiles(new \FluentSupport\Framework\Http\Request\Request($app, [], []));
                $onBehalf = $policy->uploadTicketFiles(new \FluentSupport\Framework\Http\Request\Request($app, [], [
                    'on_behalf' => ['email' => $marker . '-draft-victim@example.invalid'],
                ]));
            } finally {
                $switchTo($adminId);
            }

            FsTest::assert($plain === true, 'a draft-reply agent may still upload for itself');
            FsTest::assert($onBehalf === false, 'but not on_behalf of a customer');
        });
    } finally {
        $switchTo($adminId);

        $attachments = Attachment::whereIn('person_id', [$owner->id, $other->id, $agent->id])
            ->orWhereIn('ticket_id', array_merge([$ownerTicket->id], $createdTicketIds ?: [0]))
            ->get();
        foreach ($attachments as $attachment) {
            if ($attachment->file_path && file_exists($attachment->file_path)) {
                @unlink($attachment->file_path);
            }
            $attachment->delete();
        }

        foreach ($tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if ($agentUserId && get_user_by('ID', $agentUserId)) {
            wp_delete_user($agentUserId);
        }
        if ($draftUserId && get_user_by('ID', $draftUserId)) {
            wp_delete_user($draftUserId);
        }
    }
};
