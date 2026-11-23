<?php
/**
 * Ticket importer endpoints (ticket_importer/*): the stats list, a full
 * Freshdesk import through the REST endpoint, a re-run that skips what was
 * already imported, and the delete endpoint for a cloud source.
 *
 * Freshdesk is never called: pre_http_request answers for it. The importer
 * checks that its host resolves to a public IP, so the case skips when the
 * lab has no DNS.
 *
 * Imported dates must keep their hour: the cloud importers used to format
 * them with the 12-hour "h", so 15:30 was stored as 03:30.
 *
 * Imported tickets carry the fixture marker in their title and are adopted
 * for cleanup; the people, origin meta and migration options are removed in
 * the finally.
 */

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Ticket;

return function () {

    $marker = FsFactory::marker();
    $domain = 'https://fstest.freshdesk.com';
    $originId = (int) (time() % 100000000) + 7000000;
    $customerEmail = strtolower($marker) . '-fd-customer@example.invalid';
    $agentEmail = strtolower($marker) . '-fd-agent@example.invalid';

    $json = function ($data) {
        return ['headers' => [], 'body' => wp_json_encode($data), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    };

    $requester = ['id' => 501, 'name' => 'Fresh Customer', 'email' => $customerEmail];
    $ticket = [
        'id'          => $originId,
        'subject'     => 'Printer on fire [' . $marker . ']',
        'description' => '<p>It is on fire</p>',
        'status'      => 2,
        'priority'    => 1,
        'created_at'  => '2024-03-05T15:30:00Z',
        'updated_at'  => '2024-03-05T16:45:00Z',
    ];
    $single = $ticket + [
        'requester'     => $requester,
        'attachments'   => [],
        'stats'         => ['requester_responded_at' => '2024-03-05T17:10:00Z', 'agent_responded_at' => '2024-03-05T16:45:00Z', 'status_updated_at' => '2024-03-05T16:45:00Z'],
        'conversations' => [
            ['body' => '<p>We are on it</p>', 'source' => 0, 'user_id' => 900, 'support_email' => $agentEmail, 'created_at' => '2024-03-05T16:45:00Z', 'updated_at' => '2024-03-05T16:45:00Z', 'attachments' => []],
            ['body' => '<p>Thanks, it is out</p>', 'source' => 0, 'user_id' => 501, 'created_at' => '2024-03-05T17:10:00Z', 'updated_at' => '2024-03-05T17:10:00Z', 'attachments' => []],
        ],
    ];

    $fakeFreshdesk = function ($pre, $args, $url) use ($json, $domain, $ticket, $single, $originId, $agentEmail) {
        if (strpos($url, $domain) !== 0) {
            return $pre;
        }
        if (strpos($url, '/api/v2/tickets?') !== false) {
            return $json(strpos($url, '&page=1&') !== false || substr($url, -7) === '&page=1' || strpos($url, 'page=1&') !== false ? [$ticket] : []);
        }
        if (strpos($url, '/api/v2/tickets/' . $originId) !== false) {
            return $json($single);
        }
        if (strpos($url, '/api/v2/agents/900') !== false) {
            return $json(['contact' => ['name' => 'Fresh Agent', 'email' => $agentEmail]]);
        }
        return $json([]);
    };

    $import = function ($page) use ($domain) {
        return FsTest::rest('POST', '/ticket_importer/import', [
            'handler' => 'freshdesk',
            'page'    => $page,
            'query'   => ['access_token' => 'fake-token', 'domain' => $domain],
        ]);
    };

    $keys = function ($result) {
        $data = json_decode(wp_json_encode($result['data']), true);
        $keys = is_array($data) ? array_keys($data) : [];
        sort($keys);
        return $keys;
    };

    $originalMigrated = get_option('_fs_migrate_freshdesk');
    $originalInfo = Meta::where('object_type', '_fs_freshdesk_migration_info')->get()->toArray();

    add_filter('pre_http_request', $fakeFreshdesk, 10, 3);

    try {
        FsTest::case('importer_stats_contract', function () {
            $result = FsTest::rest('GET', '/ticket_importer');
            FsTest::assertHealthy($result, 'stats');

            $stats = json_decode(wp_json_encode($result['data']['stats'] ?? []), true);
            $byHandler = array_column($stats, null, 'handler');

            foreach (['helpscout', 'freshdesk', 'zendesk'] as $handler) {
                FsTest::assert(isset($byHandler[$handler]), $handler . ' listed');
                $statKeys = array_keys($byHandler[$handler] ?? []);
                sort($statKeys);
                FsTest::assertSame(['handler', 'last_migrated', 'name', 'previously_imported', 'type'], $statKeys, $handler . ' keys');
                FsTest::assertSame('sass', $byHandler[$handler]['type'] ?? null, $handler . ' type');
            }
        });

        FsTest::case('importer_freshdesk_imports_a_ticket_with_its_replies', function () use ($import, $keys, $originId, $marker, $customerEmail) {
            if (gethostbyname('fstest.freshdesk.com') === 'fstest.freshdesk.com') {
                FsTest::skip('no DNS in the lab; the importer only accepts hosts that resolve');
            }

            $first = $import(1);
            FsTest::assertHealthy($first, 'import page 1');
            FsTest::assertSame(['completed', 'handler', 'has_more', 'imported_page', 'insert_ids', 'next_page', 'remaining', 'skips', 'total_pages', 'total_tickets'], $keys($first), 'page 1 keys');
            FsTest::assertSame(true, $first['data']['has_more'] ?? null, 'more pages may follow');

            $ticketId = (int) Meta::where('object_type', 'ticket_meta')->where('key', '_freshdesk_origin_id')->where('value', $originId)->value('object_id');
            FsTest::assert($ticketId > 0, 'ticket imported with its origin id');

            $ticket = Ticket::find($ticketId);
            FsFactory::adoptRestTicket($ticket);

            FsTest::assertSame('Printer on fire [' . $marker . ']', $ticket->title, 'title');
            FsTest::assertSame('active', $ticket->status, 'status 2 is active');
            // Stored in site time, whatever zone the lab is in.
            FsTest::assertSame(wp_date('Y-m-d H:i:s', strtotime('2024-03-05T15:30:00Z')), (string) $ticket->created_at, 'created_at is the same moment in site time');
            FsTest::assertSame($customerEmail, (string) Person::where('id', $ticket->customer_id)->value('email'), 'customer');

            $replies = Conversation::where('ticket_id', $ticketId)->orderBy('id')->get(['person_id', 'created_at'])->toArray();
            FsTest::assertSame(2, count($replies), 'both replies');
            FsTest::assertSame(wp_date('Y-m-d H:i:s', strtotime('2024-03-05T16:45:00Z')), (string) ($replies[0]['created_at'] ?? ''), 'agent reply time');
            FsTest::assertSame(wp_date('Y-m-d H:i:s', strtotime('2024-03-05T17:10:00Z')), (string) ($replies[1]['created_at'] ?? ''), 'customer reply time');

            $last = $import(2);
            FsTest::assertHealthy($last, 'import page 2');
            FsTest::assertSame(['completed', 'handler', 'has_more', 'imported_page', 'insert_ids', 'message', 'next_page', 'remaining', 'skips', 'total_pages', 'total_tickets'], $keys($last), 'last page keys');
            FsTest::assertSame(false, $last['data']['has_more'] ?? null, 'no more pages');
        });

        FsTest::case('importer_freshdesk_rerun_skips_imported_tickets', function () use ($import, $originId) {
            if (gethostbyname('fstest.freshdesk.com') === 'fstest.freshdesk.com') {
                FsTest::skip('no DNS in the lab');
            }

            $again = $import(1);
            FsTest::assertHealthy($again, 'import page 1 again');
            FsTest::assertSame([], $again['data']['insert_ids'] ?? null, 'nothing new');
            FsTest::assertSame(1, (int) ($again['data']['skips'] ?? 0), 'the ticket is skipped');
            FsTest::assertSame(1, (int) Meta::where('object_type', 'ticket_meta')->where('key', '_freshdesk_origin_id')->where('value', $originId)->count(), 'imported once');
        });

        // The helpdesk tables hold site time. The services send UTC, so each date is
        // moved to the site's zone on the way in, as a ticket created here would have it.
        FsTest::case('importer_stores_remote_dates_in_site_time', function () use ($import, $originId) {
            if (gethostbyname('fstest.freshdesk.com') === 'fstest.freshdesk.com') {
                FsTest::skip('no DNS in the lab');
                return;
            }

            $previous = [get_option('timezone_string'), get_option('gmt_offset')];
            update_option('timezone_string', 'America/New_York');
            // Forget the first import, so the same source ticket comes in again.
            Meta::where('object_type', 'ticket_meta')->where('key', '_freshdesk_origin_id')->where('value', $originId)->delete();

            try {
                FsTest::assertHealthy($import(1), 'import under America/New_York');
                $ticketId = (int) Meta::where('object_type', 'ticket_meta')->where('key', '_freshdesk_origin_id')->where('value', $originId)->value('object_id');
                $ticket = Ticket::find($ticketId);
                FsFactory::adoptRestTicket($ticket);

                // 15:30 UTC is 10:30 in New York (UTC-5 in March before DST).
                FsTest::assertSame('2024-03-05 10:30:00', (string) $ticket->created_at, 'ticket created_at');
                FsTest::assertSame(
                    ['2024-03-05 11:45:00', '2024-03-05 12:10:00'],
                    array_map('strval', array_column(Conversation::where('ticket_id', $ticketId)->orderBy('id')->get(['created_at'])->toArray(), 'created_at')),
                    'reply times'
                );
                FsTest::assertSame(
                    ['2024-03-05 11:45:00', '2024-03-05 12:10:00', 4500],
                    [(string) $ticket->last_agent_response, (string) $ticket->last_customer_response, (int) $ticket->first_response_time],
                    'reply bookkeeping is on the same clock; the first response took 75 minutes'
                );
            } finally {
                update_option('timezone_string', $previous[0]);
                update_option('gmt_offset', $previous[1]);
            }
        });

        FsTest::case('importers_never_write_a_utc_date_string', function () {
            foreach (glob(dirname(__DIR__, 2) . '/app/Modules/TicketImport/Importers/*.php') as $file) {
                FsTest::assert(strpos(file_get_contents($file), "gmdate('Y-m-d H:i:s'") === false, basename($file) . ' stores dates through siteTime()');
            }
        });

        // The page loop Awesome Support, SupportCandy and JS Help Desk share. Their plugins
        // aren't in the lab, so a minimal importer feeds it 45 source tickets, 20 a page.
        FsTest::case('importer_plugin_sources_share_one_page_loop', function () use ($marker) {
            $importer = new class extends \FluentSupport\App\Modules\TicketImport\Importers\BaseImporter {
                public $total = 45;
                protected $limit = 20;
                public function stats() { return []; }
                public function deleteTickets($page) { return []; }
                public function doMigration($page, $handler)
                {
                    $this->handler = $handler;
                    return $this->importPage($page, $this->total, [], '_fs_migrate_fstest_source');
                }
            };

            $page1 = $importer->doMigration(1, 'fstest-source');
            $keys = array_keys($page1);
            sort($keys);
            FsTest::assertSame(['completed', 'handler', 'has_more', 'imported_page', 'insert_ids', 'next_page', 'remaining', 'skips', 'total_pages', 'total_tickets'], $keys, 'page keys');
            FsTest::assertSame(44, $page1['completed'], 'page 1 of 3 is 44% of 45 tickets');
            FsTest::assertSame(true, $page1['has_more'], 'more pages');

            $page3 = $importer->doMigration(3, 'fstest-source');
            FsTest::assertSame(100, $page3['completed'], 'last page is 100%');
            FsTest::assertSame(false, $page3['has_more'], 'no more pages');
            FsTest::assert(isset($page3['message']) && get_option('_fs_migrate_fstest_source'), 'finish recorded');
            delete_option('_fs_migrate_fstest_source');

            $importer->total = 0;
            FsTest::assertSame(['had_tickets' => 'no', 'message' => 'Sorry, no tickets available for import.'], (function ($r) { ksort($r); return $r; })($importer->doMigration(1, 'fstest-source')), 'empty source');
        });

        FsTest::case('importer_delete_for_a_cloud_source_does_nothing', function () {
            $result = FsTest::rest('DELETE', '/ticket_importer/delete', ['handler' => 'freshdesk', 'page' => 1]);
            FsTest::assertSame(200, (int) $result['status'], 'status');
            FsTest::assertSame(null, $result['data'], 'empty answer');
        });

        FsTest::case('importer_import_needs_the_admin_settings_permission', function () {
            $adminId = get_current_user_id();
            wp_set_current_user(0);
            \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);
            try {
                $result = FsTest::rest('POST', '/ticket_importer/import', ['handler' => 'freshdesk', 'page' => 1]);
            } finally {
                wp_set_current_user($adminId);
                \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);
            }
            FsTest::assert(in_array((int) $result['status'], [401, 403], true), 'refused logged out, got ' . $result['status']);
        });
    } finally {
        remove_filter('pre_http_request', $fakeFreshdesk, 10);

        $ticketIds = Meta::where('object_type', 'ticket_meta')->where('key', '_freshdesk_origin_id')->where('value', $originId)->pluck('object_id')->toArray();
        if ($ticketIds) {
            Conversation::whereIn('ticket_id', $ticketIds)->delete();
            Meta::where('object_type', 'ticket_meta')->whereIn('object_id', $ticketIds)->delete();
        }
        Person::whereIn('email', [$customerEmail, $agentEmail])->delete();

        Meta::where('object_type', '_fs_freshdesk_migration_info')->delete();
        foreach ($originalInfo as $row) {
            unset($row['id']);
            Meta::insert($row);
        }
        if ($originalMigrated === false) {
            delete_option('_fs_migrate_freshdesk');
        } else {
            update_option('_fs_migrate_freshdesk', $originalMigrated, 'no');
        }
    }
};
