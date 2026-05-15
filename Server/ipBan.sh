#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# This script can be run on demand to quickly block an IP range that Fail2ban will alert on
# helpful if you can’t remember the ufw syntax
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

ip2ban="$1"
#TODO - do some validation on the input
ufw insert 1 deny from $ip2ban to any
