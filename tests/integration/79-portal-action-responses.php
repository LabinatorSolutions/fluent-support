<?php
/**
 * Portal reply, close and re-open return only what the portal needs: the new reply
 * with its author's display fields, and the ticket's status. The customer row
 * behind them carries the agents' private note, the IP address and the user id;
 * a signed ticket link is enough to call these, so none of that may come back.
 * The portal WP user isn't factory-tracked; the finally removes it.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;

return function () {

    $adminId = get_current_user_id();
    $marker = FsFactory::marker();

    $userId = wp_insert_user([
        'user_login' => substr($marker . '-portal-ack', 0, 60),
        'user_email' => $marker . '-portal-ack@example.invalid',
        'user_pass'  => wp_generate_password(20),
        'role'       => 'subscriber',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException('Could not create portal WP user: ' . $userId->get_error_message());
    }

    try {
        $customer = FsFactory::customer([
            'email'      => $marker . '-portal-ack@example.invalid',
            'user_id'    => $userId,
            'note'       => 'Agent-only customer note ' . $marker,
            'ip_address' => '10.79.0.1',
        ]);

        $ticket = FsFactory::ticket([
            'customer_id' => $customer->id,
            'privacy'     => 'private',
            'status'      => 'active',
        ]);
        $serial = (int) Ticket::find($ticket->id)->serial_number;

        $asCustomer = function () use ($userId) {
            wp_set_current_user($userId);
            PermissionManager::currentUserPermissions(false);
        };

        // The values only the customer row carries; none may appear in an action response.
        $assertNoPrivateCustomerData = function ($result, $label) use ($customer, $marker) {
            $json = wp_json_encode($result['data']);

            FsTest::assert(strpos($json, 'Agent-only customer note ' . $marker) === false, $label . ': the agents\' customer note must not be sent');
            FsTest::assert(strpos($json, '10.79.0.1') === false, $label . ': the customer IP must not be sent');
            FsTest::assert(strpos($json, $customer->email) === false, $label . ': the customer email must not be sent');
        };

        FsTest::case('portal_reply_returns_the_reply_without_the_customer_row', function () use (
            $asCustomer, $serial, $customer, $assertNoPrivateCustomerData, $marker
        ) {
            $asCustomer();
            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/responses', [
                'content' => '<p>Portal reply ' . esc_html($marker) . '</p>',
            ]);
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/responses');

            $assertNoPrivateCustomerData($result, 'reply');

            $data = json_decode(wp_json_encode($result['data']), true);
            $person = $data['response']['person'] ?? [];

            // The portal adds the reply to the thread, which shows the author's name and photo.
            FsTest::assertSame((int) $customer->id, (int) ($person['id'] ?? 0), 'reply person id');
            FsTest::assertSame($customer->full_name, $person['full_name'] ?? null, 'reply person full_name');
            FsTest::assertSame('customer', $person['person_type'] ?? null, 'reply person person_type');
            FsTest::assert(!empty($person['photo']), 'reply person photo');
            FsTest::assert(!empty($data['ticket']['status']), 'the ticket status is returned');
        });

        FsTest::case('portal_close_returns_only_the_ticket_status', function () use ($asCustomer, $serial, $assertNoPrivateCustomerData) {
            $asCustomer();
            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/close');
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/close');

            $assertNoPrivateCustomerData($result, 'close');
            $data = json_decode(wp_json_encode($result['data']), true);
            FsTest::assertSame('closed', $data['ticket']['status'] ?? null, 'close returns the new status');
        });

        FsTest::case('portal_reopen_returns_only_the_ticket_status', function () use ($asCustomer, $serial, $assertNoPrivateCustomerData) {
            $asCustomer();
            $result = FsTest::rest('POST', '/customer-portal/tickets/' . $serial . '/re-open');
            FsTest::assertHealthy($result, 'POST /customer-portal/tickets/{serial}/re-open');

            $assertNoPrivateCustomerData($result, 're-open');
            $data = json_decode(wp_json_encode($result['data']), true);
            FsTest::assertSame('active', $data['ticket']['status'] ?? null, 're-open returns the new status');
        });
    } finally {
        wp_set_current_user($adminId);
        PermissionManager::currentUserPermissions(false);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }
};
