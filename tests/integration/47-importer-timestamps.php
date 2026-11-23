<?php
/**
 * An imported ticket gets the same reply bookkeeping as one created here:
 * last agent / customer reply times, waiting_since, first response time and
 * time to close. The importer used to write the agent's time into
 * last_customer_response and never set the two durations.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\TicketImport\Importers\BaseImporter;

return function () {

    $marker = FsFactory::marker();

    $importer = new class extends BaseImporter {
        protected $handler = 'fstest-import';

        public function stats()
        {
            return [];
        }

        public function doMigration($page, $handler)
        {
            return [];
        }

        public function deleteTickets($page)
        {
            return [];
        }

        // What a cloud importer does with a date the service sent.
        public function fromSource($remoteDate)
        {
            return $this->siteTime($remoteDate);
        }
    };

    FsTest::case('imported ticket gets reply times, waiting_since, first response and close time', function () use ($importer, $marker) {
        $customer = FsFactory::customer();
        $agent = FsFactory::agent();
        $at = function ($hours) {
            return date('Y-m-d H:i:s', strtotime('2026-01-05 08:00:00') + $hours * HOUR_IN_SECONDS);
        };
        $reply = function ($fromCustomer, $hours) use ($customer, $agent, $at) {
            return [
                'content'           => '<p>Reply at ' . $hours . 'h</p>',
                'conversation_type' => 'response',
                'created_at'        => $at($hours),
                'updated_at'        => $at($hours),
                'is_customer_reply' => $fromCustomer,
                'user'              => ['email' => $fromCustomer ? $customer->email : $agent->email],
            ];
        };

        $ticket = $importer->migrateTicket([
            'origin_id'   => FsTest::uniq('origin'),
            'title'       => 'Imported ' . $marker,
            'content'     => '<p>Imported body</p>',
            'status'      => 'closed',
            'created_at'  => $at(0),
            'updated_at'  => $at(6),
            'resolved_at' => $at(6),
            'customer'    => ['id' => $customer->id],
            'replies'     => [$reply(true, 1), $reply(false, 2), $reply(true, 3), $reply(false, 5)],
        ]);
        FsTest::assert($ticket instanceof Ticket, 'ticket imported');
        if (!$ticket instanceof Ticket) {
            return;
        }
        FsFactory::adoptRestTicket($ticket);

        $row = Ticket::find($ticket->id)->getAttributes();
        FsTest::assertSame($at(5), $row['last_agent_response'], 'last_agent_response');
        FsTest::assertSame($at(3), $row['last_customer_response'], 'last_customer_response');
        FsTest::assertSame($at(5), $row['waiting_since'], 'waiting_since starts at the last agent reply');
        FsTest::assertSame(2 * HOUR_IN_SECONDS, (int) $row['first_response_time'], 'first response after 2 hours');
        FsTest::assertSame(6 * HOUR_IN_SECONDS, (int) $row['total_close_time'], 'closed after 6 hours');
        FsTest::assertSame(4, (int) $row['response_count'], 'response_count');
    });

    FsTest::case('imported durations are elapsed time across a daylight-saving change', function () use ($importer, $marker) {
        $previous = [get_option('timezone_string'), get_option('gmt_offset')];
        update_option('timezone_string', 'America/New_York');

        $customer = FsFactory::customer();
        $agent = FsFactory::agent();

        try {
            // Site time, as an importer stores it. The clocks went back on 2024-11-03 at 02:00:
            // 16:00 on the 2nd (EDT) to 15:00 on the 3rd (EST) is 24 hours, not 23.
            $ticket = $importer->migrateTicket([
                'origin_id'   => FsTest::uniq('origin'),
                'title'       => 'Imported over DST ' . $marker,
                'content'     => '<p>Imported body</p>',
                'status'      => 'closed',
                'created_at'  => '2024-11-02 16:00:00',
                'updated_at'  => '2024-11-03 16:00:00',
                'resolved_at' => '2024-11-03 16:00:00',
                'customer'    => ['id' => $customer->id],
                'replies'     => [[
                    'content'           => '<p>First reply</p>',
                    'conversation_type' => 'response',
                    'created_at'        => '2024-11-03 15:00:00',
                    'updated_at'        => '2024-11-03 15:00:00',
                    'is_customer_reply' => false,
                    'user'              => ['email' => $agent->email],
                ]],
            ]);
            FsTest::assert($ticket instanceof Ticket, 'ticket imported');
            if (!$ticket instanceof Ticket) {
                return;
            }
            FsFactory::adoptRestTicket($ticket);

            $row = Ticket::find($ticket->id)->getAttributes();
            FsTest::assertSame(24 * HOUR_IN_SECONDS, (int) $row['first_response_time'], 'first response after 24 real hours');
            FsTest::assertSame(25 * HOUR_IN_SECONDS, (int) $row['total_close_time'], 'closed after 25 real hours');

            // Dates from a cloud service keep their own moment, also in the hour that repeats when
            // the clocks go back (01:50 EDT, then 01:10 EST) and over the hour that is skipped in spring.
            foreach ([['2024-11-03T05:50:00Z', '2024-11-03T06:10:00Z', 'the repeated hour'], ['2024-03-10T06:50:00Z', '2024-03-10T07:10:00Z', 'the skipped hour']] as [$created, $replied, $label]) {
                $cloud = $importer->migrateTicket([
                    'origin_id'  => FsTest::uniq('origin'),
                    'title'      => 'Imported from a cloud source ' . $marker,
                    'content'    => '<p>Imported body</p>',
                    'status'     => 'active',
                    'created_at' => $importer->fromSource($created),
                    'updated_at' => $importer->fromSource($replied),
                    'customer'   => ['id' => $customer->id],
                    'replies'    => [[
                        'content'           => '<p>First reply</p>',
                        'conversation_type' => 'response',
                        'created_at'        => $importer->fromSource($replied),
                        'updated_at'        => $importer->fromSource($replied),
                        'is_customer_reply' => false,
                        'user'              => ['email' => $agent->email],
                    ]],
                ]);
                FsFactory::adoptRestTicket($cloud);
                FsTest::assertSame(20 * MINUTE_IN_SECONDS, (int) Ticket::find($cloud->id)->first_response_time, 'first response after 20 minutes in ' . $label);
            }
        } finally {
            update_option('timezone_string', $previous[0]);
            update_option('gmt_offset', $previous[1]);
        }
    });

    FsTest::case('source-filled reply times do not stop waiting_since following the replies', function () use ($importer, $marker) {
        $customer = FsFactory::customer();
        $agent = FsFactory::agent();
        $at = function ($hours) {
            return date('Y-m-d H:i:s', strtotime('2026-01-05 08:00:00') + $hours * HOUR_IN_SECONDS);
        };
        $reply = function ($fromCustomer, $hours) use ($customer, $agent, $at) {
            return [
                'content'           => '<p>Reply at ' . $hours . 'h</p>',
                'conversation_type' => 'response',
                'created_at'        => $at($hours),
                'updated_at'        => $at($hours),
                'is_customer_reply' => $fromCustomer,
                'user'              => ['email' => $fromCustomer ? $customer->email : $agent->email],
            ];
        };

        // Like Freshdesk: the final times come with the ticket.
        $ticket = $importer->migrateTicket([
            'origin_id'              => FsTest::uniq('origin'),
            'title'                  => 'Imported prefilled ' . $marker,
            'content'                => '<p>Imported body</p>',
            'status'                 => 'active',
            'created_at'             => $at(0),
            'updated_at'             => $at(3),
            'last_customer_response' => $at(2),
            'last_agent_response'    => $at(3),
            'customer'               => ['id' => $customer->id],
            'replies'                => [$reply(false, 1), $reply(true, 2), $reply(false, 3)],
        ]);
        FsTest::assert($ticket instanceof Ticket, 'ticket imported');
        if (!$ticket instanceof Ticket) {
            return;
        }
        FsFactory::adoptRestTicket($ticket);

        $row = Ticket::find($ticket->id)->getAttributes();
        FsTest::assertSame($at(3), $row['last_agent_response'], 'last_agent_response');
        FsTest::assertSame($at(2), $row['last_customer_response'], 'last_customer_response');
        FsTest::assertSame($at(3), $row['waiting_since'], 'waiting_since is the last agent reply');
    });
};
