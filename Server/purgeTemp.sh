#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# find SSH keys from temp app and purge them if too old
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

tempList=`grep "tmp-" /home/bluesky/.ssh/authorized_keys | awk -F '-' '{ print $NF }'`
if [ "$tempList" != "" ]; then
myEpoch=`date +%s`
expirEpoch=`expr $myEpoch - 14400`
for thisLine in $tempList; do
  if [ $thisLine -lt $expirEpoch ]; then
    sed -i "/$thisLine/d" /home/bluesky/.ssh/authorized_keys
  fi
done
fi
exit 0