#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# This wrapper script is called by requiring it in /home/bluesky/.ssh/authorized_keys
# It prevents BlueSky clients from shelling directly into the server or running commands on the server
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

echo "shell access is not permitted for BlueSky"
exit 127