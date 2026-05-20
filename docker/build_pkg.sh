#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# shellcheck source=sign-helpers.sh
source /usr/local/bin/sign-helpers.sh
signValidateOrDisable

IDENTIFIER="com.solarwindsmsp.bluesky.pkg"
APPNAME="BlueSky"

stagePayload() {
  rm -rf /tmp/pkg-payload/*
  rm -rf /tmp/pkg-payload/.* 2> /dev/null
  cp -RL /usr/local/bin/BlueSkyConnect/Client/* /tmp/pkg-payload/
  cp -R /usr/local/bin/BlueSkyConnect/Client/.ssh /tmp/pkg-payload/
}

mkdir -p /tmp/pkg
PKG_LOCATION="/tmp/pkg/${APPNAME}-${BLUESKY_VERSION}.pkg"
FINGERPRINT_FILE="${PKG_LOCATION}.fingerprint"
newFingerprint=$(computeBuildFingerprint "$APPNAME")

# Cache check: if a previously-built pkg exists with a matching fingerprint
# over all inputs that affect its bytes, reuse it. Skips ~5s of build plus
# any signing + ~30-90s of notary round-trip per restart when nothing has
# changed. To force a fresh build, remove ${PKG_LOCATION}.fingerprint.
if [[ -f "$PKG_LOCATION" && -f "$FINGERPRINT_FILE" && "$(cat "$FINGERPRINT_FILE")" == "$newFingerprint" ]]; then
  echo "Using cached ${PKG_LOCATION} (inputs unchanged since last build)"
else
  # cache miss — invalidate the notary sidecar and any prior fingerprint
  # so a partial failure can't leave a stale "cache is valid" signal.
  rm -f "${PKG_LOCATION}.notarized" "$FINGERPRINT_FILE"
  rm -rf /tmp/pkg/BlueSky-*.pkg
  mkdir /tmp/pkg-flat /tmp/pkg-payload /tmp/pkg-scripts 2> /dev/null
  rm -rf /tmp/pkg-flat/*
  rm -rf /tmp/pkg-scripts/*

  # stage payload, then attempt signing. If any Mach-O sign fails, restage
  # from source so the resulting pkg is consistently unsigned, then continue.
  stagePayload
  if ! signMachOPayload /tmp/pkg-payload; then
    echo "WARN: Mach-O signing failed; restaging client payload unsigned" >&2
    stagePayload
    # shellcheck disable=SC2034  # read by signEnabled() in sign-helpers.sh
    SIGN_PKG=0
  fi

  # Unsigned path (signing off or fell back): ad-hoc sign every bundled
  # Mach-O so arm64 slices launch on Apple Silicon. No-op when real signing
  # succeeded. Best-effort per file — never aborts the build.
  if ! signEnabled; then
    adhocSignMachOPayload /tmp/pkg-payload
  fi

  NUM_FILES=$(find /tmp/pkg-payload | wc -l)
  INSTALL_KB_SIZE=$(du -k -s /tmp/pkg-payload | awk '{print $1}')

  # write out the PackageInfo file to flat pkg location
  cat << EOF > /tmp/pkg-flat/PackageInfo
<?xml version="1.0" encoding="utf-8"?>
<pkg-info postinstall-action="none" format-version="2" identifier="${IDENTIFIER}" version="${BLUESKY_VERSION}" generator-version="InstallCmds-611 (16G1036)" install-location="/var/bluesky" auth="root">
    <payload numberOfFiles="${NUM_FILES}" installKBytes="${INSTALL_KB_SIZE}"/>
    <bundle-version/>
    <upgrade-bundle/>
    <update-bundle/>
    <atomic-update-bundle/>
    <strict-identifier/>
    <relocate/>
    <scripts>
        <preinstall file="./preinstall"/>
        <postinstall file="./postinstall"/>
    </scripts>
</pkg-info>
EOF

  # write out the Scripts
  cat << EOF > /tmp/pkg-scripts/preinstall
#!/bin/bash
mkdir -p /var/bluesky
exit 0
EOF

  cat << EOF > /tmp/pkg-scripts/postinstall
#!/bin/bash
/var/bluesky/helper.sh
exit 0
EOF

  # make sure they are executed
  chmod +x /tmp/pkg-scripts/*

  # compress the scripts
  ( cd /tmp/pkg-scripts && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Scripts
  # compress the payload
  ( cd /tmp/pkg-payload && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Payload
  # create Bom file
  ( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 . ) > /tmp/pkg/.bom
  ( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 .ssh | sed 's/^\./\.\/\.ssh/' ) >> /tmp/pkg/.bom
  mkbom -i /tmp/pkg/.bom /tmp/pkg-flat/Bom
  rm -f /tmp/pkg/.bom
  # pkg it up!!
  ( cd /tmp/pkg-flat && xar --compression none -cf "${PKG_LOCATION}" * )
  echo "osx package has been built: ${PKG_LOCATION}"

  # sign the assembled pkg. Keep an unsigned backup so we can roll back on
  # rcodesign failure (it modifies the pkg in place via a tmp-rename dance).
  if signEnabled; then
    cp "${PKG_LOCATION}" "${PKG_LOCATION}.bak"
    if signPkgFile "${PKG_LOCATION}"; then
      rm -f "${PKG_LOCATION}.bak"
    else
      echo "WARN: pkg signing failed; reverting to unsigned pkg" >&2
      mv -f "${PKG_LOCATION}.bak" "${PKG_LOCATION}"
      # shellcheck disable=SC2034  # read by signEnabled() in markPkgSignState below
      SIGN_PKG=0
    fi
  fi

  # record successful build so the next restart can hit cache
  echo "$newFingerprint" > "$FINGERPRINT_FILE"
fi

# Tell notarize-pkgs.sh whether this pkg is signed. If anything in the
# signing chain fell back to unsigned (validation, Mach-O sign, pkg sign),
# leave a sentinel so notarization is skipped instead of wasting an Apple
# round-trip on a pkg that will be rejected.
markPkgSignState "${PKG_LOCATION}"

# The web admin download link is published by client-config.sh after the
# full build + notarize sequence, so it never points at a pkg that is still
# mid-notarization.
