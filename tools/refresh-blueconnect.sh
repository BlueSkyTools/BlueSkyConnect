#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# Refresh the vendored BlueConnect-Admin server payload from upstream.
#
# Pulls the five bs_*.json.php endpoints into Server/html/ and the schema
# migration into Server/blueconnect/migrations/ at a pinned upstream ref.
# Pass a different ref (tag, branch, or commit) as the first argument to
# bump it; the resolved commit sha is printed at the end for VENDOR.md.
#
# See https://github.com/echoparkbaby/BlueConnect-Admin
# Licensed under the Apache License, Version 2.0

set -euo pipefail

# pinned upstream ref - keep in sync with Server/blueconnect/VENDOR.md
DEFAULT_REF="v1.1.3"
REF="${1:-$DEFAULT_REF}"

REPO="echoparkbaby/BlueConnect-Admin"
RAW_BASE="https://raw.githubusercontent.com/$REPO/$REF/server"

# repo root is one level up from this script's tools/ directory
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTML_DIR="$REPO_ROOT/Server/html"
MIGRATIONS_DIR="$REPO_ROOT/Server/blueconnect/migrations"

# endpoints land in the served docroot; migrations stay outside it
ENDPOINTS=(
  bs_categories.json.php
  bs_health.json.php
  bs_host_action.json.php
  bs_host_update.json.php
  bs_hosts.json.php
)
# schema migrations; docker/run applies whatever .sql lands here, in filename order
MIGRATIONS=(
  2026-05-03-categories-sort-order.sql
  2026-05-14-computers-blueconnect-columns.sql
)

fetchFile() {
  local src="$1" dest="$2"
  echo "  $src -> ${dest#"$REPO_ROOT"/}"
  curl -fsSL "$src" -o "$dest"
}

echo "Refreshing vendored BlueConnect-Admin payload from $REPO @ $REF..."

mkdir -p "$MIGRATIONS_DIR"

for endpoint in "${ENDPOINTS[@]}"; do
  fetchFile "$RAW_BASE/$endpoint" "$HTML_DIR/$endpoint"
done
for migration in "${MIGRATIONS[@]}"; do
  fetchFile "$RAW_BASE/migrations/$migration" "$MIGRATIONS_DIR/$migration"
done

# resolve the ref to an immutable commit sha for the provenance note. awk reads
# the whole response (no early exit) so curl never hits a closed pipe under
# pipefail; the first "sha" line is the commit.
RESOLVED="$(curl -fsSL "https://api.github.com/repos/$REPO/commits/$REF" \
  | awk -F '"' '/"sha"/ && !seen { print $4; seen = 1 }')"

echo "Done. Vendored from $REF (commit $RESOLVED)."
echo "Update Server/blueconnect/VENDOR.md with this ref/commit if it changed."
