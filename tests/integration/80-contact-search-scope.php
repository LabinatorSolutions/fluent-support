<?php
/**
 * GET /tickets/search-contact backs "create a ticket for a customer". Browsing the
 * customer list by partial name or email needs fst_sensitive_data, like the
 * Customers menu; other agents find a customer by their full email address only.
 * The WordPress user fallback never offers support staff or administrators.
 * WP users aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\Tickets\AgentTicketAccess;

return function () {

    $adminId = get_current_user_id();
    $wpUserIds = [];

    $makeWpUser = function ($slug, $role = 'subscriber') use (&$wpUserIds) {
        $login = FsTest::uniq($slug);
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $login . '@example.test',
            'role'       => $role,
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create WP user: ' . $userId->get_error_message());
        }

        $wpUserIds[] = $userId;

        return get_user_by('ID', $userId);
    };

    $makeAgent = function (array $permissions) use ($makeWpUser) {
        $user = $makeWpUser('fs-contact-agent');
        $agent = FsFactory::agent();
        $agent->user_id = $user->ID;
        $agent->save();
        PermissionManager::attachPermissions($user->ID, $permissions);

        return $user;
    };

    $runAs = function ($userId) {
        wp_set_current_user($userId);
        PermissionManager::currentUserPermissions(false);
        AgentTicketAccess::resetCache();
        // Each case stays far below the 60-per-5-minutes limit; clear it so reruns don't trip it.
        delete_transient('fs_contact_search_' . $userId);
    };

    $search = function ($term) {
        $result = FsTest::rest('GET', '/tickets/search-contact', ['search' => $term]);
        $data = json_decode(wp_json_encode($result['data']), true);

        return [$result, $data, array_column((array) ($data['data'] ?? []), 'email')];
    };

    try {
        $restrictedUser = $makeAgent(['fst_manage_own_tickets']);
        $sensitiveUser = $makeAgent(['fst_manage_own_tickets', 'fst_sensitive_data']);

        // The marker is shared by every suite in a run, so search a name only these two carry.
        $needle = FsTest::uniq('contactsearch');
        $customer = FsFactory::customer(['last_name' => $needle]);
        $otherCustomer = FsFactory::customer(['last_name' => $needle]);

        FsTest::case('agent_without_sensitive_data_cannot_browse_customers_by_partial_search', function () use (
            $runAs, $restrictedUser, $search, $needle, $customer, $otherCustomer
        ) {
            $runAs($restrictedUser->ID);
            [$result, $data, $emails] = $search($needle);

            FsTest::assert(!in_array($customer->email, $emails, true) && !in_array($otherCustomer->email, $emails, true),
                'a partial search must not list customers, got: ' . implode(', ', $emails));
            FsTest::assert(stripos($result['message'], 'full email') !== false,
                'the agent is told to enter the full email address, got: ' . $result['message']);
        });

        FsTest::case('agent_without_sensitive_data_finds_a_customer_by_exact_email', function () use (
            $runAs, $restrictedUser, $search, $customer, $otherCustomer
        ) {
            $runAs($restrictedUser->ID);
            [$result, $data, $emails] = $search($customer->email);
            FsTest::assertHealthy($result, 'GET /tickets/search-contact (exact email)');

            FsTest::assertSame([$customer->email], $emails, 'exactly the customer with that email');
            FsTest::assertSame('fluent_support', $data['provider'] ?? null, 'found among Fluent Support customers');
        });

        FsTest::case('agent_with_sensitive_data_can_search_by_partial_name_or_email', function () use (
            $runAs, $sensitiveUser, $search, $needle, $customer, $otherCustomer
        ) {
            $runAs($sensitiveUser->ID);
            [$result, $data, $emails] = $search($needle);
            FsTest::assertHealthy($result, 'GET /tickets/search-contact (partial, sensitive)');

            FsTest::assert(in_array($customer->email, $emails, true) && in_array($otherCustomer->email, $emails, true),
                'both fixture customers are listed, got: ' . implode(', ', $emails));
        });

        FsTest::case('partial_search_can_be_opened_up_with_a_filter', function () use (
            $runAs, $restrictedUser, $search, $needle, $customer
        ) {
            $allow = function () {
                return true;
            };
            add_filter('fluent_support/contact_search_partial_allowed', $allow);

            try {
                $runAs($restrictedUser->ID);
                [$result, $data, $emails] = $search($needle);
                FsTest::assertHealthy($result, 'GET /tickets/search-contact (partial, filter on)');
                FsTest::assert(in_array($customer->email, $emails, true), 'the filter allows the partial search');
            } finally {
                remove_filter('fluent_support/contact_search_partial_allowed', $allow);
            }
        });

        $plainUser = $makeWpUser('fs-contact-plain');
        $adminUser = $makeWpUser('fs-contact-admin', 'administrator');
        $agentUser = $makeAgent(['fst_manage_own_tickets']);

        foreach (['administrator' => $adminUser, 'support agent' => $agentUser] as $label => $staffUser) {
            FsTest::case('wp_user_fallback_does_not_offer_a_' . str_replace(' ', '_', $label), function () use (
                $runAs, $sensitiveUser, $restrictedUser, $search, $staffUser, $label
            ) {
                foreach ([$sensitiveUser, $restrictedUser] as $viewer) {
                    $runAs($viewer->ID);
                    [$result, $data, $emails] = $search($staffUser->user_email);
                    FsTest::assertHealthy($result, 'GET /tickets/search-contact (' . $label . ' email)');

                    FsTest::assert(!in_array($staffUser->user_email, $emails, true),
                        'a ' . $label . ' must not be offered as a ticket contact');
                }
            });
        }

        FsTest::case('wp_user_fallback_still_offers_a_regular_user', function () use ($runAs, $restrictedUser, $search, $plainUser) {
            $runAs($restrictedUser->ID);
            [$result, $data, $emails] = $search($plainUser->user_email);
            FsTest::assertHealthy($result, 'GET /tickets/search-contact (regular WP user)');

            FsTest::assertSame([$plainUser->user_email], $emails, 'the WordPress user is found by email');
            FsTest::assertSame('wp_users', $data['provider'] ?? null, 'found among WordPress users');
        });

        // The ticket "change customer" picker: GET /customers imports a WP user found by
        // exact email (Pro), which must never turn staff into customers.
        // Returns a customer the search created (never one that existed before).
        $customerSearch = function ($email) use ($runAs, $adminId) {
            if (Customer::where('email', $email)->exists()) {
                throw new RuntimeException('A customer with ' . $email . ' already exists; the case needs a fresh email.');
            }
            $runAs($adminId);
            $result = FsTest::rest('GET', '/customers', ['search' => $email]);
            FsTest::assertHealthy($result, 'GET /customers');

            return Customer::where('email', $email)->first();
        };

        if (defined('FLUENT_SUPPORT_PRO_DIR_FILE')) {
            foreach (['administrator' => $adminUser, 'support agent' => $agentUser] as $label => $staffUser) {
                FsTest::case('customer_list_search_does_not_import_a_' . str_replace(' ', '_', $label), function () use ($customerSearch, $staffUser, $label) {
                    $created = $customerSearch($staffUser->user_email);
                    if ($created) {
                        $created->delete();
                    }
                    FsTest::assert(!$created, 'a ' . $label . ' must not become a customer');
                });
            }

            FsTest::case('customer_list_search_still_imports_a_regular_user', function () use ($customerSearch, $plainUser) {
                $created = $customerSearch($plainUser->user_email);
                if ($created) {
                    $created->delete();
                }
                FsTest::assert((bool) $created, 'the WordPress user becomes a customer');
            });
        }
    } finally {
        $runAs($adminId);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($wpUserIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
