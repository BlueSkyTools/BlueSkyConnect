#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# This script ensures that all incoming SSH connections originated by the
# server are only allowed to read the expected serial number from the generated
# hash in settings.plist
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

if [[ "${SSH_ORIGINAL_COMMAND:=UNSET}" = "UNSET" ]]; then
	echo "shell access is not permitted for BlueSky"
	exit 127
fi

command="$SSH_ORIGINAL_COMMAND"

if [[ "$command" = "/usr/bin/defaults read /var/bluesky/settings serial" ]]; then
  /usr/bin/defaults read /var/bluesky/settings serial
elif [[ "$command" = "/usr/libexec/PlistBuddy -c 'Print serial' /var/bluesky/settings.plist" ]]; then
  /usr/libexec/PlistBuddy -c 'Print serial' /var/bluesky/settings.plist
else
  echo "invalid command"
  exit 127
fi
