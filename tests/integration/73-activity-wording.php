<?php
/**
 * Activity line wording.
 *
 * The ticket thread shows the person's name in front of each activity line
 * ("Sarah closed this ticket"), so a new line starts with the verb and never
 * repeats the actor's name. Automatic closes are marked by source instead, so
 * the thread shows them as system events rather than as the agent's action.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    $me = Helper::getAgentByUserId();
    if (!$me) {
        FsTest::case('activity wording: admin has an agent row', function () {
            FsTest::skip('the admin user has no agent row');
        });
        return;
    }

    $latestActivity = function ($ticket) {
        return Conversation::where('ticket_id', $ticket->id)
            ->where('conversation_type', 'internal_info')
            ->orderBy('id', 'DESC')
            ->first();
    };

    FsTest::case('activity wording: close and reopen start with the verb', function () use ($me, $latestActivity) {
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'active']);

        $closed = FsTest::rest('POST', '/tickets/' . $ticket->id . '/close');
        FsTest::assertHealthy($closed, 'close');
        FsTest::assertSame('closed this ticket', $latestActivity($ticket)->content, 'close line');

        $reopened = FsTest::rest('POST', '/tickets/' . $ticket->id . '/re-open');
        FsTest::assertHealthy($reopened, 'reopen');
        FsTest::assertSame('reopened this ticket', $latestActivity($ticket)->content, 'reopen line');
    });

    FsTest::case('activity wording: assigning names only the assignee', function () use ($me, $latestActivity) {
        $other = FsFactory::agent();
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'active']);

        $toOther = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', ['prop_name' => 'agent_id', 'prop_value' => $other->id]);
        FsTest::assertHealthy($toOther, 'assign to another agent');
        // The assignee's name is bolded and escaped on the server.
        FsTest::assertSame('assigned this ticket to <strong>' . esc_html($other->full_name) . '</strong>', $latestActivity($ticket)->content, 'assign-other line');

        $toSelf = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', ['prop_name' => 'agent_id', 'prop_value' => $me->id]);
        FsTest::assertHealthy($toSelf, 'assign to self');
        FsTest::assertSame('assigned this ticket to themselves', $latestActivity($ticket)->content, 'assign-self line');
    });

    FsTest::case('activity wording: a status change starts with the verb', function () use ($me, $latestActivity) {
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'new']);

        $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', ['prop_name' => 'status', 'prop_value' => 'active']);
        FsTest::assertHealthy($result, 'status change');

        $statuses = Helper::ticketStatuses();
        FsTest::assertSame(
            'changed the status from ' . $statuses['new'] . ' to ' . $statuses['active'],
            $latestActivity($ticket)->content,
            'status line'
        );
    });

    FsTest::case('activity wording: an automatic close is marked as a system event', function () use ($me, $latestActivity) {
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'active']);

        (new TicketService())->close($ticket, $me, 'Closed automatically after inactivity', 'yes', 'automatic');
        $activity = $latestActivity($ticket);

        FsTest::assertSame('automatic', $activity->source, 'source marks it as automatic');
        FsTest::assertSame('Closed automatically after inactivity', $activity->content, 'content');
    });

    FsTest::case('activity wording: a reply-and-close takes its activity source from the filter', function () use ($me, $latestActivity) {
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'active']);
        $source = function () {
            return 'automatic';
        };

        add_filter('fluent_support/ticket_close_activity_source', $source);
        try {
            (new ResponseService())->createResponse([
                'content'      => '<p>Closing reply ' . FsFactory::marker() . '</p>',
                'close_ticket' => 'yes',
            ], $me, $ticket, true);
        } finally {
            remove_filter('fluent_support/ticket_close_activity_source', $source);
        }

        FsTest::assertSame('automatic', $latestActivity($ticket)->source, 'source from the filter');
    });

    FsTest::case('activity wording: a normal close keeps the default source', function () use ($me, $latestActivity) {
        $ticket = FsFactory::ticket(['agent_id' => $me->id, 'status' => 'active']);

        (new TicketService())->close($ticket, $me, '', 'yes');

        FsTest::assert(!in_array($latestActivity($ticket)->source, ['automatic', 'automation'], true), 'not marked automatic');
    });
};
