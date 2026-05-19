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

# create folders to work in
mkdir -p /tmp/pkg
mkdir /tmp/pkg-flat 2>/dev/null
mkdir /tmp/pkg-payload 2>/dev/null
mkdir /tmp/pkg-scripts

# clean up old files
rm -rf /tmp/pkg-flat/*
rm -rf /tmp/pkg-scripts/*
rm -rf /tmp/pkg/BlueSky-*.pkg

# stage payload, then attempt signing. If any Mach-O sign fails, restage
# from source so the resulting pkg is consistently unsigned, then continue.
stagePayload
if ! signMachOPayload /tmp/pkg-payload; then
  echo "WARN: Mach-O signing failed; restaging client payload unsigned" >&2
  stagePayload
  # shellcheck disable=SC2034  # read by signEnabled() in sign-helpers.sh
  SIGN_PKG=0
fi

NUM_FILES=$(find /tmp/pkg-payload | wc -l)
INSTALL_KB_SIZE=$(du -k -s /tmp/pkg-payload | awk '{print $1}')

# write out the PackageInfo file to flat pkg location
cat <<EOF > /tmp/pkg-flat/PackageInfo
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
cat <<EOF > /tmp/pkg-scripts/preinstall
#!/bin/bash
mkdir -p /var/bluesky
exit 0
EOF

cat <<EOF > /tmp/pkg-scripts/postinstall
#!/bin/bash
/var/bluesky/helper.sh
exit 0
EOF

# make sure they are executed
chmod +x /tmp/pkg-scripts/*

PKG_LOCATION="/tmp/pkg/${APPNAME}-${BLUESKY_VERSION}.pkg"

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
  fi
fi

# Tell notarize-pkgs.sh whether this pkg is signed. If anything in the
# signing chain fell back to unsigned (validation, Mach-O sign, pkg sign),
# leave a sentinel so notarization is skipped instead of wasting an Apple
# round-trip on a pkg that will be rejected.
markPkgSignState "${PKG_LOCATION}"

RANDOM_DIR=`uuidgen`
mkdir /var/www/html/"${RANDOM_DIR}"
ln -s "${PKG_LOCATION}" /var/www/html/"${RANDOM_DIR}"/
if grep -q '<ul class="nav navbar-nav" id="agent">' /var/www/html/hooks/agent-links.php; then
    # Agent link exists, replace it
    sed -i "/<ul class=\"nav navbar-nav\" id=\"agent\">/,/<\/ul>/c\\
<ul class=\"nav navbar-nav\" id=\"agent\">\\
  <a href=\"${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg\" class=\"btn btn-default navbar-btn visible-sm visible-md visible-lg\"><i class=\"glyphicon glyphicon-download-alt\"></i>Download BlueSky Agent</a>\\
  <a href=\"${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg\" class=\"visible-xs btn btn-default navbar-btn btn-lg\"><i class=\"glyphicon glyphicon-download-alt\"></i>Download BlueSky Agent</a>\\
</ul>" /var/www/html/hooks/agent-links.php
else
    cat <<EOF >> /var/www/html/hooks/agent-links.php
<ul class="nav navbar-nav" id="agent">
  <a href="${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg" class="btn btn-default navbar-btn visible-sm visible-md visible-lg"><i class="glyphicon glyphicon-download-alt"></i>Download BlueSky Agent</a>
  <a href="${RANDOM_DIR}/${APPNAME}-${BLUESKY_VERSION}.pkg" class="visible-xs btn btn-default navbar-btn btn-lg"><i class="glyphicon glyphicon-download-alt"></i>Download BlueSky Agent</a>
</ul>
EOF
fi
