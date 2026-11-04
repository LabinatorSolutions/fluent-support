<?php
/**
 * Ticket hook ledger: for each ticket action, from each source (admin REST,
 * portal, MCP, PHP API, Pro email piping, Pro merge and split), the
 * fluent_support/* actions it fires, in order. Filters are not listed.
 *
 * Today's ledger includes the gaps the tickets rebuild fixes
 * (dev-works/tickets-architecture.md section 5): a step that fixes one
 * re-records the ledger, and the diff shows exactly which hooks changed.
 * Expected values: fixtures/115-ticket-hook-ledger.json (tests/lib/contract.php).
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\ResponseTools;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;

return function () {
    FsTest::interceptMail();
    $ledger = new FsContract('115-ticket-hook-ledger');
    $marker = FsFactory::marker();
    $adminId = get_current_user_id();
    $adminAgent = Helper::getAgentByUserId($adminId);
    $userIds = [];

    // The fluent_support/* actions (not filters) fired while $fn runs, in order.
    $hooks = function (callable $fn) {
        global $wp_actions;
        $seen = [];
        $before = (array) $wp_actions;
        $listen = function () use (&$seen) {
            $name = current_filter();
            if (strpos($name, 'fluent_support/') === 0 || strpos($name, 'fluent_support\\') === 0) {
                $seen[] = $name;
            }
        };
        add_action('all', $listen);
        try {
            $fn();
        } finally {
            remove_action('all', $listen);
        }
        $fired = array_filter($seen, fn($name) => ($wp_actions[$name] ?? 0) > ($before[$name] ?? 0));
        return array_values($fired);
    };
    $record = fn($label, callable $fn) => $ledger->check($label, $hooks($fn));
    $adopt = function () use ($marker) {
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $ticket) {
            FsFactory::adoptRestTicket($ticket);
        }
        foreach (Customer::where('email', 'LIKE', '%' . $marker . '%')->get() as $customer) {
            FsFactory::adoptRestCustomer($customer);
        }
    };
    $ticket = fn(array $attrs = []) => FsFactory::ticket($attrs + ['status' => 'active', 'agent_id' => $adminAgent->id]);
    $portalCustomer = function () use ($marker, &$userIds) {
        $login = FsTest::uniq('fs-ledger');
        $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $marker . '-' . $login . '@example.invalid', 'role' => 'subscriber']);
        $userIds[] = $userId;
        return FsFactory::customer(['user_id' => $userId, 'email' => $marker . '-' . $login . '@example.invalid']);
    };
    $as = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
    };

    try {
        FsTest::case('ledger: create', function () use ($record, $marker, $adopt, $portalCustomer, $as, $adminId) {
            $customer = FsFactory::customer();
            $record('create: admin, existing customer', fn() => FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Ledger ' . $marker, 'content' => '<p>Body</p>', 'customer_id' => $customer->id]]));
            $record('create: admin, new customer', fn() => FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Ledger new ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes'], 'newCustomer' => ['email' => $marker . '-ledger-new@example.invalid', 'first_name' => 'New']]));
            $record('create: MCP, new customer', fn() => TicketTools::createTicket(['title' => 'Ledger mcp ' . $marker, 'content' => 'Body', 'customer_email' => $marker . '-ledger-mcp@example.invalid']));
            $record('create: MCP, with an agent', fn() => TicketTools::createTicket(['title' => 'Ledger mcp agent ' . $marker, 'content' => 'Body', 'customer_email' => $marker . '-ledger-mcp@example.invalid', 'agent_id' => FsFactory::agent()->id]));
            $record('create: PHP API', fn() => FluentSupportApi('tickets')->createTicket(['title' => 'Ledger api ' . $marker, 'content' => '<p>Body</p>', 'customer_id' => $customer->id]));

            $owner = $portalCustomer();
            $as($owner->user_id);
            try {
                $record('create: portal', fn() => FsTest::rest('POST', 'customer-portal/tickets', ['title' => 'Ledger portal ' . $marker, 'content' => '<p>Body</p>']));
            } finally {
                $as($adminId);
            }
            $adopt();
        });

        FsTest::case('ledger: thread', function () use ($record, $ticket, $adminAgent, $portalCustomer, $as, $adminId) {
            $one = $ticket();
            $base = 'tickets/' . $one->id;
            $record('reply: admin', fn() => FsTest::rest('POST', $base . '/responses', ['content' => '<p>Reply</p>', 'conversation_type' => 'response', 'force_send' => 'yes']));
            $record('note: admin', fn() => FsTest::rest('POST', $base . '/responses', ['content' => '<p>Note</p>', 'conversation_type' => 'note']));
            $draft = null;
            $record('approval draft: admin', function () use ($base, &$draft) {
                $draft = FsTest::rest('POST', $base . '/responses', ['content' => '<p>Draft</p>', 'conversation_type' => 'draft_response']);
            });
            $draftId = (int) json_decode(wp_json_encode($draft['data']), true)['response']['id'];
            $record('approval draft: approved', fn() => FsTest::rest('PUT', $base . '/approve_draft_response/' . $draftId, ['content' => '<p>Approved</p>']));
            $responseId = (int) Conversation::where('ticket_id', $one->id)->where('conversation_type', 'response')->value('id');
            $record('reply: edit', fn() => FsTest::rest('PUT', $base . '/responses/' . $responseId, ['content' => '<p>Edited</p>']));
            $record('reply: delete', fn() => FsTest::rest('DELETE', $base . '/responses/' . $responseId));
            $record('reply: bulk', fn() => FsTest::rest('POST', 'tickets/bulk-reply', ['ticket_ids' => [$one->id], 'content' => '<p>Bulk</p>']));
            $record('reply: MCP', fn() => ResponseTools::replyToTicket(['ticket_id' => $one->id, 'content' => 'From MCP']));
            $record('note: MCP', fn() => ResponseTools::addInternalNote(['ticket_id' => $one->id, 'content' => 'MCP note']));
            $record('reply: PHP API', fn() => FluentSupportApi('tickets')->addResponse(['content' => '<p>API reply</p>'], (int) $adminAgent->id, (int) $one->id));

            $unassigned = $ticket(['agent_id' => null, 'status' => 'new']);
            $record('reply: admin on an unassigned ticket (auto-assign)', fn() => FsTest::rest('POST', 'tickets/' . $unassigned->id . '/responses', ['content' => '<p>First</p>', 'conversation_type' => 'response', 'force_send' => 'yes']));

            $owner = $portalCustomer();
            $theirs = $ticket(['customer_id' => $owner->id]);
            $serial = (int) Ticket::find($theirs->id)->serial_number;
            $as($owner->user_id);
            try {
                $record('reply: portal', fn() => FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/responses', ['content' => '<p>Customer reply</p>']));
                $record('close: portal', fn() => FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/close'));
                $record('reopen: portal', fn() => FsTest::rest('POST', 'customer-portal/tickets/' . $serial . '/re-open'));
            } finally {
                $as($adminId);
            }
        });

        FsTest::case('ledger: close, reopen, assign and properties', function () use ($record, $ticket, $adminAgent, $marker) {
            $one = $ticket();
            $base = 'tickets/' . $one->id;
            $record('close: admin', fn() => FsTest::rest('POST', $base . '/close', ['close_ticket_silently' => 'no']));
            $record('reopen: admin', fn() => FsTest::rest('POST', $base . '/re-open'));
            $record('close: MCP', fn() => TicketTools::closeTicket(['ticket_id' => $one->id]));
            $record('reopen: MCP (status active)', fn() => TicketTools::updateTicket(['ticket_id' => $one->id, 'status' => 'active']));
            // Without the flag the close is silent (a quirk; the admin UI always sends it).
            $record('close: admin, no silent flag', fn() => FsTest::rest('POST', $base . '/close'));
            FsTest::rest('POST', $base . '/re-open');
            $record('close: bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'close_tickets', 'ticket_ids' => [$one->id]]));
            $record('reopen: bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'reopen_tickets', 'ticket_ids' => [$one->id]]));

            $other = FsFactory::agent();
            $record('assign: admin property', fn() => FsTest::rest('PUT', $base . '/property', ['prop_name' => 'agent_id', 'prop_value' => $other->id]));
            $record('assign: bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'assign_agent', 'ticket_ids' => [$one->id], 'agent_id' => $adminAgent->id]));
            $record('assign: MCP', fn() => TicketTools::updateTicket(['ticket_id' => $one->id, 'agent_id' => $other->id]));

            $product = FsFactory::product();
            $fresh = $ticket(['status' => 'new']);
            $record('change: status new to active', fn() => FsTest::rest('PUT', 'tickets/' . $fresh->id . '/property', ['prop_name' => 'status', 'prop_value' => 'active']));
            $record('change: title', fn() => FsTest::rest('PUT', $base . '/property', ['prop_name' => 'title', 'prop_value' => 'Ledger title ' . $marker]));
            $record('change: priority', fn() => FsTest::rest('PUT', $base . '/property', ['prop_name' => 'priority', 'prop_value' => 'critical']));
            $record('change: client priority', fn() => FsTest::rest('PUT', $base . '/property', ['prop_name' => 'client_priority', 'prop_value' => 'medium']));
            $record('change: product', fn() => FsTest::rest('PUT', $base . '/property', ['prop_name' => 'product_id', 'prop_value' => $product->id]));
            $record('change: priority, bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'change_priority', 'ticket_ids' => [$one->id], 'priority' => 'normal']));
            $record('change: product, bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'change_product', 'ticket_ids' => [$one->id], 'product_id' => $product->id]));
            $record('change: priority, MCP', fn() => TicketTools::updateTicket(['ticket_id' => $one->id, 'priority' => 'medium']));
            $record('change: customer', fn() => FsTest::rest('PUT', $base . '/change-customer', ['customer' => FsFactory::customer()->id]));

            $mailbox = FsFactory::mailbox();
            $record('move: mailbox screen', fn() => FsTest::rest('PUT', 'mailboxes/' . $one->mailbox_id . '/move_tickets', ['ticket_ids' => [$one->id], 'new_box_id' => $mailbox->id, 'move_type' => 'selected']));
            $record('move: MCP', fn() => TicketTools::updateTicket(['ticket_id' => $one->id, 'mailbox_id' => FsFactory::mailbox()->id]));

            $tag = FsFactory::tag(['tag_type' => 'ticket_tag']);
            $record('tags: add', fn() => FsTest::rest('POST', $base . '/tags', ['tag_id' => $tag->id]));
            $record('tags: add again', fn() => FsTest::rest('POST', $base . '/tags', ['tag_id' => $tag->id]));
            $record('tags: remove', fn() => FsTest::rest('DELETE', $base . '/tags/' . $tag->id));
            $record('tags: bulk add', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'assign_tags', 'ticket_ids' => [$one->id], 'tag_ids' => [$tag->id]]));
            $record('tags: bulk remove', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'remove_tags', 'ticket_ids' => [$one->id], 'tag_ids' => [$tag->id]]));
        });

        FsTest::case('ledger: delete', function () use ($record, $ticket) {
            $record('delete: admin', fn() => FsTest::rest('DELETE', 'tickets/' . $ticket()->id . '/delete'));
            $record('delete: bulk', fn() => FsTest::rest('POST', 'tickets/bulk-actions', ['bulk_action' => 'delete_tickets', 'ticket_ids' => [$ticket()->id]]));
            $record('delete: MCP', fn() => TicketTools::deleteTicket(['ticket_id' => $ticket()->id, 'confirm' => true]));
        });

        FsTest::case('ledger: Pro email piping, merge, split and watchers', function () use ($record, $ticket, $marker, $adopt, $adminAgent) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $handler = \FluentSupportPro\App\Services\Integrations\FluentEmailPiping\ByMailHandler::class;
            $mailbox = FsFactory::mailbox();
            $email = $marker . '-ledger-pipe@example.invalid';
            $record('create: email piping', fn() => $handler::handleEmailData(['subject' => 'Ledger pipe ' . $marker, 'sender' => ['email' => $email, 'name' => 'Pipe'], 'content' => '<p>Piped</p>', 'message_id' => '<' . $marker . '-pipe@example.invalid>'], $mailbox, 'email'));
            $adopt();
            $piped = Ticket::where('title', 'Ledger pipe ' . $marker)->first();
            if ($piped) {
                $record('reply: email piping', fn() => $handler::handleEmailData(['subject' => 'Re: Ledger pipe #' . $piped->id, 'sender' => ['email' => $email, 'name' => 'Pipe'], 'content' => '<p>Piped reply</p>', 'message_id' => '<' . $marker . '-pipe-2@example.invalid>', 'is_reply' => true], $mailbox, 'email'));
            }

            $one = $ticket();
            $base = 'tickets/' . $one->id;
            $record('watchers: sync', fn() => FsTest::rest('POST', $base . '/sync-watchers', ['watchers' => [$adminAgent->id]]));
            $record('watchers: add', fn() => FsTest::rest('POST', $base . '/add_watchers', ['watchers' => [FsFactory::agent()->id]]));

            $conversation = FsFactory::conversation($one, ['person_id' => $one->customer_id]);
            $record('split', fn() => FsTest::rest('POST', $base . '/split_ticket', ['split_ticket' => ['conversation_id' => $conversation->id, 'customer_id' => $one->customer_id, 'title' => 'Ledger split ' . $marker, 'content' => '<p>Split</p>']]));
            $adopt();
            $source = $ticket(['customer_id' => $one->customer_id]);
            $record('merge', fn() => FsTest::rest('POST', $base . '/merge_tickets', ['ticket_to_merge' => [$source->id]]));
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }

    $ledger->finish();
};
