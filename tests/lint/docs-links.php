<?php
/**
 * Lint: help links must point at the docs site, docs.fluentsupport.com.
 *
 * The docs moved from fluentsupport.com/docs/ to docs.fluentsupport.com. The old
 * pages still load, so a stale link shows readers an old copy instead of failing.
 * Same slug on the new site, without the trailing slash:
 *     https://fluentsupport.com/docs/managing-tickets-using-slack/
 *  -> https://docs.fluentsupport.com/managing-tickets-using-slack
 *
 * Usage:  php tests/lint/docs-links.php            # core + pro sibling
 *         php tests/lint/docs-links.php <dir>      # explicit target (self-test)
 * Exit:   0 clean, 1 violations found
 */

$root = is_dir(__DIR__ . '/../../app') ? dirname(__DIR__, 2) : getcwd();

// Scans core plus every registered sibling. A missing sibling reports SKIP so it
// doesn't read as covered. The self-test passes a path:
// `php tests/lint/docs-links.php tests/lint/fixtures/docs-links` must exit 1.
$scanPaths = [$root . '/app', $root . '/resources', $root . '/readme.txt'];
$config = is_file(__DIR__ . '/../suite.config.php') ? require __DIR__ . '/../suite.config.php' : [];
$skips = [];
if (!isset($argv[1]) || $argv[1] === '') {
    foreach (isset($config['siblings']) ? $config['siblings'] : [] as $slug => $sibling) {
        $siblingRoot = dirname($root) . '/' . $slug;
        if (is_dir($siblingRoot . '/app')) {
            $scanPaths[] = $siblingRoot . '/app';
            $scanPaths[] = $siblingRoot . '/readme.txt';
        } else {
            $skips[] = $slug;
        }
    }
} else {
    $scanPaths = [rtrim($argv[1], '/')];
}

$files = [];
foreach ($scanPaths as $path) {
    if (is_file($path)) {
        $files[] = $path;
        continue;
    }
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (preg_match('/\.(php|vue|js|json|txt)$/', $file->getFilename())) {
            $files[] = $file->getPathname();
        }
    }
}

$violations = [];
foreach ($files as $file) {
    foreach (file($file) as $i => $line) {
        if (preg_match('#https?://(?:www\.)?fluentsupport\.com/docs\b[^\s\'"<>)]*#i', $line, $hit)) {
            $violations[] = [
                'file' => str_replace(dirname($root) . '/', '', $file),
                'line' => $i + 1,
                'url'  => $hit[0],
            ];
        }
    }
}

echo 'docs-links: scanned ' . count($files) . " files\n";
foreach ($skips as $slug) {
    echo "SKIP: sibling '{$slug}' is not present on this machine; its tree was NOT scanned.\n";
}

if (!$violations) {
    echo "OK: every docs link points at docs.fluentsupport.com.\n";
    exit(0);
}

echo "\nFAIL: " . count($violations) . " link(s) to the old docs location:\n\n";
foreach ($violations as $v) {
    echo "  {$v['file']}:{$v['line']}\n    {$v['url']}\n\n";
}
echo "Use https://docs.fluentsupport.com/<slug> (same slug, no trailing slash).\n";
exit(1);
