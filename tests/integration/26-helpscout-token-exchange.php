<?php
/**
 * The HelpScout importer swaps its authorization code for a token on the
 * server (ticket_importer/helpscout/token), so the App Secret goes to HelpScout
 * in a request body, never in a browser URL. HelpScout is never called: the
 * pre_http_request filter answers for it and records the request.
 */

return function () {

    $sent = [];
    $answer = null;
    $fakeHelpScout = function ($pre, $args, $url) use (&$sent, &$answer) {
        if (strpos($url, 'helpscout.net') === false) {
            return $pre;
        }
        $sent[] = ['url' => $url, 'method' => $args['method'] ?? '', 'body' => $args['body'] ?? []];
        return $answer;
    };
    add_filter('pre_http_request', $fakeHelpScout, 10, 3);

    try {
        FsTest::case('helpscout_token_is_fetched_on_the_server_with_the_secret_in_the_body', function () use (&$sent, &$answer) {
            $sent = [];
            $answer = ['response' => ['code' => 200], 'body' => wp_json_encode([
                'access_token' => 'tok-123', 'refresh_token' => 'ref-456', 'token_type' => 'bearer', 'expires_in' => 7200,
            ])];

            $result = FsTest::rest('POST', '/ticket_importer/helpscout/token', ['app_id' => 'app-1', 'app_secret' => 'secret-9', 'code' => 'code-7']);

            FsTest::assertHealthy($result, 'token exchange');
            FsTest::assertSame('tok-123', $result['data']['access_token'] ?? null, 'access token returned');
            FsTest::assertSame(1, count($sent), 'one request to HelpScout');
            FsTest::assertSame('https://api.helpscout.net/v2/oauth2/token', $sent[0]['url'], 'no query string on the token URL');
            FsTest::assertSame('POST', $sent[0]['method'], 'method');
            FsTest::assertSame('secret-9', $sent[0]['body']['client_secret'] ?? null, 'secret in the body');
            FsTest::assert(!isset($result['data']['client_secret']) && strpos(wp_json_encode($result['data']), 'secret-9') === false, 'the secret is not echoed back');
        });

        FsTest::case('helpscout_token_reports_helpscouts_error', function () use (&$answer) {
            $answer = ['response' => ['code' => 400], 'body' => wp_json_encode(['error' => 'invalid_grant', 'error_description' => 'Code expired'])];

            $result = FsTest::rest('POST', '/ticket_importer/helpscout/token', ['app_id' => 'app-1', 'app_secret' => 'secret-9', 'code' => 'old']);

            FsTest::assertSame(422, (int) $result['status'], 'status');
            FsTest::assertSame('Code expired', $result['message'] ?? null, 'HelpScout message');
        });

        FsTest::case('helpscout_token_needs_all_three_values', function () use (&$sent) {
            $sent = [];
            $result = FsTest::rest('POST', '/ticket_importer/helpscout/token', ['app_id' => 'app-1', 'code' => 'code-7']);

            FsTest::assertSame(422, (int) $result['status'], 'status');
            FsTest::assertSame([], $sent, 'HelpScout not called');
        });
    } finally {
        remove_filter('pre_http_request', $fakeHelpScout, 10);
    }
};
