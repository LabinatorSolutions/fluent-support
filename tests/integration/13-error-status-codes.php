<?php
/**
 * Controller errors carry a fitting HTTP status: a missing record is 404, an
 * exception thrown with a 401/403/404/409 code keeps it, everything else is
 * 422. The message body does not change.
 */

return function () {

    FsTest::case('a missing ticket is 404 with a message', function () {
        $result = FsTest::rest('GET', '/tickets/999999999');

        FsTest::assertSame(404, $result['status'], 'status');
        FsTest::assert(!empty($result['data']['message']), 'message present');
    });

    FsTest::case('an invalid bulk value is still 422', function () {
        $ticket = FsFactory::ticket();
        $result = FsTest::rest('POST', '/tickets/bulk-actions', [
            'bulk_action' => 'change_priority',
            'ticket_ids'  => [$ticket->id],
            'priority'    => 'not-a-priority',
        ]);

        FsTest::assertSame(422, $result['status'], 'status');
        FsTest::assertSame('Invalid ticket priority.', $result['data']['message'] ?? null, 'message unchanged');
    });
};
