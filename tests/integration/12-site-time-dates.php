<?php
/**
 * Every date the API sends is site time ("Y-m-d H:i:s"), as stored, including
 * created_at / updated_at. MCP keeps ISO 8601 but with the site's offset, for
 * stored strings as well as model dates. Runs with a non-UTC site timezone,
 * where the old ISO-UTC serialization showed a different clock time.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\MCP\Helpers\MCPHelper;

return function () {

    $withSiteTimezone = function ($timezone, callable $fn) {
        $previous = get_option('timezone_string');
        $previousOffset = get_option('gmt_offset');
        update_option('timezone_string', $timezone);
        try {
            return $fn();
        } finally {
            update_option('timezone_string', $previous);
            update_option('gmt_offset', $previousOffset);
        }
    };

    FsTest::case('REST ticket created_at and updated_at are the stored site time', function () use ($withSiteTimezone) {
        $withSiteTimezone('Asia/Dhaka', function () {
            $ticket = FsFactory::ticket();
            Ticket::where('id', $ticket->id)->update([
                'created_at'    => '2026-01-02 10:00:00',
                'updated_at'    => '2026-01-02 11:30:00',
                'waiting_since' => '2026-01-02 10:00:00',
            ]);

            $result = FsTest::rest('GET', '/tickets/' . $ticket->id);
            FsTest::assertHealthy($result, 'GET /tickets/{id}');

            $data = json_decode(wp_json_encode($result['data']['ticket'] ?? []), true);
            FsTest::assertSame('2026-01-02 10:00:00', $data['created_at'] ?? null, 'created_at');
            FsTest::assertSame('2026-01-02 11:30:00', $data['updated_at'] ?? null, 'updated_at');
            FsTest::assertSame('2026-01-02 10:00:00', $data['waiting_since'] ?? null, 'waiting_since, same format');
        });
    });

    FsTest::case('MCP dates carry the site offset for stored strings and model dates', function () use ($withSiteTimezone) {
        $withSiteTimezone('Asia/Dhaka', function () {
            $ticket = FsFactory::ticket();
            Ticket::where('id', $ticket->id)->update(['created_at' => '2026-01-02 10:00:00', 'last_agent_response' => '2026-01-02 10:00:00']);
            $fresh = Ticket::find($ticket->id);

            FsTest::assertSame('2026-01-02T10:00:00+06:00', MCPHelper::toIso8601($fresh->created_at), 'model date');
            // last_agent_response is a plain string column, not a date attribute.
            FsTest::assertSame('2026-01-02T10:00:00+06:00', MCPHelper::toIso8601($fresh->last_agent_response), 'stored string');
        });
    });
};
