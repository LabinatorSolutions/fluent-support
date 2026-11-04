<?php
/**
 * The advanced ticket filter fails closed (roadmap 2.1, 2.2):
 * - customer and agent conditions only use the fields the filter offers, so a
 *   hidden column (hash, remote_uid) can't be probed through result counts;
 * - "contains" treats % and _ in the value as literal characters;
 * - a group with an unknown filter type, or with no valid rows, matches nothing
 *   instead of every ticket.
 * Customer and agent conditions run through Pro's hooks, so those cases skip without Pro.
 */

use FluentSupport\App\Models\Customer;

return function () {

    $proActive = has_action('fluent_support\tickets_filter_customer');
    $needle = FsTest::uniq('fail-closed');

    // Ticket titles matching one filter group: title contains $needle AND $rows.
    $search = function (array $groups) {
        $result = FsTest::rest('GET', '/tickets', [
            'per_page'         => 100,
            'filter_type'      => 'advanced',
            'advanced_filters' => wp_json_encode($groups),
        ]);
        FsTest::assertHealthy($result, 'GET /tickets (advanced filter)');

        $payload = json_decode(wp_json_encode($result['data']['tickets'] ?? []), true);
        $titles = array_column($payload['data'] ?? [], 'title');
        sort($titles);
        return $titles;
    };
    $titleRow = ['source' => ['tickets', 'title'], 'operator' => 'contains', 'value' => $needle];

    $plain = FsFactory::customer(['first_name' => 'Annex', 'zip' => '1207']);
    $percent = FsFactory::customer(['first_name' => 'An%nex']);
    $plainTicket = FsFactory::ticket(['customer_id' => $plain->id, 'title' => $needle . ' plain']);
    $percentTicket = FsFactory::ticket(['customer_id' => $percent->id, 'title' => $needle . ' percent']);
    $plainHash = (string) Customer::where('id', $plain->id)->value('hash');

    FsTest::case('advanced_filter_ignores_customer_columns_it_does_not_offer', function () use ($proActive, $search, $titleRow, $plain, $plainHash, $plainTicket) {
        if (!$proActive) {
            FsTest::skip('customer conditions need Pro');
        }
        FsTest::assert($plainHash !== '', 'fixture customer has a hash');

        $byHash = $search([[$titleRow, ['source' => ['customer', 'hash'], 'operator' => '=', 'value' => $plainHash]]]);
        $byEmail = $search([[$titleRow, ['source' => ['customer', 'email'], 'operator' => '=', 'value' => $plain->email]]]);

        FsTest::assertSame([], $byHash, 'a hidden column matches nothing, so it reveals nothing');
        FsTest::assertSame([$plainTicket->title], $byEmail, 'an offered column still filters');
    });

    FsTest::case('advanced_filter_postal_code_reads_the_zip_column', function () use ($proActive, $search, $titleRow, $plainTicket) {
        if (!$proActive) {
            FsTest::skip('customer conditions need Pro');
        }

        $byPostalCode = $search([[$titleRow, ['source' => ['customer', 'postal_code'], 'operator' => '=', 'value' => '1207']]]);

        FsTest::assertSame([$plainTicket->title], $byPostalCode, 'postal_code filters on zip');
    });

    FsTest::case('advanced_filter_contains_treats_percent_as_a_literal', function () use ($proActive, $search, $titleRow, $percentTicket) {
        if (!$proActive) {
            FsTest::skip('customer conditions need Pro');
        }

        $titles = $search([[$titleRow, ['source' => ['customer', 'first_name'], 'operator' => 'contains', 'value' => 'n%n']]]);

        FsTest::assertSame([$percentTicket->title], $titles, 'only the name with a literal %');
    });

    FsTest::case('advanced_filter_group_with_unknown_type_matches_nothing', function () use ($search, $titleRow) {
        $titles = $search([[$titleRow, ['source' => ['no_such_type', 'anything'], 'operator' => '=', 'value' => 'x']]]);

        FsTest::assertSame([], $titles, 'unknown filter type');
    });

    FsTest::case('advanced_filter_group_with_no_valid_rows_matches_nothing', function () use ($search, $needle) {
        $titles = $search([[['source' => ['tickets', 'title'], 'operator' => '', 'value' => $needle]]]);

        FsTest::assertSame([], $titles, 'a group whose rows are all invalid');
    });
};
