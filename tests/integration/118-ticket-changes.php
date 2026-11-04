<?php
/**
 * TicketService::change() is the one writer of title, status, priority, client
 * priority, product and customer (dev-works/tickets-architecture.md 3.1):
 * - Every status change writes the activity line, MCP's too.
 * - The new hooks pass what changed: ticket_status_changed ($ticket, $old, $new,
 *   $by), ticket_priority_changed (same), ticket_updated ($ticket, [field =>
 *   [from, to]], $by).
 * - Values are checked the same way from every caller (an empty title is refused).
 * - Workflow field changes fire the same hooks.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {
    $admin = Helper::getAgentByUserId(get_current_user_id());
    $workflowIds = [];

    // [hook => [args...]] for the given hooks while $fn runs.
    $capture = function (array $hooks, callable $fn) {
        $seen = [];
        $listeners = [];
        foreach ($hooks as $hook) {
            $listeners[$hook] = function () use ($hook, &$seen) {
                $seen[$hook][] = func_get_args();
            };
            add_action('fluent_support/' . $hook, $listeners[$hook], 10, 4);
        }
        try {
            $fn();
        } finally {
            foreach ($listeners as $hook => $listener) {
                remove_action('fluent_support/' . $hook, $listener, 10);
            }
        }
        return $seen;
    };

    try {
        FsTest::case('changes: an MCP status change writes the activity line', function () {
            $ticket = FsFactory::ticket(['status' => 'new']);
            TicketTools::updateTicket(['ticket_id' => $ticket->id, 'status' => 'active']);
            FsTest::assertSame('active', Ticket::find($ticket->id)->status, 'status');
            FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->where('content', 'LIKE', 'changed the status from%')->count(), 'activity line');
        });

        FsTest::case('changes: the new hooks pass the old value, the new value and who changed it', function () use ($capture, $admin) {
            $ticket = FsFactory::ticket(['status' => 'new', 'priority' => 'normal']);
            $seen = $capture(['ticket_status_changed', 'ticket_priority_changed', 'ticket_updated'], function () use ($ticket) {
                FsTest::rest('PUT', 'tickets/' . $ticket->id . '/property', ['prop_name' => 'status', 'prop_value' => 'active']);
                FsTest::rest('PUT', 'tickets/' . $ticket->id . '/property', ['prop_name' => 'priority', 'prop_value' => 'critical']);
            });
            $status = $seen['ticket_status_changed'][0] ?? [];
            FsTest::assertSame([(int) $ticket->id, 'new', 'active', (int) $admin->id], [(int) $status[0]->id, $status[1], $status[2], (int) $status[3]->id], 'status hook');
            $priority = $seen['ticket_priority_changed'][0] ?? [];
            FsTest::assertSame(['normal', 'critical'], [$priority[1] ?? null, $priority[2] ?? null], 'priority hook');
            FsTest::assertSame([['status' => ['new', 'active']], ['priority' => ['normal', 'critical']]], array_map(fn($args) => $args[1], $seen['ticket_updated'] ?? []), 'ticket_updated changes');
        });

        // The admin and MCP refuse it today; workflows and add-ons call change() directly.
        FsTest::case('changes: a closed ticket set to new reopens as new', function () use ($capture, $admin) {
            $ticket = FsFactory::ticket(['status' => 'closed']);
            $seen = $capture(['ticket_status_changed'], fn() => (new TicketService())->change(Ticket::find($ticket->id), ['status' => 'new'], $admin));
            FsTest::assertSame('new', Ticket::find($ticket->id)->status, 'status');
            FsTest::assertSame([['closed', 'active'], ['active', 'new']], array_map(fn($args) => [$args[1], $args[2]], $seen['ticket_status_changed'] ?? []), 'status hooks');
        });

        FsTest::case('changes: a reply that closes the ticket passes the real old status', function () use ($capture) {
            $ticket = FsFactory::ticket(['status' => 'new']);
            $seen = $capture(['ticket_status_changed'], fn() => FsTest::rest('POST', 'tickets/' . $ticket->id . '/responses', ['content' => '<p>Done</p>', 'conversation_type' => 'response', 'close_ticket' => 'yes']));
            FsTest::assertSame('closed', Ticket::find($ticket->id)->status, 'closed');
            FsTest::assertSame(['new', 'closed'], [$seen['ticket_status_changed'][0][1] ?? null, $seen['ticket_status_changed'][0][2] ?? null], 'status hook');
        });

        FsTest::case('changes: an empty title is refused from MCP as from the ticket view', function () {
            $ticket = FsFactory::ticket();
            $before = Ticket::find($ticket->id)->title;
            $result = TicketTools::updateTicket(['ticket_id' => $ticket->id, 'title' => '   ']);
            FsTest::assert(is_wp_error($result), 'MCP refuses');
            FsTest::assertSame(422, FsTest::rest('PUT', 'tickets/' . $ticket->id . '/property', ['prop_name' => 'title', 'prop_value' => '  '])['status'], 'ticket view refuses');
            FsTest::assertSame($before, Ticket::find($ticket->id)->title, 'title kept');
        });

        FsTest::case('changes: a workflow priority change fires the priority hook', function () use ($capture, &$workflowIds) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $workflow = \FluentSupportPro\App\Models\Workflow::create(['title' => 'Changes ' . FsFactory::marker(), 'trigger_type' => 'manual', 'trigger_key' => '', 'settings' => ['conditions' => []]]);
            \FluentSupportPro\App\Models\Workflow::where('id', $workflow->id)->update(['status' => 'published']);
            $workflowIds[] = (int) $workflow->id;
            $action = \FluentSupportPro\App\Models\WorkflowAction::create(['workflow_id' => $workflow->id, 'action_name' => 'fs_action_change_priority', 'title' => 'Priority', 'settings' => ['priority' => 'critical']]);
            Meta::create(['object_type' => 'workflow_meta', 'object_id' => $workflow->id, 'key' => '_fs_workflow_action_sequence', 'value' => maybe_serialize([(int) $action->id])]);

            $ticket = FsFactory::ticket(['priority' => 'normal', 'client_priority' => 'normal']);
            $seen = $capture(['ticket_priority_changed'], fn() => FsTest::rest('POST', '/workflows/' . $workflow->id . '/run', ['ticket_ids' => [$ticket->id]]));
            FsTest::assertSame(['normal', 'critical'], [$seen['ticket_priority_changed'][0][1] ?? null, $seen['ticket_priority_changed'][0][2] ?? null], 'priority hook from the workflow');
            FsTest::assertSame(['critical', 'critical'], [Ticket::find($ticket->id)->priority, Ticket::find($ticket->id)->client_priority], 'both priorities');
        });
    } finally {
        if ($workflowIds) {
            Meta::where('object_type', 'workflow_meta')->whereIn('object_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\WorkflowAction::whereIn('workflow_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\Workflow::whereIn('id', $workflowIds)->delete();
        }
    }
};
