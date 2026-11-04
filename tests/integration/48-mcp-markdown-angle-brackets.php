<?php
/**
 * MCP markdown content must keep typed angle brackets as text.
 *
 * Parsedown runs without safe mode so markdown can carry inline HTML. A stray
 * "<" in text (x<5, List<String>) must still survive kses instead of being
 * stripped as an unknown tag.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\ResponseTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;

return function () {

    $marker = FsFactory::marker();

    FsTest::case('mcp_markdown_ticket_keeps_typed_angle_brackets', function () use ($marker) {
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        TicketTools::createTicket([
            'title'          => 'Mcp markdown angle ' . $marker,
            'content'        => "My script fails when x<5 and y>3. List<String> failed. Also <Component/> breaks.\n\n**bold** <script>alert(1)</script>",
            'customer_email' => $customer->email,
            'mailbox_id'     => $mailbox->id,
        ]);

        $stored = null;
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
            FsFactory::adoptRestTicket($persisted);
            $stored = $persisted;
        }

        if (!$stored) {
            FsTest::fail('MCP createTicket did not store a ticket');
            return;
        }

        $content = (string) $stored->content;

        FsTest::assert(strpos($content, 'x&lt;5 and y&gt;3') !== false,
            'MCP ticket must keep x<5 and y>3 as text, got: ' . $content);
        FsTest::assert(strpos($content, 'List&lt;String&gt;') !== false,
            'MCP ticket must keep List<String> as text, got: ' . $content);
        FsTest::assert(strpos($content, '&lt;Component/&gt;') !== false,
            'MCP ticket must keep <Component/> as text, got: ' . $content);
        FsTest::assert(strpos($content, '<strong>bold</strong>') !== false,
            'MCP ticket must still render markdown, got: ' . $content);
        FsTest::assert(stripos($content, '<script') === false,
            'MCP ticket must not contain a live <script> tag, got: ' . $content);
    });

    // HTML5-only entity names: wp_kses_post() only knows the HTML 4.01
    // table, so &epsi;/&upsi;/&ohm; must be decoded to characters before kses runs, or
    // their leading & gets escaped to &amp; and the literal entity text is stored.
    $assertEntitiesDecoded = function ($content, $label) {
        $content = (string) $content;

        FsTest::assert(strpos($content, 'Ξεσκ ψυχο Ω') !== false,
            $label . ' must decode HTML5-only entities (epsi/upsi/ohm) to characters, got: ' . $content);
        FsTest::assert(strpos($content, 'x&lt;5') !== false,
            $label . ' must keep typed angle-bracket text encoded, got: ' . $content);
        FsTest::assert(stripos($content, '<b>') === false,
            $label . ' must not turn encoded markup into a live tag, got: ' . $content);
    };

    FsTest::case('mcp_create_ticket_decodes_html5_only_entities', function () use ($marker, $assertEntitiesDecoded) {
        $customer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        TicketTools::createTicket([
            'title'          => 'Mcp entities ticket ' . $marker,
            'content'        => '&Xi;&epsi;&sigma;&kappa; &psi;&upsi;&chi;&omicron; &ohm; x&lt;5 and &lt;b&gt;',
            'content_format' => 'html',
            'customer_email' => $customer->email,
            'mailbox_id'     => $mailbox->id,
        ]);

        $stored = null;
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
            FsFactory::adoptRestTicket($persisted);
            $stored = $persisted;
        }

        if (!$stored) {
            FsTest::fail('MCP createTicket did not store a ticket');
            return;
        }

        $assertEntitiesDecoded($stored->content, 'MCP-created ticket content');
    });

    FsTest::case('mcp_reply_decodes_html5_only_entities', function () use ($marker, $assertEntitiesDecoded) {
        $customer = FsFactory::customer();
        $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'title' => 'Mcp entities reply ' . $marker, 'status' => 'new']);

        $result = ResponseTools::replyToTicket([
            'ticket_id'      => $ticket->id,
            'content'        => '&Xi;&epsi;&sigma;&kappa; &psi;&upsi;&chi;&omicron; &ohm; x&lt;5 and &lt;b&gt;',
            'content_format' => 'html',
        ]);

        if (is_wp_error($result)) {
            FsTest::fail('MCP reply returned an error: ' . $result->get_error_message());
            return;
        }

        $stored = Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'response')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$stored) {
            FsTest::fail('MCP reply stored no conversation row');
            return;
        }

        $assertEntitiesDecoded($stored->content, 'MCP reply content');
    });
};
