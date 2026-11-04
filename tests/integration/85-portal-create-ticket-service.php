<?php
/**
 * Portal tickets are created through TicketService::storeCustomerTicket(), the
 * same steps as an agent's ticket minus the agent's own: an agent filing on_behalf
 * of a customer must not mark the ticket as agent-created (created_by, the
 * ticket_created_behalf_of_customer hook and its email). Input for a field the
 * ticket form disables is dropped, and a create_ticket_data filter cannot move the
 * ticket into a mailbox the acting agent is restricted from.
 * The agent user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $allowedBox = FsFactory::mailbox();
    $restrictedBox = FsFactory::mailbox();
    $product = FsFactory::product();
    $agent = FsFactory::agent();

    $login = FsTest::uniq('fs-create-svc-agent');
    $agentUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($agentUserId)) {
        throw new RuntimeException('Could not create agent user: ' . $agentUserId->get_error_message());
    }

    $switchTo = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    // Posts a ticket on behalf of a fresh customer; returns [result, ticket or null].
    $createOnBehalf = function ($slug, array $extra = []) use ($marker, $allowedBox) {
        $email = $marker . '-' . $slug . '@example.invalid';
        $result = FsTest::rest('POST', '/customer-portal/tickets', $extra + [
            'title'      => 'Create service ' . $slug . ' ' . $marker,
            'content'    => '<p>Create service ' . $marker . '</p>',
            'mailbox_id' => $allowedBox->id,
            'on_behalf'  => ['email' => $email, 'first_name' => 'CreateSvc'],
        ]);

        $ticket = null;
        $customer = Customer::where('email', $email)->first();
        if ($customer) {
            foreach (Ticket::where('customer_id', $customer->id)->get() as $row) {
                FsFactory::adoptRestTicket($row);
                $ticket = $row;
            }
            FsFactory::adoptRestCustomer($customer);
        }

        return [$result, $ticket];
    };

    try {
        $agent->user_id = $agentUserId;
        $agent->save();
        PermissionManager::attachPermissions($agentUserId, ['fst_sensitive_data', 'fst_manage_other_tickets', 'fst_manage_settings']);
        $agent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        FsTest::case('portal create: an agent filing on_behalf does not make an agent-created ticket', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf) {
            $fired = ['ticket_created' => 0, 'ticket_created_behalf_of_customer' => 0];
            $count = function ($hook) use (&$fired) {
                return function () use (&$fired, $hook) {
                    $fired[$hook]++;
                };
            };
            $onCreated = $count('ticket_created');
            $onBehalfCreated = $count('ticket_created_behalf_of_customer');
            add_action('fluent_support/ticket_created', $onCreated);
            add_action('fluent_support/ticket_created_behalf_of_customer', $onBehalfCreated);

            $switchTo($agentUserId);
            try {
                [$result, $ticket] = $createOnBehalf('not-agent-created');
            } finally {
                $switchTo($adminId);
                remove_action('fluent_support/ticket_created', $onCreated);
                remove_action('fluent_support/ticket_created_behalf_of_customer', $onBehalfCreated);
            }

            FsTest::assertHealthy($result, 'on_behalf ticket');
            FsTest::assert($ticket !== null, 'ticket created');
            FsTest::assert(empty($ticket->created_by), 'created_by stays empty, got ' . var_export($ticket->created_by, true));
            FsTest::assertSame('web', (string) $ticket->source, 'source');
            FsTest::assertSame(1, $fired['ticket_created'], 'ticket_created fires once');
            FsTest::assertSame(0, $fired['ticket_created_behalf_of_customer'], 'ticket_created_behalf_of_customer does not fire');
        });

        FsTest::case('portal create: product and priority are kept while the form enables them', function () use ($createOnBehalf, $product) {
            [$result, $ticket] = $createOnBehalf('fields-enabled', [
                'product_id'      => $product->id,
                'client_priority' => 'high',
            ]);

            FsTest::assertHealthy($result, 'ticket with product and priority');
            FsTest::assertSame((int) $product->id, (int) $ticket->product_id, 'product_id');
            FsTest::assertSame('high', (string) $ticket->client_priority, 'client_priority');
        });

        FsTest::case('portal create: input for a field the form disables is dropped', function () use ($createOnBehalf, $product) {
            $disable = function () {
                return ['product_services', 'priority'];
            };
            add_filter('fluent_support/disabled_ticket_fields', $disable, 99);

            try {
                [$result, $ticket] = $createOnBehalf('fields-disabled', [
                    'product_id'      => $product->id,
                    'client_priority' => 'high',
                ]);
            } finally {
                remove_filter('fluent_support/disabled_ticket_fields', $disable, 99);
            }

            FsTest::assertHealthy($result, 'ticket with disabled fields');
            FsTest::assert(empty($ticket->product_id), 'product_id dropped, got ' . var_export($ticket->product_id, true));
            // The column default fills it instead.
            FsTest::assert((string) $ticket->client_priority !== 'high', 'client_priority dropped, got ' . var_export($ticket->client_priority, true));
        });

        FsTest::case('portal create: a filter cannot move an agent\'s ticket into a restricted mailbox', function () use ($switchTo, $agentUserId, $adminId, $createOnBehalf, $restrictedBox) {
            $reroute = function ($data) use ($restrictedBox) {
                $data['mailbox_id'] = $restrictedBox->id;
                return $data;
            };
            add_filter('fluent_support/create_ticket_data', $reroute, 99);

            $switchTo($agentUserId);
            try {
                [$result, $ticket] = $createOnBehalf('rerouted');
            } finally {
                $switchTo($adminId);
                remove_filter('fluent_support/create_ticket_data', $reroute, 99);
            }

            FsTest::assertSame(403, (int) $result['status'], 'status');
            FsTest::assert(strpos((string) $result['message'], 'business box') !== false, 'message: ' . $result['message']);
            FsTest::assert($ticket === null, 'no ticket created');
        });
    } finally {
        $switchTo($adminId);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($agentUserId);
    }
};
