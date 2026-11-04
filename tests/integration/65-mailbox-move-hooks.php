<?php
/**
 * Moving a ticket between mailboxes must fire the documented move hooks
 * regardless of which surface performs the move.
 *
 * fluent_support/before_move_tickets and fluent_support/tickets_moved are the
 * public contract for "this ticket changed mailbox" (both since v1.5.7), and
 * integrations route on them. They are fired in exactly one place —
 * MailBoxService::moveTickets() — so any surface that writes mailbox_id
 * directly moves the ticket silently and breaks every listener.
 *
 * This covers the free-side surfaces: the bulk move endpoint the ticket-view
 * switcher posts to, and the generic property endpoint which must refuse
 * mailbox_id outright. The pro workflow "Change Mailbox" action is covered
 * in 90-pro-workflows.php.
 */

use FluentSupport\App\Models\Ticket;

return function () {

    /**
     * Record both hooks for the duration of one move, then unhook.
     *
     * @param callable $move
     * @return array{before: array<int,array>, after: array<int,array>}
     */
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

    FsTest::case('bulk move via PUT /mailboxes/{id}/move_tickets fires both hooks', function () use ($captureMoveHooks) {
        $oldBox = FsFactory::mailbox();
        $newBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($oldBox, $newBox, $ticket, &$result) {
            $result = FsTest::rest('PUT', '/mailboxes/' . $oldBox->id . '/move_tickets', [
                'new_box_id' => $newBox->id,
                'ticket_ids' => [$ticket->id],
                'move_type'  => 'Custom',
            ]);
        });

        FsTest::assertHealthy($result, 'PUT /mailboxes/{id}/move_tickets');

        FsTest::assertSame((int) $newBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'bulk move persists the new mailbox');
        FsTest::assertSame(1, count($seen['before']), 'before_move_tickets fired once on bulk move');
        FsTest::assertSame(1, count($seen['after']), 'tickets_moved fired once on bulk move');

        if ($seen['after']) {
            FsTest::assertSame((int) $oldBox->id, $seen['after'][0]['old_box'], 'tickets_moved carries the source mailbox');
            FsTest::assertSame((int) $newBox->id, $seen['after'][0]['new_box'], 'tickets_moved carries the target mailbox');
            FsTest::assert(in_array((int) $ticket->id, $seen['after'][0]['ticket_ids'], true),
                'tickets_moved carries the moved ticket id');
        }
    });

    FsTest::case('moving a ticket to the mailbox it is already in fires nothing', function () use ($captureMoveHooks) {
        $box = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);

        $result = null;
        $seen = $captureMoveHooks(function () use ($box, $ticket, &$result) {
            $result = FsTest::rest('PUT', '/mailboxes/' . $box->id . '/move_tickets', [
                'new_box_id' => $box->id,
                'ticket_ids' => [$ticket->id],
                'move_type'  => 'Custom',
            ]);
        });

        FsTest::assert($result['status'] >= 400,
            'a same-box move must be rejected, got status ' . $result['status']);

        FsTest::assertSame((int) $box->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'the mailbox is unchanged');
        FsTest::assertSame(0, count($seen['before']), 'a no-op change fires nothing');
        FsTest::assertSame(0, count($seen['after']), 'a no-op change fires nothing');
    });

    FsTest::case('mailbox_id is rejected by the generic property endpoint', function () use ($captureMoveHooks) {
        $oldBox = FsFactory::mailbox();
        $newBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id]);

        // The property endpoint assigns the column and saves, which would move the
        // ticket without firing either hook. mailbox_id is off the allowlist so the
        // only way to move a ticket is through the move service; the ticket-view
        // switcher posts to mailboxes/{id}/move_tickets.
        $result = null;
        $seen = $captureMoveHooks(function () use ($newBox, $ticket, &$result) {
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', [
                'prop_name'  => 'mailbox_id',
                'prop_value' => (string) $newBox->id,
            ]);
        });

        FsTest::assert($result['status'] >= 400,
            'property endpoint must refuse mailbox_id, got status ' . $result['status']);

        FsTest::assertSame((int) $oldBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
            'refused property update leaves the mailbox untouched');

        FsTest::assertSame(0, count($seen['after']),
            'a refused request moves nothing, so no move hook fires');
    });

    FsTest::case('non-mailbox property updates still work and fire no move hooks', function () use ($captureMoveHooks) {
        $ticket = FsFactory::ticket();

        $result = null;
        $seen = $captureMoveHooks(function () use ($ticket, &$result) {
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', [
                'prop_name'  => 'priority',
                'prop_value' => 'critical',
            ]);
        });

        FsTest::assertHealthy($result, 'PUT /tickets/{id}/property (priority)');
        FsTest::assertSame('critical', (string) Ticket::find($ticket->id)->priority,
            'the non-mailbox branch still assigns and saves');
        FsTest::assertSame(0, count($seen['after']), 'a priority change is not a move');
    });

    FsTest::case('moveTickets refuses a mailbox restricted for the current user', function () {
        $adminId = get_current_user_id();

        $oldBox = FsFactory::mailbox();
        $restrictedBox = FsFactory::mailbox();
        $ticket = FsFactory::ticket(['mailbox_id' => $oldBox->id]);

        $viewer = FsFactory::agent();
        $login = FsTest::uniq('fs-restricted-mover');
        $viewerUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            // Administrator so the AdminSettingsPolicy gate on this route
            // (fst_manage_settings) passes — the restriction under test is
            // the agent-level mailbox restriction, not the route policy.
            'role'       => 'administrator',
        ]);
        if (is_wp_error($viewerUserId)) {
            throw new RuntimeException('Could not create viewer user: ' . $viewerUserId->get_error_message());
        }

        try {
            $viewer->user_id = $viewerUserId;
            $viewer->save();
            $viewer->updateMeta('agent_restrictions', [
                'businessBoxRestrictions' => true,
                'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
            ]);

            // Through the REST endpoint the ticket-view switcher and bulk-move
            // UI actually use — the restriction now lives in the controller,
            // not in MailBoxService::moveTickets() (see MailBoxController::moveTickets).
            wp_set_current_user($viewerUserId);

            $result = FsTest::rest('PUT', '/mailboxes/' . $oldBox->id . '/move_tickets', [
                'new_box_id' => $restrictedBox->id,
                'ticket_ids' => [$ticket->id],
                'move_type'  => 'Custom',
            ]);

            FsTest::assertSame(422, $result['status'], 'moving into a mailbox restricted for the current user must be refused');
            FsTest::assertSame((int) $oldBox->id, (int) Ticket::find($ticket->id)->mailbox_id,
                'a refused move leaves the mailbox untouched');
        } finally {
            wp_set_current_user($adminId);
            wp_delete_user($viewerUserId);
        }
    });
};
