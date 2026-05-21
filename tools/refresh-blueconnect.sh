#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# Refresh the vendored BlueConnect-Admin server payload from upstream.
#
# Pulls the docroot PHP (the bs_*.json.php endpoints plus the shared bs_auth.php
# include) into Server/html/ and the schema migrations into
# Server/blueconnect/migrations/ at a pinned upstream ref. Everything is vendored
# pristine - the DB-backed auth we used to carry as a local patch is upstream as
# of v1.2.0 (enabled via WEBADMIN_AUTH=db in docker/run), so there is nothing to
# re-apply. Pass a different ref (tag, branch, or commit) as the first argument
# to bump it; the resolved commit sha is printed at the end for VENDOR.md.
#
# See https://github.com/echoparkbaby/BlueConnect-Admin
# Licensed under the Apache License, Version 2.0

set -euo pipefail

# pinned upstream ref - keep in sync with Server/blueconnect/VENDOR.md
DEFAULT_REF="v1.2.0"
REF="${1:-$DEFAULT_REF}"

REPO="echoparkbaby/BlueConnect-Admin"
RAW_BASE="https://raw.githubusercontent.com/$REPO/$REF/server"

# repo root is one level up from this script's tools/ directory
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTML_DIR="$REPO_ROOT/Server/html"
MIGRATIONS_DIR="$REPO_ROOT/Server/blueconnect/migrations"

# docroot PHP: the endpoints plus the shared bs_auth.php include they require.
# migrations stay outside the docroot so the .sql is not web-downloadable.
DOCROOT_FILES=(
  bs_auth.php
  bs_authkeys_audit.json.php
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

for file in "${DOCROOT_FILES[@]}"; do
  fetchFile "$RAW_BASE/$file" "$HTML_DIR/$file"
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
