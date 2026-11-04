<?php
/**
 * Ticket REST contract for the admin panel: the status code and response key
 * paths of every ticket and thread endpoint the admin UI calls, as an admin.
 *
 * The tickets rebuild (dev-works/tickets-architecture.md) may change how these
 * answers are built, never their shape. Expected values:
 * fixtures/114-tickets-contract.json (see tests/lib/contract.php to re-record).
 *
 * Not covered: FluentBooking, FluentCRM sync, Fluent Boards and AI endpoints
 * (they need those plugins or an AI key), file uploads (68, 83).
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;

return function () {
    $contract = new FsContract('114-tickets-contract');
    $marker = FsFactory::marker();
    $adminAgent = Helper::getAgentByUserId(get_current_user_id());
    $call = fn($method, $route, array $params = []) => FsTest::rest($method, $route, $params);
    $id = fn($result, $path) => (int) \FluentSupport\Framework\Support\Arr::get(json_decode(wp_json_encode($result['data']), true), $path);

    // One customer with a full ticket (agent, product, mailbox, tag, replies,
    // a note, a draft for approval), so every optional block is present.
    $customer = FsFactory::customer();
    $other = FsFactory::customer();
    $agent = FsFactory::agent();
    $mailbox = FsFactory::mailbox();
    $mailbox2 = FsFactory::mailbox();
    $product = FsFactory::product();
    $tag = FsFactory::tag(['tag_type' => 'ticket_tag']);
    $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $agent->id, 'mailbox_id' => $mailbox->id, 'product_id' => $product->id, 'status' => 'active']);
    FsFactory::attachTag($ticket, $tag);
    FsFactory::conversation($ticket, ['person_id' => $customer->id]);
    FsFactory::conversation($ticket, ['person_id' => $agent->id]);
    FsFactory::conversation($ticket, ['person_id' => $agent->id, 'conversation_type' => 'note']);
    $second = FsFactory::ticket(['customer_id' => $customer->id, 'mailbox_id' => $mailbox->id, 'status' => 'new']);
    $base = 'tickets/' . $ticket->id;

    FsTest::case('tickets contract: list, counts and saved searches', function () use ($contract, $call, $customer) {
        $contract->response('GET tickets (customer)', $call('GET', 'tickets', ['customer_id' => $customer->id, 'per_page' => 20]));
        $contract->response('GET tickets (board view)', $call('GET', 'tickets', ['customer_id' => $customer->id, 'per_page' => 10]));
        $contract->response('GET tickets/sidebar-counts', $call('GET', 'tickets/sidebar-counts'));
        $contract->response('GET tickets/label-search', $call('GET', 'tickets/label-search'));
    });

    FsTest::case('tickets contract: create', function () use ($contract, $call, $customer, $mailbox, $product, $marker) {
        $existing = $call('POST', 'tickets', ['ticket' => ['title' => 'Contract ' . $marker, 'content' => '<p>Body ' . $marker . '</p>', 'customer_id' => $customer->id, 'mailbox_id' => $mailbox->id, 'product_id' => $product->id, 'client_priority' => 'normal']]);
        $contract->response('POST tickets (existing customer)', $existing);
        $new = $call('POST', 'tickets', ['ticket' => ['title' => 'Contract new ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes', 'mailbox_id' => $mailbox->id], 'newCustomer' => ['email' => $marker . '-contract-new@example.invalid', 'first_name' => 'New']]);
        $contract->response('POST tickets (new customer)', $new);
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $created) {
            FsFactory::adoptRestTicket($created);
        }
        foreach (\FluentSupport\App\Models\Customer::where('email', $marker . '-contract-new@example.invalid')->get() as $created) {
            FsFactory::adoptRestCustomer($created);
        }
    });

    FsTest::case('tickets contract: the ticket view and its sidebar', function () use ($contract, $call, $base, $customer) {
        $contract->response('GET tickets/{id}', $call('GET', $base, ['customer_id' => $customer->id, 'with_data' => ['fluentcrm_profile']]));
        $contract->response('GET tickets/{id}/widgets', $call('GET', $base . '/widgets'));
        $contract->response('GET tickets/{id}/mentionable-agents', $call('GET', $base . '/mentionable-agents'));
        $contract->response('GET tickets/{id}/live_activity', $call('GET', $base . '/live_activity'));
        $contract->response('DELETE tickets/{id}/live_activity', $call('DELETE', $base . '/live_activity'));
        $contract->response('GET tickets/{id}/custom-data', $call('GET', $base . '/custom-data'));
    });

    FsTest::case('tickets contract: replies, notes, drafts and approval', function () use ($contract, $call, $base, $id) {
        $reply = $call('POST', $base . '/responses', ['content' => '<p>Contract reply</p>', 'conversation_type' => 'response', 'force_send' => 'yes']);
        $contract->response('POST tickets/{id}/responses (reply)', $reply);
        $contract->response('POST tickets/{id}/responses (note)', $call('POST', $base . '/responses', ['content' => '<p>Contract note</p>', 'conversation_type' => 'note']));
        $draft = $call('POST', $base . '/responses', ['content' => '<p>Contract draft</p>', 'conversation_type' => 'draft_response']);
        $contract->response('POST tickets/{id}/responses (approval draft)', $draft);

        $responseId = $id($reply, 'response.id');
        $contract->response('PUT tickets/{id}/responses/{rid}', $call('PUT', $base . '/responses/' . $responseId, ['content' => '<p>Contract edit</p>']));
        $contract->response('PUT tickets/{id}/approve_draft_response/{rid}', $call('PUT', $base . '/approve_draft_response/' . $id($draft, 'response.id'), ['content' => '<p>Approved</p>']));
        $contract->response('DELETE tickets/{id}/responses/{rid}', $call('DELETE', $base . '/responses/' . $responseId));

        $contract->response('POST tickets/{id}/draft', $call('POST', $base . '/draft', ['content' => '<p>Autosave</p>']));
        $saved = $call('GET', $base . '/draft');
        $contract->response('GET tickets/{id}/draft', $saved);
        $contract->response('DELETE tickets/{draft_id}/draft', $call('DELETE', 'tickets/' . $id($saved, 'draft.id') . '/draft'));
    });

    FsTest::case('tickets contract: properties, tags, customer, close and reopen', function () use ($contract, $call, $base, $adminAgent, $product, $tag, $other, $marker) {
        // The title keeps the fixture marker, so cleanup still knows the row.
        foreach (['title' => 'Contract title ' . $marker, 'priority' => 'medium', 'client_priority' => 'critical', 'product_id' => $product->id, 'agent_id' => $adminAgent->id] as $prop => $value) {
            $contract->response('PUT tickets/{id}/property (' . $prop . ')', $call('PUT', $base . '/property', ['prop_name' => $prop, 'prop_value' => $value]));
        }
        $contract->response('DELETE tickets/{id}/tags/{tag}', $call('DELETE', $base . '/tags/' . $tag->id));
        $contract->response('POST tickets/{id}/tags', $call('POST', $base . '/tags', ['tag_id' => $tag->id]));
        $contract->response('POST tickets/{id}/close', $call('POST', $base . '/close', ['close_ticket_silently' => 'no']));
        $contract->response('POST tickets/{id}/re-open', $call('POST', $base . '/re-open'));
        $contract->response('PUT tickets/{id}/change-customer', $call('PUT', $base . '/change-customer', ['customer' => $other->id]));
    });

    FsTest::case('tickets contract: bulk actions and bulk reply', function () use ($contract, $call, $second, $adminAgent, $product, $tag) {
        $ids = [$second->id];
        $actions = [
            'assign_agent'    => ['agent_id' => $adminAgent->id],
            'change_priority' => ['priority' => 'normal'],
            'change_product'  => ['product_id' => $product->id],
            'assign_tags'     => ['tag_ids' => [$tag->id]],
            'remove_tags'     => ['tag_ids' => [$tag->id]],
            'close_tickets'   => [],
            'reopen_tickets'  => [],
        ];
        foreach ($actions as $action => $extra) {
            $contract->response('POST tickets/bulk-actions (' . $action . ')', $call('POST', 'tickets/bulk-actions', ['bulk_action' => $action, 'ticket_ids' => $ids] + $extra));
        }
        $contract->response('POST tickets/bulk-reply', $call('POST', 'tickets/bulk-reply', ['ticket_ids' => $ids, 'content' => '<p>Bulk reply</p>']));
    });

    FsTest::case('tickets contract: mailbox list and move', function () use ($contract, $call, $mailbox, $mailbox2, $second) {
        $contract->response('GET mailboxes/{id}/tickets', $call('GET', 'mailboxes/' . $mailbox->id . '/tickets'));
        $contract->response('PUT mailboxes/{id}/move_tickets', $call('PUT', 'mailboxes/' . $mailbox->id . '/move_tickets', ['ticket_ids' => [$second->id], 'new_box_id' => $mailbox2->id, 'move_type' => 'selected']));
    });

    FsTest::case('tickets contract: Pro watchers, customer tickets, split and merge', function () use ($contract, $call, $base, $ticket, $customer, $adminAgent, $id, $marker) {
        if (!defined('FLUENTSUPPORTPRO')) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        $contract->response('POST tickets/{id}/sync-watchers', $call('POST', $base . '/sync-watchers', ['watchers' => [$adminAgent->id]]));
        $contract->response('POST tickets/{id}/add_watchers', $call('POST', $base . '/add_watchers', ['watchers' => [$adminAgent->id]]));
        $contract->response('GET tickets/customer_tickets/{cid}', $call('GET', 'tickets/customer_tickets/' . $customer->id, ['exclude_ticket_id' => $ticket->id]));

        $conversationId = (int) \FluentSupport\App\Models\Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->value('id');
        $split = $call('POST', $base . '/split_ticket', ['split_ticket' => ['conversation_id' => $conversationId, 'customer_id' => $customer->id, 'title' => 'Split ' . $marker, 'content' => '<p>Split</p>']]);
        $contract->response('POST tickets/{id}/split_ticket', $split);
        foreach (Ticket::where('title', 'Split ' . $marker)->get() as $created) {
            FsFactory::adoptRestTicket($created);
        }

        $toMerge = FsFactory::ticket(['customer_id' => $customer->id]);
        $contract->response('POST tickets/{id}/merge_tickets', $call('POST', $base . '/merge_tickets', ['ticket_to_merge' => [$toMerge->id]]));
    });

    FsTest::case('tickets contract: delete', function () use ($contract, $call, $customer) {
        $one = FsFactory::ticket(['customer_id' => $customer->id]);
        $two = FsFactory::ticket(['customer_id' => $customer->id]);
        $contract->response('DELETE tickets/{id}/delete', $call('DELETE', 'tickets/' . $one->id . '/delete'));
        $contract->response('POST tickets/bulk-actions (delete_tickets)', $call('POST', 'tickets/bulk-actions', ['bulk_action' => 'delete_tickets', 'ticket_ids' => [$two->id]]));
    });

    $contract->finish();
};
