<?php
/**
 * ResponseService is the one writer of the thread (dev-works/tickets-architecture.md 3.2):
 * - An agent's reply to an unassigned ticket assigns it like any assignment:
 *   the activity line and both assignment hooks.
 * - The first message of an agent-started ticket and a piped email reply go
 *   through the shared reply path: the reply filters run, and a customer's
 *   piped reply leaves a new ticket new, as a portal reply does.
 * - A piped reply keeps its email Message-ID.
 * - A workflow reply fills smartcodes once.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;

return function () {
    FsTest::interceptMail();
    $marker = FsFactory::marker();
    $admin = Helper::getAgentByUserId(get_current_user_id());
    $workflowIds = [];

    // Appends a tag to every reply's content while $fn runs.
    $withReplyFilter = function (callable $fn) {
        $tag = function ($content) {
            return $content . '<p>[reply-filter-ran]</p>';
        };
        add_filter('fluent_support/response_content_before_use_anywhere', $tag);
        try {
            return $fn();
        } finally {
            remove_filter('fluent_support/response_content_before_use_anywhere', $tag);
        }
    };

    try {
        FsTest::case('thread: a reply that sanitizes to nothing is refused, not a server error', function () {
            $ticket = FsFactory::ticket();
            $result = FsTest::rest('POST', 'tickets/' . $ticket->id . '/responses', ['content' => '<script></script>', 'conversation_type' => 'response']);
            FsTest::assertSame(422, $result['status'], 'status');
        });

        FsTest::case('thread: a reply that assigns an unassigned ticket writes the activity line', function () use ($admin) {
            $ticket = FsFactory::ticket(['agent_id' => null, 'status' => 'new']);
            FsTest::rest('POST', 'tickets/' . $ticket->id . '/responses', ['content' => '<p>Mine now</p>', 'conversation_type' => 'response', 'force_send' => 'yes']);
            FsTest::assertSame((int) $admin->id, (int) Ticket::find($ticket->id)->agent_id, 'assigned');
            FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->where('content', 'assigned this ticket to themselves')->count(), 'activity line');
        });

        FsTest::case('thread: the first message of an agent-started ticket goes through the reply path', function () use ($withReplyFilter, $marker) {
            $customer = FsFactory::customer();
            $result = $withReplyFilter(fn() => FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Outreach ' . $marker, 'content' => '<p>Hello there</p>', 'customer_id' => $customer->id, 'agent_initiated' => 'yes']]));
            FsTest::assertHealthy($result, 'create');
            $ticket = Ticket::where('title', 'Outreach ' . $marker)->first();
            FsFactory::adoptRestTicket($ticket);
            $first = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();
            FsTest::assert($first && strpos($first->content, '[reply-filter-ran]') !== false, 'reply filters ran on the first message');
            FsTest::assertSame(['active', 1, 0], [$ticket->status, (int) $ticket->response_count, (int) $ticket->first_response_time], 'counters');
        });

        FsTest::case('thread: a piped reply runs the reply filters, keeps a new ticket new and keeps its Message-ID', function () use ($withReplyFilter, $marker) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $handler = \FluentSupportPro\App\Services\Integrations\FluentEmailPiping\ByMailHandler::class;
            $email = $marker . '-thread-pipe@example.invalid';
            $mailbox = FsFactory::mailbox();
            $handler::handleEmailData(['subject' => 'Thread pipe ' . $marker, 'sender' => ['email' => $email, 'name' => 'Pipe'], 'content' => '<p>Opening</p>', 'message_id' => '<' . $marker . '-1@example.invalid>'], $mailbox, 'email');
            $ticket = Ticket::where('title', 'Thread pipe ' . $marker)->first();
            FsFactory::adoptRestTicket($ticket);
            FsFactory::adoptRestCustomer($ticket->customer);
            FsTest::assertSame('new', $ticket->status, 'piped ticket starts new');

            $withReplyFilter(fn() => $handler::handleEmailData(['subject' => 'Re: Thread pipe #' . $ticket->id, 'sender' => ['email' => $email, 'name' => 'Pipe'], 'content' => '<p>Following up</p>', 'message_id' => '<' . $marker . '-2@example.invalid>', 'is_reply' => true], $mailbox, 'email'));
            $reply = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();
            FsTest::assert($reply && strpos($reply->content, '[reply-filter-ran]') !== false, 'reply filters ran');
            FsTest::assertSame('<' . $marker . '-2@example.invalid>', $reply ? $reply->message_id : null, 'Message-ID kept');
            $ticket = Ticket::find($ticket->id);
            FsTest::assertSame(['new', 1], [$ticket->status, (int) $ticket->response_count], 'still new, one reply');
        });

        FsTest::case('thread: a workflow reply fills smartcodes once, from text', function () use (&$workflowIds) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $workflow = \FluentSupportPro\App\Models\Workflow::create(['title' => 'Thread ' . FsFactory::marker(), 'trigger_type' => 'manual', 'trigger_key' => '', 'settings' => ['conditions' => []]]);
            \FluentSupportPro\App\Models\Workflow::where('id', $workflow->id)->update(['status' => 'published']);
            $workflowIds[] = (int) $workflow->id;
            $action = \FluentSupportPro\App\Models\WorkflowAction::create(['workflow_id' => $workflow->id, 'action_name' => 'fs_action_create_response', 'title' => 'Reply', 'settings' => ['response_body' => '<p>Hi {{customer.first_name}}</p>']]);
            Meta::create(['object_type' => 'workflow_meta', 'object_id' => $workflow->id, 'key' => '_fs_workflow_action_sequence', 'value' => maybe_serialize([(int) $action->id])]);

            $nonText = 0;
            $count = function ($value) use (&$nonText) {
                if (!is_string($value)) {
                    $nonText++;
                }
                return $value;
            };
            add_filter('fluent_support/parse_smartcode_data', $count, 1);
            try {
                FsTest::rest('POST', '/workflows/' . $workflow->id . '/run', ['ticket_ids' => [FsFactory::ticket()->id]]);
            } finally {
                remove_filter('fluent_support/parse_smartcode_data', $count, 1);
            }
            FsTest::assertSame(0, $nonText, 'smartcodes are never filled from an array');
        });
    } finally {
        if ($workflowIds) {
            Meta::where('object_type', 'workflow_meta')->whereIn('object_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\WorkflowAction::whereIn('workflow_id', $workflowIds)->delete();
            \FluentSupportPro\App\Models\Workflow::whereIn('id', $workflowIds)->delete();
        }
    }
};
