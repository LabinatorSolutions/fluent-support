<?php
/**
 * Dashboard stats: Agent Performance (Reports::agentsToday) and
 * tickets awaiting a reply by product (getActiveTicketsByProductStats).
 * Values were pinned against the per-agent / per-product version, then the
 * queries were capped. "Today" is the site's today, like the stored dates.
 */

use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\Reports\Reports;

return function () {

    $now = current_time('mysql');
    $earlier = date('Y-m-d H:i:s', strtotime($now) - 60);

    $busy = FsFactory::agent();
    $idle = FsFactory::agent();
    $product = FsFactory::product();
    $customer = FsFactory::customer();

    // Busy agent today: 2 new tickets (one also waiting), 1 closed, 1 reply.
    $waiting = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $busy->id, 'product_id' => $product->id, 'status' => 'active']);
    Ticket::where('id', $waiting->id)->update(['created_at' => $now, 'last_agent_response' => $earlier, 'last_customer_response' => $now]);

    $closed = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $busy->id, 'status' => 'closed']);
    Ticket::where('id', $closed->id)->update(['created_at' => $now, 'resolved_at' => $now]);

    FsFactory::conversation($waiting, ['person_id' => $busy->id, 'conversation_type' => 'response', 'created_at' => $now]);

    // Customer reply today: not an agent response.
    FsFactory::conversation($waiting, ['person_id' => $customer->id, 'conversation_type' => 'response', 'created_at' => $now]);

    $statsFor = function ($agentId) {
        foreach (Reports::agentsToday() as $row) {
            if ((int) $row['agent_id'] === (int) $agentId) {
                return $row;
            }
        }
        return null;
    };

    FsTest::case('Agent Performance counts today\'s work per agent', function () use ($statsFor, $busy) {
        $row = $statsFor($busy->id);
        FsTest::assert($row !== null, 'busy agent listed');
        if (!$row) {
            return;
        }
        FsTest::assertSame(1, (int) $row['stats']['waiting_today']['count'], 'waiting');
        FsTest::assertSame(2, (int) $row['stats']['new_tickets']['count'], 'new tickets');
        FsTest::assertSame(1, (int) $row['stats']['closed_tickets']['count'], 'closed tickets');
        FsTest::assertSame(1, (int) $row['stats']['responses']['count'], 'agent responses');
        FsTest::assertSame($busy->full_name, $row['agent_name'], 'name');
    });

    FsTest::case('Agent Performance leaves out agents with nothing today', function () use ($statsFor, $idle) {
        FsTest::assert($statsFor($idle->id) === null, 'idle agent not listed');
    });

    FsTest::case('awaiting tickets are counted per product', function () use ($product) {
        $stats = Reports::awaitingByProduct();
        FsTest::assertSame(['title' => $product->title, 'count' => 1], $stats[$product->id] ?? null, 'fixture product');
    });

    FsTest::case('suggested tickets: the agent\'s waiting tickets, oldest customer reply first, at most 5', function () use ($customer) {
        $agent = FsFactory::agent();
        $ids = [];
        foreach ([3, 1, 6, 2, 5, 4] as $hoursAgo) {
            $ticket = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => 'active']);
            $customerAt = date('Y-m-d H:i:s', current_time('timestamp') - $hoursAgo * HOUR_IN_SECONDS);
            Ticket::where('id', $ticket->id)->update(['last_customer_response' => $customerAt, 'last_agent_response' => date('Y-m-d H:i:s', strtotime($customerAt) - 60)]);
            $ids[$hoursAgo] = (int) $ticket->id;
        }
        // Answered: not waiting, never suggested.
        $answered = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => 'active']);
        Ticket::where('id', $answered->id)->update(['last_customer_response' => '2020-01-01 00:00:00', 'last_agent_response' => '2020-01-02 00:00:00']);

        $suggested = array_map('intval', \FluentSupport\App\Services\TicketHelper::getSuggestedTickets($agent->id)->pluck('id')->all());

        FsTest::assertSame([$ids[6], $ids[5], $ids[4], $ids[3], $ids[2]], $suggested, 'oldest first, five of six');

        // Many matches: sorted in SQL instead of loading them all. Same result.
        $sqlSorted = new class extends \FluentSupport\App\Services\TicketHelper {
            const SORT_IN_PHP_MAX = 2;
        };
        $suggested = array_map('intval', $sqlSorted::getSuggestedTickets($agent->id)->pluck('id')->all());
        FsTest::assertSame([$ids[6], $ids[5], $ids[4], $ids[3], $ids[2]], $suggested, 'same order when sorted in SQL');
    });

    FsTest::case('suggested tickets fall back to the oldest unassigned open tickets', function () use ($customer) {
        $agent = FsFactory::agent();
        // Older unassigned tickets in the lab come first; take the ids around our fixtures.
        $first = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => null, 'status' => 'active']);
        $second = FsFactory::ticket(['customer_id' => $customer->id, 'agent_id' => null, 'status' => 'new']);

        $expected = array_map('intval', Ticket::where('status', '!=', 'closed')
            ->where(function ($q) { $q->whereNull('agent_id')->orWhere('agent_id', '0'); })
            ->orderBy('id', 'ASC')->limit(5)->pluck('id')->all());
        FsTest::assert(in_array((int) $first->id, $expected, true) || count($expected) === 5, 'fixture eligible');

        $suggested = array_map('intval', \FluentSupport\App\Services\TicketHelper::getSuggestedTickets($agent->id)->pluck('id')->all());
        FsTest::assertSame($expected, $suggested, 'oldest unassigned open tickets');

        $sqlSorted = new class extends \FluentSupport\App\Services\TicketHelper {
            const SORT_IN_PHP_MAX = 1;
        };
        $suggested = array_map('intval', $sqlSorted::getSuggestedTickets($agent->id)->pluck('id')->all());
        FsTest::assertSame($expected, $suggested, 'same tickets when sorted in SQL');
    });

    FsTest::case('dashboard stats run a fixed number of queries', function () {
        $agentQueries = FsTest::countQueries(function () {
            Reports::agentsToday();
        });
        $productQueries = FsTest::countQueries(function () {
            Reports::awaitingByProduct();
        });
        // Were 4 per agent and 1 per product.
        FsTest::assert($agentQueries <= 8, 'agent performance queries: ' . $agentQueries);
        FsTest::assert($productQueries <= 3, 'product queries: ' . $productQueries);
    });
};
