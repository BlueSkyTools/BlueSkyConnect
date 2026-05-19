#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# shellcheck source=sign-helpers.sh
source /usr/local/bin/sign-helpers.sh
signValidateOrDisable

IDENTIFIER="com.solarwindsmsp.bluesky.admin.pkg"
APPNAME="BlueSkyAdmin"

stagePayload() {
  rm -rf /tmp/pkg-payload/*
  rm -rf /tmp/pkg-payload/.* 2> /dev/null

  cp -RL /usr/local/bin/BlueSkyConnect/Admin\ Tools/*.app /tmp/pkg-payload/
  cp -L /usr/local/bin/BlueSkyConnect/Admin\ Tools/server.txt /tmp/pkg-payload/
  cp -L /usr/local/bin/BlueSkyConnect/Admin\ Tools/blueskyadmin.pub /tmp/pkg-payload/

  cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Admin\ Setup.app/Contents/Resources/
  cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Admin.app/Contents/Resources/
  cp /tmp/pkg-payload/server.txt /tmp/pkg-payload/BlueSky\ Temporary\ Client.app/Contents/Resources/
  cp /tmp/pkg-payload/blueskyadmin.pub /tmp/pkg-payload/BlueSky\ Admin\ Setup.app/Contents/Resources/
  cp /tmp/pkg-payload/blueskyadmin.pub /tmp/pkg-payload/BlueSky\ Admin.app/Contents/Resources/
  cp -L /usr/local/bin/BlueSkyConnect/Client/blueskyclient.pub /tmp/pkg-payload/BlueSky\ Temporary\ Client.app/Contents/Resources/
  rm /tmp/pkg-payload/server.txt /tmp/pkg-payload/blueskyadmin.pub
}

# create folders to work in
mkdir -p /tmp/pkg
mkdir /tmp/pkg-flat 2> /dev/null
mkdir /tmp/pkg-payload 2> /dev/null

# clean up old files
rm -rf /tmp/pkg-flat/*
rm -rf /tmp/pkg/BlueSkyAdmin-*.pkg

# stage apps + inject resources, then attempt to sign each .app. If any
# sign fails, restage so the resulting pkg is consistently unsigned.
stagePayload
appSigningFailed=0
for app in /tmp/pkg-payload/*.app; do
  if ! signAppBundle "$app"; then
    appSigningFailed=1
    break
  fi
done
if [[ "$appSigningFailed" == "1" ]]; then
  echo "WARN: app bundle signing failed; restaging admin payload unsigned" >&2
  stagePayload
  # shellcheck disable=SC2034  # read by signEnabled() in sign-helpers.sh
  SIGN_PKG=0
fi

# get info about our payload
NUM_FILES=$(find /tmp/pkg-payload | wc -l)
INSTALL_KB_SIZE=$(du -k -s /tmp/pkg-payload | awk '{print $1}')

# write out the PackageInfo file to flat pkg location
cat <<EOF > /tmp/pkg-flat/PackageInfo
<?xml version="1.0" encoding="utf-8"?>
<pkg-info postinstall-action="none" format-version="2" identifier="${IDENTIFIER}" version="${BLUESKY_VERSION}" generator-version="InstallCmds-611 (16G1036)" install-location="/Applications/Utilities" auth="root">
    <payload numberOfFiles="${NUM_FILES}" installKBytes="${INSTALL_KB_SIZE}"/>
    <bundle-version/>
    <upgrade-bundle/>
    <update-bundle/>
    <atomic-update-bundle/>
    <strict-identifier/>
    <relocate/>
    <scripts/>
</pkg-info>
EOF

PKG_LOCATION="/tmp/pkg/${APPNAME}-${BLUESKY_VERSION}.pkg"

# compress the payload
( cd /tmp/pkg-payload && find . | cpio -o --format odc --owner 0:80 | gzip -c ) > /tmp/pkg-flat/Payload
# create Bom file
( cd /tmp/pkg-payload && ls4mkbom -u 0 -g 80 . ) > /tmp/pkg/.bom
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

# Tell notarize-pkgs.sh whether this pkg is signed. If anything in the
# signing chain fell back to unsigned (validation, app sign, pkg sign),
# leave a sentinel so notarization is skipped instead of wasting an Apple
# round-trip on a pkg that will be rejected.
markPkgSignState "${PKG_LOCATION}"

RANDOM_DIR=`uuidgen`
mkdir /var/www/html/"${RANDOM_DIR}"
ln -s "${PKG_LOCATION}" /var/www/html/"${RANDOM_DIR}"/
if grep -q '<ul class="nav navbar-nav" id="admin_agent">' /var/www/html/hooks/agent-links.php; then
    # Agent link exists, replace it
    sed -i "/<ul class=\"nav navbar-nav\" id=\"admin_agent\">/,/<\/ul>/c\\
<ul class=\"nav navbar-nav\" id=\"admin_agent\">\\
  <a href=\"${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg\" class=\"btn btn-default navbar-btn visible-sm visible-md visible-lg\"><i class=\"glyphicon glyphicon-download-alt\"></i>Download BlueSky Admin Tools</a>\\
  <a href=\"${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg\" class=\"visible-xs btn btn-default navbar-btn btn-lg\"><i class=\"glyphicon glyphicon-download-alt\"></i>Download BlueSky Admin Tools</a>\\
</ul>" /var/www/html/hooks/agent-links.php
else
    cat <<EOF >> /var/www/html/hooks/agent-links.php
<ul class="nav navbar-nav" id="admin_agent">
  <a href="${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg" class="btn btn-default navbar-btn visible-sm visible-md visible-lg"><i class="glyphicon glyphicon-download-alt"></i>Download BlueSky Admin Tools</a>
  <a href="${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg" class="visible-xs btn btn-default navbar-btn btn-lg"><i class="glyphicon glyphicon-download-alt"></i>Download BlueSky Admin Tools</a>
</ul>
EOF
fi
