<?php
/**
 * S1 — REST smoke runner.
 *
 * Dispatches every entry in tests/smoke/routes.manifest.php in-process and
 * asserts no DB error, no plugin exception, an expected status and no PHP
 * diagnostics from Fluent Support.
 *
 * GET only. Mutating routes belong in tests/integration/.
 *
 * Usage (inside the lab):
 *   tests/bin/lab wp eval-file wp-content/plugins/fluent-support/tests/bin/run-smoke.php
 *   ... run-smoke.php filter=tickets
 */

require_once __DIR__ . '/../lib/harness.php';

FsTest::boot();

$manifest = require __DIR__ . '/../smoke/routes.manifest.php';

// Optional substring filter: `wp eval-file ... filter=reports`
$filter = '';
foreach ((array) $args as $arg) {
    if (strpos($arg, '--filter=') === 0) {
        $filter = substr($arg, 9);
    } else if (strpos($arg, 'filter=') === 0) {
        $filter = substr($arg, 7);
    }
}

/**
 * Look up an existing row for routes that need concrete path or query values.
 * A missing row skips the case. The lab seed should satisfy every resolver,
 * so a skip usually means seed drift.
 */
$resolve = function ($needs) {
    switch ($needs) {
        case 'ticket':
            $row = \FluentSupport\App\Models\Ticket::first();
            return $row ? ['id' => $row->id, 'ticket_id' => $row->id] : null;
        case 'customer':
            $row = \FluentSupport\App\Models\Customer::first();
            return $row ? ['id' => $row->id, 'customer_id' => $row->id] : null;
        case 'agent':
            $row = \FluentSupport\App\Models\Agent::first();
            return $row ? ['id' => $row->id, 'agent_id' => $row->id] : null;
        case 'mailbox':
            $row = \FluentSupport\App\Models\MailBox::first();
            return $row ? ['id' => $row->id, 'box_id' => $row->id, 'mailbox_id' => $row->id] : null;
        case 'product':
            $row = \FluentSupport\App\Models\Product::first();
            return $row ? ['id' => $row->id, 'product_id' => $row->id] : null;
        case 'tag':
            $row = \FluentSupport\App\Models\Tag::first();
            return $row ? ['id' => $row->id, 'tag_id' => $row->id] : null;
        case 'ticket_tag':
            // fs_taggables rows scoped to tag_type = 'ticket_tag' (pro surface).
            $row = \FluentSupport\App\Models\TicketTag::first();
            return $row ? ['id' => $row->id, 'tag_id' => $row->id] : null;
        case 'conversation':
            $row = \FluentSupport\App\Models\Conversation::first();
            return $row ? [
                'id'              => $row->id,
                'conversation_id' => $row->id,
                'ticket_id'       => $row->ticket_id,
            ] : null;
        case 'saved_reply':
            $row = \FluentSupport\App\Models\SavedReply::first();
            return $row ? ['id' => $row->id] : null;
        case 'activity':
            $row = \FluentSupport\App\Models\Activity::first();
            return $row ? ['id' => $row->id] : null;
        case 'workflow':
            if (!class_exists('\FluentSupportPro\App\Models\Workflow')) {
                return null;
            }
            $row = \FluentSupportPro\App\Models\Workflow::first();
            return $row ? ['id' => $row->id, 'workflow_id' => $row->id] : null;
        case 'wp_user':
            $users = get_users(['number' => 1, 'orderby' => 'ID']);
            return $users ? ['id' => $users[0]->ID, 'user_id' => $users[0]->ID] : null;
        default:
            return null;
    }
};

/**
 * Substitute resolver tokens recursively. A value that is exactly `{id}` keeps
 * the resolved integer type; embedded tokens are string-replaced.
 */
$replaceTokens = function ($value, array $tokens) use (&$replaceTokens) {
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = $replaceTokens($item, $tokens);
        }
        return $value;
    }

    if (!is_string($value)) {
        return $value;
    }

    foreach ($tokens as $token => $replacement) {
        if ($value === '{' . $token . '}') {
            return $replacement;
        }
        $value = str_replace('{' . $token . '}', (string) $replacement, $value);
    }

    return $value;
};

// A warm transient can hide a broken query behind a 200.
$cleared = FsTest::clearCaches();

WP_CLI::log('Fluent Support smoke — ' . count($manifest) . ' cases'
    . ($filter ? " (filter: {$filter})" : '')
    . ' — cleared ' . $cleared . " cache entries\n");

foreach ($manifest as $entry) {
    $label  = isset($entry['label']) ? $entry['label'] : $entry['route'];
    $route  = $entry['route'];
    $params = isset($entry['params']) ? $entry['params'] : [];
    $ok     = isset($entry['ok']) ? $entry['ok'] : [200, 201, 204];

    if ($filter !== '' && stripos($label . ' ' . $route, $filter) === false) {
        continue;
    }

    FsTest::case($label, function () use ($entry, $route, $params, $ok, $label, $resolve, $replaceTokens) {
        if (isset($entry['skip'])) {
            FsTest::skip($entry['skip']);
            return;
        }

        // Pro-only routes: skip by name when the sibling is absent.
        if (isset($entry['requires_constant']) && !defined($entry['requires_constant'])) {
            FsTest::skip('sibling providing ' . $entry['requires_constant'] . ' is not active');
            return;
        }

        if (isset($entry['needs'])) {
            $tokens = $resolve($entry['needs']);
            if (!$tokens) {
                FsTest::skip('no ' . $entry['needs'] . ' exists on this site');
                return;
            }
            $route = $replaceTokens($route, $tokens);
            $params = $replaceTokens($params, $tokens);
        }

        if (preg_match('/\{[^}]+\}/', $route)) {
            FsTest::fail($label . "\n  unresolved route placeholder: " . $route);
            return;
        }

        $result = FsTest::rest('GET', $route, $params);
        if (
            getenv('FS_STRICT_SQL') === '1'
            && isset($entry['strict_sql_known_failure'])
            && strpos($result['db_error'], 'only_full_group_by') !== false
        ) {
            FsTest::skip(
                'KNOWN-FAILURE: STRICT-SQL — '
                . $entry['strict_sql_known_failure']
                . ' | ' . $result['db_error']
            );
            return;
        }

        FsTest::assertHealthy($result, $label . "  [GET {$route}]", $ok);
    });
}

FsTest::finish('SMOKE');
