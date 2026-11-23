<?php
/**
 * MCP tools added for the consumer review: notes saved as real notes, the
 * informational reply flag, base64 attachments on replies and notes,
 * update-customer, split-ticket (Pro) and the prompts.
 *
 * Attachment rows and files are removed in each case's finally; the factory
 * does not track them.
 */

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\AbilitiesRegistrar;
use FluentSupport\App\Modules\MCP\PromptsRegistrar;
use FluentSupport\App\Modules\MCP\Support\AbilityGuard;
use FluentSupport\App\Modules\MCP\Tools\CustomerTools;
use FluentSupport\App\Modules\MCP\Tools\ManagementTools;
use FluentSupport\App\Modules\MCP\Tools\ResponseTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    FsTest::interceptMail();

    // A valid 1x1 PNG.
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    $result = function ($value) {
        return json_decode(wp_json_encode($value), true);
    };

    $errorCode = function ($value) {
        if (!is_wp_error($value)) {
            return 'ok';
        }
        $decoded = json_decode($value->get_error_message(), true);
        return $decoded['error']['code'] ?? $value->get_error_code();
    };

    $removeAttachments = function ($ticketId) {
        foreach (Attachment::where('ticket_id', $ticketId)->get() as $attachment) {
            if ($attachment->file_path && file_exists($attachment->file_path)) {
                wp_delete_file($attachment->file_path);
            }
            $attachment->delete();
        }
    };

    // A ticket whose customer spoke a minute ago, so it is waiting on an agent.
    // A reply in the same second would tie, and a tie counts as the customer's turn.
    $waitingTicket = function () {
        $ticket = FsFactory::ticket();
        $minuteAgo = date('Y-m-d H:i:s', current_time('timestamp') - 60);
        Ticket::where('id', $ticket->id)->update([
            'last_customer_response' => $minuteAgo,
            'last_agent_response'    => null,
            'waiting_since'          => $minuteAgo,
        ]);
        return Ticket::find($ticket->id);
    };

    FsTest::case('internal_note_is_a_note_and_fires_the_note_hook', function () use ($result) {
        $ticket = FsFactory::ticket();
        $fired = 0;
        $counter = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/note_added_by_agent', $counter);

        try {
            $data = $result(ResponseTools::addInternalNote(['ticket_id' => $ticket->id, 'content' => 'Checking with billing']));
            $note = Conversation::find($data['data']['note']['id'] ?? 0);

            FsTest::assertSame('note', $note ? $note->conversation_type : null, 'conversation_type');
            FsTest::assertSame(1, $fired, 'note_added_by_agent fires once, so mentions notify and activity is logged');
        } finally {
            remove_action('fluent_support/note_added_by_agent', $counter);
        }
    });

    $waitingOn = function ($ticketId) use ($result) {
        $data = $result(TicketTools::getTicket(['ticket_id' => $ticketId, 'with_responses' => false, 'with_integrations' => false]));
        return array_key_exists('waiting_on', $data['data'] ?? []) ? $data['data']['waiting_on'] : 'missing';
    };

    FsTest::case('informational_reply_keeps_the_ticket_waiting_on_an_agent', function () use ($waitingTicket, $waitingOn) {
        $held = $waitingTicket();
        FsTest::assertSame('agent', $waitingOn($held->id), 'a customer message leaves the turn with the agent');
        ResponseTools::replyToTicket(['ticket_id' => $held->id, 'content' => 'Passed to billing', 'informational' => true]);
        FsTest::assertSame('agent', $waitingOn($held->id), 'an informational reply keeps the turn with the agent');

        $answered = $waitingTicket();
        ResponseTools::replyToTicket(['ticket_id' => $answered->id, 'content' => 'Here is the fix']);
        FsTest::assertSame('customer', $waitingOn($answered->id), 'a normal reply hands the turn to the customer');
    });

    FsTest::case('every_ticket_output_says_whose_turn_it_is', function () use ($waitingTicket, $waitingOn, $result) {
        $open = $waitingTicket();

        $list = $result(TicketTools::listTickets(['customer_id' => $open->customer_id]));
        $row = $list['data']['tickets'][0] ?? [];
        FsTest::assertSame('agent', $row['waiting_on'] ?? 'missing', 'list-tickets row');
        FsTest::assert(!array_key_exists('last_reply_by', $row), 'the old last_reply_by field is gone');

        $customerTickets = $result(CustomerTools::getCustomerTickets(['customer_id' => $open->customer_id]));
        FsTest::assertSame('agent', $customerTickets['data']['tickets'][0]['waiting_on'] ?? 'missing', 'get-customer-tickets row');

        $closed = FsFactory::ticket(['status' => 'closed']);
        FsTest::assertSame(null, $waitingOn($closed->id), 'nobody is waiting on a closed ticket');
    });

    FsTest::case('informational_draft_is_rejected', function () use ($errorCode) {
        $ticket = FsFactory::ticket();
        $code = $errorCode(ResponseTools::replyToTicket(['ticket_id' => $ticket->id, 'content' => 'x', 'draft' => true, 'informational' => true]));
        FsTest::assertSame('invalid_param', $code, 'draft + informational');
    });

    FsTest::case('reply_attaches_a_base64_file_to_the_response', function () use ($result, $png, $removeAttachments) {
        $ticket = FsFactory::ticket();

        try {
            $data = $result(ResponseTools::replyToTicket([
                'ticket_id'   => $ticket->id,
                'content'     => 'Screenshot attached',
                'attachments' => [['filename' => 'pixel.png', 'content_base64' => $png]],
            ]));

            $responseId = $data['data']['response']['id'] ?? 0;
            $attachment = Attachment::where('conversation_id', $responseId)->first();

            FsTest::assert((bool) $attachment, 'attachment row linked to the reply');
            if ($attachment) {
                FsTest::assertSame('active', $attachment->status, 'status');
                FsTest::assertSame('image/png', $attachment->file_type, 'file_type');
                FsTest::assert(file_exists($attachment->file_path), 'file stored in the ticket folder');
            }
            FsTest::assertSame('pixel.png', $data['data']['response']['attachments'][0]['title'] ?? null, 'reply payload lists the file');
        } finally {
            $removeAttachments($ticket->id);
        }
    });

    FsTest::case('upload_limits_list_the_extensions_the_check_accepts', function () use ($png, $removeAttachments) {
        ManagementTools::invalidateSupportContextCache();
        $uploads = json_decode(wp_json_encode(ManagementTools::getSupportContext(['include' => ['uploads']])['data']['uploads']), true);
        $extensions = $uploads['allowed_extensions'] ?? [];

        FsTest::assert(in_array('png', $extensions, true), 'png is listed, got: ' . implode(',', $extensions));
        FsTest::assert(!in_array('php', $extensions, true), 'php is never listed');
        FsTest::assert(!array_key_exists('accepted_types', $uploads), 'category labels are gone');
        FsTest::assert(strpos($uploads['note'] ?? '', 'base64') !== false, 'note says the size is of the file, not the base64 text');

        // A listed extension really passes the check.
        $ticket = FsFactory::ticket();
        try {
            $staged = \FluentSupport\App\Modules\MCP\Support\AttachmentStager::stage([['filename' => 'pixel.png', 'content_base64' => $png]], $ticket, \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::resolveAgent());
            FsTest::assert(is_array($staged) && count($staged) === 1, 'listed png is accepted');
        } finally {
            $removeAttachments($ticket->id);
        }
    });

    FsTest::case('disallowed_or_mismatched_files_are_rejected_and_nothing_is_kept', function () use ($errorCode, $png, $removeAttachments) {
        $ticket = FsFactory::ticket();
        $text = base64_encode('<?php echo 1;');

        try {
            $php = ResponseTools::replyToTicket([
                'ticket_id'   => $ticket->id,
                'content'     => 'one',
                'attachments' => [['filename' => 'shell.php', 'content_base64' => $text]],
            ]);
            FsTest::assertSame('file_type_not_allowed', $errorCode($php), 'php extension');

            // The first file is fine; the second lies about its type. Neither may stay.
            $fake = ResponseTools::replyToTicket([
                'ticket_id'   => $ticket->id,
                'content'     => 'two',
                'attachments' => [
                    ['filename' => 'pixel.png', 'content_base64' => $png],
                    ['filename' => 'fake.png', 'content_base64' => base64_encode('not an image')],
                ],
            ]);
            FsTest::assertSame('file_type_not_allowed', $errorCode($fake), 'content does not match .png');

            FsTest::assertSame(0, Attachment::where('ticket_id', $ticket->id)->count(), 'no attachment rows left behind');
            FsTest::assertSame(0, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->count(), 'no reply sent');

            $bad = ResponseTools::replyToTicket([
                'ticket_id'   => $ticket->id,
                'content'     => 'three',
                'attachments' => [['filename' => 'a.png', 'content_base64' => '***not base64***']],
            ]);
            FsTest::assertSame('invalid_param', $errorCode($bad), 'invalid base64');
        } finally {
            $removeAttachments($ticket->id);
        }
    });

    FsTest::case('a_failed_batch_removes_files_a_cloud_driver_already_took', function () use ($errorCode, $png, $removeAttachments) {
        $ticket = FsFactory::ticket();

        // Stands in for a cloud driver: it takes the file off the server as it is staged.
        $toRemote = function ($attachment) {
            if ($attachment->file_path && file_exists($attachment->file_path)) {
                wp_delete_file($attachment->file_path);
            }
            $attachment->driver = 'fs_test_remote';
            $attachment->save();
        };
        $remoteDeletes = [];
        $deleteRemote = function ($attachment) use (&$remoteDeletes) {
            $remoteDeletes[] = (int) $attachment->id;
        };
        add_action('fluent_support/attachment_uploaded_as_temp', $toRemote, 10, 1);
        add_action('fluent_support/delete_remote_attachment_fs_test_remote', $deleteRemote, 10, 1);

        try {
            // The first file is staged and taken remote; the second lies about its type.
            $fake = ResponseTools::replyToTicket([
                'ticket_id'   => $ticket->id,
                'content'     => 'remote rollback',
                'attachments' => [
                    ['filename' => 'pixel.png', 'content_base64' => $png],
                    ['filename' => 'fake.png', 'content_base64' => base64_encode('not an image')],
                ],
            ]);
            FsTest::assertSame('file_type_not_allowed', $errorCode($fake), 'the batch fails on the second file');

            // Remote deletes are queued (StorageManager) and run in the background.
            \FluentSupport\App\Services\Storage\StorageManager::processDeleteQueue();
            FsTest::assertSame(1, count($remoteDeletes), 'the remote copy of the first file is deleted');
            FsTest::assertSame(0, Attachment::where('ticket_id', $ticket->id)->count(), 'no attachment rows left behind');
        } finally {
            remove_action('fluent_support/attachment_uploaded_as_temp', $toRemote, 10);
            remove_action('fluent_support/delete_remote_attachment_fs_test_remote', $deleteRemote, 10);
            Attachment::where('status', \FluentSupport\App\Services\Storage\StorageManager::STATUS_DELETING)->where('driver', 'fs_test_remote')->delete();
            $removeAttachments($ticket->id);
        }
    });

    FsTest::case('bulk_close_can_skip_the_closing_email', function () use ($result) {
        $quiet = FsFactory::ticket();
        $loud = FsFactory::ticket();
        $fired = [];
        $counter = function ($ticket) use (&$fired) {
            $fired[] = (int) $ticket->id;
        };
        add_action('fluent_support/ticket_closed', $counter);

        try {
            $result(ManagementTools::bulkAction(['ticket_ids' => [$quiet->id], 'action' => 'close', 'silent' => true]));
            $result(ManagementTools::bulkAction(['ticket_ids' => [$loud->id], 'action' => 'close']));

            FsTest::assertSame('closed', Ticket::find($quiet->id)->status, 'silent close still closes');
            FsTest::assert(!in_array((int) $quiet->id, $fired, true), 'silent close fires no closing hook, so no closing email');
            FsTest::assert(in_array((int) $loud->id, $fired, true), 'a normal bulk close announces the closure');
        } finally {
            remove_action('fluent_support/ticket_closed', $counter);
        }
    });

    FsTest::case('update_customer_changes_only_the_fields_passed', function () use ($result, $errorCode) {
        $customer = FsFactory::customer(['last_name' => 'Before', 'city' => 'Oulu']);

        $data = $result(CustomerTools::updateCustomer(['customer_id' => $customer->id, 'last_name' => 'After', 'note' => 'Prefers email']));
        $fresh = Customer::find($customer->id);

        FsTest::assertSame('After', $fresh->last_name, 'last_name changed');
        FsTest::assertSame('Prefers email', $fresh->note, 'note changed');
        FsTest::assertSame('Oulu', $fresh->city, 'city untouched');
        FsTest::assertSame((string) $customer->email, (string) $fresh->email, 'email untouched');
        FsTest::assertSame(['last_name', 'note'], $data['data']['changed'] ?? null, 'changed fields reported');

        // Email changes are left to people: a redirect request is a takeover trick.
        $newEmail = 'moved-' . FsTest::uniq('c') . '@example.invalid';
        FsTest::assertSame('not_allowed', $errorCode(CustomerTools::updateCustomer(['customer_id' => $customer->id, 'email' => $newEmail, 'note' => 'ignored'])), 'email change refused');
        FsTest::assertSame((string) $customer->email, (string) Customer::find($customer->id)->email, 'email unchanged after the refused call');
        FsTest::assertSame('Prefers email', (string) Customer::find($customer->id)->note, 'nothing else changed in the refused call');
        FsTest::assertSame('invalid_param', $errorCode(CustomerTools::updateCustomer(['customer_id' => $customer->id])), 'nothing to change');
        FsTest::assertSame('invalid_param', $errorCode(CustomerTools::updateCustomer(['customer_id' => $customer->id, 'first_name' => ''])), 'first_name cannot be cleared');
    });

    FsTest::case('customer_tickets_by_name_lists_the_matches_instead_of_picking_one', function () use ($result, $errorCode) {
        $name = 'Samename' . FsTest::uniq('n');
        $first = FsFactory::customer(['first_name' => $name, 'last_name' => 'Alpha']);
        $second = FsFactory::customer(['first_name' => $name, 'last_name' => 'Beta']);
        $ticket = FsFactory::ticket(['customer_id' => $first->id]);

        $data = $result(CustomerTools::getCustomerTickets(['name' => $name]));
        $ids = array_map('intval', array_column($data['data']['candidates'] ?? [], 'id'));
        sort($ids);

        FsTest::assertSame([(int) $first->id, (int) $second->id], $ids, 'both matches are listed');
        FsTest::assert(array_key_exists('customer', $data['data']) && $data['data']['customer'] === null, 'no customer is picked');
        FsTest::assert(!array_key_exists('tickets', $data['data']), 'no ticket list that could read as an empty result');
        FsTest::assertSame([true, 2], [$data['meta']['ambiguous'] ?? null, $data['meta']['match_count'] ?? null], 'meta says how many matched');

        // The full name, the email and the id each name one customer.
        foreach ([['name' => $name . ' Alpha'], ['email' => $first->email], ['customer_id' => $first->id]] as $args) {
            $one = $result(CustomerTools::getCustomerTickets($args));
            FsTest::assertSame(
                [(int) $first->id, [(int) $ticket->id]],
                [(int) ($one['data']['customer']['id'] ?? 0), array_map('intval', array_column($one['data']['tickets'] ?? [], 'id'))],
                'one customer by ' . key($args)
            );
        }

        FsTest::assertSame('not_found', $errorCode(CustomerTools::getCustomerTickets(['name' => $name . ' Nobody'])), 'no match');
        FsTest::assertSame('not_found', $errorCode(CustomerTools::getCustomerTickets(['name' => '%' . substr($name, 4) . '_'])), '% and _ are literal text, not wildcards');
        FsTest::assertSame(0, (int) $result(CustomerTools::searchCustomers(['search' => $name . '%']))['meta']['paging']['total'], 'search-customers reads % literally');
    });

    FsTest::case('per_page_above_the_maximum_is_reported_in_meta_warnings', function () use ($result) {
        $clamped = $result(AbilitiesRegistrar::warnClampedPerPage(CustomerTools::searchCustomers(['per_page' => 500]), ['per_page' => 500]));
        FsTest::assertSame(\FluentSupport\App\Modules\MCP\Helpers\MCPHelper::MAX_PER_PAGE, $clamped['meta']['paging']['per_page'], 'per_page is clamped');
        FsTest::assertSame(1, count($clamped['meta']['warnings'] ?? []), 'one warning');
        FsTest::assert(strpos($clamped['meta']['warnings'][0], '500') !== false, 'the warning names the value asked for');

        $kept = $result(AbilitiesRegistrar::warnClampedPerPage(CustomerTools::searchCustomers(['per_page' => 5]), ['per_page' => 5]));
        FsTest::assert(!array_key_exists('warnings', $kept['meta']), 'no warning when per_page is within the maximum');
    });

    FsTest::case('update_customer_respects_mailbox_restrictions', function () use ($errorCode) {
        $adminId = get_current_user_id();
        $allowedBox = FsFactory::mailbox();
        $restrictedBox = FsFactory::mailbox();
        $hidden = FsFactory::customer();
        $visible = FsFactory::customer();
        $noTickets = FsFactory::customer();
        FsFactory::ticket(['customer_id' => $hidden->id, 'mailbox_id' => $restrictedBox->id]);
        FsFactory::ticket(['customer_id' => $visible->id, 'mailbox_id' => $allowedBox->id]);

        $viewer = FsFactory::agent();
        $login = FsTest::uniq('fs-mcp-cust');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        $switchTo = function ($id) {
            wp_set_current_user($id);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
        };

        try {
            $viewer->user_id = $userId;
            $viewer->save();
            PermissionManager::attachPermissions($userId, ['fst_manage_own_tickets', 'fst_sensitive_data']);
            $viewer->updateMeta('agent_restrictions', [
                'businessBoxRestrictions' => true,
                'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
            ]);
            $switchTo($userId);

            FsTest::assertSame('forbidden', $errorCode(CustomerTools::updateCustomer(['customer_id' => $hidden->id, 'note' => 'x'])), 'customer only in a restricted mailbox');
            FsTest::assertSame('ok', $errorCode(CustomerTools::updateCustomer(['customer_id' => $visible->id, 'note' => 'x'])), 'customer with a ticket the agent can see');
            FsTest::assertSame('ok', $errorCode(CustomerTools::updateCustomer(['customer_id' => $noTickets->id, 'note' => 'x'])), 'customer with no tickets');
        } finally {
            $switchTo($adminId);
            Meta::where('object_id', $viewer->id)->where('object_type', 'person_meta')->where('key', 'agent_restrictions')->delete();
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            if (!is_wp_error($userId) && get_user_by('ID', $userId)) {
                wp_delete_user($userId);
            }
        }
    });

    FsTest::case('split_ticket_moves_one_message_to_a_new_ticket', function () use ($result, $errorCode) {
        if (!class_exists('\FluentSupportPro\App\Services\ProTicketService')) {
            FsTest::skip('fluent-support-pro is not active');
            return;
        }

        $ticket = FsFactory::ticket();
        $message = FsFactory::conversation($ticket, [
            'person_id'         => $ticket->customer_id,
            'conversation_type' => 'response',
            'content'           => '<p>Second issue ' . FsFactory::marker() . '</p>',
        ]);
        $note = FsFactory::conversation($ticket, ['conversation_type' => 'note', 'content' => '<p>note ' . FsFactory::marker() . '</p>']);

        FsTest::assertSame('not_found', $errorCode(TicketTools::splitTicket(['ticket_id' => $ticket->id, 'response_id' => $note->id])), 'a note cannot be split');

        $data = $result(TicketTools::splitTicket(['ticket_id' => $ticket->id, 'response_id' => $message->id, 'title' => 'Second issue ' . FsFactory::marker()]));
        $newId = (int) ($data['data']['ticket']['id'] ?? 0);
        $new = $newId ? Ticket::find($newId) : null;

        if ($new) {
            FsFactory::adoptRestTicket($new);
        }

        FsTest::assert((bool) $new, 'new ticket created');
        if ($new) {
            FsTest::assertSame((int) $ticket->customer_id, (int) $new->customer_id, 'customer defaults to the message author');
            FsTest::assert(strpos((string) $new->content, 'Second issue') !== false, 'message becomes the opening content');
        }
        FsTest::assert(!Conversation::find($message->id), 'message left the original ticket');
    });

    FsTest::case('split_ticket_is_silent_for_the_customer_unless_asked', function () use ($result) {
        if (!class_exists('\FluentSupportPro\App\Services\ProTicketService')) {
            FsTest::skip('fluent-support-pro is not active');
            return;
        }

        // Turn the customer's "ticket created by agent" email on for this inbox, so a
        // quiet split is quiet because of the tool, not because the email was off.
        $box = FsFactory::mailbox(['box_type' => 'email']);
        $box->saveMeta('_email_ticket_created_by_agent_email_to_customer', [
            'status'        => 'yes',
            'email_subject' => 'Ticket created',
            'email_body'    => '<p>{{ticket.content}}</p>',
        ]);

        $split = function ($notify) use ($box, $result) {
            $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);
            $message = FsFactory::conversation($ticket, [
                'person_id'         => $ticket->customer_id,
                'conversation_type' => 'response',
                'content'           => '<p>Other issue ' . FsFactory::marker() . '</p>',
            ]);
            $email = (string) Customer::find($ticket->customer_id)->email;

            FsTest::interceptMail();
            $data = $result(TicketTools::splitTicket([
                'ticket_id'       => $ticket->id,
                'response_id'     => $message->id,
                'title'           => 'Split ' . FsFactory::marker(),
                'notify_customer' => $notify,
            ]));
            if (!empty($data['data']['ticket']['id'])) {
                FsFactory::adoptRestTicket(Ticket::find($data['data']['ticket']['id']));
            }

            return count(array_filter(FsTest::sentMails(), function ($mail) use ($email) {
                return strpos(wp_json_encode($mail['to'] ?? ''), $email) !== false;
            }));
        };

        FsTest::assertSame(0, $split(false), 'a default split emails the customer nothing');
        FsTest::assert($split(true) > 0, 'notify_customer=true sends the new-ticket email');
    });

    FsTest::case('list_tickets_filters_by_whose_turn_it_is', function () use ($waitingTicket, $result) {
        $owed = $waitingTicket();
        $answered = $waitingTicket();
        ResponseTools::replyToTicket(['ticket_id' => $answered->id, 'content' => 'Here is the fix']);

        $ids = function ($waitingOn) use ($result, $owed, $answered) {
            $data = $result(TicketTools::listTickets(['waiting_on' => $waitingOn, 'search' => FsFactory::marker(), 'per_page' => 100]));
            $found = array_map('intval', array_column($data['data']['tickets'] ?? [], 'id'));
            return [in_array((int) $owed->id, $found, true), in_array((int) $answered->id, $found, true)];
        };

        FsTest::assertSame([true, false], $ids('agent'), 'waiting_on=agent: the owed ticket only');
        FsTest::assertSame([false, true], $ids('customer'), 'waiting_on=customer: the answered ticket only');
    });

    FsTest::case('note_result_reports_mentions_and_whether_they_notify', function () use ($result) {
        $ticket = FsFactory::ticket();
        $colleague = FsFactory::agent();

        $data = $result(ResponseTools::addInternalNote(['ticket_id' => $ticket->id, 'content' => '@[' . $colleague->id . ':Colleague] please check']));
        $mentions = $data['data']['note']['mentions'] ?? null;

        FsTest::assert(is_array($mentions), 'mentions reported');
        FsTest::assertSame((int) $colleague->id, (int) ($mentions['agents'][0]['agent_id'] ?? 0), 'mentioned agent');
        FsTest::assertSame(true, $mentions['agents'][0]['found'] ?? null, 'agent exists');
        FsTest::assert(is_bool($mentions['notified'] ?? null), 'says whether notifications are on');
        FsTest::assert(strpos($mentions['note'] ?? '', $mentions['notified'] ? 'notification' : 'turned off') !== false, 'note matches the flag');

        $plain = $result(ResponseTools::addInternalNote(['ticket_id' => $ticket->id, 'content' => 'no mention here']));
        FsTest::assert(!array_key_exists('mentions', $plain['data']['note'] ?? []), 'no mentions key when nobody is mentioned');
    });

    FsTest::case('schemas_match_what_the_server_accepts', function () {
        $defs = AbilitiesRegistrar::getDefinitions();
        $attachments = $defs['fluent-support/reply-to-ticket']['input_schema']['properties']['attachments'];
        FsTest::assertSame(\FluentSupport\App\Modules\MCP\Support\AttachmentStager::maxFiles(), $attachments['maxItems'], 'attachment maxItems is the site limit');

        foreach (['list-tickets', 'create-ticket', 'update-ticket'] as $tool) {
            $enum = $defs['fluent-support/' . $tool]['input_schema']['properties']['priority']['enum'];
            FsTest::assertSame(['normal', 'medium', 'critical'], $enum, $tool . ' priority values');
        }
    });

    FsTest::case('insights_can_show_one_agent', function () use ($result) {
        $agent = \FluentSupport\App\Modules\MCP\Helpers\MCPHelper::resolveAgent();
        $data = $result(ManagementTools::getSupportInsights(['period' => '7d', 'agent_id' => $agent->id]))['data'];

        FsTest::assert(is_int($data['agent_performance_total'] ?? null), 'total is reported');
        foreach ($data['agent_performance'] as $row) {
            FsTest::assertSame((int) $agent->id, (int) $row['agent_id'], 'only the requested agent');
        }
    });

    FsTest::case('update_ticket_reopens_and_assigns', function () use ($result, $errorCode) {
        $closed = FsFactory::ticket(['status' => 'closed', 'resolved_at' => current_time('mysql')]);

        FsTest::assertSame('ticket_closed', $errorCode(TicketTools::updateTicket(['ticket_id' => $closed->id, 'status' => 'new'])), 'a closed ticket only accepts status=active');

        $reopenHooks = 0;
        $counter = function () use (&$reopenHooks) {
            $reopenHooks++;
        };
        add_action('fluent_support/ticket_reopen', $counter);
        try {
            $data = $result(TicketTools::updateTicket(['ticket_id' => $closed->id, 'status' => 'active', 'priority' => 'critical']));
        } finally {
            remove_action('fluent_support/ticket_reopen', $counter);
        }
        $fresh = Ticket::find($closed->id);
        FsTest::assertSame('active', $fresh->status, 'status=active reopens');
        FsTest::assertSame('critical', $fresh->priority, 'other fields in the same call are saved');
        FsTest::assertSame(1, $reopenHooks, 'the reopen hooks fire once, as in the ticket view');
        FsTest::assert(strpos($data['summary'] ?? '', 'reopened') !== false, 'summary says it was reopened');

        $agent = FsFactory::agent();
        $open = FsFactory::ticket();
        $result(TicketTools::updateTicket(['ticket_id' => $open->id, 'agent_id' => $agent->id]));
        FsTest::assertSame((int) $agent->id, (int) Ticket::find($open->id)->agent_id, 'update-ticket assigns');
    });

    FsTest::case('configuration_tools_are_not_exposed', function () {
        $names = array_merge(array_keys(AbilitiesRegistrar::getDefinitions()), \FluentSupport\App\Modules\MCP\MCPInit::exposedAbilityNames(true));
        foreach (['list-workflows', 'create-saved-reply', 'update-saved-reply', 'delete-saved-reply', 'create-tag', 'assign-ticket', 'reopen-ticket'] as $removed) {
            FsTest::assert(!in_array('fluent-support/' . $removed, $names, true), $removed . ' must not be registered');
        }
        FsTest::assertSame(24, count(AbilitiesRegistrar::getDefinitions()), 'lean tool count');
    });

    FsTest::case('tag_names_pick_existing_tags_and_never_create_one', function () use ($result, $errorCode) {
        $tag = FsFactory::tag(['tag_type' => 'ticket_tag']);
        $ticket = FsFactory::ticket();
        $tagCount = function () {
            return \FluentSupport\App\Models\TicketTag::count();
        };
        $ticketTagIds = function () use ($ticket) {
            return array_map('intval', Ticket::with('tags')->find($ticket->id)->tags->pluck('id')->all());
        };

        $before = $tagCount();

        // Different case and surrounding spaces still pick the existing tag.
        $result(ManagementTools::tagTicket(['ticket_id' => $ticket->id, 'add_tag_names' => ['  ' . strtoupper($tag->title) . ' ']]));
        FsTest::assertSame([(int) $tag->id], $ticketTagIds(), 'the existing tag is applied');
        FsTest::assertSame($before, $tagCount(), 'no tag is created');

        // An unknown name is refused, lists the real tags, and changes nothing,
        // even when a valid name comes with it.
        $other = FsFactory::tag(['tag_type' => 'ticket_tag']);
        $response = ManagementTools::tagTicket(['ticket_id' => $ticket->id, 'add_tag_names' => [$other->title, 'Made Up ' . FsFactory::marker()]]);
        FsTest::assertSame('unknown_tag', $errorCode($response), 'unknown name refused');
        $error = json_decode($response->get_error_message(), true)['error'] ?? [];
        FsTest::assert(in_array($tag->title, $error['available_tags'] ?? [], true), 'the error lists the existing tags');
        FsTest::assertSame([(int) $tag->id], $ticketTagIds(), 'the valid name in the refused call is not applied');
        FsTest::assertSame($before + 1, $tagCount(), 'still no tag created by the MCP');
    });

    FsTest::case('prompts_return_messages_that_name_the_ticket', function () {
        $draft = PromptsRegistrar::draftReply(['ticket_id' => '42', 'guidance' => 'confirm the refund']);
        $text = $draft['messages'][0]['content']['text'] ?? '';

        FsTest::assertSame('user', $draft['messages'][0]['role'] ?? null, 'role');
        FsTest::assert(strpos($text, 'ticket_id=42') !== false, 'names the ticket');
        FsTest::assert(strpos($text, 'draft=true') !== false, 'asks for a draft');
        FsTest::assert(strpos($text, 'confirm the refund') !== false, 'carries the guidance');

        $report = PromptsRegistrar::supportReport(['period' => 'bogus']);
        FsTest::assert(strpos($report['messages'][0]['content']['text'], 'period=7d') !== false, 'unknown period falls back to 7d');
    });

    FsTest::case('every_tool_and_prompt_has_a_permission_entry', function () {
        // An ability missing from AbilityGuard is denied to everyone, admins included.
        $names = array_merge(array_keys(AbilitiesRegistrar::getDefinitions()), array_keys(PromptsRegistrar::getDefinitions()));
        foreach ($names as $name) {
            FsTest::assert(AbilityGuard::check($name), $name . ' must be allowed for an administrator');
        }
    });
};
