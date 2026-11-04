<?php
/**
 * TicketService::storeCustomerTicket() is the create path for tickets filed for
 * a customer: the portal, remote portals, Fluent Forms and FluentCRM (roadmap
 * 4.2). Fluent Forms and FluentCRM aren't in the lab, so this pins what they
 * rely on:
 * - a logged-in agent who isn't acting on_behalf (submitting a form, running a
 *   funnel) is not held to their mailbox restrictions;
 * - a mailbox that no longer exists falls back to the default mailbox;
 * - the create hooks fire once.
 * The restricted user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;
use FluentSupport\App\Services\Tickets\TicketService;

return function () {

    $adminId = get_current_user_id();
    $restrictedBox = FsFactory::mailbox();
    $agent = FsFactory::agent();

    $login = FsTest::uniq('fs-create-agent');
    $userId = wp_insert_user([
        'user_login' => $login,
        'user_pass'  => wp_generate_password(24, true, true),
        'user_email' => $login . '@example.test',
        'role'       => 'subscriber',
    ]);
    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
    }

    $switchTo = function ($id) {
        wp_set_current_user($id);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
    };

    $ticketData = function (array $extra = []) {
        return $extra + [
            'title'   => 'Customer ticket ' . FsTest::uniq('ct') . ' [' . FsFactory::marker() . ']',
            'content' => '<p>From a form</p>',
            'source'  => 'web',
        ];
    };

    try {
        $agent->user_id = $userId;
        $agent->save();
        PermissionManager::attachPermissions($userId, ['fst_manage_own_tickets']);
        $agent->updateMeta('agent_restrictions', [
            'businessBoxRestrictions' => true,
            'restrictedBusinessBoxes' => [(int) $restrictedBox->id],
        ]);

        FsTest::case('customer_ticket_ignores_the_logged_in_agents_mailbox_limits', function () use ($switchTo, $userId, $adminId, $restrictedBox, $ticketData) {
            $customer = FsFactory::customer();

            $switchTo($userId);
            try {
                $ticket = (new TicketService())->storeCustomerTicket($ticketData(['customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id]), $customer);
            } finally {
                $switchTo($adminId);
            }
            FsFactory::adoptRestTicket($ticket);

            FsTest::assertSame((int) $restrictedBox->id, (int) $ticket->mailbox_id, 'created in the mailbox the form asked for');
        });

        FsTest::case('customer_ticket_on_behalf_keeps_the_agents_mailbox_limits', function () use ($switchTo, $userId, $adminId, $restrictedBox, $ticketData) {
            $customer = FsFactory::customer();

            $switchTo($userId);
            try {
                $refused = null;
                try {
                    (new TicketService())->storeCustomerTicket($ticketData(['customer_id' => $customer->id, 'mailbox_id' => $restrictedBox->id]), $customer, true);
                } catch (\Exception $e) {
                    $refused = $e;
                }
            } finally {
                $switchTo($adminId);
            }

            FsTest::assert($refused !== null, 'refused for an agent acting on_behalf');
        });

        FsTest::case('customer_ticket_with_a_deleted_mailbox_uses_the_default', function () use ($ticketData) {
            $customer = FsFactory::customer();
            $default = Helper::getDefaultMailBox();
            FsTest::assert((bool) $default, 'the lab has a default mailbox');

            $ticket = (new TicketService())->storeCustomerTicket($ticketData(['customer_id' => $customer->id, 'mailbox_id' => 999999999]), $customer);
            FsFactory::adoptRestTicket($ticket);

            FsTest::assertSame((int) $default->id, (int) $ticket->mailbox_id, 'default mailbox');
        });

        FsTest::case('customer_ticket_fires_the_create_hooks_once', function () use ($ticketData) {
            $customer = FsFactory::customer();
            $seen = ['filter' => 0, 'before' => 0, 'created' => 0];
            $onFilter = function ($data) use (&$seen) {
                $seen['filter']++;
                return $data;
            };
            $onBefore = function () use (&$seen) {
                $seen['before']++;
            };
            $onCreated = function () use (&$seen) {
                $seen['created']++;
            };
            add_filter('fluent_support/create_ticket_data', $onFilter);
            add_action('fluent_support/before_ticket_create', $onBefore, 10, 0);
            add_action('fluent_support/ticket_created', $onCreated, 10, 0);
            try {
                $ticket = (new TicketService())->storeCustomerTicket($ticketData(['customer_id' => $customer->id]), $customer);
            } finally {
                remove_filter('fluent_support/create_ticket_data', $onFilter);
                remove_action('fluent_support/before_ticket_create', $onBefore, 10);
                remove_action('fluent_support/ticket_created', $onCreated, 10);
            }
            FsFactory::adoptRestTicket($ticket);

            FsTest::assertSame(['filter' => 1, 'before' => 1, 'created' => 1], $seen, 'each once');
            FsTest::assert(empty($ticket->created_by), 'not agent-created');
        });
    } finally {
        $switchTo($adminId);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
