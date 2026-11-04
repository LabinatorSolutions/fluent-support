<?php
/**
 * Advanced filter by ticket source. "No source" stands for NULL or empty,
 * which SQL's IN / NOT IN would otherwise skip. The advanced filter runs
 * through Pro's tickets_filter_tickets hook, so the cases skip without Pro.
 */

use FluentSupport\App\Models\Ticket;

return function () {

    $proActive = has_action('fluent_support\tickets_filter_tickets');
    $needle = FsTest::uniq('source-filter');

    // One group: title contains $needle AND source <operator> <values>.
    $search = function ($operator, array $values) use ($needle) {
        $result = FsTest::rest('GET', '/tickets', [
            'per_page'         => 100,
            'filter_type'      => 'advanced',
            'advanced_filters' => wp_json_encode([[
                ['source' => ['tickets', 'title'], 'operator' => 'contains', 'value' => $needle],
                ['source' => ['tickets', 'source'], 'operator' => $operator, 'value' => $values],
            ]]),
        ]);
        FsTest::assertHealthy($result, 'GET /tickets (source filter)');

        // The paginator comes back as an object; normalize it to arrays.
        $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
        $titles = array_column($payload['data'] ?? [], 'title');
        sort($titles);
        return $titles;
    };

    $fixtures = [];
    foreach (['web' => 'web', 'email' => 'email', 'null' => null, 'empty' => ''] as $name => $source) {
        $ticket = FsFactory::ticket(['title' => $needle . ' ' . $name]);
        // The factory always sets a source; write the one under test directly.
        Ticket::where('id', $ticket->id)->update(['source' => $source]);
        $fixtures[$name] = Ticket::where('id', $ticket->id)->value('title');
    }

    $expect = function (array $names) use ($fixtures) {
        $titles = array_map(function ($name) use ($fixtures) { return $fixtures[$name]; }, $names);
        sort($titles);
        return $titles;
    };

    FsTest::case('source includes email returns only the email ticket', function () use ($proActive, $search, $expect) {
        if (!$proActive) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        FsTest::assertSame($expect(['email']), $search('in', ['email']), 'tickets');
    });

    FsTest::case('source includes No source returns NULL and empty sources', function () use ($proActive, $search, $expect) {
        if (!$proActive) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        FsTest::assertSame($expect(['empty', 'null']), $search('in', [Ticket::SOURCE_NONE]), 'tickets');
        FsTest::assertSame($expect(['email', 'empty', 'null']), $search('in', ['email', Ticket::SOURCE_NONE]), 'with email');
    });

    FsTest::case('source not including web keeps tickets with no source', function () use ($proActive, $search, $expect) {
        if (!$proActive) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        FsTest::assertSame($expect(['email', 'empty', 'null']), $search('not_in', ['web']), 'tickets');
    });

    FsTest::case('source not including No source drops NULL and empty sources', function () use ($proActive, $search, $expect) {
        if (!$proActive) {
            FsTest::skip('Fluent Support Pro is not active');
            return;
        }
        FsTest::assertSame($expect(['email', 'web']), $search('not_in', [Ticket::SOURCE_NONE]), 'tickets');
        FsTest::assertSame($expect(['email']), $search('not_in', ['web', Ticket::SOURCE_NONE]), 'with web');
    });
};
