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

# macOS 11+ uses bootout/bootstrap; 10.x uses the legacy unload/load
osVersionMajor=$(sw_vers -productVersion | awk -F . '{ print $1 }')

sleep 3
if [ ${osVersionMajor:-10} -ge 11 ]; then
  launchctl bootout system /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist 2> /dev/null
  launchctl bootstrap system /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist
else
  launchctl unload /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist
  launchctl load -w /Library/LaunchDaemons/com.solarwindsmsp.bluesky.plist
fi

exit 0
