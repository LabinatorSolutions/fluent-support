<?php
/**
 * Customers: the customers/* endpoints the Customers pages, the customer form
 * and the ticket "change customer" picker call (resources/admin/Modules/Customers/).
 *
 * - The list: search, status and open-ticket filters, the counts per row, and
 *   importing a WordPress user found by exact email.
 * - One customer with its tickets and ticket counts; the form fields.
 * - Create: required fields, unique email, no user_id, the WordPress user linked by email.
 * - Update: fields saved, a taken email refused, identity columns kept.
 * - Bulk status, avatar reset, and the fst_sensitive_data gate.
 * - Responses carry the fields the pages read, never the portal hash or IPs.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {
    $adminId = get_current_user_id();
    $marker = FsFactory::marker();
    $userIds = [];
    $customerIds = [];

    $rest = function ($method, $route, array $params = []) {
        $result = FsTest::rest($method, $route, $params);
        $result['data'] = json_decode(wp_json_encode($result['data']), true);
        return $result;
    };
    $email = fn($slug) => $slug . '-' . strtolower(wp_generate_password(6, false)) . '@' . $marker . '.example.test';
    $wpUser = function ($slug) use (&$userIds, $email) {
        $address = $email($slug);
        $userId = wp_insert_user(['user_login' => FsTest::uniq('fs-cust-' . $slug), 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $address, 'role' => 'subscriber', 'first_name' => 'Wp', 'last_name' => 'User']);
        $userIds[] = $userId;
        return [$userId, $address];
    };

    $customer = FsFactory::customer(['first_name' => 'Grace', 'last_name' => 'Hopper', 'city' => 'Arlington', 'note' => 'VIP']);
    Customer::where('id', $customer->id)->update(['ip_address' => '203.0.113.9', 'remote_uid' => 'r-' . $marker]);
    FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'active']);
    FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'closed']);
    $withTicket = FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'new']);
    FsFactory::conversation($withTicket, ['person_id' => $customer->id]);

    $hidden = ['hash', 'ip_address', 'last_ip_address', 'remote_uid'];

    try {
        FsTest::case('index finds a customer with ticket and reply counts', function () use ($rest, $customer, $hidden) {
            $result = $rest('GET', '/customers', ['search' => $customer->email]);
            FsTest::assertHealthy($result, 'GET customers');
            $page = $result['data']['customers'];
            foreach (['data', 'total', 'per_page', 'current_page'] as $key) {
                FsTest::assert(array_key_exists($key, $page), "page has no '$key'");
            }
            $row = $page['data'][0] ?? [];
            FsTest::assertSame((int) $customer->id, (int) ($row['id'] ?? 0), 'found');
            FsTest::assertSame(['total_tickets' => 3, 'open_tickets' => 2, 'total_responses' => 1], ['total_tickets' => $row['total_tickets'] ?? null, 'open_tickets' => $row['open_tickets'] ?? null, 'total_responses' => $row['total_responses'] ?? null], 'counts');
            foreach (['email', 'full_name', 'photo', 'status', 'created_at', 'last_response_at', 'first_name', 'last_name', 'city', 'country'] as $key) {
                FsTest::assert(array_key_exists($key, $row), "row has no '$key'");
            }
            FsTest::assertSame('Arlington', $row['city'] ?? null, 'city for the Location column');
            foreach ($hidden as $key) {
                FsTest::assert(!array_key_exists($key, $row), "row carries '$key'");
            }
        });

        FsTest::case('index filters by status and open tickets', function () use ($rest, $customer) {
            $ids = fn($params) => array_map(fn($row) => (int) $row['id'], (array) ($rest('GET', '/customers', $params + ['search' => $customer->email])['data']['customers']['data'] ?? []));
            FsTest::assertSame([(int) $customer->id], $ids(['status' => 'active']), 'active');
            FsTest::assertSame([], $ids(['status' => 'inactive']), 'inactive');
            FsTest::assertSame([(int) $customer->id], $ids(['has_open_tickets' => 'yes']), 'with open tickets');
        });

        FsTest::case('index imports a WordPress user found by exact email', function () use ($rest, $wpUser, &$customerIds) {
            if (!defined('FLUENT_SUPPORT_PRO_DIR_FILE')) {
                FsTest::skip('Pro is not active');
                return;
            }
            list($userId, $address) = $wpUser('import');
            $rows = $rest('GET', '/customers', ['search' => $address])['data']['customers']['data'] ?? [];
            $created = Customer::where('email', $address)->first();
            if ($created) {
                $customerIds[] = (int) $created->id;
            }
            FsTest::assert((bool) $created, 'customer created from the WordPress user');
            FsTest::assertSame((int) $userId, (int) ($created->user_id ?? 0), 'linked to the user');
            FsTest::assertSame([$address], array_column($rows, 'email'), 'returned');

            $none = $rest('GET', '/customers', ['search' => $address . '.nope', 'import_contacts' => 'no'])['data']['customers']['data'] ?? null;
            FsTest::assertSame([], $none, 'no import when asked not to');
        });

        FsTest::case('show: the customer, its tickets and ticket counts', function () use ($rest, $customer, $hidden) {
            $result = $rest('GET', '/customers/' . $customer->id, ['with' => ['tickets', 'ticket_stats']]);
            FsTest::assertHealthy($result, 'GET customers/{id}');
            $data = $result['data'];
            FsTest::assertSame('Arlington', $data['customer']['city'] ?? null, 'city');
            FsTest::assertSame('VIP', $data['customer']['note'] ?? null, 'note');
            FsTest::assert(array_key_exists('profile_edit_url', $data['customer']), 'profile_edit_url');
            foreach ($hidden as $key) {
                FsTest::assert(!array_key_exists($key, $data['customer']), "customer carries '$key'");
            }
            FsTest::assertSame(3, count($data['tickets'] ?? []), 'tickets');
            FsTest::assertSame(['id', 'title', 'status', 'customer_id', 'ticket_number', 'serial_number', 'created_at', 'updated_at'], array_slice(array_keys($data['tickets'][0] ?? []), 0, 8), 'ticket keys');
            $stats = $data['ticket_stats'] ?? [];
            ksort($stats);
            FsTest::assertSame(['active' => 1, 'closed' => 1, 'new' => 1], $stats, 'ticket stats');

            FsTest::assertSame(404, $rest('GET', '/customers/99999999')['status'], 'unknown customer');
        });

        FsTest::case('form fields', function () use ($rest, $customer) {
            $fields = $rest('GET', '/customers/customerField/' . $customer->id)['data']['customerField'] ?? [];
            FsTest::assertSame(['first_name', 'last_name', 'email', 'title', 'note', 'status', 'status_html'], array_keys($fields['basic_fields'] ?? []), 'basic fields');
            FsTest::assertSame(['address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country'], array_keys($fields['address_fields'] ?? []), 'address fields');
            FsTest::assertSame(true, $fields['basic_fields']['email']['disabled'] ?? null, 'email locked when editing');
            FsTest::assertSame(false, $rest('GET', '/customers/customerField/0')['data']['customerField']['basic_fields']['email']['disabled'] ?? null, 'email open when adding');
        });

        FsTest::case('create: required fields, unique email, no user_id, WordPress user linked by email', function () use ($rest, $customer, $email, $wpUser, &$customerIds, $hidden) {
            FsTest::assertSame(422, $rest('POST', '/customers', ['first_name' => 'No Email'])['status'], 'no email');
            FsTest::assertSame(422, $rest('POST', '/customers', ['email' => $email('noname')])['status'], 'no first name');
            FsTest::assertSame(422, $rest('POST', '/customers', ['email' => $customer->email, 'first_name' => 'Dup'])['status'], 'taken email');
            FsTest::assertSame(422, $rest('POST', '/customers', ['email' => $email('uid'), 'first_name' => 'Uid', 'user_id' => 1])['status'], 'user_id refused');

            list($userId, $address) = $wpUser('create');
            $result = $rest('POST', '/customers', ['email' => $address, 'first_name' => 'Ada', 'city' => 'London', 'hash' => 'forged', 'person_type' => 'agent']);
            FsTest::assertHealthy($result, 'POST customers');
            FsTest::assertSame('Customer has been added', $result['message'], 'message');
            $created = Customer::where('email', $address)->first();
            $customerIds[] = (int) ($created->id ?? 0);
            FsTest::assertSame(['user_id' => (int) $userId, 'city' => 'London', 'person_type' => 'customer'], ['user_id' => (int) $created->user_id, 'city' => $created->city, 'person_type' => $created->person_type], 'saved');
            FsTest::assert($created->hash !== 'forged', 'hash was mass-assigned');
            foreach ($hidden as $key) {
                FsTest::assert(!array_key_exists($key, $result['data']['customer'] ?? []), "created customer carries '$key'");
            }
        });

        FsTest::case('update: fields saved, a taken email refused, identity columns kept', function () use ($rest, $customer, $email, &$customerIds) {
            $other = FsFactory::customer();
            $hash = Customer::find($customer->id)->hash;

            $result = $rest('PUT', '/customers/' . $customer->id, ['email' => $customer->email, 'first_name' => 'Grace', 'last_name' => 'Brewster', 'state' => 'VA', 'hash' => 'x', 'person_type' => 'agent', 'user_id' => 1]);
            FsTest::assertHealthy($result, 'PUT customers/{id}');
            FsTest::assertSame('Customer has been updated', $result['message'], 'message');
            $saved = Customer::find($customer->id);
            FsTest::assertSame(['last_name' => 'Brewster', 'state' => 'VA', 'hash' => $hash, 'user_id' => 0, 'person_type' => 'customer'], ['last_name' => $saved->last_name, 'state' => $saved->state, 'hash' => $saved->hash, 'user_id' => (int) $saved->user_id, 'person_type' => $saved->person_type], 'saved, identity kept');
            FsTest::assertSame(['city' => 'Arlington', 'note' => 'VIP', 'status' => 'active'], ['city' => $saved->city, 'note' => $saved->note, 'status' => $saved->status], 'fields left out of the request are kept');

            $taken = $rest('PUT', '/customers/' . $customer->id, ['email' => $other->email, 'first_name' => 'Grace']);
            FsTest::assertSame(422, $taken['status'], 'taken email refused');
            FsTest::assert($taken['message'] !== '', 'taken email message');
            FsTest::assertSame(['unique' => 'Email address has been assigned to other customer'], $taken['data']['errors']['email'] ?? null, 'error map');
            FsTest::assertSame($customer->email, Customer::find($customer->id)->email, 'email unchanged');

            FsTest::assertSame(422, $rest('PUT', '/customers/' . $customer->id, ['email' => $customer->email])['status'], 'first name required');
            FsTest::assert($rest('PUT', '/customers/99999999', ['email' => $email('ghost'), 'first_name' => 'G'])['status'] >= 400, 'unknown customer refused');
        });

        FsTest::case('bulk status blocks and unblocks customers, never agents', function () use ($rest, $customer) {
            $second = FsFactory::customer();
            // Imported rows can have no status; they count as active and can be blocked.
            Customer::where('id', $second->id)->update(['status' => null]);
            $agent = FsFactory::agent();

            $result = $rest('PUT', '/customers/bulk-status', ['customer_ids' => [$customer->id, $second->id, $agent->id], 'status' => 'inactive']);
            FsTest::assertHealthy($result, 'PUT customers/bulk-status');
            FsTest::assertSame(2, (int) ($result['data']['updated'] ?? -1), 'updated');
            FsTest::assertSame('inactive', Customer::find($second->id)->status, 'blocked');
            FsTest::assertSame('active', \FluentSupport\App\Models\Agent::find($agent->id)->status, 'agent untouched');
            FsTest::assertSame(422, $rest('PUT', '/customers/bulk-status', ['customer_ids' => [$customer->id], 'status' => 'gone'])['status'], 'unknown status');

            $rest('PUT', '/customers/bulk-status', ['customer_ids' => [$customer->id, $second->id], 'status' => 'active']);
            FsTest::assertSame('active', Customer::find($customer->id)->status, 'unblocked');
        });

        FsTest::case('avatar reset', function () use ($rest, $customer) {
            $result = $rest('POST', '/customers/reset_avatar/' . $customer->id);
            FsTest::assertHealthy($result, 'POST customers/reset_avatar/{id}');
            FsTest::assertSame('Customer avatar reset to gravatar default', $result['message'], 'message');
            FsTest::assertSame(404, $rest('POST', '/customers/reset_avatar/99999999')['status'], 'unknown customer');
        });

        FsTest::case('an agent without fst_sensitive_data is refused', function () use ($rest, $customer, &$userIds, $adminId) {
            $login = FsTest::uniq('fs-cust-agent');
            $userId = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24, true, true), 'user_email' => $login . '@example.test', 'role' => 'subscriber']);
            $userIds[] = $userId;
            FsFactory::agent(['user_id' => $userId]);
            PermissionManager::attachPermissions($userId, ['fst_view_tickets', 'fst_manage_own_tickets']);
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
            try {
                FsTest::assertSame(403, $rest('GET', '/customers')['status'], 'list');
                FsTest::assertSame(403, $rest('GET', '/customers/' . $customer->id)['status'], 'show');
                FsTest::assertSame(403, $rest('PUT', '/customers/' . $customer->id, ['email' => $customer->email, 'first_name' => 'X'])['status'], 'update');
            } finally {
                wp_set_current_user($adminId);
                PermissionManager::currentUserPermissions(false);
            }
        });
    } finally {
        foreach (array_filter($customerIds) as $id) {
            Ticket::where('customer_id', $id)->delete();
            Customer::where('id', $id)->delete();
        }
        Customer::where('email', 'like', '%@' . $marker . '.example.test')->delete();
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($userIds as $userId) {
            wp_delete_user($userId);
        }
    }
};
