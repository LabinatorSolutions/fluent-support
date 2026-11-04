<?php
/**
 * Customers list smart search (CustomerSearch): the parser on its own, then each term
 * kind against real rows through GET customers?smart_search=yes.
 * Every fixture's last name and email carry $token, so `email:<token>` limits a query to them.
 */

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Services\CustomerSearch;

return function () {

    $token = strtolower(preg_replace('/[^a-z0-9]/i', '', FsTest::uniq('smart')));

    // ---- parser (no database) --------------------------------------------

    FsTest::case('smart search parse: words, phrase, email, #id and scoped terms', function () {
        $plan = CustomerSearch::parse('anna "new york" #12 city:berlin');
        FsTest::assertSame(['anna'], $plan['words'], 'bare word');
        FsTest::assertSame(['new york'], $plan['phrases'], 'quoted phrase');
        FsTest::assertSame([12], $plan['ids'], '#id');
        FsTest::assertSame([['field' => 'city', 'key' => 'city', 'value' => 'berlin']], $plan['scoped'], 'scoped term');
        FsTest::assertSame('words', $plan['mode'], 'mode');

        FsTest::assertSame('email', CustomerSearch::parse('jane@acme.com')['mode'], 'a lone address is an email search');
        FsTest::assertSame('empty', CustomerSearch::parse('   ')['mode'], 'blank is empty');
    });

    FsTest::case('smart search parse: "key: value" takes the next token, quoted values keep spaces', function () {
        FsTest::assertSame('berlin', CustomerSearch::parse('city: berlin')['scoped'][0]['value'], 'next token');
        FsTest::assertSame('new york', CustomerSearch::parse('city:"new york"')['scoped'][0]['value'], 'quoted value');
        FsTest::assertSame('zip', CustomerSearch::parse('postal_code:10001')['scoped'][0]['field'], 'alias maps to its field');
        // The controller runs sanitize_text_field(), which turns a lone "<" into "&lt;".
        FsTest::assertSame('<2', CustomerSearch::parse(sanitize_text_field('tickets:<2'))['scoped'][0]['value'], 'sanitized < survives');
    });

    FsTest::case('smart search parse: unknown keys become text and are reported; URLs and times are not', function () {
        $plan = CustomerSearch::parse('foo:bar 10:30 https://acme.com');
        FsTest::assertSame(['foo'], $plan['unknown'], 'only foo is reported');
        FsTest::assertSame([], $plan['scoped'], 'nothing scoped');
        FsTest::assertSame('foo:bar 10:30 https://acme.com', $plan['text'], 'all kept as text');
    });

    FsTest::case('smart search parse: reads at most MAX_TERMS terms', function () {
        $plan = CustomerSearch::parse(implode(' ', range(1, CustomerSearch::MAX_TERMS + 5)));
        FsTest::assertSame(CustomerSearch::MAX_TERMS, count($plan['words']), 'term cap');
    });

    // ---- against rows -------------------------------------------------------

    $make = function ($first, $suffix, $attributes) use ($token) {
        $createdAt = $attributes['created_at'];
        unset($attributes['created_at']);

        // The fixture marker must stay in the email, or the factory refuses to clean the row up.
        $customer = FsFactory::customer(array_merge([
            'first_name' => $first,
            'last_name'  => 'Berg' . $token,
            'email'      => FsFactory::marker() . '-' . $token . '-' . $suffix . '@example.invalid',
        ], $attributes));

        // The model stamps created_at on create, so set it afterwards.
        Customer::where('id', $customer->id)->update(['created_at' => $createdAt]);

        return $customer;
    };

    $anna = $make('Anna', 'anna', ['city' => 'Berlin', 'created_at' => '2025-03-10 09:00:00']);
    $nina = $make('Nina', 'nina', ['city' => 'New York', 'status' => 'inactive', 'created_at' => '2026-05-02 09:00:00']);
    $joanna = $make('Joanna', 'joanna', ['city' => 'Berlin', 'created_at' => '2026-05-20 09:00:00']);

    FsFactory::ticket(['customer_id' => $anna->id, 'status' => 'closed']);
    foreach (['new', 'active', 'closed'] as $status) {
        FsFactory::ticket(['customer_id' => $joanna->id, 'status' => $status]);
    }

    $search = function ($text, array $params = []) {
        $result = FsTest::rest('GET', '/customers', array_merge([
            'search'          => $text,
            'smart_search'    => 'yes',
            'import_contacts' => 'no',
            'per_page'        => 50,
        ], $params));
        FsTest::assertHealthy($result, 'GET customers smart search: ' . $text);

        return json_decode(wp_json_encode($result['data']), true);
    };

    $ids = function ($text, array $params = []) use ($search) {
        $ids = array_map('intval', array_column($search($text, $params)['customers']['data'], 'id'));
        sort($ids);

        return $ids;
    };

    $sorted = function () {
        $ids = array_map(function ($customer) {
            return (int) $customer->id;
        }, func_get_args());
        sort($ids);

        return $ids;
    };

    FsTest::case('smart search: scoped field plus a word narrow together', function () use ($ids, $sorted, $token, $anna, $joanna, $nina) {
        FsTest::assertSame($sorted($anna, $joanna), $ids("email:$token city:berlin"), 'city:berlin');
        FsTest::assertSame($sorted($anna, $joanna), $ids("email:$token city:berlin anna"), 'anna matches Anna and Joanna');
        FsTest::assertSame($sorted($nina), $ids("email:$token city:\"new york\""), 'quoted city');
    });

    FsTest::case('smart search: status matches exactly, and "blocked" means inactive', function () use ($ids, $sorted, $token, $anna, $joanna, $nina) {
        FsTest::assertSame($sorted($nina), $ids("email:$token status:blocked"), 'blocked');
        FsTest::assertSame($sorted($anna, $joanna), $ids("email:$token status:active"), 'active');
        FsTest::assertSame([], $ids("email:$token status:act"), 'a partial status matches nothing');
    });

    FsTest::case('smart search: ticket counts compare, including customers with none', function () use ($ids, $sorted, $token, $anna, $joanna, $nina) {
        FsTest::assertSame($sorted($joanna), $ids("email:$token tickets:>1"), 'more than one ticket');
        FsTest::assertSame($sorted($nina), $ids("email:$token tickets:0"), 'no tickets');
        FsTest::assertSame($sorted($anna, $nina), $ids("email:$token tickets:<2"), 'fewer than two includes zero');
        FsTest::assertSame($sorted($anna, $nina), $ids("email:$token open:0"), 'no open tickets (Anna only has a closed one)');
        FsTest::assertSame($sorted($joanna), $ids("email:$token open:2"), 'exactly two open');
        FsTest::assertSame([], $ids("email:$token tickets:many"), 'a non-number matches nothing');
    });

    FsTest::case('smart search: a ticket without a customer does not hide customers with none', function () use ($ids, $sorted, $token, $anna, $nina) {
        global $wpdb;

        // NOT IN (..., NULL) matches nothing, so one orphan ticket used to empty these results.
        $orphan = FsFactory::ticket(['status' => 'new']);
        $wpdb->update($wpdb->prefix . 'fs_tickets', ['customer_id' => null], ['id' => $orphan->id]);

        FsTest::assertSame($sorted($nina), $ids("email:$token tickets:0"), 'no tickets');
        FsTest::assertSame($sorted($anna, $nina), $ids("email:$token tickets:<2"), 'fewer than two includes zero');
        FsTest::assertSame($sorted($anna, $nina), $ids("email:$token open:0"), 'no open tickets');
    });

    FsTest::case('smart search: created: (and its since: alias) matches a year, a month, and comparisons', function () use ($ids, $sorted, $token, $anna, $joanna, $nina) {
        FsTest::assertSame($sorted($nina, $joanna), $ids("email:$token created:2026-05"), 'May 2026 (created:)');
        FsTest::assertSame($sorted($anna), $ids("email:$token since:<2026"), 'before 2026');
        FsTest::assertSame($sorted($joanna), $ids("email:$token since:>2026-05-02"), 'after 2 May 2026');
        FsTest::assertSame([], $ids("email:$token since:2026-13"), 'an impossible month matches nothing');
    });

    FsTest::case('smart search: an empty scoped value or an unknown key never widens the search', function () use ($search, $ids, $token) {
        FsTest::assertSame([], $ids("email:$token city:"), 'city: with no value matches nothing');

        $data = $search("email:$token foo:bar");
        FsTest::assertSame(0, (int) $data['customers']['total'], 'foo:bar is text and matches no one');
        FsTest::assertSame(['foo'], $data['search_meta']['unknown'], 'search_meta reports the unknown key');
    });

    FsTest::case('smart search: #id and id: lists', function () use ($ids, $sorted, $anna, $nina) {
        FsTest::assertSame($sorted($anna), $ids('#' . $anna->id), '#id');
        FsTest::assertSame($sorted($anna, $nina), $ids('id:' . $anna->id . ',' . $nina->id), 'id list');
    });

    FsTest::case('smart search: best match first only when asked, newest first otherwise', function () use ($search, $token, $anna, $joanna) {
        $text = 'anna berg' . $token;

        $ranked = $search($text, ['sort_by' => 'relevance']);
        FsTest::assertSame((int) $anna->id, (int) $ranked['customers']['data'][0]['id'], 'exact full name ranks first');
        FsTest::assert(!empty($ranked['search_meta']['ranked']), 'search_meta says ranked');

        $byDate = $search($text, ['sort_by' => 'created_at', 'sort_type' => 'DESC']);
        FsTest::assertSame((int) $joanna->id, (int) $byDate['customers']['data'][0]['id'], 'newest first when a column sort is chosen');
        FsTest::assert(empty($byDate['search_meta']['ranked']), 'search_meta says not ranked');
    });

    FsTest::case('smart search: the status tab still applies on top of the search', function () use ($ids, $sorted, $token, $nina) {
        FsTest::assertSame($sorted($nina), $ids("email:$token", ['status' => 'inactive']), 'Blocked tab');
    });

    FsTest::case('plain search (ticket picker) ignores the smart syntax', function () use ($token) {
        $result = FsTest::rest('GET', '/customers', ['search' => "email:$token", 'import_contacts' => 'no']);
        FsTest::assertHealthy($result, 'GET customers plain search');

        $data = json_decode(wp_json_encode($result['data']), true);
        FsTest::assertSame(0, (int) $data['customers']['total'], '"email:<token>" is literal text there');
        FsTest::assert(!array_key_exists('search_meta', $data), 'no search_meta without smart_search');
    });
};
