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
