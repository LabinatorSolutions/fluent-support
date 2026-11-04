<?php
/**
 * Ticket and reply content is kses'd as sent, never entity-decoded.
 *
 * Decoding after kses turns &lt;script&gt; into a live tag; decoding before
 * kses makes it strip typed text like x&lt;5 and y&gt;3 as a bogus tag.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $marker = FsFactory::marker();
    $adminId = get_current_user_id();

    // Encoded markup kses would let through as text, next to markup that must survive.
    $encoded = '&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(2)&gt;';
    $kept = '<strong>keep me</strong>';
    // A typed < or > arrives entity-encoded and must be stored as text.
    $text = 'x&lt;5 and y&gt;3 List&lt;String&gt;';
    $body = '<p>' . $encoded . '</p><p>' . $kept . '</p><p>' . $text . '</p>';

    /**
     * Assert the content holds no live markup and keeps allowed tags and typed text.
     *
     * @param string $content
     * @param string $label
     * @return void
     */
    $assertSanitized = function ($content, $label) use ($kept, $text) {
        $content = (string) $content;

        FsTest::assert(stripos($content, '<script') === false,
            $label . ' must not contain a live <script> tag, got: ' . $content);
        FsTest::assert(stripos($content, '<img') === false,
            $label . ' must not contain a live <img> tag, got: ' . $content);
        FsTest::assert(strpos($content, $kept) !== false,
            $label . ' must keep allowed markup, got: ' . $content);
        FsTest::assert(strpos($content, $text) !== false,
            $label . ' must keep typed angle-bracket text, got: ' . $content);
    };

    /**
     * Adopt every ticket carrying the marker, so a failing case is still cleaned up.
     *
     * @return void
     */
    $adoptTickets = function () use ($marker) {
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
            FsFactory::adoptRestTicket($persisted);
        }
    };

    // HTML5-only entity names: wp_kses_post() only knows the HTML 4.01
    // table, so &epsi;/&upsi;/&ohm; must be decoded to characters before kses runs, or
    // their leading & gets escaped to &amp; and the literal entity text is stored.
    $entityBody = '<p>&Xi;&epsi;&sigma;&kappa; &psi;&upsi;&chi;&omicron; &ohm;</p>'
        . '<p>x&lt;5 and &lt;b&gt;</p><p>typed &amp;epsi;</p>';

    /**
     * Assert HTML5-only entities resolved to characters, markup entities stayed encoded.
     *
     * @param string $content
     * @param string $label
     * @return void
     */
    $assertEntitiesDecoded = function ($content, $label) {
        $content = (string) $content;

        FsTest::assert(strpos($content, 'Ξεσκ ψυχο Ω') !== false,
            $label . ' must decode HTML5-only entities (epsi/upsi/ohm) to characters, got: ' . $content);
        FsTest::assert(strpos($content, 'x&lt;5 and &lt;b&gt;') !== false,
            $label . ' must keep encoded angle brackets as text, got: ' . $content);
        FsTest::assert(stripos($content, '<b>') === false,
            $label . ' must not turn encoded markup into a live tag, got: ' . $content);
        FsTest::assert(strpos($content, 'typed &amp;epsi;') !== false,
            $label . ' must keep a literally typed entity name as text, got: ' . $content);
    };

    FsTest::case('agent_created_ticket_does_not_store_decoded_markup', function () use ($marker, $body, $assertSanitized, $adoptTickets) {
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        $result = FsTest::rest('POST', '/tickets', [
            'ticket' => [
                'customer_id' => $customer->id,
                'mailbox_id'  => $mailbox->id,
                'title'       => 'Kses order agent ' . $marker,
                'content'     => $body,
                'priority'    => 'normal',
            ],
        ]);

        $adoptTickets();
        FsTest::assertHealthy($result, 'POST /tickets');

        $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
        $stored = $created ? Ticket::find($created) : null;

        if (!$stored) {
            FsTest::fail('POST /tickets did not store a ticket. payload: ' . wp_json_encode($result['data']));
            return;
        }

        $assertSanitized($stored->content, 'fs_tickets.content');
    });

    FsTest::case('agent_reply_response_does_not_return_decoded_markup', function () use ($marker, $body, $assertSanitized) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Kses order reply ' . $marker, 'status' => 'new']);

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => $body,
            'conversation_type' => 'response',
        ]);
        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

        $content = isset($result['data']['response']['content']) ? (string) $result['data']['response']['content'] : '';

        if ($content === '') {
            FsTest::fail('POST /tickets/{id}/responses returned no response content. payload: ' . wp_json_encode($result['data']));
            return;
        }

        $assertSanitized($content, 'the returned reply');
    });

    FsTest::case('agent_created_ticket_decodes_html5_only_entities', function () use ($marker, $entityBody, $assertEntitiesDecoded, $adoptTickets) {
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        $result = FsTest::rest('POST', '/tickets', [
            'ticket' => [
                'customer_id' => $customer->id,
                'mailbox_id'  => $mailbox->id,
                'title'       => 'Kses entities agent ' . $marker,
                'content'     => $entityBody,
                'priority'    => 'normal',
            ],
        ]);

        $adoptTickets();
        FsTest::assertHealthy($result, 'POST /tickets');

        $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
        $stored = $created ? Ticket::find($created) : null;

        if (!$stored) {
            FsTest::fail('POST /tickets did not store a ticket. payload: ' . wp_json_encode($result['data']));
            return;
        }

        $assertEntitiesDecoded($stored->content, 'fs_tickets.content');
    });

    FsTest::case('agent_reply_response_decodes_html5_only_entities', function () use ($marker, $entityBody, $assertEntitiesDecoded) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Kses entities reply ' . $marker, 'status' => 'new']);

        $result = FsTest::rest('POST', '/tickets/' . $ticket->id . '/responses', [
            'content'           => $entityBody,
            'conversation_type' => 'response',
        ]);
        FsTest::assertHealthy($result, 'POST /tickets/{id}/responses');

        $content = isset($result['data']['response']['content']) ? (string) $result['data']['response']['content'] : '';

        if ($content === '') {
            FsTest::fail('POST /tickets/{id}/responses returned no response content. payload: ' . wp_json_encode($result['data']));
            return;
        }

        $assertEntitiesDecoded($content, 'the returned reply');
    });

    FsTest::case('piped_email_does_not_store_decoded_markup', function () use ($marker, $body, $assertSanitized, $adoptTickets) {
        $handler = 'FluentSupportPro\App\Services\Integrations\FluentEmailPiping\ByMailHandler';
        if (!class_exists($handler)) {
            FsTest::skip('fluent-support-pro is not active');
            return;
        }

        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox(['box_type' => 'email']);
        $mimeId = $marker . '-kses-pipe';

        $result = $handler::processPayload([
            'subject'   => 'Kses order piping ' . $marker,
            'body_text' => $body,
            'messageId' => '<' . $mimeId . '@example.invalid>',
            'from'      => ['value' => [['name' => 'Kses Pipe', 'address' => $customer->email]]],
            'to'        => ['value' => [['address' => $mailbox->email]]],
        ], $mailbox, $mimeId);

        $adoptTickets();

        $created = is_array($result) && !empty($result['ticket_id']) ? (int) $result['ticket_id'] : 0;
        $stored = $created ? Ticket::find($created) : null;

        if (!$stored) {
            FsTest::fail('processPayload did not store a ticket. result: ' . wp_json_encode(is_wp_error($result) ? $result->get_error_message() : $result));
            return;
        }

        $assertSanitized($stored->content, 'piped fs_tickets.content');
    });

    $login = substr($marker . '-kses-portal', 0, 60);
    $userId = wp_insert_user([
        'user_login' => $login,
        'user_email' => $marker . '-kses-portal@example.invalid',
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
    }

    try {
        $customer = FsFactory::customer([
            'email'   => $marker . '-kses-portal@example.invalid',
            'user_id' => $userId,
        ]);
        $mailbox = FsFactory::mailbox();

        FsTest::case('portal_created_ticket_does_not_store_decoded_markup', function () use ($marker, $body, $userId, $mailbox, $assertSanitized, $adoptTickets) {
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);

            $result = FsTest::rest('POST', '/customer-portal/tickets', [
                'title'      => 'Kses order portal ' . $marker,
                'content'    => $body,
                'mailbox_id' => $mailbox->id,
            ]);

            $adoptTickets();
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets');

            $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
            $stored = $created ? Ticket::find($created) : null;

            if (!$stored) {
                FsTest::fail('POST /customer-portal/tickets did not store a ticket. payload: ' . wp_json_encode($result['data']));
                return;
            }

            $assertSanitized($stored->content, 'fs_tickets.content');
        });

        FsTest::case('portal_reply_does_not_store_decoded_markup', function () use ($marker, $body, $userId, $customer, $assertSanitized) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Kses order portal reply ' . $marker]);
            $serial = (int) Ticket::find($ticket->id)->serial_number;

            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/responses', [
                'content' => $body,
            ]);
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/responses');

            $stored = Conversation::where('ticket_id', $ticket->id)
                ->where('conversation_type', 'response')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$stored) {
                FsTest::fail('POST /customer-portal/tickets/{serial}/responses stored no reply. payload: ' . wp_json_encode($result['data']));
                return;
            }

            $assertSanitized($stored->content, 'fs_conversations.content');
        });

        FsTest::case('portal_created_ticket_decodes_html5_only_entities', function () use ($marker, $entityBody, $userId, $mailbox, $assertEntitiesDecoded, $adoptTickets) {
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);

            $result = FsTest::rest('POST', '/customer-portal/tickets', [
                'title'      => 'Kses entities portal ' . $marker,
                'content'    => $entityBody,
                'mailbox_id' => $mailbox->id,
            ]);

            $adoptTickets();
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets');

            $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
            $stored = $created ? Ticket::find($created) : null;

            if (!$stored) {
                FsTest::fail('POST /customer-portal/tickets did not store a ticket. payload: ' . wp_json_encode($result['data']));
                return;
            }

            $assertEntitiesDecoded($stored->content, 'fs_tickets.content');
        });

        FsTest::case('portal_reply_decodes_html5_only_entities', function () use ($marker, $entityBody, $userId, $customer, $assertEntitiesDecoded) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Kses entities portal reply ' . $marker]);
            $serial = (int) Ticket::find($ticket->id)->serial_number;

            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);

            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/responses', [
                'content' => $entityBody,
            ]);
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/responses');

            $stored = Conversation::where('ticket_id', $ticket->id)
                ->where('conversation_type', 'response')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$stored) {
                FsTest::fail('POST /customer-portal/tickets/{serial}/responses stored no reply. payload: ' . wp_json_encode($result['data']));
                return;
            }

            $assertEntitiesDecoded($stored->content, 'fs_conversations.content');
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
