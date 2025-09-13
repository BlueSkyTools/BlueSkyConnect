#!/bin/bash

# c)2011-2014 Best Macs, Inc.
# c)2014-2015 Mac-MSP LLC
# Copyright 2016-2017 SolarWinds Worldwide, LLC
# Copyright 2025 Best Practices LLC

# Licensed under the Apache License, Version 2.0 (the "License");
#   you may not use this file except in compliance with the License.
#   You may obtain a copy of the License at

#       http://www.apache.org/licenses/LICENSE-2.0

#   Unless required by applicable law or agreed to in writing, software
#   distributed under the License is distributed on an "AS IS" BASIS,
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#   See the License for the specific language governing permissions and
#   limitations under the License.

# v 1.0.20250913

# sets up Windows client with necessary files and download zip

# get the server host FQDN
apacheConf="default-ssl"
if [[ ${USE_HTTP} ]]; then
	apacheConf="000-default"
fi
if [[ -z "${SERVERFQDN}" ]]; then
	hostName=`grep ServerName /etc/apache2/sites-enabled/"$apacheConf".conf | awk '{ print $NF }'`
	if [ "$hostName" == "" ]; then
		echo "Server FQDN is not readable from apache. Please double check your server setup."
		exit 2
	fi
else
	hostName=$SERVERFQDN
fi

# read the SSHd known_host ID
hostKey=`ssh-keyscan -t ed25519 localhost | awk '{ print $2,$3 }'`

# lookup the server's public IP (TODO - maybe a more efficient way to do this?)
ipAddress=`curl -s http://ipinfo.io/ip`

# create server.json
if [ "$hostKey" == "" ] || [ "$hostName" == "" ] || [ "$ipAddress" == "" ]; then
	echo "Can't read data needed to make server.json file. Check SSH and DNS setups"
	exit 2
fi
cat > /usr/local/bin/BlueSky/Windows/BlueConnect/server.json <<EOF
{
  "address": "$hostName",
  "serverkey": "[$hostName]:3122,[$ipAddress]:3122 $hostKey"
}
EOF


# copy the blueskyclient.pub file which I renamed while making Win 3.0 to better reflect what it does
if [ ! -e /usr/local/bin/BlueSky/Client/blueskyclient.pub ]; then
	echo "Can't find the signing public cert. Please run Mac client-config.sh first before setting up Windows."
	exit 2
fi
cp /usr/local/bin/BlueSky/Client/blueskyclient.pub /usr/local/bin/BlueSky/Windows/BlueConnect/blue_signing.pub 

# copy the blueskyd.pub
# small design difference: I have Windows make the authorized_keys file with the setup-blueconnect.ps1 script
# on the Mac it's built server side by client-config.sh and installed by pkg (that will probably change)
if [ ! -e /usr/local/bin/BlueSky/Server/blueskyd.pub ]; then
	echo "Can't find the server's public key. Please run Mac client-config.sh first before setting up Windows."
	exit 2
fi
cp /usr/local/bin/BlueSky/Server/blueskyd.pub /usr/local/bin/BlueSky/Windows/BlueConnect/blueskyd.pub 

# doing a little obfuscation here
# the only place this uuid will exist is in the name of the downloads and the setup script we generate
# this should make it very difficult for just anyone to go to your server and download your Windows zip
# but the setup script to deploy will have it (which is basically the same as someone having the Mac .pkg)
if [ ! -e /usr/local/bin/BlueSky/Windows/random.txt ]; then
	uuidgen > /usr/local/bin/BlueSky/Windows/random.txt
fi
randomHash=$(cat /usr/local/bin/BlueSky/Windows/random.txt)

# zip it up
zip -jr /var/www/html/BlueConnect-Win-$randomHash.zip /usr/local/bin/BlueSky/Windows/BlueConnect/*

# get md5 - the setup script uses this to validate the downloaded zip
md5sum /var/www/html/BlueConnect-Win-$randomHash.zip | awk '{print $1}' > /var/www/html/BlueConnect-Win-$randomHash.md5

# set perms so web server can push
chown www-data /var/www/html/BlueConnect-Win*

# setup script will only need modification once unless the server FQDN or UUID change
# it's ok for the sed commands to not find anything

# modify setup script with FQDN
sed -i "s/HOSTNAME/$hostName/g" /usr/local/bin/BlueSky/Windows/setup-blueconnect.ps1

# modify setup script with uuid
sed -i "s/RANDOM/$randomHash/g" /usr/local/bin/BlueSky/Windows/setup-blueconnect.ps1




