<?php
/**
 * POST /customers links a WordPress user through the customer email only. A client
 * user_id is refused, so a new record can't become another account's portal identity.
 */

use FluentSupport\App\Models\Customer;

return function () {

    $marker = FsFactory::marker();

    $userId = wp_insert_user([
        'user_login' => substr($marker . '-create-linked', 0, 60),
        'user_email' => $marker . '-create-linked@example.invalid',
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create WP user: ' . $userId->get_error_message());
    }

    // Hand a REST-created customer to the factory so cleanup removes it.
    $adopt = function ($email) {
        $customer = Customer::where('email', $email)->first();
        return $customer ? FsFactory::adoptRestCustomer($customer) : null;
    };

    try {
        FsTest::case('customer create: a client user_id is refused', function () use ($marker, $userId, $adopt) {
            $email = $marker . '-create-with-uid@example.invalid';

            $result = FsTest::rest('POST', '/customers', [
                'email'      => $email,
                'first_name' => 'WithUid',
                'user_id'    => $userId,
            ]);

            FsTest::assertSame(422, (int) $result['status'], 'status');
            FsTest::assert(strpos($result['message'], 'user_id is not accepted') !== false, 'message: ' . $result['message']);
            FsTest::assert(!$adopt($email), 'no customer created');
        });

        FsTest::case('customer create: an empty user_id is tolerated', function () use ($marker, $adopt) {
            $email = $marker . '-create-zero-uid@example.invalid';

            $result = FsTest::rest('POST', '/customers', [
                'email'      => $email,
                'first_name' => 'ZeroUid',
                'user_id'    => 0,
            ]);

            FsTest::assertHealthy($result, 'create with user_id 0');
            FsTest::assert((bool) $adopt($email), 'customer created');
        });

        FsTest::case('customer create: the WordPress user is linked by email', function () use ($marker, $userId, $adopt) {
            $email = $marker . '-create-linked@example.invalid';

            $result = FsTest::rest('POST', '/customers', [
                'email'      => $email,
                'first_name' => 'Linked',
            ]);

            FsTest::assertHealthy($result, 'create linked by email');
            $customer = $adopt($email);
            FsTest::assertSame((int) $userId, $customer ? (int) $customer->user_id : null, 'linked user id');
        });
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
