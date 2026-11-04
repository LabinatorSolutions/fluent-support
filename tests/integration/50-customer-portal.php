<?php
/**
 * Customer portal: a logged-in customer sees their own tickets and nothing else.
 *
 * The portal resolves the customer from the current WP user, so these cases
 * use real WP users linked to factory customers. Portal routes address tickets
 * by serial_number, not the primary key.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();

    /**
     * Become a WP user and drop the cached permission set.
     *
     * @param int $userId
     * @return void
     */
    $becomeUser = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    /**
     * Create a WP user whose email carries the fixture marker.
     *
     * @param string $slug
     * @return int
     */
    $makeWpUser = function ($slug) use ($marker) {
        $login = substr($marker . '-' . $slug, 0, 60);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $marker . '-' . $slug . '@example.invalid',
            'user_pass'  => wp_generate_password(20),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
        }

        return (int) $userId;
    };

    $ownerUserId = $makeWpUser('portal-owner');
    $intruderUserId = $makeWpUser('portal-intruder');

    try {
        $owner = FsFactory::customer([
            'email'   => $marker . '-portal-owner@example.invalid',
            'user_id' => $ownerUserId,
        ]);
        $intruder = FsFactory::customer([
            'email'   => $marker . '-portal-intruder@example.invalid',
            'user_id' => $intruderUserId,
        ]);

        // Serials default to the primary key. Force them apart so a lookup by id
        // can't pass for a lookup by serial.
        $serialBase = 900000 + wp_rand(1, 80000);

        $ownerTicket = FsFactory::ticket([
            'customer_id'   => $owner->id,
            'title'         => 'Portal owner ticket',
            'privacy'       => 'private',
            'serial_number' => $serialBase,
        ]);
        $intruderTicket = FsFactory::ticket([
            'customer_id'   => $intruder->id,
            'title'         => 'Portal intruder ticket',
            'privacy'       => 'private',
            'serial_number' => $serialBase + 1,
        ]);

        $ownerSerial = (int) Ticket::find($ownerTicket->id)->serial_number;

        FsTest::case('portal_fixtures_have_a_serial_number_distinct_from_the_row_id', function () use (
            $ownerTicket, $ownerSerial
        ) {
            FsTest::assert($ownerSerial > 0 && $ownerSerial !== (int) $ownerTicket->id,
                'the portal fixture must be addressable by a serial that is not its id (id '
                . $ownerTicket->id . ', serial ' . $ownerSerial . ')');
        });

        FsTest::case('portal_lists_only_the_logged_in_customers_tickets', function () use (
            $becomeUser, $ownerUserId, $ownerTicket, $intruderTicket
        ) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('GET', '/customer-portal/tickets', [
                'per_page'    => 100,
                'filter_type' => 'all',
            ]);
            FsTest::assertHealthy($result, 'GET /customer-portal/tickets');

            $payload = $result['data']['tickets'];
            if (is_object($payload)) {
                $payload = json_decode(json_encode($payload), true);
            }
            if (!isset($payload['data'])) {
                FsTest::fail('portal ticket list has no data array: ' . wp_json_encode($result['data']));
                return;
            }

            $ids = [];
            foreach ($payload['data'] as $row) {
                $ids[] = (int) ((array) $row)['id'];
            }

            FsTest::assert(in_array((int) $ownerTicket->id, $ids, true),
                'the customer must see their own ticket');
            FsTest::assert(!in_array((int) $intruderTicket->id, $ids, true),
                'the customer must NOT see another customer ticket');
        });

        FsTest::case('portal_list_caps_per_page', function () use ($becomeUser, $ownerUserId) {
            $becomeUser($ownerUserId);
            foreach ([100000 => 100, -5 => 1] as $asked => $expected) {
                $result = FsTest::rest('GET', '/customer-portal/tickets', ['per_page' => $asked]);
                $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
                FsTest::assertSame($expected, (int) ($payload['per_page'] ?? 0), 'per_page ' . $asked);
            }
        });

        FsTest::case('portal_customer_can_read_their_own_ticket', function () use (
            $becomeUser, $ownerUserId, $ownerSerial, $ownerTicket
        ) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('GET', '/customer-portal/tickets/' . $ownerSerial);
            FsTest::assertHealthy($result, 'GET /customer-portal/tickets/{serial}');

            $returned = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
            FsTest::assertSame((int) $ownerTicket->id, $returned, 'the portal returns the ticket we asked for');
        });

        FsTest::case('portal_unknown_ticket_number_is_refused_without_a_fatal_error', function () use (
            $becomeUser, $ownerUserId, $serialBase
        ) {
            $becomeUser($ownerUserId);

            // Past both fixture serials; no ticket in the suite owns it.
            $unknownSerial = $serialBase + 50000;
            FsTest::assert(!Ticket::where('serial_number', $unknownSerial)->exists(), 'the serial is unused');

            $result = FsTest::rest('GET', '/customer-portal/tickets/' . $unknownSerial);

            FsTest::assert(!$result['is_exception'], 'no plugin exception: ' . $result['message']);
            FsTest::assert(strpos($result['message'], 'do not have permission to this support ticket') !== false,
                'the same refusal as a ticket the customer cannot see, got: ' . $result['message']);
        });

        FsTest::case('portal_ticket_opened_by_an_agent_exposes_only_the_agents_name_and_photo', function () use (
            $becomeUser, $ownerUserId, $owner, $serialBase
        ) {
            $agent = FsFactory::agent();
            $ticket = FsFactory::ticket([
                'customer_id'   => $owner->id,
                'title'         => 'Portal on-behalf ticket',
                'privacy'       => 'private',
                'serial_number' => $serialBase + 2,
                'created_by'    => $agent->id,
            ]);
            $serial = (int) Ticket::find($ticket->id)->serial_number;

            $becomeUser($ownerUserId);

            $result = FsTest::rest('GET', '/customer-portal/tickets/' . $serial);
            FsTest::assertHealthy($result, 'GET /customer-portal/tickets/{serial} (on behalf)');

            $payload = json_decode(wp_json_encode($result['data']['ticket'] ?? []), true);

            FsTest::assertSame($agent->full_name, $payload['created_by_agent']['full_name'] ?? null,
                'the portal shows who opened the ticket');
            // The Agent row behind created_by carries email, IP and user id; none of it is the customer's business.
            FsTest::assert(!array_key_exists('created_by_person', $payload),
                'the portal must not serialize the creating agent record, got keys: ' . implode(',', array_keys($payload)));
            FsTest::assert(strpos(wp_json_encode($payload), $agent->email) === false,
                'the creating agent email must not appear anywhere in the portal ticket payload');
        });

        FsTest::case('portal_customer_cannot_read_another_customers_ticket', function () use (
            $becomeUser, $intruderUserId, $ownerSerial, $ownerTicket
        ) {
            $becomeUser($intruderUserId);

            $result = FsTest::rest('GET', '/customer-portal/tickets/' . $ownerSerial);

            $returned = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
            FsTest::assert($returned !== (int) $ownerTicket->id,
                'another customer must never receive ticket ' . $ownerTicket->id
                . ' (status ' . $result['status'] . ')');
            FsTest::assert($result['status'] !== 200,
                'reading another customer ticket must not return 200, got ' . $result['status']);
        });

        FsTest::case('portal_customer_cannot_reply_to_another_customers_ticket', function () use (
            $becomeUser, $intruderUserId, $ownerSerial, $ownerTicket, $marker
        ) {
            $becomeUser($intruderUserId);

            $before = Conversation::where('ticket_id', $ownerTicket->id)->count();

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $ownerSerial . '/responses', [
                'content' => '<p>Intruder reply ' . $marker . '</p>',
            ]);

            FsTest::assert($result['status'] !== 200,
                'replying to another customer ticket must be refused, got ' . $result['status']);

            $after = Conversation::where('ticket_id', $ownerTicket->id)->count();
            FsTest::assertSame((int) $before, (int) $after,
                'a refused reply must not write a conversation row');
        });

        FsTest::case('portal_customer_reply_is_stored_against_their_own_ticket', function () use (
            $becomeUser, $ownerUserId, $ownerSerial, $ownerTicket, $owner, $marker
        ) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $ownerSerial . '/responses', [
                'content' => '<p>Owner reply ' . $marker . '</p>',
            ]);
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/responses');

            $conversation = Conversation::where('ticket_id', $ownerTicket->id)
                ->where('conversation_type', 'response')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$conversation) {
                FsTest::fail('the portal reply was not stored as a conversation');
                return;
            }

            FsTest::assert(strpos((string) $conversation->content, $marker) !== false,
                'the stored reply must be the content we posted');
            FsTest::assertSame((int) $owner->id, (int) $conversation->person_id,
                'the reply is attributed to the logged-in customer');
        });

        FsTest::case('portal_customer_cannot_close_another_customers_ticket', function () use (
            $becomeUser, $intruderUserId, $ownerSerial, $ownerTicket
        ) {
            $becomeUser($intruderUserId);

            $statusBefore = Ticket::find($ownerTicket->id)->status;

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $ownerSerial . '/close');

            FsTest::assert($result['status'] !== 200,
                'closing another customer ticket must be refused, got ' . $result['status']);
            FsTest::assertSame($statusBefore, Ticket::find($ownerTicket->id)->status,
                'a refused close must leave the ticket status untouched');
        });

        FsTest::case('portal_customer_can_close_their_own_ticket', function () use (
            $becomeUser, $ownerUserId, $ownerSerial, $ownerTicket
        ) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $ownerSerial . '/close');
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/close');

            $ticket = Ticket::find($ownerTicket->id);
            FsTest::assertSame('closed', $ticket->status, 'the customer closed their own ticket');
            FsTest::assert(!empty($ticket->resolved_at), 'a portal close must stamp resolved_at');
        });

        FsTest::case('portal_ticket_responses_leave_out_agent_priority_and_mail_internals', function () use (
            $becomeUser, $ownerUserId, $owner, $marker
        ) {
            $private = ['priority', 'message_id', 'content_hash', 'secret_content'];
            $kept = ['id', 'title', 'status', 'serial_number', 'client_priority', 'last_agent_response', 'last_customer_response'];
            // Reply, close and re-open return only what the portal reads back: the status.
            $actionKept = ['id', 'status', 'serial_number'];

            $ticket = FsFactory::ticket(['customer_id' => $owner->id, 'title' => 'Portal fields ' . $marker]);
            Ticket::where('id', $ticket->id)->update([
                'priority'       => 'critical',
                'message_id'     => '<fstest-' . $marker . '@example.invalid>',
                'content_hash'   => md5($marker),
                'secret_content' => 'secret ' . $marker,
            ]);
            $serial = (int) Ticket::find($ticket->id)->serial_number;

            $becomeUser($ownerUserId);

            $check = function ($row, $label, $expectedKeys = null) use ($private, $kept) {
                $expectedKeys = $expectedKeys ?: $kept;
                $row = json_decode(wp_json_encode($row), true);
                if (!is_array($row)) {
                    FsTest::fail($label . ': no ticket in the response');
                    return;
                }
                foreach ($private as $key) {
                    FsTest::assert(!array_key_exists($key, $row), $label . ': ' . $key . ' left out');
                }
                foreach ($expectedKeys as $key) {
                    FsTest::assert(array_key_exists($key, $row), $label . ': ' . $key . ' kept');
                }
            };

            $list = FsTest::rest('GET', '/customer-portal/tickets', ['per_page' => 100, 'filter_type' => 'all']);
            FsTest::assertHealthy($list, 'list');
            $rows = json_decode(wp_json_encode($list['data']['tickets']), true);
            $row = null;
            foreach ((array) ($rows['data'] ?? []) as $candidate) {
                if ((int) $candidate['id'] === (int) $ticket->id) {
                    $row = $candidate;
                }
            }
            $check($row, 'list');

            $single = FsTest::rest('GET', '/customer-portal/tickets/' . $serial);
            FsTest::assertHealthy($single, 'single');
            $check($single['data']['ticket'] ?? null, 'single');

            $reply = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/responses', ['content' => '<p>Fields ' . $marker . '</p>']);
            FsTest::assertHealthy($reply, 'reply');
            $check($reply['data']['ticket'] ?? null, 'reply', $actionKept);

            $closed = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/close');
            FsTest::assertHealthy($closed, 'close');
            $check($closed['data']['ticket'] ?? null, 'close', $actionKept);

            $reopened = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/re-open');
            FsTest::assertHealthy($reopened, 'reopen');
            $check($reopened['data']['ticket'] ?? null, 'reopen', $actionKept);

            $created = FsTest::rest('POST', '/customer-portal/tickets', ['title' => 'Created ' . $marker, 'content' => '<p>New ' . $marker . '</p>']);
            FsTest::assertHealthy($created, 'create');
            $createdRow = json_decode(wp_json_encode($created['data']['ticket'] ?? null), true);
            foreach ($private as $key) {
                FsTest::assert(is_array($createdRow) && !array_key_exists($key, $createdRow), 'create: ' . $key . ' left out');
            }
            if (!empty($createdRow['id'])) {
                FsFactory::adoptRestTicket(Ticket::find($createdRow['id']));
            }
        });

        FsTest::case('portal_me_endpoint_reports_the_logged_in_user', function () use (
            $becomeUser, $ownerUserId, $owner
        ) {
            $becomeUser($ownerUserId);

            $result = FsTest::rest('GET', '/customer-portal/me');
            FsTest::assertHealthy($result, 'GET /customer-portal/me');

            FsTest::assertSame((int) $ownerUserId, (int) $result['data']['user_id'], 'me.user_id');
            FsTest::assertSame($owner->email, (string) $result['data']['email'], 'me.email');
        });

        // FS-PORTAL-001: a signed link must not open a blocked customer's ticket.
        FsTest::case('portal_signed_link_refuses_a_blocked_customer', function () use ($becomeUser, $adminId) {
            if (!\FluentSupport\App\Services\Helper::isPublicSignedTicketEnabled()) {
                FsTest::skip('signed ticket links are off in this lab');
            }

            $blocked = FsFactory::customer(['status' => 'blocked']);
            $blockedTicket = FsFactory::ticket(['customer_id' => $blocked->id]);
            $activeTicket = FsFactory::ticket();

            $becomeUser(0);
            try {
                $refused = FsTest::rest('GET', '/customer-portal/tickets/' . $blockedTicket->serial_number, ['intended_ticket_hash' => $blockedTicket->hash]);
                $opened = FsTest::rest('GET', '/customer-portal/tickets/' . $activeTicket->serial_number, ['intended_ticket_hash' => $activeTicket->hash]);
            } finally {
                $becomeUser($adminId);
            }

            FsTest::assertSame(403, (int) $refused['status'], 'blocked customer, signed link');
            FsTest::assertHealthy($opened, 'active customer, signed link');
        });
    } finally {
        $becomeUser($adminId);

        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ([$ownerUserId, $intruderUserId] as $userId) {
            if ($userId) {
                wp_delete_user($userId);
            }
        }
    }
};
