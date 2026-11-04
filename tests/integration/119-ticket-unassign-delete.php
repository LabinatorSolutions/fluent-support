<?php
/**
 * Unassigning and deleting through TicketService (dev-works/tickets-architecture.md 3.1):
 * - A workflow that unassigns a ticket writes the activity line and fires
 *   fluent_support/ticket_unassigned ($ticket, $previousAgentId, $by).
 * - Deleting a ticket fires deleting_ticket and ticket_deleted from every
 *   path: the workflow Delete action and the WordPress privacy eraser too.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;

return function () {
    $admin = Helper::getAgentByUserId(get_current_user_id());
    $workflowIds = [];

    $capture = function ($hook, callable $fn) {
        $seen = [];
        $listener = function () use (&$seen) {
            $seen[] = func_get_args();
        };
        add_action('fluent_support/' . $hook, $listener, 10, 3);
        try {
            $fn();
        } finally {
            remove_action('fluent_support/' . $hook, $listener, 10);
        }
        return $seen;
    };
    $runWorkflow = function ($actionName, array $settings, $ticket) use (&$workflowIds) {
        $workflow = \FluentSupportPro\App\Models\Workflow::create(['title' => 'Assign/delete ' . FsFactory::marker(), 'trigger_type' => 'manual', 'trigger_key' => '', 'settings' => ['conditions' => []]]);
        \FluentSupportPro\App\Models\Workflow::where('id', $workflow->id)->update(['status' => 'published']);
        $workflowIds[] = (int) $workflow->id;
        $action = \FluentSupportPro\App\Models\WorkflowAction::create(['workflow_id' => $workflow->id, 'action_name' => $actionName, 'title' => $actionName, 'settings' => $settings]);
        Meta::create(['object_type' => 'workflow_meta', 'object_id' => $workflow->id, 'key' => '_fs_workflow_action_sequence', 'value' => maybe_serialize([(int) $action->id])]);
        return FsTest::rest('POST', '/workflows/' . $workflow->id . '/run', ['ticket_ids' => [$ticket->id]]);
    };

    try {
        FsTest::case('unassign/delete: a workflow unassign writes the line and fires ticket_unassigned', function () use ($capture, $runWorkflow, $admin) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $agent = FsFactory::agent();
            $ticket = FsFactory::ticket(['agent_id' => $agent->id]);
            $seen = $capture('ticket_unassigned', fn() => $runWorkflow('fs_action_assign_agent', ['agent_id' => 'unassigned'], $ticket));
            FsTest::assertSame(null, Ticket::find($ticket->id)->agent_id, 'unassigned');
            FsTest::assertSame([(int) $ticket->id, (int) $agent->id, (int) $admin->id], [(int) ($seen[0][0]->id ?? 0), (int) ($seen[0][1] ?? 0), (int) ($seen[0][2]->id ?? 0)], 'hook args');
            FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->where('content', 'unassigned this ticket')->count(), 'activity line');
        });

        FsTest::case('unassign/delete: the workflow Delete action fires ticket_deleted', function () use ($capture, $runWorkflow) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $ticket = FsFactory::ticket();
            $seen = $capture('ticket_deleted', fn() => $runWorkflow('fs_delete_ticket', [], $ticket));
            FsTest::assertSame(null, Ticket::find($ticket->id), 'deleted');
            FsTest::assertSame((int) $ticket->id, (int) ($seen[0][1]['id'] ?? 0), 'ticket_deleted fired');
        });

        FsTest::case('unassign/delete: the privacy eraser fires ticket_deleted for each ticket', function () use ($capture) {
            $customer = FsFactory::customer();
            $one = FsFactory::ticket(['customer_id' => $customer->id]);
            $two = FsFactory::ticket(['customer_id' => $customer->id]);
            $seen = $capture('ticket_deleted', fn() => (new \FluentSupport\App\Hooks\Handlers\PrivacyHandler())->erasePersonalData($customer->email));
            $ids = array_map(fn($args) => (int) $args[1]['id'], $seen);
            sort($ids);
            FsTest::assertSame([(int) $one->id, (int) $two->id], $ids, 'both tickets');
        });
    } finally {
        if ($workflowIds) {
            Meta::where('object_type', 'workflow_meta')->whereIn('object_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\WorkflowAction::whereIn('workflow_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\Workflow::whereIn('id', $workflowIds)->delete();
        }
    }
};
