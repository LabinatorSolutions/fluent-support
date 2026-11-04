<?php
/**
 * Data that only names an email (a guest Fluent Forms submission, a piped email)
 * may fill empty fields on a linked customer, never replace saved ones. Anyone
 * can type an email, so it does not prove the sender owns the profile. A caller
 * that passes the user_id (portal, logged-in form) still updates the record.
 *
 * The Fluent Forms cases run FeedIntegration::notify() and skip when Fluent
 * Forms is not active; the piping case skips without Pro.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Services\Integrations\FluentForm\FeedIntegration;
use FluentSupport\Framework\Support\Arr;
use FluentSupportPro\App\Services\Integrations\FluentEmailPiping\ByMailHandler;

return function () {

    $marker = FsFactory::marker();
    FsTest::interceptMail();

    // A linked customer whose WordPress user has no name meta (the WP default),
    // which is when the submitted name used to win.
    $email = $marker . '-victim@example.invalid';
    $userId = wp_insert_user([
        'user_login' => substr($marker . '-victim', 0, 60),
        'user_email' => $email,
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create WP user: ' . $userId->get_error_message());
    }

    $customer = FsFactory::customer([
        'email'      => $email,
        'user_id'    => $userId,
        'first_name' => 'Real',
        'last_name'  => 'Owner',
        'city'       => 'Dhaka',
        'zip'        => '',
    ]);

    $reset = function () use ($customer) {
        Customer::where('id', $customer->id)->update(['first_name' => 'Real', 'last_name' => 'Owner', 'city' => 'Dhaka', 'zip' => '']);
        return Customer::find($customer->id);
    };

    /**
     * Run the Fluent Forms feed for one submission and adopt the ticket it creates.
     * Null when Fluent Forms is not active.
     */
    $notify = null;
    if (defined('FLUENTFORM') && class_exists(FeedIntegration::class)) {
        $notify = function (array $customer, $slug) use ($marker) {
            $other = [];
            foreach (['city', 'zip'] as $field) {
                if (isset($customer[$field])) {
                    $other[] = ['label' => $field, 'item_value' => $customer[$field]];
                }
            }

            $feed = [
                'settings'        => ['name' => 'Unverified profile feed'],
                'processedValues' => array_merge(Arr::only($customer, ['email', 'first_name', 'last_name']), [
                    'list_id'               => FsFactory::mailbox()->id,
                    'ticket_title'          => 'Unverified profile form ' . $slug . ' ' . $marker,
                    'ticket_content'        => 'Submitted through the form.',
                    'customer_other_fields' => $other,
                ]),
            ];

            (new FeedIntegration())->notify($feed, [], (object) ['id' => 0, 'ip' => '203.0.113.9'], (object) ['id' => 0]);

            foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
                FsFactory::adoptRestTicket($persisted);
            }
        };
    }

    try {
        FsTest::case('unverified email data does not replace saved customer fields', function () use ($email, $customer, $reset) {
            $reset();

            $found = CustomerService::findOrCreate([
                'email'           => $email,
                'first_name'      => 'Spoofed',
                'last_name'       => 'Name',
                'city'            => 'Elsewhere',
                'zip'             => '1207',
                'last_ip_address' => '203.0.113.9',
            ]);

            $after = Customer::find($customer->id);
            FsTest::assertSame((int) $customer->id, (int) $found->id, 'matched the existing customer');
            FsTest::assertSame('Real', $after->first_name, 'first_name kept');
            FsTest::assertSame('Owner', $after->last_name, 'last_name kept');
            FsTest::assertSame('Dhaka', $after->city, 'city kept');
            FsTest::assertSame('1207', (string) $after->zip, 'empty zip filled');
        });

        FsTest::case('a caller-supplied user_id still updates the customer', function () use ($email, $userId, $customer, $reset) {
            $reset();

            CustomerService::findOrCreate([
                'user_id' => $userId,
                'email'   => $email,
                'city'    => 'Chattogram',
            ]);

            FsTest::assertSame('Chattogram', Customer::find($customer->id)->city, 'city updated');
        });

        FsTest::case('fluent forms: a guest submission does not replace saved customer fields', function () use ($notify, $email, $customer, $reset) {
            if (!$notify) {
                FsTest::skip('Fluent Forms is not active');
                return;
            }

            $reset();
            $admin = get_current_user_id();
            wp_set_current_user(0);
            try {
                $notify(['email' => $email, 'first_name' => 'Spoofed', 'last_name' => 'Name', 'city' => 'Elsewhere', 'zip' => '1207'], 'guest');
            } finally {
                wp_set_current_user($admin);
            }

            $after = Customer::find($customer->id);
            FsTest::assertSame('Real', $after->first_name, 'first_name kept');
            FsTest::assertSame('Owner', $after->last_name, 'last_name kept');
            FsTest::assertSame('Dhaka', $after->city, 'city kept');
            FsTest::assertSame('1207', (string) $after->zip, 'empty zip filled');
        });

        FsTest::case('fluent forms: a logged-in owner still updates their customer fields', function () use ($notify, $email, $userId, $customer, $reset) {
            if (!$notify) {
                FsTest::skip('Fluent Forms is not active');
                return;
            }

            $reset();
            $admin = get_current_user_id();
            wp_set_current_user($userId);
            try {
                $notify(['email' => $email, 'first_name' => 'Renamed', 'last_name' => 'Owner', 'city' => 'Chattogram'], 'owner');
            } finally {
                wp_set_current_user($admin);
            }

            $after = Customer::find($customer->id);
            FsTest::assertSame('Renamed', $after->first_name, 'first_name updated');
            FsTest::assertSame('Chattogram', $after->city, 'city updated');
        });

        FsTest::case('pro: a piped email does not rename a linked customer', function () use ($marker, $email, $customer, $reset) {
            if (!defined('FLUENTSUPPORTPRO') || !class_exists(ByMailHandler::class)) {
                FsTest::skip('Fluent Support Pro is not active');
                return;
            }

            $reset();

            $result = ByMailHandler::handleEmailData([
                'subject'    => 'Unverified profile pipe ' . $marker,
                'sender'     => ['email' => $email, 'name' => 'Spoofed Name'],
                'content'    => '<p>Piped by someone else.</p>',
                'message_id' => '<' . $marker . '-profile@example.invalid>',
            ], FsFactory::mailbox(), 'email');

            foreach (Ticket::where('title', 'LIKE', '%' . $marker . '%')->get() as $persisted) {
                FsFactory::adoptRestTicket($persisted);
            }

            FsTest::assert(!is_wp_error($result) && !empty($result['ticket_id']), 'piping created a ticket: ' . wp_json_encode($result));
            $after = Customer::find($customer->id);
            FsTest::assertSame('Real', $after->first_name, 'first_name kept');
            FsTest::assertSame('Owner', $after->last_name, 'last_name kept');
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
