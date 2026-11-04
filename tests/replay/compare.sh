#!/usr/bin/env bash
#
# Compares every REST route's answer on a base branch (default: develop) with
# the current branch, in both plugins, from the same seeded database.
#
#   bash tests/replay/compare.sh [base-branch]
#   php tests/lab/tmp/replay/diff.php tests/lab/tmp/replay/old.jsonl tests/lab/tmp/replay/new.jsonl [i]
#
# Steps: back up the lab DB, seed one fixed data set (seed.php), snapshot it,
# replay every call (replay.php) on the base branch, restore the snapshot,
# replay on the current branch, restore the backup, print the diff summary.
# Both working trees must be clean: the script checks out the base branch and
# comes back. Files live in tests/lab/tmp/replay (untracked, inside the lab
# mount) so they survive the branch switch.

set -euo pipefail

base="${1:-develop}"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
pro="$root/../fluent-support-pro"
work="$root/tests/lab/tmp/replay"
inLab="wp-content/plugins/fluent-support/tests/lab/tmp/replay"

wp() { bash "$root/tests/bin/lab" wp "$@" 2>&1 | grep -v '^ *Container '; }

for repo in "$root" "$pro"; do
  if [ -n "$(git -C "$repo" status --porcelain --untracked-files=no)" ]; then
    echo "$repo has uncommitted changes" >&2; exit 1
  fi
done
headFree="$(git -C "$root" rev-parse --abbrev-ref HEAD)"
headPro="$(git -C "$pro" rev-parse --abbrev-ref HEAD)"

mkdir -p "$work"
cp "$root"/tests/replay/{seed.php,replay.php,diff.php} "$work/"
cp "$root/tests/smoke/routes.manifest.php" "$work/"

restore() {
  git -C "$root" checkout -q "$headFree"
  git -C "$pro" checkout -q "$headPro"
  wp db import /var/www/html/replay-preseed.sql >/dev/null || true
}
trap restore EXIT

# Replays to the end; a call that ends the process (wp_send_json) is recorded
# as DIED and the next run starts after it.
run() {
  local out="$1" start=0 log last
  : > "$out"
  for _ in $(seq 1 300); do
    log="$(wp eval-file "$inLab/replay.php" "start=$start" || true)"
    printf '%s\n' "$log" | grep '^REC ' >> "$out" || true
    if printf '%s\n' "$log" | grep -q '^DONE '; then return 0; fi
    last="$(printf '%s\n' "$log" | grep '^START ' | tail -1 | awk '{print $2}')"
    if [ -z "$last" ]; then printf '%s\n' "$log" | tail -20 >&2; return 1; fi
    if ! printf '%s\n' "$log" | grep -q "\"i\":$last,"; then
      echo "REC {\"i\":$last,\"status\":\"DIED\",\"body\":null}" >> "$out"
    fi
    start=$((last + 1))
  done
  return 1
}

wp db export /var/www/html/replay-preseed.sql >/dev/null
wp eval-file "$inLab/seed.php" >/dev/null
wp db export /var/www/html/replay-base.sql >/dev/null

git -C "$root" checkout -q "$base"
git -C "$pro" checkout -q "$base"
wp db import /var/www/html/replay-base.sql >/dev/null
run "$work/old.jsonl"

git -C "$root" checkout -q "$headFree"
git -C "$pro" checkout -q "$headPro"
wp db import /var/www/html/replay-base.sql >/dev/null
run "$work/new.jsonl"

php "$work/diff.php" "$work/old.jsonl" "$work/new.jsonl"
