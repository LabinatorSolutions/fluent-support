#!/usr/bin/env bash
#
# Version mismatch check: one plugin on an older ref, the other on this branch.
# Tickets and email piping must keep working (reports and connectors may not).
#
#   bash tests/bin/compat.sh free develop   # new Pro on old free
#   bash tests/bin/compat.sh pro develop    # old Pro on new free
#
# Both working trees must be clean; the switched repo goes back to its branch.

set -euo pipefail

which="${1:?free or pro}"
base="${2:-develop}"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
repo="$root"
[ "$which" = "pro" ] && repo="$root/../fluent-support-pro"

for r in "$root" "$root/../fluent-support-pro"; do
  if [ -n "$(git -C "$r" status --porcelain --untracked-files=no)" ]; then
    echo "$r has uncommitted changes" >&2; exit 1
  fi
done

head="$(git -C "$repo" rev-parse --abbrev-ref HEAD)"
trap 'git -C "$repo" checkout -q "$head"' EXIT
git -C "$repo" checkout -q --detach "$base"

# Suites present on both sides: ticket lifecycle, list filters, piping.
bash "$root/tests/bin/lab" wp eval-file wp-content/plugins/fluent-support/tests/bin/run-integration.php \
  filter=10-ticket-lifecycle.php,20-ticket-filters.php,94-pro-email-piping-kses-order.php,95-pro-email-auto-mail.php,96-pro-email-threading-headers.php
