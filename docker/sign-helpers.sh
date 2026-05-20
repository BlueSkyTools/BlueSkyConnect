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
    if [[ -n "${!var}" && ! -f "/signing/${!var}" ]]; then
      echo "WARN: $var='${!var}' but /signing/${!var} does not exist in the container" >&2
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
    --p12-file "/signing/$DEVID_APP_P12" \
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
        --p12-file "/signing/$DEVID_APP_P12" \
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
    --p12-file "/signing/$DEVID_INSTALLER_P12" \
    --p12-password "$DEVID_INSTALLER_P12_PASSWORD" \
    "$pkg"; then
    echo "WARN: rcodesign failed to sign $pkg" >&2
    return 1
  fi
}

# Ad-hoc signing for the unsigned distribution path. Apple Silicon's kernel
# refuses to exec an arm64 Mach-O that carries no signature at all, so a
# wholly-unsigned universal .app dies at launch with a vague "can't be
# opened" error. An ad-hoc signature (rcodesign sign with no key) carries no
# identity and needs no cert or notarization, but satisfies that kernel
# requirement — restoring the old "unsigned just works" behavior on M-series
# Macs. Used only when real Developer ID signing is off or fell back.
adhocSignAppBundle() {
  local app="$1"
  echo "Ad-hoc signing app bundle: $app"
  if ! rcodesign sign "$app"; then
    echo "WARN: rcodesign failed to ad-hoc sign $app" >&2
    return 1
  fi
}

adhocSignMachOPayload() {
  local payloadDir="$1"
  local f
  while IFS= read -r -d '' f; do
    if isMachO "$f"; then
      echo "Ad-hoc signing Mach-O: $f"
      if ! rcodesign sign "$f"; then
        echo "WARN: rcodesign failed to ad-hoc sign $f" >&2
      fi
    fi
  done < <(find "$payloadDir" -type f -print0)
}

# Sentinel path used to communicate sign-state from build_*.sh to
# notarize-pkgs.sh across process boundaries (SIGN_PKG=0 set inside a
# build script is not visible to the later notarize-pkgs.sh invocation).
pkgSentinelPath() {
  echo "/tmp/.unsigned-$(basename "$1")"
}

markPkgSignState() {
  local pkg="$1"
  local sentinel
  sentinel=$(pkgSentinelPath "$pkg")
  if signEnabled; then
    rm -f "$sentinel"
  else
    touch "$sentinel"
  fi
}

# Compute a stable hash over everything that affects the bytes of a pkg, so
# build_*.sh can skip the rebuild + sign + notarize pipeline on container
# restart when nothing has changed. The hash is persisted next to the pkg
# as ${pkg}.fingerprint; matching it means the cached pkg is reusable.
#
# The build scripts + rcodesign are hashed too: a new image can reuse a
# bind-mounted /tmp/pkg from an older image (the volume survives image
# upgrades), so a change to build logic or the signing tool must invalidate
# the cache even when the version and source are unchanged.
#
# SIGN_SKIP_NOTARIZE is included because it changes the output bytes — a
# notarized pkg carries an embedded stapled ticket, a skipped one does not.
#
# NOTARY_API_KEY_P8 is intentionally not included — it's just submission
# auth, not material that affects the pkg or its stapled ticket.
computeBuildFingerprint() {
  local pkgname="$1" # "BlueSky" or "BlueSkyAdmin"
  local sourceRoot="/usr/local/bin/BlueSkyConnect"
  {
    echo "BLUESKY_VERSION=${BLUESKY_VERSION}"
    echo "SIGN_PKG=${SIGN_PKG:-0}"
    echo "SIGN_SKIP_NOTARIZE=${SIGN_SKIP_NOTARIZE:-0}"
    # build environment shared by both pkgs (rcodesign drives ad-hoc signing
    # on the unsigned path too, so it matters regardless of SIGN_PKG)
    sha256sum /usr/local/bin/sign-helpers.sh \
      /usr/local/bin/notarize-pkgs.sh \
      /usr/local/bin/rcodesign 2> /dev/null
    if [[ "$pkgname" == "BlueSky" ]]; then
      sha256sum /usr/local/bin/build_pkg.sh 2> /dev/null
      ( cd "$sourceRoot/Client" && find . -type f -print0 | sort -z | xargs -0 sha256sum )
    elif [[ "$pkgname" == "BlueSkyAdmin" ]]; then
      sha256sum /usr/local/bin/build_admin_pkg.sh 2> /dev/null
      ( cd "$sourceRoot/Admin Tools" && find . -type f -print0 | sort -z | xargs -0 sha256sum )
      sha256sum "$sourceRoot/Client/blueskyclient.pub"
    fi
    if [[ "$SIGN_PKG" == "1" ]]; then
      sha256sum "/signing/$DEVID_APP_P12" "/signing/$DEVID_INSTALLER_P12"
    fi
  } | sha256sum | awk '{print $1}'
}
