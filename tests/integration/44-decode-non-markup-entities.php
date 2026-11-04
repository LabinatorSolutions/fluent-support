<?php
/**
 * Helper::decodeNonMarkupEntities() is the single primitive every content-intake
 * path shares: manual tickets/replies (via sanitizeHtmlContent), the 6 ticket
 * importers, MCP tools, FluentCRM/FluentForm integrations, and Pro's email
 * piping. These cases pin its behaviour directly so a change here cannot
 * silently break every caller at once.
 *
 * wp_kses_post() only knows the HTML 4.01 entity table (see wp-includes/kses.php
 * $allowedentitynames), so HTML5-only names like &epsi;/&upsi;/&ohm; get their
 * leading & escaped to &amp; unless decoded first. Markup entities (<, >, &, ",
 * ') must stay encoded, or kses would see live tags after decoding.
 */

use FluentSupport\App\Services\Helper;

return function () {

    FsTest::case('decodeNonMarkupEntities: HTML5-only Greek entities become characters', function () {
        $decoded = Helper::decodeNonMarkupEntities('&Xi;&epsi;&sigma;&kappa; &psi;&upsi;&chi;&omicron; &ohm;');

        FsTest::assert($decoded === 'Ξεσκ ψυχο Ω',
            'HTML5-only entities (epsi/upsi/ohm) alongside HTML4 ones must all decode, got: ' . $decoded);
    });

    FsTest::case('decodeNonMarkupEntities: markup entities stay encoded', function () {
        $input = 'x&lt;5 and y&gt;3, &amp;amp;, &quot;quoted&quot;, it&#39;s';
        $decoded = Helper::decodeNonMarkupEntities($input);

        FsTest::assert($decoded === $input,
            'markup-significant entities must pass through unchanged, got: ' . $decoded);
    });

    FsTest::case('decodeNonMarkupEntities: numeric and hex refs for angle brackets stay encoded', function () {
        $input = 'a &#60;script&#62; b &#x3c;img&#x3e; c';
        $decoded = Helper::decodeNonMarkupEntities($input);

        FsTest::assert($decoded === $input,
            'numeric/hex angle-bracket refs must stay encoded, got: ' . $decoded);
    });

    FsTest::case('decodeNonMarkupEntities: double-encoded markup is never unwrapped twice', function () {
        $input = '&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;';
        $decoded = Helper::decodeNonMarkupEntities($input);

        FsTest::assert($decoded === $input,
            'a fully double-encoded payload must pass through decode unchanged, got: ' . $decoded);
        FsTest::assert(stripos(wp_kses_post($decoded), '<script') === false,
            'and must never become a live tag after kses, got: ' . wp_kses_post($decoded));
    });

    FsTest::case('decodeNonMarkupEntities: two-character entities that contain a bracket stay encoded', function () {
        // &nvlt; decodes to "<" plus U+20D2, so an exact-match guard let it through and kses
        // then ate everything up to the next ">".
        $input = 'x &nvlt; 5, &nvlt;img src=x onerror=alert(1)&nvgt; tail';
        $decoded = Helper::decodeNonMarkupEntities($input);

        FsTest::assert($decoded === $input,
            'entities decoding to anything containing < or > must stay encoded, got: ' . $decoded);
        FsTest::assert(strpos(wp_kses_post($decoded), 'tail') !== false && stripos(wp_kses_post($decoded), '<img') === false,
            'kses must keep the surrounding text and never see a tag, got: ' . wp_kses_post($decoded));
    });

    FsTest::case('sanitizeHtmlContent: entity-obfuscated attributes and protocols are still stripped', function () {
        $cases = [
            '<a href="javascript&colon;alert(1)">a</a>',
            '<a href="java&Tab;script:alert(1)">a</a>',
            '<a href="&#106;avascript:alert(1)">a</a>',
            '<img src="x" onerror&equals;"alert(1)">',
        ];

        // An encoded quote must stay encoded, or it would close the attribute value.
        $quoted = Helper::sanitizeHtmlContent('<p title="&quot; onmouseover=&quot;alert(1)">t</p>');
        FsTest::assert(strpos($quoted, 'title="&quot; onmouseover=&quot;alert(1)"') !== false,
            'an encoded quote inside an attribute must stay encoded, got: ' . $quoted);

        foreach ($cases as $input) {
            $out = Helper::sanitizeHtmlContent($input);

            FsTest::assert(stripos($out, 'javascript') === false,
                'no javascript: protocol may survive, input: ' . $input . ' got: ' . $out);
            FsTest::assert(!preg_match('/<[^>]*\\bon[a-z]+\\s*=/i', $out),
                'no live event handler attribute may survive, input: ' . $input . ' got: ' . $out);
        }
    });

    FsTest::case('decodeNonMarkupEntities: unrecognized and wrongly-cased names pass through untouched', function () {
        $decoded = Helper::decodeNonMarkupEntities('&notarealentity; &Epsi; &EPSI;');

        FsTest::assert($decoded === '&notarealentity; &Epsi; &EPSI;',
            'unknown or wrongly-cased entity names must not be altered, got: ' . $decoded);
    });

    FsTest::case('decodeNonMarkupEntities: a literally typed entity name is indistinguishable from decode input, by design', function () {
        // Someone typing the literal text "&epsi;" (e.g. documenting the bug itself) decodes
        // the same as a real HTML5-encoded epsilon. This is a known, accepted trade-off:
        // there is no way to tell "typed &epsi;" from "encoded ε" once both are UTF-8 text.
        $decoded = Helper::decodeNonMarkupEntities('literal &epsi; text');

        FsTest::assert($decoded === 'literal ε text',
            'typed entity-name text resolves the same as encoded input, got: ' . $decoded);
    });

    FsTest::case('decodeNonMarkupEntities: an array (malformed request) becomes empty, not "Array"', function () {
        FsTest::assertSame('', Helper::decodeNonMarkupEntities(['<b>x</b>']), 'array input');
        FsTest::assertSame('', Helper::sanitizeHtmlContent(['x']), 'array input through sanitizeHtmlContent');
        FsTest::assertSame('', Helper::decodeNonMarkupEntities(null), 'null input');
        FsTest::assertSame('42', Helper::decodeNonMarkupEntities(42), 'a number is still cast to text');
    });
};
