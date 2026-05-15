#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# called by inoticoming when a good public key is copied to /home/*/newkeys by the keymaster
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

tmpFile="$1"

fileLoc=`ls /home/admin/newkeys/$1 2>/dev/null`

if [ "$fileLoc" != "" ]; then
	targetLoc="admin"
	prefixCode="command=\"/usr/local/bin/BlueSkyConnect/Server/$targetLoc-wrapper.sh\""
else
	targetLoc="bluesky"
	prefixCode="command=\"/usr/local/bin/BlueSkyConnect/Server/$targetLoc-wrapper.sh\",no-X11-forwarding,no-agent-forwarding,no-pty"
fi

pubKey=`cat "/home/$targetLoc/newkeys/$tmpFile"`
serialNum=`echo "$pubKey" | awk '{ print $NF }'`
# 256 SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0 uploaded@1510761187 sysadmin@Sidekick.local (ECDSA)
fingerPrint=`ssh-keygen -l -f /home/$targetLoc/newkeys/$tmpFile | awk '{ print $2 }' | cut -d : -f 2`

#remove previous keys with same serial
if [ "$serialNum" != "" ]; then
	sed -i "/$serialNum/d" /home/$targetLoc/.ssh/authorized_keys
fi
# install it
echo "$prefixCode $pubKey" >> /home/$targetLoc/.ssh/authorized_keys

rm -f "/home/$targetLoc/newkeys/$tmpFile"

# add to admin keys table
if [ "$targetLoc" == "admin" ]; then
	adminKeys=`cat /home/admin/.ssh/authorized_keys | awk '{ print $NF }'`
	myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"
	myQry="update global set adminkeys='$adminKeys'"
	$myCmd "$myQry"
fi


exit 0