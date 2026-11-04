<?php
/**
 * Customers list: sorting, bulk block/unblock, and the FluentCRM / WP-user import that
 * the list opts out of (import_contacts=no) while the ticket "change customer" picker keeps it.
 * The WP user and any imported customer aren't factory-tracked; the finally removes them.
 */

use FluentSupport\App\Models\Customer;

return function () {

    $token = strtolower(FsTest::uniq('custlist'));

    // Name order (b, a, c) differs from creation order (a, b, c), so the two sorts are distinguishable.
    // The model stamps created_at on create, so dates are set afterwards. Creation order is
    // reversed from date order, so the default sort can't pass by insertion order alone.
    $third = FsFactory::customer(['first_name' => $token . ' c']);
    $second = FsFactory::customer(['first_name' => $token . ' a']);
    $first = FsFactory::customer(['first_name' => $token . ' b']);
    Customer::where('id', $first->id)->update(['created_at' => '2026-01-01 10:00:00']);
    Customer::where('id', $second->id)->update(['created_at' => '2026-01-02 10:00:00']);
    Customer::where('id', $third->id)->update(['created_at' => '2026-01-03 10:00:00']);
    $agentRow = FsFactory::agent();

    $listIds = function (array $params) use ($token) {
        $result = FsTest::rest('GET', '/customers', array_merge(['search' => $token, 'per_page' => 20], $params));
        FsTest::assertHealthy($result, 'GET customers');

        $data = json_decode(wp_json_encode($result['data']), true);

        return array_map('intval', array_column($data['customers']['data'], 'id'));
    };

    $importEmail = $token . '-wp-user@example.invalid';
    $wpUserId = 0;

    try {
        FsTest::case('customers list: default order is newest customer first', function () use ($listIds, $first, $second, $third) {
            FsTest::assertSame([(int) $third->id, (int) $second->id, (int) $first->id], $listIds([]), 'created_at DESC');
        });

        FsTest::case('customers list: sort_by first_name ASC orders by name', function () use ($listIds, $first, $second, $third) {
            FsTest::assertSame(
                [(int) $second->id, (int) $first->id, (int) $third->id],
                $listIds(['sort_by' => 'first_name', 'sort_type' => 'ASC']),
                'a, b, c'
            );
        });

        FsTest::case('customers list: an unknown sort_by falls back to the default order', function () use ($listIds, $first, $second, $third) {
            FsTest::assertSame(
                [(int) $third->id, (int) $second->id, (int) $first->id],
                $listIds(['sort_by' => 'email; DROP TABLE x', 'sort_type' => 'sideways']),
                'not a sortable column, so created_at DESC'
            );
        });

        FsTest::case('customers bulk-status: blocks and unblocks only customer rows', function () use ($first, $second, $agentRow) {
            $result = FsTest::rest('PUT', '/customers/bulk-status', [
                'status'       => 'inactive',
                'customer_ids' => [(int) $first->id, (int) $second->id],
            ]);
            FsTest::assertHealthy($result, 'PUT customers/bulk-status inactive');
            FsTest::assertSame('inactive', Customer::find($first->id)->status, 'first customer blocked');
            FsTest::assertSame('inactive', Customer::find($second->id)->status, 'second customer blocked');

            // An agent id is not a customer: the person_type scope keeps it out of the update.
            $agentResult = FsTest::rest('PUT', '/customers/bulk-status', [
                'status'       => 'inactive',
                'customer_ids' => [(int) $agentRow->id],
            ]);
            FsTest::assertSame('active', \FluentSupport\App\Models\Agent::find($agentRow->id)->status, 'agent row untouched');
            FsTest::assert(empty($agentResult['data']['updated']), 'no rows reported for an agent id');

            $result = FsTest::rest('PUT', '/customers/bulk-status', [
                'status'       => 'active',
                'customer_ids' => [(int) $first->id],
            ]);
            FsTest::assertHealthy($result, 'PUT customers/bulk-status active');
            FsTest::assertSame('active', Customer::find($first->id)->status, 'first customer unblocked');
        });

        FsTest::case('customers bulk-status: rejects a status other than active/inactive', function () use ($third) {
            $result = FsTest::rest('PUT', '/customers/bulk-status', [
                'status'       => 'deleted',
                'customer_ids' => [(int) $third->id],
            ]);
            FsTest::assertSame(422, $result['status'], 'invalid status is a 422');
            FsTest::assertSame('active', Customer::find($third->id)->status, 'status unchanged');
        });

        $wpUserId = wp_insert_user([
            'user_login' => $token . '-wp',
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $importEmail,
            'first_name' => 'Imported',
        ]);

        if (is_wp_error($wpUserId)) {
            throw new RuntimeException('Could not create WP user: ' . $wpUserId->get_error_message());
        }

        FsTest::case('customers list: import_contacts=no never creates a customer from a WP user', function () use ($importEmail) {
            $result = FsTest::rest('GET', '/customers', ['search' => $importEmail, 'import_contacts' => 'no']);
            FsTest::assertHealthy($result, 'GET customers import_contacts=no');

            $data = json_decode(wp_json_encode($result['data']), true);
            FsTest::assertSame(0, (int) $data['customers']['total'], 'no customer listed');
            FsTest::assertSame(0, Customer::where('email', $importEmail)->count(), 'no customer row created');
        });

        FsTest::case('customers list: the default search still imports the WP user (ticket customer picker)', function () use ($importEmail) {
            if (!defined('FLUENT_SUPPORT_PRO_DIR_FILE')) {
                FsTest::skip('WP-user import needs Pro');
                return;
            }

            $result = FsTest::rest('GET', '/customers', ['search' => $importEmail]);
            FsTest::assertHealthy($result, 'GET customers default search');
            FsTest::assertSame(1, Customer::where('email', $importEmail)->count(), 'customer created from the WP user');
        });
    } finally {
        Customer::where('email', $importEmail)->delete();

        if ($wpUserId && !is_wp_error($wpUserId)) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($wpUserId);
        }
    }
};
