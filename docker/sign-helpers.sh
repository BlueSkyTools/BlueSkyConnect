#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# Helpers for optional code signing + notarization at pkg build time.
# Sourced by build_pkg.sh and build_admin_pkg.sh. All functions are no-ops
# unless SIGN_PKG=1.
#
# Fail-soft model: validation problems or rcodesign errors do NOT abort the
# build. They log a warning, set SIGN_PKG=0 in the current process so the
# rest of this build runs unsigned, and the build script restages any
# partially-signed payload from source so the resulting pkg is consistent.

ENTITLEMENTS_PATH="/tmp/Entitlements.plist"

signEnabled() {
  [[ "$SIGN_PKG" == "1" ]]
}

signValidateOrDisable() {
  if ! signEnabled; then
    return 0
  fi

  local missing=0
  local var
  for var in DEVID_APP_P12 DEVID_APP_P12_PASSWORD \
    DEVID_INSTALLER_P12 DEVID_INSTALLER_P12_PASSWORD; do
    if [[ -z "${!var}" ]]; then
      echo "WARN: SIGN_PKG=1 but $var is not set" >&2
      missing=1
    fi
  done

  if [[ "$SIGN_SKIP_NOTARIZE" != "1" ]]; then
    for var in NOTARY_API_KEY_P8 NOTARY_API_KEY_ID NOTARY_API_ISSUER_ID; do
      if [[ -z "${!var}" ]]; then
        echo "WARN: SIGN_PKG=1 but $var is not set (notarization requires it; set SIGN_SKIP_NOTARIZE=1 to skip)" >&2
        missing=1
      fi
    done
  fi

  for var in DEVID_APP_P12 DEVID_INSTALLER_P12 NOTARY_API_KEY_P8; do
    if [[ -n "${!var}" && ! -f "${!var}" ]]; then
      echo "WARN: $var points to '${!var}' but no such file exists in the container" >&2
      missing=1
    fi
  done

  if [[ "$missing" -ne 0 ]]; then
    echo "WARN: signing prerequisites missing; pkg will be built unsigned" >&2
    SIGN_PKG=0
    return 0
  fi

  cat > "$ENTITLEMENTS_PATH" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>com.apple.security.automation.apple-events</key>
	<true/>
	<key>com.apple.security.cs.disable-library-validation</key>
	<true/>
</dict>
</plist>
EOF
}

signAppBundle() {
  signEnabled || return 0
  local app="$1"
  echo "Signing app bundle: $app"
  if ! rcodesign sign \
    --p12-file "$DEVID_APP_P12" \
    --p12-password "$DEVID_APP_P12_PASSWORD" \
    --code-signature-flags runtime \
    --entitlements-xml-path "$ENTITLEMENTS_PATH" \
    --digest sha256 \
    --for-notarization \
    "$app"; then
    echo "WARN: rcodesign failed to sign $app" >&2
    return 1
  fi
}

isMachO() {
  # Mach-O magic bytes:
  #   feedface / feedfacf — thin 32/64 BE
  #   cefaedfe / cffaedfe — thin 32/64 LE
  #   cafebabe / bebafeca — fat
  #   cafebabf / bfbafeca — fat 64
  # cafebabe also matches Java .class, but no .class files live in the payload.
  local magic
  magic=$(head -c 4 "$1" 2> /dev/null | od -An -tx1 | tr -d ' \n')
  case "$magic" in
    feedface | feedfacf | cefaedfe | cffaedfe | cafebabe | bebafeca | cafebabf | bfbafeca) return 0 ;;
    *) return 1 ;;
  esac
}

signMachOPayload() {
  signEnabled || return 0
  local payloadDir="$1"
  local f
  while IFS= read -r -d '' f; do
    if isMachO "$f"; then
      echo "Signing Mach-O: $f"
      if ! rcodesign sign \
        --p12-file "$DEVID_APP_P12" \
        --p12-password "$DEVID_APP_P12_PASSWORD" \
        --code-signature-flags runtime \
        --digest sha256 \
        --for-notarization \
        "$f"; then
        echo "WARN: rcodesign failed to sign $f" >&2
        return 1
      fi
    fi
  done < <(find "$payloadDir" -type f -print0)
}

signPkgFile() {
  signEnabled || return 0
  local pkg="$1"
  echo "Signing pkg: $pkg"
  if ! rcodesign sign \
    --p12-file "$DEVID_INSTALLER_P12" \
    --p12-password "$DEVID_INSTALLER_P12_PASSWORD" \
    "$pkg"; then
    echo "WARN: rcodesign failed to sign $pkg" >&2
    return 1
  fi
}
