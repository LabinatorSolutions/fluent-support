<?php
/**
 * One way to create a ticket (dev-works/tickets-architecture.md 3.1):
 * - The customer comes from CustomerService::findOrCreate() everywhere: an
 *   existing customer's email is reused.
 * - A ticket created with an agent gets the same activity line and hooks as any
 *   assignment, after ticket_created; creating one for another agent through the
 *   admin needs fst_assign_agents.
 * - A piped email ticket goes through the shared create path, so
 *   before_ticket_create sees the data after create_ticket_data.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Tools\TicketTools;
use FluentSupport\App\Modules\PermissionManager;

return function () {
    $marker = FsFactory::marker();
    $adminId = get_current_user_id();
    $userIds = [];

    $adopt = function () use ($marker) {
        foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $ticket) {
            FsFactory::adoptRestTicket($ticket);
        }
        foreach (Customer::where('email', 'LIKE', '%' . $marker . '%')->get() as $customer) {
            FsFactory::adoptRestCustomer($customer);
        }
    };

    try {
        FsTest::case('create: an existing customer email is reused', function () use ($marker, $adopt) {
            $customer = FsFactory::customer(['email' => $marker . '-reuse@example.invalid']);
            FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Reuse ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes'], 'newCustomer' => ['email' => $customer->email, 'first_name' => 'Other']]);
            $adopt();
            FsTest::assertSame((int) $customer->id, (int) Ticket::where('title', 'Reuse ' . $marker)->value('customer_id'), 'same customer');
            FsTest::assertSame(1, Customer::where('email', $customer->email)->count(), 'no second customer');
        });

        FsTest::case('create: the new customer\'s user_id and remote_uid are not taken from the request', function () use ($marker, $adopt) {
            $owner = FsFactory::customer(['user_id' => get_current_user_id(), 'remote_uid' => 'r-' . $marker]);
            FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Linked ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes'], 'newCustomer' => ['email' => $marker . '-linked@example.invalid', 'user_id' => get_current_user_id(), 'remote_uid' => 'r-' . $marker]]);
            $adopt();
            $customerId = (int) Ticket::where('title', 'Linked ' . $marker)->value('customer_id');
            FsTest::assert($customerId && $customerId !== (int) $owner->id, 'not filed under the user\'s customer');
            FsTest::assertSame($marker . '-linked@example.invalid', Customer::find($customerId)->email, 'the customer of the entered email');
        });

        FsTest::case('create: an existing linked customer is reused, not rewritten', function () use ($marker, $adopt, &$userIds) {
            $email = $marker . '-linked@example.invalid';
            $userId = wp_insert_user(['user_login' => FsTest::uniq('fs-linked'), 'user_pass' => wp_generate_password(24), 'user_email' => $email, 'role' => 'subscriber']);
            $userIds[] = $userId;
            // Registering the user may already make its customer; link one either way.
            $customer = Customer::where('user_id', $userId)->first() ?: FsFactory::customer(['email' => $email]);
            FsFactory::adoptRestCustomer($customer);
            \FluentSupport\App\Models\Person::where('id', $customer->id)->update(['user_id' => $userId, 'status' => 'blocked']);
            $customer = Customer::find($customer->id);
            $hash = \FluentSupport\App\Models\Person::find($customer->id)->hash;
            FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Rewrite ' . $marker, 'content' => '<p>Body</p>', 'create_customer' => 'yes'], 'newCustomer' => ['email' => $email, 'first_name' => 'Ada', 'status' => 'active', 'person_type' => 'agent', 'hash' => 'known']]);
            $adopt();
            $people = \FluentSupport\App\Models\Person::where('email', $email)->get();
            foreach ($people as $person) {
                if ((int) $person->id !== (int) $customer->id) {
                    \FluentSupport\App\Models\Person::where('id', $person->id)->delete();
                }
            }
            FsTest::assertSame([(int) $customer->id], array_map('intval', $people->pluck('id')->toArray()), 'no second person made for the email');
            $fresh = \FluentSupport\App\Models\Person::find($customer->id);
            FsTest::assertSame(['customer', 'blocked', $hash, $customer->first_name], [$fresh->person_type, $fresh->status, $fresh->hash, $fresh->first_name], 'type, status, hash and name kept');
        });

        FsTest::case('create: assigning while creating needs fst_assign_agents', function () use ($marker, $adopt, $adminId, &$userIds) {
            $login = FsTest::uniq('fs-create');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
            $userIds[] = $userId;
            FsFactory::agent(['user_id' => $userId]);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets', 'fst_manage_unassigned_tickets', 'fst_manage_other_tickets']);
            $other = FsFactory::agent();
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Assign at create ' . $marker, 'content' => '<p>Body</p>', 'customer_id' => FsFactory::customer()->id, 'agent_id' => $other->id]]);
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
            $adopt();
            FsTest::assert((int) Ticket::where('title', 'Assign at create ' . $marker)->value('agent_id') !== (int) $other->id, 'not assigned to the other agent');
        });

        FsTest::case('create: the response names the assigned agent without their private fields', function () use ($marker, $adopt) {
            $agent = FsFactory::agent();
            $result = FsTest::rest('POST', 'tickets', ['ticket' => ['title' => 'Agent fields ' . $marker, 'content' => '<p>Body</p>', 'customer_id' => FsFactory::customer()->id, 'agent_id' => $agent->id]]);
            $adopt();
            $row = json_decode(wp_json_encode($result['data']['ticket']['agent'] ?? []), true);
            FsTest::assertSame((int) $agent->id, (int) ($row['id'] ?? 0), 'agent');
            foreach (['email', 'ip_address', 'hash', 'note', 'remote_uid'] as $key) {
                FsTest::assert(!array_key_exists($key, $row), 'agent carries ' . $key);
            }
        });

        FsTest::case('create: a ticket created with an agent gets the assignment line and hooks', function () use ($marker, $adopt) {
            $agent = FsFactory::agent();
            $fired = [];
            $listen = function () use (&$fired) {
                $fired[] = current_filter();
            };
            add_action('fluent_support/ticket_agent_change', $listen);
            add_action('fluent_support/agent_assigned_to_ticket', $listen);
            try {
                TicketTools::createTicket(['title' => 'MCP agent ' . $marker, 'content' => 'Body', 'customer_email' => $marker . '-mcp-agent@example.invalid', 'agent_id' => $agent->id]);
            } finally {
                remove_action('fluent_support/ticket_agent_change', $listen);
                remove_action('fluent_support/agent_assigned_to_ticket', $listen);
            }
            $adopt();
            $ticket = Ticket::where('title', 'MCP agent ' . $marker)->first();
            FsTest::assertSame((int) $agent->id, (int) $ticket->agent_id, 'assigned');
            FsTest::assertSame(['fluent_support/ticket_agent_change', 'fluent_support/agent_assigned_to_ticket'], $fired, 'hooks');
            FsTest::assertSame(1, Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'internal_info')->where('content', 'LIKE', 'assigned this ticket%')->count(), 'activity line');
        });

        FsTest::case('create: a piped ticket goes through create_ticket_data before before_ticket_create', function () use ($marker, $adopt) {
            if (!defined('FLUENTSUPPORTPRO')) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }
            $tag = function ($data) {
                $data['title'] = $data['title'] . ' [filtered]';
                return $data;
            };
            $seenTitle = null;
            $before = function ($data) use (&$seenTitle) {
                $seenTitle = $data['title'] ?? null;
            };
            add_filter('fluent_support/create_ticket_data', $tag);
            add_action('fluent_support/before_ticket_create', $before);
            try {
                \FluentSupportPro\App\Services\Integrations\FluentEmailPiping\ByMailHandler::handleEmailData(['subject' => 'Piped create ' . $marker, 'sender' => ['email' => $marker . '-piped-create@example.invalid', 'name' => 'Pipe'], 'content' => '<p>Body</p>', 'message_id' => '<' . $marker . '-create@example.invalid>'], FsFactory::mailbox(), 'email');
            } finally {
                remove_filter('fluent_support/create_ticket_data', $tag);
                remove_action('fluent_support/before_ticket_create', $before);
            }
            $adopt();
            FsTest::assertSame('Piped create ' . $marker . ' [filtered]', $seenTitle, 'before_ticket_create sees the filtered data');
            FsTest::assertSame('Piped create ' . $marker . ' [filtered]', Ticket::where('title', 'LIKE', 'Piped create ' . $marker . '%')->value('title'), 'stored');
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
