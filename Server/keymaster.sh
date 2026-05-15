#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# receives what should be a public key for addition to BlueSky
# checks it and then hands it off to gatekeeper by way of inoticoming
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

dataUp="$1"
tmpName=`uuidgen`

# decrypt. if admin fails, try client. if both fail, reject it.  Whichever one passes, note the type.
echo "$dataUp" | openssl smime -decrypt -inform PEM -inkey /usr/local/bin/BlueSkyConnect/Server/blueskyclient.key -out /tmp/$tmpName.pub
if [ $? -ne 0 ]; then
	echo "$dataUp" | openssl smime -decrypt -inform PEM -inkey /usr/local/bin/BlueSkyConnect/Server/blueskyadmin.key -out /tmp/$tmpName.pub
	if [ $? -ne 0 ]; then
		echo "Invalid"
		exit 0
	else
		targetLoc="admin"
	fi
else
	targetLoc="bluesky"
fi

pubKey=`cat /tmp/$tmpName.pub`

keyValid=`ssh-keygen -l -f /tmp/$tmpName.pub`
# keyValid contains the hash that will appear in auth.log
# 256 SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0 uploaded@1510761187 sysadmin@Sidekick.local (ECDSA)
fingerPrint=`echo "$keyValid" | awk '{ print $2 }' | cut -d : -f 2`
if [[ "$keyValid" == *"ED25519"* ]] || [[ "$keyValid" == *"RSA"* ]]; then
  mv /tmp/$tmpName.pub /home/$targetLoc/newkeys/$tmpName.pub
  echo "Installed"
  if [ "$targetLoc" == "admin" ] && [ -e /usr/local/bin/BlueSkyConnect/Server/emailHelper.sh ]; then
    #email the subscriber about it
    keyID=`echo "$pubKey" | awk '{ print $NF }'`
    /usr/local/bin/BlueSkyConnect/Server/emailHelper.sh "BlueSky Admin Key Registered" "A new admin key with identifier $keyID was registered in your server. If you did not expect this, please invoke Emergency Stop."
  fi
else
#  rm -f /tmp/$tmpName.pub
  echo "Invalid"
fi

exit 0