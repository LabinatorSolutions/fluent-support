<?php
/**
 * FluentCRM "Create Support Ticket" action: a blocked customer stays blocked and
 * gets no ticket, and the contact's status and hash never reach the customer row.
 * WordPress users and CRM contacts aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

return function () {

    if (!defined('FLUENTCRM') || !has_action('fluentcrm_funnel_sequence_handle_fs_create_ticket')) {
        FsTest::case('fluentcrm: create support ticket action', function () {
            FsTest::skip('FluentCRM is not active, so the Create Support Ticket action is not registered');
        });
        return;
    }

    $marker = FsFactory::marker();
    $mailbox = FsFactory::mailbox();
    $userIds = [];
    $subscriberIds = [];

    // A FluentCRM contact (CRM status 'subscribed') with a WordPress user of the same email.
    $makeContact = function ($prefix) use (&$userIds, &$subscriberIds, $marker) {
        $login = FsTest::uniq($prefix);
        $email = $marker . '-' . $login . '@example.invalid';
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $email,
            'role'       => 'subscriber',
        ]);
        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create user: ' . $userId->get_error_message());
        }
        $userIds[] = $userId;

        $subscriber = \FluentCrm\App\Models\Subscriber::create([
            'email'      => $email,
            'first_name' => 'Crm',
            'last_name'  => 'Contact',
            'city'       => 'Crm City',
            'status'     => 'subscribed',
            'user_id'    => $userId,
        ]);
        $subscriberIds[] = $subscriber->id;

        return [$userId, \FluentCrm\App\Models\Subscriber::find($subscriber->id)];
    };

    // Runs the step the way FluentCRM's funnel runner does; returns the tickets it created.
    $runAction = function ($subscriber) use ($mailbox, $marker) {
        $title = 'CRM action ticket ' . $marker . ' ' . FsTest::uniq('t');
        $sequence = (object) ['id' => 0, 'settings' => [
            'ticket_title'   => $title,
            'ticket_content' => '<p>Created by automation</p>',
            'business_inbox' => $mailbox->id,
        ]];

        do_action('fluentcrm_funnel_sequence_handle_fs_create_ticket', $subscriber, $sequence, 0, null);

        $tickets = Ticket::where('title', $title)->get();
        foreach ($tickets as $ticket) {
            FsFactory::adoptRestTicket($ticket);
        }

        return $tickets;
    };

    try {
        FsTest::case('blocked_customer_is_not_unblocked_and_gets_no_ticket', function () use ($makeContact, $runAction) {
            list($userId, $subscriber) = $makeContact('fs-crm-blocked');
            $customer = FsFactory::customer(['email' => $subscriber->email, 'user_id' => $userId, 'status' => 'inactive']);
            $hash = Customer::find($customer->id)->hash;

            $tickets = $runAction($subscriber);

            $after = Customer::find($customer->id);
            FsTest::assertSame('inactive', $after->status, 'the action set a blocked customer back to active');
            FsTest::assertSame(0, count($tickets), 'the action created a ticket for a blocked customer');
            FsTest::assertSame($hash, $after->hash, 'the action overwrote the customer hash with the CRM contact hash');
        });

        FsTest::case('active_customer_gets_ticket_and_keeps_own_hash', function () use ($makeContact, $runAction) {
            list($userId, $subscriber) = $makeContact('fs-crm-active');
            $customer = FsFactory::customer(['email' => $subscriber->email, 'user_id' => $userId]);
            $hash = Customer::find($customer->id)->hash;

            $tickets = $runAction($subscriber);

            $after = Customer::find($customer->id);
            FsTest::assertSame(1, count($tickets), 'the action did not create a ticket for an active customer');
            FsTest::assert(count($tickets) && (int) $tickets[0]->customer_id === (int) $customer->id, 'the ticket is not on the existing customer');
            FsTest::assertSame('active', $after->status, 'an active customer changed status');
            FsTest::assertSame($hash, $after->hash, 'the action overwrote the customer hash with the CRM contact hash');
        });

        FsTest::case('new_contact_becomes_active_customer_not_subscribed', function () use ($makeContact, $runAction) {
            list(, $subscriber) = $makeContact('fs-crm-new');

            $tickets = $runAction($subscriber);

            $customer = Customer::where('email', $subscriber->email)->first();
            if ($customer) {
                FsFactory::adoptRestCustomer($customer);
            }
            FsTest::assert((bool) $customer, 'the action did not create a customer for a new contact');
            FsTest::assertSame('active', $customer ? $customer->status : null, 'a new customer did not get the active status');
            FsTest::assert($customer && $customer->hash !== $subscriber->hash, 'a new customer reused the CRM contact hash');
            FsTest::assertSame(1, count($tickets), 'the action did not create a ticket for a new contact');
        });

        FsTest::case('blocked_customer_matched_by_email_gets_no_ticket', function () use ($makeContact, $runAction) {
            // The blocked row is not linked to the WordPress user that owns its email.
            list(, $subscriber) = $makeContact('fs-crm-email');
            $customer = FsFactory::customer(['email' => $subscriber->email, 'status' => 'inactive']);

            $tickets = $runAction($subscriber);

            $others = Customer::where('email', $subscriber->email)->where('id', '!=', $customer->id)->get();
            foreach ($others as $other) {
                FsFactory::adoptRestCustomer($other);
            }
            FsTest::assertSame(0, count($tickets), 'a blocked customer got a ticket through a new customer for the same email');
            FsTest::assertSame(0, count($others), 'the action created a second customer for a blocked email');
            FsTest::assertSame('inactive', Customer::find($customer->id)->status, 'the blocked customer changed status');
        });

        FsTest::case('active_customer_linked_to_the_user_wins_over_blocked_email', function () use ($makeContact, $runAction, $marker) {
            list($userId, $subscriber) = $makeContact('fs-crm-both');
            $blocked = FsFactory::customer(['email' => $subscriber->email, 'status' => 'inactive']);
            $active = FsFactory::customer(['email' => $marker . '-both-other@example.invalid', 'user_id' => $userId, 'status' => 'active']);

            $tickets = $runAction($subscriber);

            FsTest::assertSame(1, count($tickets), 'an old blocked row with the same email blocked the user\'s own active customer');
            FsTest::assertSame((int) $active->id, count($tickets) ? (int) $tickets->first()->customer_id : null, 'the ticket went to the wrong customer');
            FsTest::assertSame('inactive', Customer::find($blocked->id)->status, 'the blocked customer changed status');
        });

        // Customers this action created before 2.1.0 can still be 'subscribed'; the action makes them active again.
        FsTest::case('legacy_subscribed_customer_is_made_active', function () use ($makeContact, $runAction) {
            list($userId, $subscriber) = $makeContact('fs-crm-legacy');
            $customer = FsFactory::customer(['email' => $subscriber->email, 'user_id' => $userId, 'status' => 'subscribed']);

            $tickets = $runAction($subscriber);

            FsTest::assertSame(1, count($tickets), 'a customer on the legacy subscribed status got no ticket');
            FsTest::assertSame('active', Customer::find($customer->id)->status, 'the legacy subscribed status was not made active');
        });

        // Any other leftover CRM status stays as it is and the customer stays blocked.
        FsTest::case('legacy_unsubscribed_customer_stays_blocked', function () use ($makeContact, $runAction) {
            list($userId, $subscriber) = $makeContact('fs-crm-unsub');
            $customer = FsFactory::customer(['email' => $subscriber->email, 'user_id' => $userId, 'status' => 'unsubscribed']);

            $tickets = $runAction($subscriber);

            FsTest::assertSame(0, count($tickets), 'a customer on the unsubscribed status got a ticket');
            FsTest::assertSame('unsubscribed', Customer::find($customer->id)->status, 'the unsubscribed status was changed');
        });
    } finally {
        if ($subscriberIds) {
            \FluentCrm\App\Models\Subscriber::whereIn('id', $subscriberIds)->delete();
        }
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
