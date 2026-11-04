<?php
// php diff.php old.jsonl new.jsonl [i] — summary, or the detail of call i.
$load = function ($f) {
    $out = [];
    $prev = null;
    foreach (file($f) as $l) {
        $r = json_decode(substr($l, 4), true);
        // What this call changed: counts as deltas, ticket rows that moved.
        $st = $r['state'] ?? null;
        if ($st && $prev) {
            $delta = [];
            foreach ($st['counts'] as $t => $c) {
                if ($c !== $prev['counts'][$t]) $delta['counts'][$t] = $c - $prev['counts'][$t];
            }
            foreach ($st['tickets'] as $k => $row) {
                foreach ((array) $row as $col => $v) {
                    if (($prev['tickets'][$k][$col] ?? null) !== $v) $delta['tickets'][$k][$col] = $v;
                }
            }
            $r['state'] = $delta;
        } else {
            $r['state'] = [];
        }
        if ($st) $prev = $st;
        $out[$r['i']] = $r;
    }
    return $out;
};
$flat = function ($v, $prefix = '') use (&$flat) {
    if (!is_array($v)) {
        return [$prefix => $v];
    }
    if (!$v) {
        return [$prefix => '[]'];
    }
    // A list of rows is keyed by row id, so order does not count.
    if (array_keys($v) === range(0, count($v) - 1) && count(array_filter($v, fn($r) => is_array($r) && isset($r['id']))) === count($v)) {
        $keyed = [];
        foreach ($v as $r) $keyed['#' . $r['id']] = $r;
        $v = $keyed;
    }
    $out = [];
    foreach ($v as $k => $x) {
        $out += $flat($x, $prefix === '' ? (string) $k : $prefix . '.' . $k);
    }
    return $out;
};
[$old, $new] = [$load($argv[1]), $load($argv[2])];
$only = $argv[3] ?? null;
$sum = ['same' => 0, 'status' => 0, 'body' => 0, 'state' => 0];
foreach ($old as $i => $o) {
    if ($only !== null && (string) $i !== $only) continue;
    $n = $new[$i] ?? null;
    $call = $o['call'] ?? ($n['call'] ?? '?');
    $fo = $flat($o['body'] ?? null); $fn = $flat($n['body'] ?? null);
    $removed = array_diff_key($fo, $fn); $added = array_diff_key($fn, $fo);
    $changed = [];
    foreach (array_intersect_key($fo, $fn) as $k => $v) {
        if ($v !== $fn[$k]) $changed[$k] = [$v, $fn[$k]];
    }
    $so = $flat($o['state'] ?? []); $sn = $flat($n['state'] ?? []);
    $stateDiff = [];
    foreach ($so + $sn as $k => $v) {
        if (($so[$k] ?? null) !== ($sn[$k] ?? null)) $stateDiff[$k] = [$so[$k] ?? null, $sn[$k] ?? null];
    }
    $kind = $o['status'] !== $n['status'] ? 'status' : (($removed || $added || $changed) ? 'body' : ($stateDiff ? 'state' : 'same'));
    $sum[$kind]++;
    if ($only === null) {
        if ($kind !== 'same') printf("%3d %-6s %s  %s -> %s  (-%d +%d ~%d, state %d)\n", $i, $kind, $call, json_encode($o['status']), json_encode($n['status']), count($removed), count($added), count($changed), count($stateDiff));
        continue;
    }
    echo "$call\nstatus: " . json_encode($o['status']) . ' -> ' . json_encode($n['status']) . "\nparams: " . json_encode($o['params'] ?? null) . "\n";
    foreach ($removed as $k => $v) echo "  - $k = " . substr(json_encode($v), 0, 160) . "\n";
    foreach ($added as $k => $v) echo "  + $k = " . substr(json_encode($v), 0, 160) . "\n";
    foreach ($changed as $k => [$a, $b]) echo "  ~ $k: " . substr(json_encode($a), 0, 120) . ' -> ' . substr(json_encode($b), 0, 120) . "\n";
    foreach ($stateDiff as $k => [$a, $b]) echo "  state $k: " . json_encode($a) . ' -> ' . json_encode($b) . "\n";
}
if ($only === null) print_r($sum);
