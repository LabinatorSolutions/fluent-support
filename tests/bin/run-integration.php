<?php
/**
 * Fluent Support integration runner.
 *
 * Each tests/integration/*.php file returns one closure that runs its own
 * FsTest::case() calls. Files run in sorted order as the administrator, with
 * mail intercepted and factory cleanup in finally.
 */

require_once dirname(__DIR__) . '/lib/harness.php';
require_once dirname(__DIR__) . '/lib/factory.php';
require_once dirname(__DIR__) . '/lib/contract.php';

FsTest::boot();
$adminId = get_current_user_id();
$cleared = FsTest::clearCaches();
FsTest::interceptMail();

global $wpdb;
$protectedCounts = [
    'tickets'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_tickets"),
    'conversations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_conversations"),
    'persons'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_persons"),
];

$files = glob(dirname(__DIR__) . '/integration/*.php');
$files = $files === false ? [] : $files;
sort($files);

$requestedFiles = [];
foreach (isset($args) && is_array($args) ? $args : [] as $argument) {
    if (strpos($argument, '--filter=') === 0) {
        $filterValue = substr($argument, strlen('--filter='));
    } else if (strpos($argument, 'filter=') === 0) {
        // WP-CLI 2.12 parses --filter as its own option before eval-file sees it.
        $filterValue = substr($argument, strlen('filter='));
    } else {
        continue;
    }

    $requestedFiles = array_values(array_filter(array_map(
        'trim',
        explode(',', $filterValue)
    )));
}

if ($requestedFiles) {
    $files = array_values(array_filter($files, function ($file) use ($requestedFiles) {
        return in_array(basename($file), $requestedFiles, true);
    }));
}

WP_CLI::log(sprintf(
    "Fluent Support integration — %d files%s — marker %s — cleared %d cache entries\n",
    count($files),
    $requestedFiles ? ' (filtered)' : '',
    FsFactory::marker(),
    $cleared
));

// Backstop for fatals outside FsTest::case(). The finally block below is the
// primary cleanup path because it can report cleanup errors as a test failure.
register_shutdown_function(function () use ($adminId) {
    wp_set_current_user($adminId);
    \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);

    try {
        FsFactory::cleanup();
    } catch (\Throwable $e) {
        WP_CLI::warning('Fixture shutdown cleanup failed: ' . $e->getMessage());
    }
});

if (!$files) {
    FsTest::case('integration runner discovers test files', function () {
        FsTest::fail('No tests/integration/*.php files were found.');
    });
}

$cleanupFailure = null;
try {
    foreach ($files as $file) {
        try {
            $suite = require $file;
            if (!is_callable($suite)) {
                throw new RuntimeException(basename($file) . ' must return a callable.');
            }

            wp_set_current_user($adminId);
            \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);
            $suite();
        } catch (\Throwable $e) {
            FsTest::case('integration file loads: ' . basename($file), function () use ($e) {
                throw $e;
            });
        }
    }
} finally {
    wp_set_current_user($adminId);
    \FluentSupport\App\Modules\PermissionManager::currentUserPermissions(false);

    try {
        FsFactory::cleanup();
        // Called twice on purpose: cleanup must be idempotent.
        FsFactory::cleanup();
    } catch (\Throwable $e) {
        $cleanupFailure = $e;
    }
}

if ($cleanupFailure) {
    FsTest::case('fixture cleanup is exact and idempotent', function () use ($cleanupFailure) {
        throw $cleanupFailure;
    });
}

FsTest::case('integration run preserves protected row counts', function () use ($protectedCounts, $wpdb) {
    $after = [
        'tickets'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_tickets"),
        'conversations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_conversations"),
        'persons'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fs_persons"),
    ];

    FsTest::assertSame($protectedCounts, $after, 'protected row counts are unchanged');
});

FsTest::finish('INTEGRATION');
