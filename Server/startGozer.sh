#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# starts things off - this is called by cron at reboot to start inoticoming
# could be a place for other startup tasks too
# https://youtu.be/XfdiXBA7f6U - it's whatever it wants to be

inoticoming /home/bluesky/newkeys --suffix .pub /usr/local/bin/BlueSky/Server/gatekeeper.sh {} \;
inoticoming /home/admin/newkeys --suffix .pub /usr/local/bin/BlueSky/Server/gatekeeper.sh {} \;
