#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# This script ensures that all incoming SSH connections originated by the
# server are only allowed to read the expected serial number from the generated
# hash in settings.plist
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

if [ "${SSH_ORIGINAL_COMMAND:=UNSET}" == "UNSET" ]; then
	echo "shell access is not permitted for BlueSky"
	exit 127
fi

command="$SSH_ORIGINAL_COMMAND"; export command
testCmd[1]="/usr/bin/defaults read /var/bluesky/settings serial"
testCmd[2]="/usr/libexec/PlistBuddy -c 'Print serial' /var/bluesky/settings.plist"

for thisCmd in "${testCmd[@]}"; do
  if [ "$command" == "$thisCmd" ]; then
    matchCmd="true"
    break
  else
    matchCmd="false"
  fi
done

if [ "$matchCmd" == "true" ]; then
  eval $command
else
	echo "invalid command"
	exit 127
fi