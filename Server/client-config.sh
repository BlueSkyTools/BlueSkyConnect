#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# sets up client and admin keys as well as client’s server.plist and auth_key
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

# reads option to only do one set of keys or the other
reKey="$1"
mkdir -p /usr/local/bin/BlueSkyConnect/Client/.ssh 2> /dev/null
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

# do some extra checks to see if we are in docker and what files we have
if [[ ${IN_DOCKER} ]]; then
	# check whether we have host keys to reuse - otherwise generate them...
	if [[ -f /certs/ssh_host_ed25519_key && -f /certs/ssh_host_ed25519_key.pub && -f /certs/ssh_host_rsa_key && -f /certs/ssh_host_rsa_key.pub ]]; then
		# host keys exist
		echo "Re-using host keys..."
	else
		# host keys not provided - lets make 'em
		echo "Generating host keys..."
		ssh-keygen -q -t rsa -N '' -f /certs/ssh_host_rsa_key -C localhost
		ssh-keygen -q -t ed25519 -N '' -f /certs/ssh_host_ed25519_key -C localhost
	fi
	# link the host keys back
	ln -fs /certs/ssh_host_rsa_key* /etc/ssh/
	ln -fs /certs/ssh_host_ed25519_key* /etc/ssh/

	# start ssh as we will need it for ssh-keyscan
	/usr/sbin/sshd
fi

# update cacerts for our clients
echo "Updating cacert.pem..."
curl -o /usr/local/bin/BlueSkyConnect/Client/cacert.pem https://curl.se/ca/cacert.pem

# safety check if these files are there - ignore if in docker
if [ -e /usr/local/bin/BlueSkyConnect/Server/blueskyd ] && [ "$reKey" == "" ] && [[ -z ${IN_DOCKER} ]]; then
	echo "This server has already been configured.  Please use --client or --admin to re-key the client apps."
	echo "If you are trying to set up the server again, please delete /usr/local/bin/BlueSkyConnect/Server/blueskyd* and try again."
	exit 1
fi

if [ "$reKey" != "--admin" ]; then
	# make blueskyclient pair - used for encrypting uploaded SSH keys to the server for clients
	if [[ -z ${IN_DOCKER} ]]; then
		openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /usr/local/bin/BlueSkyConnect/Server/blueskyclient.key -out /usr/local/bin/BlueSkyConnect/Client/blueskyclient.pub -subj '/'
		chown www-data /usr/local/bin/BlueSkyConnect/Server/blueskyclient.key
	else
		# in docker: check to see if we are given existing key - create new one if not
		if [ ! -e /certs/blueskyclient.key ] || [ ! -e /certs/blueskyclient.pub ]; then
			echo "Creating blueskyclient key pair..."
			openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /certs/blueskyclient.key -out /certs/blueskyclient.pub -subj '/'
		fi
		# link keys to correct location
		ln -fs /certs/blueskyclient.key /usr/local/bin/BlueSkyConnect/Server/
		ln -fs /certs/blueskyclient.pub /usr/local/bin/BlueSkyConnect/Client/
	fi
fi

if [ "$reKey" != "--client" ]; then
	# make blueskyadmin pair - used for encrypting uploaded SSH keys to the server for admins
	if [[ -z ${IN_DOCKER} ]]; then
		openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /usr/local/bin/BlueSkyConnect/Server/blueskyadmin.key -out /usr/local/bin/BlueSkyConnect/Admin\ Tools/blueskyadmin.pub -subj '/'
		chown www-data /usr/local/bin/BlueSkyConnect/Server/blueskyadmin.key
	else
		# in docker: check to see if we are given existing key - create new one if not
		if [ ! -e /certs/blueskyadmin.key ] || [ ! -e /certs/blueskyadmin.pub ]; then
			echo "Creating blueskyadmin key pair..."
			openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /certs/blueskyadmin.key -out /certs/blueskyadmin.pub -subj '/'
		fi
		# link keys to correct location
		ln -fs /certs/blueskyadmin.key /usr/local/bin/BlueSkyConnect/Server/
		ln -fs /certs/blueskyadmin.pub /usr/local/bin/BlueSkyConnect/Admin\ Tools/
	fi
fi

# only do these if reKey is not set and the blueskyd file is not present
if [ "$reKey" == "" ]; then
	# make bluesky-server-check keys - used for allowing the server to SSH in and validate the tunnel
	# still using RSA here so we can shell into older Macs
	if [[ -z ${IN_DOCKER} ]]; then
		ssh-keygen -q -t rsa -N '' -f /usr/local/bin/BlueSkyConnect/Server/blueskyd -C "$hostName"
	else
		# in docker: check to see if we are given existing key - create new one if not
		if [ ! -e /certs/blueskyd ] || [ ! -e /certs/blueskyd.pub ]; then
			echo "Creating blueskyd key pair..."
			ssh-keygen -q -t rsa -N '' -f /certs/blueskyd -C "$hostName"
		fi
		# link keys to correct location
		ln -fs /certs/blueskyd.pub /usr/local/bin/BlueSkyConnect/Server/
		ln -fs /certs/blueskyd /usr/local/bin/BlueSkyConnect/Server/
	fi
	chown www-data /usr/local/bin/BlueSkyConnect/Server/blueskyd
	echo command=\"/var/bluesky/.ssh/wrapper.sh\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty `cat /usr/local/bin/BlueSkyConnect/Server/blueskyd.pub` > /usr/local/bin/BlueSkyConnect/Client/.ssh/authorized_keys

	# create server.plist
	hostKey=`ssh-keyscan -t ed25519 -p 3122 localhost | awk '{ print $2,$3 }'`
	hostKeyRSA=`ssh-keyscan -t rsa -p 3122 localhost | awk '{ print $2,$3 }'`
	ipAddress=`curl -s http://ipinfo.io/ip`
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
<dict>
	<key>address</key>
	<string>$hostName</string>
	<key>serverkey</key>
	<string>[$hostName]:3122,[$ipAddress]:3122 $hostKey</string>
	<key>serverkeyrsa</key>
	<string>[$hostName]:3122,[$ipAddress]:3122 $hostKeyRSA</string>
</dict>
</plist>" > /usr/local/bin/BlueSkyConnect/Client/server.plist
fi

if [[ ${IN_DOCKER} ]]; then
	# stop ssh - as we will be starting later
	/usr/bin/killall sshd

	# lets make an installer pkg!
	find /var/www/html/ -type d -regextype posix-extended -regex '.*/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' -prune -exec rm -rf {} \;
	/usr/local/bin/build_pkg.sh
	/usr/local/bin/build_admin_pkg.sh
fi
