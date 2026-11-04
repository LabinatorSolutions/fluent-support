<?php
/**
 * Ticket lifecycle through the routes the admin app calls: create, read,
 * agent reply, close, reopen.
 *
 * Assertions read back from the DB, so a controller that reports success
 * without writing still fails.
 */

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $marker = FsFactory::marker();
    $customer = FsFactory::customer();
    $mailbox = FsFactory::mailbox();
    $agent = Helper::getAgentByUserId();

    $ticketId = null;

    // Runs $fn() as a freshly-created agent who has ONLY fst_draft_reply (no
    // fst_manage_tickets/fst_manage_own_tickets/etc.), then restores the admin
    // user and deletes the WP user. The WP user isn't FsFactory-tracked, so
    // cleanup happens here, not in the suite-wide teardown.
    $withDraftOnlyAgent = function (callable $fn) {
        $adminId = get_current_user_id();
        $login = FsTest::uniq('fs-lifecycle-drafter');
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            FsTest::fail('Could not create draft-only user: ' . $userId->get_error_message());
            return;
        }

        $switchTo = function ($id) {
            wp_set_current_user($id);
            PermissionManager::currentUserPermissions(false);
            AgentTicketAccess::resetCache();
        };

        try {
            $drafter = FsFactory::agent();
            $drafter->user_id = $userId;
            $drafter->save();
            PermissionManager::attachPermissions($userId, ['fst_draft_reply']);

            $switchTo($userId);
            $fn();
        } finally {
            $switchTo($adminId);
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    };

    FsTest::case('rest_created_ticket_starts_new_with_no_responses', function () use (
        $marker, $customer, $mailbox, &$ticketId
    ) {
        $result = FsTest::rest('POST', '/tickets', [
            'ticket' => [
                'customer_id' => $customer->id,
                'mailbox_id'  => $mailbox->id,
                'title'       => 'Lifecycle ticket ' . $marker,
                'content'     => '<p>Lifecycle body ' . $marker . '</p>',
                'priority'    => 'normal',
            ],
        ]);

        // Adopt persisted rows before asserting, so a write that then errors
        // still gets cleaned up.
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
            FsFactory::adoptRestTicket($persisted);
        }

        FsTest::assertHealthy($result, 'POST /tickets');

        $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
        if (!$created) {
            FsTest::fail('POST /tickets did not return a ticket id. payload: ' . wp_json_encode($result['data']));
            return;
        }

        $ticket = Ticket::find($created);
        if (!$ticket) {
            FsTest::fail('POST /tickets returned id ' . $created . ' but no fs_tickets row exists.');
            return;
        }

        $ticketId = $created;

        // Tripwire rows Ticket::deleting must sweep at cleanup, so the factory's
        // dependent-row checks aren't vacuous. purgeAttachments() tolerates the missing file.
        Meta::create([
            'object_type' => 'ticket_meta',
            'object_id'   => $created,
            'key'         => 'fs_test_cascade_tripwire',
            'value'       => $marker,
        ]);
        Attachment::create([
            'ticket_id' => $created,
            'person_id' => (int) $customer->id,
            'file_type' => 'text/plain',
            'file_path' => '/nonexistent/' . $marker . '.txt',
            'full_url'  => '',
            'title'     => 'cascade tripwire ' . $marker,
            'driver'    => 'local',
            'file_size' => 1,
            'status'    => 'active',
        ]);

        // A brand new ticket is unanswered: 'new' until an agent replies.
        FsTest::assertSame('new', $ticket->status, 'new ticket status');
        FsTest::assertSame((int) $customer->id, (int) $ticket->customer_id, 'ticket customer_id');
        FsTest::assertSame((int) $mailbox->id, (int) $ticket->mailbox_id, 'ticket mailbox_id');
        FsTest::assertSame(0, (int) $ticket->response_count, 'new ticket response_count');
        FsTest::assert($ticket->resolved_at === null || $ticket->resolved_at === '',
            'new ticket must not carry resolved_at, got: ' . var_export($ticket->resolved_at, true));
        FsTest::assert((int) $ticket->serial_number > 0,
            'ticket must get a serial_number (public identifier), got: ' . var_export($ticket->serial_number, true));
    });

    FsTest::case('get_ticket_returns_the_created_ticket', function () use (&$ticketId, $marker) {
        if (!$ticketId) {
            FsTest::skip('no ticket was created by the previous case');
            return;
        }

        $result = FsTest::rest('GET', '/tickets/' . $ticketId);
        FsTest::assertHealthy($result, 'GET /tickets/{id}');

        $returnedId = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
        FsTest::assertSame((int) $ticketId, $returnedId, 'GET /tickets/{id} returns the requested ticket');

        $title = isset($result['data']['ticket']['title']) ? (string) $result['data']['ticket']['title'] : '';
        FsTest::assert(strpos($title, $marker) !== false,
            'returned ticket title must be our fixture, got: ' . $title);
    });

    FsTest::case('agent_response_activates_ticket_and_counts_the_reply', function () use (
        &$ticketId, $marker, $agent
    ) {
        if (!$ticketId) {
            FsTest::skip('no ticket was created by the first case');
            return;
        }

        $result = FsTest::rest('POST', '/tickets/' . $ticketId . '/responses', [
            'content'           => '<p>Agent reply ' . $marker . '</p>',
            'conversation_type' => 'response',
        ]);

        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

        $responseId = isset($result['data']['response']['id']) ? (int) $result['data']['response']['id'] : 0;
        FsTest::assert($responseId > 0, 'response endpoint must return the created conversation id');

        $conversation = Conversation::find($responseId);
        if (!$conversation) {
            FsTest::fail('no fs_conversations row for returned response id ' . $responseId);
            return;
        }

        FsTest::assertSame((int) $ticketId, (int) $conversation->ticket_id, 'conversation ticket_id');
        FsTest::assertSame('response', $conversation->conversation_type, 'conversation type');
        FsTest::assertSame((int) $agent->id, (int) $conversation->person_id, 'conversation author is the acting agent');

        // Tripwires Conversation::deleting must sweep when the ticket cascades.
        Meta::create([
            'object_type' => 'response',
            'object_id'   => $responseId,
            'key'         => 'fs_test_cascade_tripwire',
            'value'       => $marker,
        ]);
        Attachment::create([
            'ticket_id'       => (int) $ticketId,
            'conversation_id' => $responseId,
            'person_id'       => (int) $agent->id,
            'file_type'       => 'text/plain',
            'file_path'       => '/nonexistent/' . $marker . '-reply.txt',
            'full_url'        => '',
            'title'           => 'cascade tripwire reply ' . $marker,
            'driver'          => 'local',
            'file_size'       => 1,
            'status'          => 'active',
        ]);

        $ticket = Ticket::find($ticketId);
        // The first agent response moves 'new' to 'active' and records the wait.
        FsTest::assertSame('active', $ticket->status, 'status after first agent reply');
        FsTest::assertSame(1, (int) $ticket->response_count, 'response_count after one reply');
        FsTest::assert($ticket->first_response_time !== null,
            'first_response_time must be recorded on the first agent reply');
        FsTest::assert(!empty($ticket->last_agent_response),
            'last_agent_response must be stamped on an agent reply');
        // An unassigned ticket is claimed by the replying agent.
        FsTest::assertSame((int) $agent->id, (int) $ticket->agent_id, 'replying agent is assigned the ticket');
    });

    FsTest::case('closing_a_ticket_stamps_resolved_at_and_logs_an_internal_note', function () use (
        &$ticketId, $agent
    ) {
        if (!$ticketId) {
            FsTest::skip('no ticket was created by the first case');
            return;
        }

        $notesBefore = Conversation::where('ticket_id', $ticketId)
            ->where('conversation_type', 'internal_info')
            ->count();

        $result = FsTest::rest('POST', '/tickets/' . $ticketId . '/close');
        FsTest::assertHealthy($result, 'POST /tickets/{id}/close');

        $ticket = Ticket::find($ticketId);
        FsTest::assertSame('closed', $ticket->status, 'status after close');
        FsTest::assert(!empty($ticket->resolved_at), 'close must stamp resolved_at');
        FsTest::assertSame((int) $agent->id, (int) $ticket->closed_by, 'closed_by is the acting agent');
        FsTest::assert($ticket->total_close_time !== null, 'close must record total_close_time');

        $notesAfter = Conversation::where('ticket_id', $ticketId)
            ->where('conversation_type', 'internal_info')
            ->count();
        FsTest::assertSame($notesBefore + 1, $notesAfter, 'close writes one internal_info conversation');

        // Closing must not be counted as a customer-visible reply.
        FsTest::assertSame(1, (int) $ticket->response_count, 'response_count is unchanged by closing');
    });

    FsTest::case('reopening_a_closed_ticket_clears_resolved_at', function () use (&$ticketId) {
        if (!$ticketId) {
            FsTest::skip('no ticket was created by the first case');
            return;
        }

        $result = FsTest::rest('POST', '/tickets/' . $ticketId . '/re-open');
        FsTest::assertHealthy($result, 'POST /tickets/{id}/re-open');

        $ticket = Ticket::find($ticketId);
        FsTest::assertSame('active', $ticket->status, 'status after reopen');
        FsTest::assert(empty($ticket->resolved_at),
            'reopen must clear resolved_at, got: ' . var_export($ticket->resolved_at, true));
    });

    FsTest::case('ticket_status_can_be_changed_through_the_property_endpoint', function () use (&$ticketId) {
        if (!$ticketId) {
            FsTest::skip('no ticket was created by the first case');
            return;
        }

        $result = FsTest::rest('PUT', '/tickets/' . $ticketId . '/property', [
            'prop_name'  => 'priority',
            'prop_value' => 'critical',
        ]);
        FsTest::assertHealthy($result, 'PUT /tickets/{id}/property (priority)');

        FsTest::assertSame('critical', Ticket::find($ticketId)->priority, 'priority after property update');

        // Only allowlisted columns may be written here.
        $rejected = FsTest::rest('PUT', '/tickets/' . $ticketId . '/property', [
            'prop_name'  => 'customer_id',
            'prop_value' => '999999',
        ]);
        FsTest::assert($rejected['status'] !== 200,
            'customer_id must not be writable through the generic property endpoint (status '
            . $rejected['status'] . ')');
    });

    // S-1: fst_draft_reply alone must never be enough to close a ticket. The
    // controller used to skip its manage-permission check for any
    // conversation_type=draft_response request, but ResponseService honors
    // close_ticket regardless of conversation type — so a draft-only agent
    // could close a ticket just by adding close_ticket=yes to a draft save.
    FsTest::case('draft_only_agent_cannot_close_ticket_via_close_ticket_param', function () use (
        $marker, $withDraftOnlyAgent
    ) {
        $ticket = FsFactory::ticket(['status' => 'active', 'title' => 'Draft-only close attempt ' . $marker]);

        $withDraftOnlyAgent(function () use ($ticket) {
            $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                'content'           => 'saving my draft',
                'conversation_type' => 'draft_response',
                'close_ticket'      => 'yes',
            ]);

            FsTest::assert(!in_array((int) $result['status'], [200, 201], true),
                'a draft-only agent sending close_ticket=yes must be rejected, got status ' . $result['status']);
        });

        $fresh = Ticket::find($ticket->id);
        FsTest::assertSame('active', $fresh->status,
            'ticket must stay open: a draft-only agent must not be able to close it via close_ticket=yes');
        FsTest::assert(empty($fresh->resolved_at), 'resolved_at must not be stamped');
        FsTest::assert(empty($fresh->closed_by), 'closed_by must not be stamped');
        FsTest::assertSame(0, Conversation::where('ticket_id', $ticket->id)->count(),
            'no conversation, draft or otherwise, should be stored once the request is rejected');
    });

    // Guards against an overcorrection: the fix above must not block a
    // draft-only agent from saving an ordinary draft (no close_ticket).
    FsTest::case('draft_only_agent_can_still_save_a_plain_draft', function () use (
        $marker, $withDraftOnlyAgent
    ) {
        $ticket = FsFactory::ticket(['status' => 'active', 'title' => 'Draft-only plain draft ' . $marker]);

        $withDraftOnlyAgent(function () use ($ticket) {
            $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
                'content'           => 'a harmless draft',
                'conversation_type' => 'draft_response',
            ]);

            FsTest::assertHealthy($result, 'draft-only agent saving a plain draft must still succeed');
        });

        $fresh = Ticket::find($ticket->id);
        FsTest::assertSame('active', $fresh->status, 'a plain draft must not change ticket status');
        FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'draft_response')->count(), 'the draft itself must be saved');
    });
};
