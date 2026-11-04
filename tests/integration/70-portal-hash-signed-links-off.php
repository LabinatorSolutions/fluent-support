<?php
/**
 * With signed ticket links turned off (disable_public_ticket), a request that
 * still carries intended_ticket_hash and no on_behalf is a remote portal relaying
 * an anonymous visitor. It must be refused, not resolved to the customer record of
 * the API user the remote portal signs in as.
 *
 * Helper::getBusinessSettings() caches in a static, so the setting is switched
 * before anything reads it; run this file on its own (filter=) to cover it.
 * The admin user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Services\Helper;

return function () {

    $original = Helper::getOption('global_business_settings', null);
    Helper::updateOption('global_business_settings', array_merge((array) $original, ['disable_public_ticket' => 'yes']));

    $adminId = get_current_user_id();
    $apiUserId = 0;

    try {
        if (Helper::isPublicSignedTicketEnabled()) {
            FsTest::case('portal: a hash is refused for the API user when signed links are off', function () {
                FsTest::skip('business settings were read before this file; run it alone with filter=70-portal-hash-signed-links-off.php');
            });
            return;
        }

        $login = FsTest::uniq('fs-remote-api');
        $apiUserId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => 'administrator',
        ]);

        if (is_wp_error($apiUserId)) {
            throw new RuntimeException('Could not create user: ' . $apiUserId->get_error_message());
        }

        // The API user once filed a test ticket, so it has a customer record.
        $customer = FsFactory::customer(['user_id' => $apiUserId]);
        $ticket = FsFactory::ticket(['customer_id' => $customer->id]);

        FsTest::case('portal: a hash is refused for the API user when signed links are off', function () use ($apiUserId, $adminId, $ticket) {
            wp_set_current_user($apiUserId);
            try {
                $relayed = FsTest::rest('GET', '/customer-portal/tickets/' . $ticket->id, ['intended_ticket_hash' => 'not-a-real-hash']);
                $own = FsTest::rest('GET', '/customer-portal/tickets/' . $ticket->id);
            } finally {
                wp_set_current_user($adminId);
            }

            FsTest::assertSame(403, (int) $relayed['status'], 'relayed anonymous request refused');
            FsTest::assertHealthy($own, 'the user still opens their own ticket without a hash');
        });
    } finally {
        wp_set_current_user($adminId);

        if ($original === null) {
            Helper::updateOption('global_business_settings', []);
        } else {
            Helper::updateOption('global_business_settings', $original);
        }

        if ($apiUserId && get_user_by('ID', $apiUserId)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($apiUserId);
        }
    }
};
