#!/usr/bin/env bash
set -euo pipefail

: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
: "${GITHUB_REPOSITORY_OWNER:?GITHUB_REPOSITORY_OWNER is required}"
: "${GH_TOKEN:?GH_TOKEN is required}"

PACKAGE_TYPE="${PACKAGE_TYPE:-container}"
PACKAGE_OWNER="${PACKAGE_OWNER:-$GITHUB_REPOSITORY_OWNER}"
PACKAGE_NAME="${PACKAGE_NAME:-${GITHUB_REPOSITORY#*/}}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DRY_RUN="${DRY_RUN:-true}"

PACKAGE_OWNER_LC="$(printf '%s' "$PACKAGE_OWNER" | tr '[:upper:]' '[:lower:]')"
PACKAGE_NAME_LC="$(printf '%s' "$PACKAGE_NAME" | tr '[:upper:]' '[:lower:]')"
PACKAGE_PATH="${PACKAGE_OWNER_LC}/${PACKAGE_NAME_LC}"

for command in gh jq curl date sort grep mktemp; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "ERROR: required command '$command' is not available" >&2
    exit 1
  fi
done

if ! [[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]]; then
  echo "ERROR: RETENTION_DAYS must be a non-negative integer" >&2
  exit 1
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

versions_file="$tmp_dir/versions.tsv"
protected_file="$tmp_dir/protected.txt"
protected_sorted_file="$tmp_dir/protected-sorted.txt"
touch "$protected_file"

date_days_ago_epoch() {
  local days="$1"

  if date -u -d "${days} days ago" +%s >/dev/null 2>&1; then
    date -u -d "${days} days ago" +%s
  else
    date -u -v-"${days}"d +%s
  fi
}

date_iso_epoch() {
  local timestamp="$1"

  if date -u -d "$timestamp" +%s >/dev/null 2>&1; then
    date -u -d "$timestamp" +%s
  else
    date -j -u -f '%Y-%m-%dT%H:%M:%SZ' "$timestamp" +%s
  fi
}

add_protected_digest() {
  local digest="$1"

  if [ -z "$digest" ] || [ "$digest" = "null" ]; then
    return
  fi

  printf '%s\n' "$digest" >> "$protected_file"
  case "$digest" in
    sha256:*) printf '%s\n' "${digest#sha256:}" >> "$protected_file" ;;
    *) printf 'sha256:%s\n' "$digest" >> "$protected_file" ;;
  esac
}

is_protected_digest() {
  local digest="$1"

  grep -Fxq "$digest" "$protected_sorted_file" && return 0
  case "$digest" in
    sha256:*) grep -Fxq "${digest#sha256:}" "$protected_sorted_file" ;;
    *) grep -Fxq "sha256:${digest}" "$protected_sorted_file" ;;
  esac
}

echo "Listing GHCR package versions for ${PACKAGE_OWNER}/${PACKAGE_NAME}..."
gh api --paginate \
  "/orgs/${PACKAGE_OWNER}/packages/${PACKAGE_TYPE}/${PACKAGE_NAME_LC}/versions?per_page=100" \
  --jq '.[] | {id, name, tags: (.metadata.container.tags // []), created_at, updated_at} | @base64' \
  > "$versions_file"

registry_token="$(
  curl -fsSL "https://ghcr.io/token?scope=repository:${PACKAGE_PATH}:pull" |
    jq -r '.token // empty'
)"

if [ -z "$registry_token" ]; then
  echo "ERROR: could not obtain a GHCR pull token for ${PACKAGE_PATH}" >&2
  exit 1
fi

fetch_and_protect_tag_manifest() {
  local tag="$1"
  local headers_file="$tmp_dir/manifest-${tag//[^A-Za-z0-9_.-]/_}.headers"
  local body_file="$tmp_dir/manifest-${tag//[^A-Za-z0-9_.-]/_}.json"
  local status

  status="$(
    curl --retry 3 --retry-all-errors --max-time 30 -sS \
      -D "$headers_file" \
      -o "$body_file" \
      -w '%{http_code}' \
      -H "Authorization: Bearer ${registry_token}" \
      -H 'Accept: application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.v2+json' \
      "https://ghcr.io/v2/${PACKAGE_PATH}/manifests/${tag}" || true
  )"

  if [ "$status" != "200" ]; then
    echo "WARN: could not inspect tag '${tag}' from GHCR; status=${status}. Its package version remains protected by name only." >&2
    return
  fi

  awk 'BEGIN { IGNORECASE = 1 } /^docker-content-digest:/ { gsub("\r", "", $2); print $2 }' "$headers_file" |
    while IFS= read -r digest; do
      add_protected_digest "$digest"
    done

  jq -r '.manifests[]?.digest // empty' "$body_file" |
    while IFS= read -r digest; do
      add_protected_digest "$digest"
    done
}

echo "Protecting tagged versions and child manifests referenced by tagged indexes..."
while IFS= read -r version; do
  name="$(printf '%s' "$version" | base64 --decode | jq -r '.name')"
  tag_count="$(printf '%s' "$version" | base64 --decode | jq -r '.tags | length')"

  if [ "$tag_count" = "0" ]; then
    continue
  fi

  add_protected_digest "$name"

  while IFS= read -r tag; do
    fetch_and_protect_tag_manifest "$tag"
  done < <(printf '%s' "$version" | base64 --decode | jq -r '.tags[]')
done < "$versions_file"

sort -u "$protected_file" > "$protected_sorted_file"

cutoff_epoch="$(date_days_ago_epoch "$RETENTION_DAYS")"
deleted_count=0
skipped_count=0

echo "Deleting untagged versions older than ${RETENTION_DAYS} days; dry_run=${DRY_RUN}..."
while IFS= read -r version; do
  id="$(printf '%s' "$version" | base64 --decode | jq -r '.id')"
  name="$(printf '%s' "$version" | base64 --decode | jq -r '.name')"
  tag_count="$(printf '%s' "$version" | base64 --decode | jq -r '.tags | length')"
  created_at="$(printf '%s' "$version" | base64 --decode | jq -r '.created_at')"
  updated_at="$(printf '%s' "$version" | base64 --decode | jq -r '.updated_at')"

  if [ "$tag_count" != "0" ]; then
    skipped_count=$((skipped_count + 1))
    continue
  fi

  if is_protected_digest "$name"; then
    echo "SKIP protected untagged version id=${id} name=${name}"
    skipped_count=$((skipped_count + 1))
    continue
  fi

  timestamp="${updated_at:-$created_at}"
  version_epoch="$(date_iso_epoch "$timestamp")"
  if [ "$version_epoch" -ge "$cutoff_epoch" ]; then
    echo "SKIP recent untagged version id=${id} name=${name} updated=${timestamp}"
    skipped_count=$((skipped_count + 1))
    continue
  fi

  if [ "$DRY_RUN" = "true" ]; then
    echo "DRY-RUN delete untagged version id=${id} name=${name} updated=${timestamp}"
  else
    echo "DELETE untagged version id=${id} name=${name} updated=${timestamp}"
    gh api \
      -X DELETE \
      "/orgs/${PACKAGE_OWNER}/packages/${PACKAGE_TYPE}/${PACKAGE_NAME_LC}/versions/${id}" \
      >/dev/null
  fi

  deleted_count=$((deleted_count + 1))
done < "$versions_file"

echo "Cleanup complete: delete_candidates=${deleted_count}, skipped=${skipped_count}"
