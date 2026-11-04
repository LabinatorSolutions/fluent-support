<?php
/**
 * `fluent_support/customer_created` must never fire for a Customer that was
 * never actually persisted.
 *
 * Customer::create() (via the ORM's Builder::create()) always returns the
 * model instance, regardless of whether save() succeeded — it never returns
 * false. A `creating` listener that cancels the save leaves the returned
 * object with exists=false and id=NULL, which is indistinguishable from a
 * real customer unless callers check ->exists. These cases force that
 * cancellation and assert the hook stays silent, and that the normal path
 * still fires it exactly once, at the three sites that call the hook:
 * TicketController::createTicket(), Customer::maybeCreateCustomer(), and
 * (covered indirectly by the same guard pattern) the MCP ticket tool.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

return function () {

    $marker = FsFactory::marker();

    /**
     * Force every Customer::save() to fail for the duration of $fn, the way
     * a third-party `creating` listener would. The toggle is a single
     * listener gated by a flag rather than flushEventListeners(), which
     * would also strip Customer::boot()'s own `creating` listener (the one
     * that sets `hash`) and never re-register it for the rest of the run.
     *
     * @param callable $fn
     * @return mixed
     */
    $cancelCustomerSave = false;
    Customer::creating(function () use (&$cancelCustomerSave) {
        if ($cancelCustomerSave) {
            return false;
        }
    });

    $withCancelledCustomerCreate = function (callable $fn) use (&$cancelCustomerSave) {
        $cancelCustomerSave = true;

        try {
            return $fn();
        } finally {
            $cancelCustomerSave = false;
        }
    };

    FsTest::case('maybe_create_customer_skips_hook_when_save_is_cancelled', function () use ($marker, $withCancelledCustomerCreate) {
        $email = $marker . '-cancelled-' . FsTest::uniq('mc') . '@example.invalid';

        $fired = 0;
        $listener = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/customer_created', $listener);

        $customer = $withCancelledCustomerCreate(function () use ($email) {
            return Customer::maybeCreateCustomer([
                'email'      => $email,
                'first_name' => 'Cancelled',
                'last_name'  => 'Customer',
            ]);
        });

        remove_action('fluent_support/customer_created', $listener);

        FsTest::assertSame(0, $fired, 'customer_created fired for a customer that was never saved');
        FsTest::assert(!$customer || !$customer->exists, 'maybeCreateCustomer returned a row marked as persisted despite the cancelled save');
        FsTest::assert(!Customer::where('email', $email)->exists(), 'a customer row exists despite the cancelled save');
    });

    FsTest::case('maybe_create_customer_still_fires_hook_on_a_real_save', function () use ($marker) {
        $email = $marker . '-real-' . FsTest::uniq('mc') . '@example.invalid';

        $fired = 0;
        $listener = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/customer_created', $listener);

        $customer = Customer::maybeCreateCustomer([
            'email'      => $email,
            'first_name' => 'Real',
            'last_name'  => 'Customer',
        ]);

        remove_action('fluent_support/customer_created', $listener);

        if ($customer && $customer->exists) {
            FsFactory::adoptRestCustomer($customer);
        }

        FsTest::assertSame(1, $fired, 'customer_created did not fire for a genuinely saved customer');
        FsTest::assert($customer && $customer->exists, 'maybeCreateCustomer did not persist the customer on the happy path');
    });

    FsTest::case('create_ticket_rejects_request_when_new_customer_save_is_cancelled', function () use ($marker, $withCancelledCustomerCreate) {
        $email = $marker . '-ticket-cancelled-' . FsTest::uniq('mc') . '@example.invalid';
        $mailbox = FsFactory::mailbox();

        $fired = 0;
        $listener = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/customer_created', $listener);

        $result = $withCancelledCustomerCreate(function () use ($email, $mailbox, $marker) {
            return FsTest::rest('POST', '/tickets', [
                'ticket' => [
                    'mailbox_id' => $mailbox->id,
                    'title'      => 'Hook guard ticket ' . $marker,
                    'content'    => '<p>Hook guard body ' . $marker . '</p>',
                    'priority'   => 'normal',
                ],
                'newCustomer' => [
                    'email'      => $email,
                    'first_name' => 'Cancelled',
                    'last_name'  => 'Ticket',
                ],
            ]);
        });

        remove_action('fluent_support/customer_created', $listener);

        FsTest::assertSame(422, $result['status'], 'createTicket did not reject a request whose new customer failed to save');
        FsTest::assertSame(0, $fired, 'createTicket fired customer_created for a customer that was never saved');
        FsTest::assert(!Customer::where('email', $email)->exists(), 'createTicket left behind a customer row despite the cancelled save');

        // Adopt defensively in case a future regression does persist the row -
        // without this, a regression would leak a fixture past cleanup.
        $leaked = Customer::where('email', $email)->first();
        if ($leaked) {
            FsFactory::adoptRestCustomer($leaked);
        }

        $leakedTicket = Ticket::where('title', 'Hook guard ticket ' . $marker)->first();
        FsTest::assert(!$leakedTicket, 'createTicket persisted a ticket despite rejecting the request');
        if ($leakedTicket) {
            FsFactory::adoptRestTicket($leakedTicket);
        }
    });

    FsTest::case('create_ticket_does_not_fire_hook_for_an_existing_customer_email', function () use ($marker) {
        $existingCustomer = FsFactory::customer();
        $mailbox = FsFactory::mailbox();

        $fired = 0;
        $listener = function () use (&$fired) {
            $fired++;
        };
        add_action('fluent_support/customer_created', $listener);

        $result = FsTest::rest('POST', '/tickets', [
            'ticket' => [
                'mailbox_id' => $mailbox->id,
                'title'      => 'Hook guard existing-customer ticket ' . $marker,
                'content'    => '<p>Hook guard existing-customer body ' . $marker . '</p>',
                'priority'   => 'normal',
            ],
            'newCustomer' => [
                'email'      => $existingCustomer->email,
                'first_name' => 'Should Not Be Used',
                'last_name'  => 'Should Not Be Used',
            ],
        ]);

        remove_action('fluent_support/customer_created', $listener);

        foreach (Ticket::where('title', 'Hook guard existing-customer ticket ' . $marker)->get() as $persisted) {
            FsFactory::adoptRestTicket($persisted);
        }

        FsTest::assertHealthy($result, 'POST /tickets with an already-registered email');
        FsTest::assertSame(0, $fired, 'createTicket fired customer_created for an email that already had a customer row');

        $created = isset($result['data']['ticket']['id']) ? (int) $result['data']['ticket']['id'] : 0;
        if ($created) {
            $ticket = Ticket::find($created);
            FsTest::assert($ticket && (int) $ticket->customer_id === (int) $existingCustomer->id,
                'createTicket did not reuse the existing customer row for a known email');
        } else {
            FsTest::fail('POST /tickets did not return a ticket id for an existing-customer email');
        }
    });
};
