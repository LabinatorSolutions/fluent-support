<?php
/**
 * TicketTools::updateTicket() used to assign mailbox_id directly, so an
 * MCP-driven mailbox change never fired the move hooks (see 65-mailbox-move-hooks.php).
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;

return function () {

    $captureMoveHooks = function (callable $move) {
        $seen = ['before' => [], 'after' => []];

        $before = function ($data, $oldBox, $newBox) use (&$seen) {
            $seen['before'][] = [
                'ticket_ids' => isset($data['ticket_ids']) ? array_map('intval', (array) $data['ticket_ids']) : [],
                'old_box'    => isset($oldBox->id) ? (int) $oldBox->id : 0,
                'new_box'    => isset($newBox->id) ? (int) $newBox->id : 0,
            ];
        };

        $after = function ($data, $oldBox, $newBox) use (&$seen) {
            $seen['after'][] = [
                'ticket_ids' => isset($data['ticket_ids']) ? array_map('intval', (array) $data['ticket_ids']) : [],
                'old_box'    => isset($oldBox->id) ? (int) $oldBox->id : 0,
                'new_box'    => isset($newBox->id) ? (int) $newBox->id : 0,
            ];
        };

        add_action('fluent_support/before_move_tickets', $before, 10, 3);
        add_action('fluent_support/tickets_moved', $after, 10, 3);

        try {
            $move();
        } finally {
            remove_action('fluent_support/before_move_tickets', $before, 10);
            remove_action('fluent_support/tickets_moved', $after, 10);
        }

        return $seen;
    };

    FsTest::case('update-ticket mailbox_id fires both move hooks', function () use ($captureMoveHooks) {
        $oldBox = FsFactory::mailbox();
        $newBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, $newBox, &$result) {
            $result = TicketTools::updateTicket([
                'ticket_id'  => $ticket->id,
                'mailbox_id' => $newBox->id,
            ]);
        });

        FsTest::assert(!is_wp_error($result), 'update-ticket mailbox_id must succeed, got: ' . (is_wp_error($result) ? $result->get_error_message() : ''));

        FsTest::assertSame((int) $newBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'update-ticket persists the new mailbox');
        FsTest::assertSame(1, count($seen['before']), 'before_move_tickets fired once');
        FsTest::assertSame(1, count($seen['after']), 'tickets_moved fired once');

        if ($seen['after']) {
            FsTest::assertSame((int) $oldBox->id, $seen['after'][0]['old_box'], 'tickets_moved carries the source mailbox');
            FsTest::assertSame((int) $newBox->id, $seen['after'][0]['new_box'], 'tickets_moved carries the target mailbox');
            FsTest::assert(in_array((int) $ticket->id, $seen['after'][0]['ticket_ids'], true),
                'tickets_moved carries the moved ticket id');
        }

        $data = $result['data']['ticket'];
        FsTest::assertSame((int) $newBox->id, (int) ($data['mailbox_id'] ?? $data['mailbox']['id'] ?? 0),
            'the response reflects the new mailbox, not a stale in-memory value');
    });

    FsTest::case('update-ticket mailbox_id to the same box is a no-op', function () use ($captureMoveHooks) {
        $box = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, $box, &$result) {
            $result = TicketTools::updateTicket([
                'ticket_id'  => $ticket->id,
                'mailbox_id' => $box->id,
            ]);
        });

        FsTest::assert(!is_wp_error($result), 'a same-box no-op must not error');
        FsTest::assertSame(0, count($seen['before']), 'a no-op fires no move hooks');
        FsTest::assertSame(0, count($seen['after']), 'a no-op fires no move hooks');
        FsTest::assertSame((int) $box->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'the mailbox is unchanged');
    });

    FsTest::case('update-ticket mailbox_id on a mailbox-less ticket assigns directly with no move hooks', function () use ($captureMoveHooks) {
        $newBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket();
        Ticket::where('id', $ticket->id)->update(['mailbox_id' => null]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, $newBox, &$result) {
            $result = TicketTools::updateTicket([
                'ticket_id'  => $ticket->id,
                'mailbox_id' => $newBox->id,
            ]);
        });

        FsTest::assert(!is_wp_error($result), 'assigning a first mailbox must succeed, got: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
        FsTest::assertSame((int) $newBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'the mailbox is assigned');
        FsTest::assertSame(0, count($seen['before']), 'an assignment from no mailbox is not a move');
        FsTest::assertSame(0, count($seen['after']), 'an assignment from no mailbox is not a move');
    });

    FsTest::case('update-ticket moves the mailbox while reassigning away from an agent restricted there', function () use ($captureMoveHooks) {
        $oldBox = FsFactory::mailbox();
        $newBox = FsFactory::mailbox();

        $restrictedAgent = FsFactory::agent();
        $restrictedAgent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $newBox->id],
        ]);
        $newAgent = FsFactory::agent();

        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id, 'agent_id' => $restrictedAgent->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, $newBox, $newAgent, &$result) {
            $result = TicketTools::updateTicket([
                'ticket_id'  => $ticket->id,
                'mailbox_id' => $newBox->id,
                'agent_id'   => $newAgent->id,
            ]);
        });

        FsTest::assert(!is_wp_error($result), 'move+reassign in one call must succeed, got: ' . (is_wp_error($result) ? $result->get_error_message() : ''));

        $fresh = Ticket::find($ticket->id);
        FsTest::assertSame((int) $newBox->id, (int) $fresh->mailbox_id, 'the mailbox moved');
        FsTest::assertSame((int) $newAgent->id, (int) $fresh->agent_id, 'the ticket was reassigned');
        FsTest::assertSame(1, count($seen['before']), 'the move still fires before_move_tickets');
        FsTest::assertSame(1, count($seen['after']), 'the move still fires tickets_moved');
    });

    FsTest::case('update-ticket leaves the mailbox and hooks untouched when agent_id in the same call is invalid', function () use ($captureMoveHooks) {
        $oldBox = FsFactory::mailbox();
        $newBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, $newBox, &$result) {
            $result = TicketTools::updateTicket([
                'ticket_id'  => $ticket->id,
                'mailbox_id' => $newBox->id,
                'agent_id'   => 999999999,
            ]);
        });

        FsTest::assert(is_wp_error($result), 'an invalid agent_id must be rejected');
        FsTest::assertSame((int) $oldBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'the mailbox move must not be left partially applied');
        FsTest::assertSame(0, count($seen['before']), 'no move hooks fire when the call is rejected');
        FsTest::assertSame(0, count($seen['after']), 'no move hooks fire when the call is rejected');
    });
};
