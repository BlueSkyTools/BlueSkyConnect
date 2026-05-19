#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# Notarize the freshly built pkgs via Apple's notary web service using
# rcodesign + an App Store Connect API key. Invoked by client-config.sh
# after build_pkg.sh and build_admin_pkg.sh when SIGN_PKG=1 and
# SIGN_SKIP_NOTARIZE != 1.

if [[ "$SIGN_PKG" != "1" || "$SIGN_SKIP_NOTARIZE" == "1" ]]; then
  exit 0
fi

set -e

NOTARY_KEY_JSON="/tmp/notary-key.json"

cleanup() {
  if [[ -f "$NOTARY_KEY_JSON" ]]; then
    shred -u "$NOTARY_KEY_JSON" 2> /dev/null || rm -f "$NOTARY_KEY_JSON"
  fi
}
trap cleanup EXIT

rcodesign encode-app-store-connect-api-key \
  -o "$NOTARY_KEY_JSON" \
  "$NOTARY_API_ISSUER_ID" "$NOTARY_API_KEY_ID" "$NOTARY_API_KEY_P8"

for pkg in "/tmp/pkg/BlueSky-${BLUESKY_VERSION}.pkg" \
  "/tmp/pkg/BlueSkyAdmin-${BLUESKY_VERSION}.pkg"; do
  if [[ ! -f "$pkg" ]]; then
    echo "Skipping notarization of $pkg (not found)"
    continue
  fi
  echo "Notarizing + stapling: $pkg"
  rcodesign notary-submit \
    --api-key-path "$NOTARY_KEY_JSON" \
    --staple \
    "$pkg"
done
