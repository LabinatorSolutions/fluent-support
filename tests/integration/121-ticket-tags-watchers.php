<?php
/**
 * Tags and watchers through TicketService (dev-works/tickets-architecture.md 3.1):
 * - A tag or watcher is added once, even when an id repeats in one call.
 * - Only ticket tags and agents are attached; other ids are ignored.
 * - Syncing watchers makes them exactly the given agents, also on a ticket
 *   with none (it added nothing there).
 * - ticket_watchers_added / ticket_watchers_removed carry the agent ids.
 */

use FluentSupport\App\Models\TagPivot;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {
    $service = new TicketService();
    $rows = fn($ticket, $type) => array_map('intval', TagPivot::where('source_type', $type)->where('source_id', $ticket->id)->orderBy('tag_id')->pluck('tag_id')->all());

    FsTest::case('tags/watchers: a tag repeated in one call is added once', function () use ($service, $rows) {
        $ticket = FsFactory::ticket();
        $tag = FsFactory::tag(['tag_type' => 'ticket_tag']);
        $fired = 0;
        $count = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/ticket_tag_added', $count);
        try {
            FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'assign_tags', 'ticket_ids' => [$ticket->id], 'tag_ids' => [$tag->id, $tag->id]]);
        } finally {
            remove_action('fluent_support/ticket_tag_added', $count);
        }
        FsTest::assertSame([(int) $tag->id], $rows($ticket, 'ticket_tag'), 'one row');
        FsTest::assertSame(1, $fired, 'one hook');
    });

    FsTest::case('tags/watchers: only ticket tags and agents are attached', function () use ($service, $rows) {
        $ticket = FsFactory::ticket();
        $other = FsFactory::tag(['tag_type' => 'agent_group']);
        $service->addTags($ticket, [$other->id, 999999999]);
        FsTest::assertSame([], $rows($ticket, 'ticket_tag'), 'no tag row');
        $customer = FsFactory::customer();
        $service->addWatchers($ticket, [$customer->id, 999999999]);
        FsTest::assertSame([], $rows($ticket, 'ticket_watcher'), 'no watcher row');
    });

    FsTest::case('tags/watchers: syncing watchers sets exactly the given agents', function () use ($rows) {
        if (!defined('FLUENTSUPPORTPRO')) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        $ticket = FsFactory::ticket();
        $a = FsFactory::agent();
        $b = FsFactory::agent();
        $c = FsFactory::agent();
        FsTest::rest('POST', 'tickets/' . $ticket->id . '/sync-watchers', ['watchers' => [$a->id, $b->id]]);
        FsTest::assertSame([(int) $a->id, (int) $b->id], $rows($ticket, 'ticket_watcher'), 'added on a ticket with none');
        FsTest::rest('POST', 'tickets/' . $ticket->id . '/sync-watchers', ['watchers' => [$c->id]]);
        FsTest::assertSame([(int) $c->id], $rows($ticket, 'ticket_watcher'), 'all replaced');
    });

    FsTest::case('tags/watchers: the watcher hooks carry the agent ids', function () use ($service) {
        $ticket = FsFactory::ticket();
        $a = FsFactory::agent();
        $seen = [];
        $listen = function ($t, $ids) use (&$seen) {
            $seen[current_filter()] = $ids;
        };
        add_action('fluent_support/ticket_watchers_added', $listen, 10, 2);
        add_action('fluent_support/ticket_watchers_removed', $listen, 10, 2);
        try {
            $service->addWatchers($ticket, [$a->id]);
            $service->removeWatchers($ticket, [$a->id]);
        } finally {
            remove_action('fluent_support/ticket_watchers_added', $listen, 10);
            remove_action('fluent_support/ticket_watchers_removed', $listen, 10);
        }
        FsTest::assertSame(['fluent_support/ticket_watchers_added' => [(int) $a->id], 'fluent_support/ticket_watchers_removed' => [(int) $a->id]], $seen, 'hooks');
    });
};
