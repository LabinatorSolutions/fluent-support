<?php
/**
 * Every way of assigning an agent goes through TicketService::assignAgent()
 * (roadmap 4.2, fixes 2.9): the ticket gets the "assigned this ticket to ..."
 * activity line, and fluent_support/ticket_agent_change and
 * fluent_support/agent_assigned_to_ticket fire with the previous agent.
 * Bulk assign used to skip the activity line and ticket_agent_change; a
 * group assign skipped both until its async job ran, and then still did.
 */

use FluentSupport\App\Hooks\Handlers\AsyncActionHandler;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;

return function () {

    // Runs $fn and records the hooks an assignment fires.
    $record = function (callable $fn) {
        $seen = ['change' => [], 'assigned' => []];
        $onChange = function ($ticket) use (&$seen) {
            $seen['change'][] = (int) $ticket->id;
        };
        $onAssigned = function ($agent, $ticket, $assigner, $previousAgentId = null) use (&$seen) {
            $seen['assigned'][] = [(int) $ticket->id, (int) $agent->id, $previousAgentId === null ? null : (int) $previousAgentId];
        };
        add_action('fluent_support/ticket_agent_change', $onChange, 10, 2);
        add_action('fluent_support/agent_assigned_to_ticket', $onAssigned, 10, 4);
        try {
            $fn();
        } finally {
            remove_action('fluent_support/ticket_agent_change', $onChange, 10);
            remove_action('fluent_support/agent_assigned_to_ticket', $onAssigned, 10);
        }
        return $seen;
    };

    $activityLines = function ($ticketId) {
        return (int) Conversation::where('ticket_id', $ticketId)
            ->where('conversation_type', 'internal_info')
            ->where('content', 'LIKE', '%assigned this ticket%')
            ->count();
    };

    FsTest::case('bulk_assign_writes_the_activity_line_and_fires_both_hooks', function () use ($record, $activityLines) {
        $previous = FsFactory::agent();
        $target = FsFactory::agent();
        $first = FsFactory::ticket(['agent_id' => $previous->id]);
        $second = FsFactory::ticket();

        $seen = $record(function () use ($first, $second, $target) {
            $result = FsTest::rest('POST', '/tickets/bulk-actions', [
                'bulk_action' => 'assign_agent',
                'agent_id'    => $target->id,
                'ticket_ids'  => [$first->id, $second->id],
            ]);
            FsTest::assertHealthy($result, 'bulk assign');
        });

        FsTest::assertSame((int) $target->id, (int) Ticket::find($first->id)->agent_id, 'first ticket assigned');
        FsTest::assertSame(1, $activityLines($first->id), 'activity line on the first ticket');
        FsTest::assertSame(1, $activityLines($second->id), 'activity line on the second ticket');

        $changed = $seen['change'];
        sort($changed);
        FsTest::assertSame([(int) $first->id, (int) $second->id], $changed, 'ticket_agent_change for each ticket');
        FsTest::assert(in_array([(int) $first->id, (int) $target->id, (int) $previous->id], $seen['assigned'], true), 'agent_assigned_to_ticket carries the previous agent');
    });

    FsTest::case('group_assign_writes_the_activity_line_when_its_job_runs', function () use ($record, $activityLines) {
        $previous = FsFactory::agent();
        $target = FsFactory::agent();
        $ticket = FsFactory::ticket(['agent_id' => $target->id]);

        // The bulk group action saves the agent, then queues this job.
        $seen = $record(function () use ($target, $ticket, $previous) {
            (new AsyncActionHandler())->resolveAgentAssigned($target->id, $ticket->id, $target->id, $previous->id);
        });

        FsTest::assertSame(1, $activityLines($ticket->id), 'activity line');
        FsTest::assertSame([(int) $ticket->id], $seen['change'], 'ticket_agent_change');
        FsTest::assertSame([[(int) $ticket->id, (int) $target->id, (int) $previous->id]], $seen['assigned'], 'agent_assigned_to_ticket');
    });

    FsTest::case('single_ticket_assign_keeps_its_activity_line_and_hooks', function () use ($record, $activityLines) {
        $previous = FsFactory::agent();
        $target = FsFactory::agent();
        $ticket = FsFactory::ticket(['agent_id' => $previous->id]);

        $seen = $record(function () use ($ticket, $target) {
            $result = FsTest::rest('PUT', '/tickets/' . $ticket->id . '/property', [
                'prop_name'  => 'agent_id',
                'prop_value' => $target->id,
            ]);
            FsTest::assertHealthy($result, 'single assign');
        });

        FsTest::assertSame((int) $target->id, (int) Ticket::find($ticket->id)->agent_id, 'assigned');
        FsTest::assertSame(1, $activityLines($ticket->id), 'activity line');
        FsTest::assertSame([(int) $ticket->id], $seen['change'], 'ticket_agent_change');
        FsTest::assertSame([[(int) $ticket->id, (int) $target->id, (int) $previous->id]], $seen['assigned'], 'agent_assigned_to_ticket');
    });
};
