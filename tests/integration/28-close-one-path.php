<?php
/**
 * Closing a ticket has one write path (roadmap 4.2): TicketService::close(),
 * and a reply that closes the ticket uses the same steps (applyClose,
 * logClose, fireCloseHooks). Both set the same fields, write the same
 * activity line through the same filters, and fire ticket_closed and
 * ticket_closed_by_{type} once.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    // Runs $fn and counts the close hooks.
    $record = function (callable $fn) {
        $seen = ['closed' => 0, 'by_agent' => 0];
        $onClosed = function () use (&$seen) {
            $seen['closed']++;
        };
        $onByAgent = function () use (&$seen) {
            $seen['by_agent']++;
        };
        add_action('fluent_support/ticket_closed', $onClosed, 10, 0);
        add_action('fluent_support/ticket_closed_by_agent', $onByAgent, 10, 0);
        try {
            $fn();
        } finally {
            remove_action('fluent_support/ticket_closed', $onClosed, 10);
            remove_action('fluent_support/ticket_closed_by_agent', $onByAgent, 10);
        }
        return $seen;
    };

    $closeLines = function ($ticketId) {
        return Conversation::where('ticket_id', $ticketId)
            ->where('conversation_type', 'internal_info')
            ->pluck('content')
            ->toArray();
    };

    $assertClosed = function ($ticketId, $agent, $label) {
        $ticket = Ticket::find($ticketId);
        FsTest::assertSame('closed', $ticket->status, $label . ': status');
        FsTest::assertSame((int) $agent->id, (int) $ticket->closed_by, $label . ': closed_by');
        FsTest::assert(!empty($ticket->resolved_at), $label . ': resolved_at');
    };

    FsTest::case('close_and_reply_close_set_the_same_fields_line_and_hooks', function () use ($record, $closeLines, $assertClosed) {
        $agent = FsFactory::agent();
        // Already the agent's, so the reply doesn't also assign it (that writes its own line).
        $direct = FsFactory::ticket(['agent_id' => $agent->id]);
        $byReply = FsFactory::ticket(['agent_id' => $agent->id]);

        $directSeen = $record(function () use ($direct, $agent) {
            (new TicketService())->close(Ticket::find($direct->id), $agent);
        });
        $replySeen = $record(function () use ($byReply, $agent) {
            (new ResponseService())->createResponse(['content' => '<p>Done</p>', 'close_ticket' => 'yes'], $agent, Ticket::find($byReply->id));
        });

        $assertClosed($direct->id, $agent, 'close()');
        $assertClosed($byReply->id, $agent, 'reply and close');
        FsTest::assertSame(['closed this ticket'], $closeLines($direct->id), 'close() activity line');
        FsTest::assertSame(['closed this ticket'], $closeLines($byReply->id), 'reply-and-close activity line');
        FsTest::assertSame(['closed' => 1, 'by_agent' => 1], $directSeen, 'close() hooks');
        FsTest::assertSame(['closed' => 1, 'by_agent' => 1], $replySeen, 'reply-and-close hooks');
    });

    FsTest::case('close_uses_the_close_note_and_source_filters', function () use ($closeLines) {
        $agent = FsFactory::agent();
        $ticket = FsFactory::ticket();

        $note = function () {
            return 'Closed by the night shift';
        };
        $source = function () {
            return 'automation';
        };
        add_filter('fluent_support/ticket_close_internal_note', $note);
        add_filter('fluent_support/ticket_close_activity_source', $source);
        try {
            (new TicketService())->close(Ticket::find($ticket->id), $agent);
        } finally {
            remove_filter('fluent_support/ticket_close_internal_note', $note);
            remove_filter('fluent_support/ticket_close_activity_source', $source);
        }

        FsTest::assertSame(['Closed by the night shift'], $closeLines($ticket->id), 'filtered activity line');
        FsTest::assertSame('automation', (string) Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->value('source'), 'filtered source');
    });

    FsTest::case('silent_close_writes_the_line_without_hooks', function () use ($record, $closeLines, $assertClosed) {
        $agent = FsFactory::agent();
        $ticket = FsFactory::ticket();

        $seen = $record(function () use ($ticket, $agent) {
            (new TicketService())->close(Ticket::find($ticket->id), $agent, '', 'yes');
        });

        $assertClosed($ticket->id, $agent, 'silent close');
        FsTest::assertSame(['closed this ticket'], $closeLines($ticket->id), 'activity line');
        FsTest::assertSame(['closed' => 0, 'by_agent' => 0], $seen, 'no hooks');
    });

    FsTest::case('closing_a_closed_ticket_changes_nothing', function () use ($record, $closeLines) {
        $agent = FsFactory::agent();
        $ticket = FsFactory::ticket();
        (new TicketService())->close(Ticket::find($ticket->id), $agent);

        $seen = $record(function () use ($ticket, $agent) {
            (new TicketService())->close(Ticket::find($ticket->id), $agent);
        });

        FsTest::assertSame(1, count($closeLines($ticket->id)), 'one activity line');
        FsTest::assertSame(['closed' => 0, 'by_agent' => 0], $seen, 'no hooks');
    });
};
