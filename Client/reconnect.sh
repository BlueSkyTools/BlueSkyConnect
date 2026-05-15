#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# script that reloads BlueSky upon network event in hopes of faster reconnection
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

ourHome="/var/bluesky"

if [ -e "$ourHome/.debug" ]; then
  set -x
fi

function logMe {
  logMsg="$1"
  logFile="$ourHome/reconnect.txt"
  if [ ! -e "$logFile" ]; then
    touch "$logFile"
  fi
  dateStamp=`date '+%Y-%m-%d %H:%M:%S'`
  echo "$dateStamp - $logMsg" >> "$logFile"
  if [ -e "$ourHome/.debug" ]; then
    echo "$logMsg"
  fi
}

if [ "$1" == "wake" ]; then
  logMe "System wake detected, Reloading bluesky service..."
else
  logMe "Network state change detected, Reloading bluesky service..."
fi
sleep 3
launchctl unload /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist
launchctl load -w /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist

exit 0
