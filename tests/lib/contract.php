<?php

/**
 * A recorded contract: per label, a value the code must keep producing (the
 * status and key paths of a REST response, or the hooks an action fires).
 *
 * The expected values live in tests/integration/fixtures/{name}.json. With no
 * file the suite records: it prints the JSON between BEGIN/END lines for the
 * host to save (the lab mounts the plugin read-only). After an intended
 * change, delete the file, record, review the diff and commit it:
 *
 *   bash tests/bin/lab wp eval-file wp-content/plugins/fluent-support/tests/bin/run-integration.php \
 *     filter={name}.php | sed -n '/^--- BEGIN {name}.json ---$/,/^--- END/{//!p;}' \
 *     > tests/integration/fixtures/{name}.json
 */
class FsContract
{
    private $name;
    private $recorded;
    private $recording = [];

    public function __construct($name)
    {
        $this->name = $name;
        $file = dirname(__DIR__) . '/integration/fixtures/' . $name . '.json';
        $this->recorded = file_exists($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    public function isRecording()
    {
        return !is_array($this->recorded);
    }

    /**
     * The status and sorted key paths of a REST result ("tickets.data[].title").
     * Values are not compared. Lists are merged over their items.
     */
    public function response($label, array $result)
    {
        FsTest::assert($result['db_error'] === '', $label . ': database error ' . $result['db_error']);

        $this->check($label, [
            'status' => (int) $result['status'],
            'paths'  => self::mergeNullLeaves(self::paths($result['data'])),
        ]);
    }

    /**
     * Any JSON-able value, compared exactly.
     */
    public function check($label, $actual)
    {
        if ($this->isRecording()) {
            $this->recording[$label] = $actual;
            return;
        }

        if (!array_key_exists($label, $this->recorded)) {
            FsTest::fail($label . ': not in the recorded contract. Actual: ' . wp_json_encode($actual));
            return;
        }

        $expected = $this->recorded[$label];
        if (isset($expected['paths'], $actual['paths'])) {
            FsTest::assertSame($expected['status'], $actual['status'], $label . ': status');
            $missing = array_values(array_diff($expected['paths'], $actual['paths']));
            $extra = array_values(array_diff($actual['paths'], $expected['paths']));
            FsTest::assert(!$missing, $label . ': keys no longer sent: ' . implode(', ', $missing));
            FsTest::assert(!$extra, $label . ': keys not in the contract are now sent: ' . implode(', ', $extra));
            return;
        }

        FsTest::assertSame($expected, $actual, $label);
    }

    /**
     * Prints the recording (when recording). Call once, last.
     */
    public function finish()
    {
        if (!$this->isRecording()) {
            return;
        }

        $recording = $this->recording;
        $name = $this->name;
        FsTest::case('record ' . $name, function () use ($recording, $name) {
            ksort($recording);
            WP_CLI::log('--- BEGIN ' . $name . '.json ---');
            WP_CLI::log(wp_json_encode($recording, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            WP_CLI::log('--- END ' . $name . '.json ---');
            FsTest::skip('no contract file; printed ' . count($recording) . ' entries, save them as fixtures/' . $name . '.json');
        });
    }

    public static function paths($value, $prefix = '')
    {
        if (is_object($value)) {
            $value = json_decode(wp_json_encode($value), true);
        }
        if (!is_array($value) || $value === []) {
            return $prefix === '' ? [] : [$prefix];
        }

        $paths = [];
        $isList = array_keys($value) === range(0, count($value) - 1);
        foreach ($value as $key => $item) {
            $path = $isList ? $prefix . '[]' : ($prefix === '' ? (string) $key : $prefix . '.' . $key);
            $paths = array_merge($paths, self::paths($item, $path));
        }
        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * Drops a leaf that another path continues ("x" next to "x.id"): a key that is
     * null on one item and an object on another is one key.
     */
    public static function mergeNullLeaves(array $paths)
    {
        return array_values(array_filter($paths, function ($path) use ($paths) {
            foreach ($paths as $other) {
                if (strpos($other, $path . '.') === 0 || strpos($other, $path . '[]') === 0) {
                    return false;
                }
            }
            return true;
        }));
    }
}
