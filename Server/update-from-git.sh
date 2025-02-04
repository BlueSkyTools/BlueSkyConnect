#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# this will do a git pull and ensure files keep your configurations
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

## TODO - ensure this is run by root

## get variables
serverFQDN=`cat /usr/local/bin/BlueSkyConnect/Server/server.txt`
mysqlRootPass=`grep password /var/local/my.cnf | awk '{ print $NF }'`
## TODO test this
mysqlCollectorPass=`grep localhost /usr/lib/cgi-bin/collector.php | head -n 1 | awk '{ print $5 }' | tr -d ,\'`

## error for blank variables
if [ "$serverFQDN" == "" ]; then
	echo "This value cannot be empty. Please fix server.txt and try again."
	exit 2
fi
if [ "$mysqlRootPass" == "" ]; then
  echo "Something really borked the my.cnf file. May need to reset the mysql root password everywhere."
  exit 2
fi

# do the pull
cd /usr/local/bin/BlueSkyConnect
git fetch
git reset --hard origin/master

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"

## if git pull was ran ahead of this script, we lost collector password. need to reset
if [ "$mysqlCollectorPass" == "" ]; then
	echo "Collector creds got trashed. Will reset."
  mysqlCollectorPass=`openssl rand -base64 36`
  myQry="drop user 'collector'@'localhost';"
  $myCmd "$myQry"
  myQry="create user 'collector'@'localhost' identified by '$mysqlCollectorPass';"
  $myCmd "$myQry"
  myQry="grant select on BlueSky.computers to 'collector'@'localhost';"
  $myCmd "$myQry"
fi
sed -i "s/CHANGETHIS/$mysqlCollectorPass/g" /usr/lib/cgi-bin/collector.php

## double-check permissions on uploaded BlueSky files
chown -R root:root /usr/local/bin/BlueSkyConnect/Server
chmod 755 /usr/local/bin/BlueSkyConnect/Server
chown www-data /usr/local/bin/BlueSkyConnect/Server/keymaster.sh
chown www-data /usr/local/bin/BlueSkyConnect/Server/processor.sh
chmod 755 /usr/local/bin/BlueSkyConnect/Server/*.sh
chown -R www-data /usr/local/bin/BlueSkyConnect/Server/html
chown www-data /usr/local/bin/BlueSkyConnect/Server/collector.php
chmod 700 /usr/local/bin/BlueSkyConnect/Server/collector.php
chown www-data /usr/local/bin/BlueSkyConnect/Server/blueskyd

# sets auth.log so admin can read it
chgrp admin /var/log/auth.log

# sets my.cnf so admin can read it to populate connection log
chmod 640 /var/local/my.cnf
chown admin /var/local/my.cnf

## change the keys for 2.1
# this can be removed in future versions, it's only for trailblazers who took arrows
remakePlist=0
# fix the ciphers and MACs
cipherCheck=`grep arcfour /etc/ssh/sshd_config`
if [ "$cipherCheck" != "" ]; then
	sed -i '/Ciphers chacha20-poly1305@openssh.com,aes128-ctr,aes192-ctr,aes256-ctr,arcfour256,arcfour128,arcfour/d' /etc/ssh/sshd_config
	echo 'Ciphers chacha20-poly1305@openssh.com,aes256-ctr' >> /etc/ssh/sshd_config
fi
maCheck=`grep hmac-sha1 /etc/ssh/sshd_config`
if [ "$maCheck" != "" ]; then
	sed -i '/MACs hmac-sha2-512,hmac-sha1,hmac-ripemd160,hmac-sha2-512-etm@openssh.com/d' /etc/ssh/sshd_config
	echo 'MACs hmac-sha2-512-etm@openssh.com,hmac-ripemd160' >> /etc/ssh/sshd_config
fi
# put the ed25519 key back
edKeyPresent=`grep ssh_host_ed25519_key /etc/ssh/sshd_config`
if [ "$edKeyPresent" == "" ]; then
	# trade: ecdsa goes away in favor of ed25519
	sed -i 's/HostKey \/etc\/ssh\/ssh_host_ecdsa_key/HostKey \/etc\/ssh\/ssh_host_ed25519_key/g' /etc/ssh/sshd_config
	service ssh restart
	remakePlist=1
fi
# put the rsa key back
rsaKeyPresent=`grep ssh_host_rsa_key /etc/ssh/sshd_config`
if [ "$rsaKeyPresent" == "" ]; then
    hostLine=`grep -n 'HostKeys for protocol version 2' /etc/ssh/sshd_config | awk -F : '{ print $1 }'`
    if [ "$hostLine" != "" ]; then
        # put it back into sshd_config
        head -n $hostLine /etc/ssh/sshd_config > /tmp/sshd_config
        echo 'HostKey /etc/ssh/ssh_host_rsa_key' >> /tmp/sshd_config
        (( hostLine ++ ))
        tail -n +$hostLine /etc/ssh/sshd_config >> /tmp/sshd_config
        mv /tmp/sshd_config /etc/ssh/sshd_config
        service ssh restart
        remakePlist=1
    else
        echo "Something is really wrong with the sshd_config file"
        exit 2
    fi
fi
if [ $remakePlist -eq 1 ]; then
    # remake Client/server.plist
    hostKey=`ssh-keyscan -t ed25519 localhost | awk '{ print $2,$3 }'`
    hostKeyRSA=`ssh-keyscan -t rsa localhost | awk '{ print $2,$3 }'`
    ipAddress=`curl ipinfo.io | grep '"ip":' | awk '{ print $NF }' | tr -d \",`
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
<dict>
    <key>address</key>
    <string>$serverFQDN</string>
    <key>serverkey</key>
    <string>[$serverFQDN]:3122,[$ipAddress]:3122 $hostKey</string>
    <key>serverkeyrsa</key>
    <string>[$serverFQDN]:3122,[$ipAddress]:3122 $hostKeyRSA</string>
</dict>
</plist>" > /usr/local/bin/BlueSkyConnect/Client/server.plist
fi

## get emailAlertAddress from mysql
myQry="select defaultemail from global"
emailAlertAddress=`$myCmd "$myQry"`

## setup credentials in /usr/local/bin/BlueSkyConnect/Server/html/config.php
sed -i "s/MYSQLROOT/$mysqlRootPass/g" /usr/local/bin/BlueSkyConnect/Server/html/config.php

## fail2ban conf - not making these active but updating our copies
sed -i "s/SERVERFQDN/$serverFQDN/g" /usr/local/bin/BlueSkyConnect/Server/sendEmail-whois-lines.conf
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSkyConnect/Server/jail.local

## update emailHelper-dist.  You still need to enable it.
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSkyConnect/Server/emailHelper-dist.sh

## put server fqdn into client config.disabled for proxy routing
sed -i "s/SERVER/$serverFQDN/g" /usr/local/bin/BlueSkyConnect/Client/.ssh/config.disabled

## That's all folks!
echo "All set.  You're up to date!"
exit 0
