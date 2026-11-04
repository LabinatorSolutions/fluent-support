<?php
/**
 * Deleting customers from the admin deletes their tickets the same way any
 * other ticket delete does: `fluent_support/ticket_deleted` fires once per
 * ticket, so the activity log and third-party listeners see each one.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Ticket;

return function () {

    // Ticket ids reported by fluent_support/ticket_deleted while $fn runs.
    $deletedTicketIds = function (callable $fn) {
        $ids = [];
        $listener = function ($agent, $ticketData) use (&$ids) {
            $ids[] = (int) $ticketData['id'];
        };
        add_action('fluent_support/ticket_deleted', $listener, 10, 2);
        try {
            $fn();
        } finally {
            remove_action('fluent_support/ticket_deleted', $listener, 10);
        }
        sort($ids);
        return $ids;
    };

    FsTest::case('single customer delete fires ticket_deleted for each of their tickets', function () use ($deletedTicketIds) {
        $customer = FsFactory::customer();
        $ticketIds = [
            FsFactory::ticket(['customer_id' => $customer->id])->id,
            FsFactory::ticket(['customer_id' => $customer->id])->id,
        ];
        sort($ticketIds);

        $fired = $deletedTicketIds(function () use ($customer) {
            \FluentSupport\App\Modules\Customers\CustomerService::delete(Customer::find($customer->id));
        });

        FsTest::assertSame($ticketIds, $fired, 'ticket_deleted fired per ticket');
        FsTest::assertSame(0, Ticket::whereIn('id', $ticketIds)->count(), 'tickets deleted');
        FsTest::assertSame(0, Customer::where('id', $customer->id)->count(), 'customer deleted');
    });

    FsTest::case('bulk customer delete fires ticket_deleted for each of their tickets', function () use ($deletedTicketIds) {
        $first = FsFactory::customer();
        $second = FsFactory::customer();
        $ticketIds = [
            FsFactory::ticket(['customer_id' => $first->id])->id,
            FsFactory::ticket(['customer_id' => $second->id])->id,
        ];
        sort($ticketIds);

        $result = null;
        $fired = $deletedTicketIds(function () use ($first, $second, &$result) {
            $result = json_decode(wp_json_encode(FsTest::rest('DELETE', '/customers/bulk-delete', ['customer_ids' => [$first->id, $second->id]])['data']), true);
        });

        FsTest::assertSame(2, $result['deleted_count'] ?? null, 'both customers deleted');
        FsTest::assertSame($ticketIds, $fired, 'ticket_deleted fired per ticket');
        FsTest::assertSame(0, Ticket::whereIn('id', $ticketIds)->count(), 'tickets deleted');
    });
};
