#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# sets up a base install of Ubuntu Server with configuration needed for BlueSky
# DO NOT RUN this on migrated BlueSky 1.x servers
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

## get variables
# you can fill these in here or the script will ask for them
serverFQDN=""
webAdminPassword=""
mysqlRootPass=""
emailAlertAddress=""
# --------- DO NOT EDIT BELOW ------------------------------------------------------
if [[ -z ${USE_HTTP} ]]; then
  USE_HTTP=0
fi

apacheConf="default-ssl"
if [[ ${IN_DOCKER} ]]; then
  serverFQDN=$SERVERFQDN
  webAdminPassword=$WEBADMINPASS
  emailAlertAddress=$EMAILALERT

  # take provided mysql root password if we have been provided it.  if not, use the password from the linked container or set to admin
  if [[ ${MYSQLROOTPASS} ]]; then
    mysqlRootPass=$MYSQLROOTPASS
    echo "Setting mysqlRootPass to what was provided"
  else
    # lets check for the linked containers password
    if [[ ${DB_ENV_MYSQL_ROOT_PASSWORD} ]]; then
      mysqlRootPass=$DB_ENV_MYSQL_ROOT_PASSWORD
      echo "Setting mysqlRootPass to that of the linked container"
    else
      mysqlRootPass="admin"
      echo "Setting mysqlRootPass to default"
    fi
  fi

  if [[ ${TIMEZONE} ]]; then
    # set timezone
    rm /etc/localtime
    echo ${TIMEZONE} > /etc/timezone
    dpkg-reconfigure -f noninteractive tzdata
  fi
fi
if [ "$USE_HTTP" -eq "1" ]; then
  apacheConf="000-default"
fi

## ask for blank variables

if [ "$serverFQDN" == "" ]; then
  echo "Please enter a fully qualified domain name for this server."
  read serverFQDN
  if [ "$serverFQDN" == "" ]; then
    echo "This value cannot be empty. Please try again."
    exit 2
  fi
fi
if [ "$webAdminPassword" == "" ]; then
  echo "Please enter a password for logging into the web admin."
  read webAdminPassword
  if [ "$webAdminPassword" == "" ]; then
    echo "This value cannot be empty. Please try again."
    exit 2
  fi
  echo "Please enter the password again."
  read webPassConf
  if [ "$webAdminPassword" != "$webPassConf" ]; then
    echo "Sorry the passwords don't match. Please try again."
    exit 2
  fi
fi
if [ "$emailAlertAddress" == "" ]; then
  echo "Please enter an email address where you will receive alerts."
  read emailAlertAddress
  if [ "$emailAlertAddress" == "" ]; then
    echo "This value cannot be empty. Please try again."
    exit 2
  fi
fi
if [ "$mysqlRootPass" == "" ]; then
  echo "Please enter a root password for MySQL. Leave it blank and we'll generate one."
  read mysqlRootPass
  if [ "$mysqlRootPass" == "" ]; then
    mysqlRootPass=`openssl rand -base64 36`
  fi
fi

## variables no one will care about
mysqlCollectorPass=`openssl rand -base64 36`

## double-check permissions on uploaded BlueSky files
chown -R root:root /usr/local/bin/BlueSkyConnect/Server
chmod 755 /usr/local/bin/BlueSkyConnect/Server
chown www-data /usr/local/bin/BlueSkyConnect/Server/keymaster.sh
chown www-data /usr/local/bin/BlueSkyConnect/Server/processor.sh
chmod 755 /usr/local/bin/BlueSkyConnect/Server/*.sh

## write server FQDN to a file for easy reference in case hostname changes
echo "$serverFQDN" > /usr/local/bin/BlueSkyConnect/Server/server.txt
echo "$serverFQDN" > /usr/local/bin/BlueSkyConnect/Admin\ Tools/server.txt

## reconfigure sshd_config to meet our specifications
echo 'Ciphers chacha20-poly1305@openssh.com,aes256-ctr' >> /etc/ssh/sshd_config
echo 'MACs hmac-sha2-512-etm@openssh.com' >> /etc/ssh/sshd_config
sed -i '/HostKey \/etc\/ssh\/ssh_host_dsa_key/d' /etc/ssh/sshd_config
sed -i '/HostKey \/etc\/ssh\/ssh_host_ecdsa_key/d' /etc/ssh/sshd_config
sed -i 's/#Port 22/Port 3122/g' /etc/ssh/sshd_config
service sshd restart
if [[ ${IN_DOCKER} ]]; then
  # disable password authentication for ssh in docker
  echo 'PasswordAuthentication no' >> /etc/ssh/sshd_config
  echo 'ChallengeResponseAuthentication no' >> /etc/ssh/sshd_config
fi
# set shorter tunnel timeouts
echo 'ClientAliveInterval 10' >> /etc/ssh/sshd_config
echo 'ClientAliveCountMax 3' >> /etc/ssh/sshd_config

## setup local firewall
if [[ -z ${IN_DOCKER} ]]; then
  ufw allow 3122
  ufw enable
  ufw allow 80
  ufw allow 443
fi

## install software
if [[ -z ${IN_DOCKER} ]]; then
  apt-get update
  sudo debconf-set-selections <<< "mysql-server mysql-server/root_password password $mysqlRootPass"
  sudo debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $mysqlRootPass"
  apt-get -y install apache2 fail2ban mysql-server php-mysql php libapache2-mod-php php-mysql inoticoming swaks curl
fi

## setup user accounts/folders
groupadd admin 2> /dev/null # will already be there on DO
useradd -m -g admin admin
useradd -m bluesky
passwd -d admin
passwd -d bluesky
usermod -s /bin/bash admin
usermod -s /bin/bash bluesky
mkdir -p /home/admin/.ssh
mkdir -p /home/bluesky/.ssh
mkdir -p /home/admin/newkeys
mkdir -p /home/bluesky/newkeys
chown www-data /home/admin/newkeys
chown www-data /home/bluesky/newkeys
chown -R admin /home/admin/.ssh
chown -R bluesky /home/bluesky/.ssh
chmod -R go-rwx /home/admin/.ssh
chmod -R go-rwx /home/bluesky/.ssh
# sets auth.log so admin can read it
touch /var/log/auth.log
chgrp admin /var/log/auth.log

## configure apache2
if [ "$USE_HTTP" -ne "1" ]; then
  if [[ ${IN_DOCKER} ]]; then
    if [[ -f /certs/${SSL_CERT} && -f /certs/${SSL_KEY} ]]; then
      # we have an ssl cert coming in
      echo "We are using the SSL cert provided."
      ln -fs /certs/${SSL_CERT} /etc/ssl/certs/ssl-cert-snakeoil.pem
      ln -fs /certs/${SSL_KEY} /etc/ssl/private/ssl-cert-snakeoil.key
    else
      # throw in self signed cert
      echo "Generating self-signed SSL cert."
      openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/ssl-cert-snakeoil.key -out /etc/ssl/certs/ssl-cert-snakeoil.pem -subj "/C=US/ST=Somewhere/L=Somewhere/O=BlueSky/OU=Development/CN=$SERVERFQDN"
    fi
    sed -i "s/CHANGETHIS/$serverFQDN/g" /etc/apache2/apache2.conf
  fi
  a2enmod ssl
  a2ensite default-ssl
fi
a2enmod cgi
sed -i "s/ServerAdmin webmaster@localhost/ServerAdmin $emailAlertAddress/g" /etc/apache2/sites-enabled/"$apacheConf".conf

#read the top half
head -n 5 /etc/apache2/sites-enabled/"$apacheConf".conf > /tmp/"$apacheConf".conf
#put this in
echo "    ServerName $serverFQDN" >> /tmp/"$apacheConf".conf
if [ "$USE_HTTP" -ne "1" ]; then
  echo '        SSLProtocol All -SSLv2 -SSLv3' >> /tmp/"$apacheConf".conf
fi
#write the bottom half
tail -n +6 /etc/apache2/sites-enabled/"$apacheConf".conf  >> /tmp/"$apacheConf".conf
#make backup and move it in place
mv /etc/apache2/sites-enabled/"$apacheConf".conf /tmp/"$apacheConf".conf.backup
mv /tmp/"$apacheConf".conf /etc/apache2/sites-enabled/"$apacheConf".conf

if [ "$USE_HTTP" -ne "1" ]; then
  ## setup port 80 redirect to 443
  echo "<VirtualHost *:80>
  Redirect permanent / https://$serverFQDN/
  ServerName $serverFQDN" > /tmp/000-default.conf
  tail -n +2 /etc/apache2/sites-enabled/000-default.conf  >> /tmp/000-default.conf
  mv /etc/apache2/sites-enabled/000-default.conf /tmp/000-default.conf.backup
  mv /tmp/000-default.conf /etc/apache2/sites-enabled/000-default.conf
fi

if [[ -z ${IN_DOCKER} ]]; then
  service apache2 restart
fi

## move web site to /var/www/html
mv /var/www/html /var/www/html.old
ln -s /usr/local/bin/BlueSkyConnect/Server/html /var/www/html
chown -R www-data /usr/local/bin/BlueSkyConnect/Server/html

## configure cron jobs
echo "@reboot /usr/local/bin/BlueSkyConnect/Server/startGozer.sh" > /tmp/mycron
echo "*/30 * * * *  /usr/local/bin/BlueSkyConnect/Server/purgeTemp.sh" >> /tmp/mycron
echo "*/5 * * * * /usr/local/bin/BlueSkyConnect/Server/serverup.sh" >> /tmp/mycron
crontab /tmp/mycron
/usr/local/bin/BlueSkyConnect/Server/startGozer.sh

## setup collector.php
ln -fs /usr/local/bin/BlueSkyConnect/Server/collector.php /usr/lib/cgi-bin/collector.php
chown www-data /usr/local/bin/BlueSkyConnect/Server/collector.php
chmod 700 /usr/local/bin/BlueSkyConnect/Server/collector.php
sed -i "s/CHANGETHIS/$mysqlCollectorPass/g" /usr/lib/cgi-bin/collector.php
if [[ ${IN_DOCKER} ]]; then
  sed -i "s/localhost/$MYSQLSERVER/g" /usr/lib/cgi-bin/collector.php
fi

## setup my.cnf
echo "[client]
user = root
password = $mysqlRootPass" > /var/local/my.cnf
if [[ ${IN_DOCKER} ]]; then
  echo "host = $MYSQLSERVER" >> /var/local/my.cnf
else
  echo "host = 127.0.0.1" >> /var/local/my.cnf
fi
chown admin:www-data /var/local/my.cnf
chmod 640 /var/local/my.cnf

# setup database
# test if database already exists
dbExists=$(/usr/bin/mysql --defaults-file=/var/local/my.cnf -N -B -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'BlueSky'" information_schema)
if [[ -z "${dbExists}" ]]; then
  # does not exist
  /usr/bin/mysql --defaults-file=/var/local/my.cnf -N -B -e 'create database BlueSky;'
  /usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky < /usr/local/bin/BlueSkyConnect/Server/myBlueSQL.sql
fi

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"

## setup credentials in /var/www/html/config.php
sed -i "s/MYSQLROOT/$mysqlRootPass/g" /var/www/html/config.php
if [[ ${IN_DOCKER} ]]; then
  sed -i "s/localhost/$MYSQLSERVER/g" /var/www/html/config.php
fi

if [[ -z ${IN_DOCKER} || -z ${dbExists} ]]; then
  ## setup credentials in membership_users table only if we are being run outside of docker OR the database was just created
  myQry="update membership_users set passMD5=MD5('$webAdminPassword'),email='$emailAlertAddress' where memberID='admin'"
  $myCmd "$myQry"
fi

## set collector mysql perms
# set variable to refer to what host(s) can connect to mysql
mysqlHostSecurity="localhost"
if [[ ${IN_DOCKER} ]]; then
  mysqlHostSecurity="%"
  # lets make sure the collector mysql user doesn't exist as we will be recreating it
  myQry="drop user 'collector'@'$mysqlHostSecurity';"
  $myCmd "$myQry"
fi
# create user
myQry="create user 'collector'@'$mysqlHostSecurity' identified by '$mysqlCollectorPass';"
$myCmd "$myQry"
myQry="grant select on BlueSky.computers to 'collector'@'$mysqlHostSecurity';"
$myCmd "$myQry"

## fail2ban conf
sed -i "s/SERVERFQDN/$serverFQDN/g" /usr/local/bin/BlueSkyConnect/Server/sendEmail-whois-lines.conf
cp /usr/local/bin/BlueSkyConnect/Server/sendEmail-whois-lines.conf /etc/fail2ban/action.d/sendEmail-whois-lines.conf
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSkyConnect/Server/jail.local
cp /usr/local/bin/BlueSkyConnect/Server/jail.local /etc/fail2ban
if [[ -z ${IN_DOCKER} ]]; then
  service fail2ban start
fi

## add emailAlertAddress to mysql for alerting
myQry="update global set defaultemail='$emailAlertAddress'"
$myCmd "$myQry"

## update emailHelper-dist.  You still need to enable it.
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSkyConnect/Server/emailHelper-dist.sh 2>/dev/null

## put server fqdn into client config.disabled for proxy routing
sed -i "s/SERVER/$serverFQDN/g" /usr/local/bin/BlueSkyConnect/Client/.ssh/config.disabled

## Run setup for client files
/usr/local/bin/BlueSkyConnect/Server/client-config.sh

## That's all folks!
if [[ -z ${IN_DOCKER} ]]; then
  echo "All set.  Please be sure to generate a CSR and/or install a verifiable SSL certificate"
  echo "in Apache by editing SSL paths in /etc/apache2/sites-enabled/default-ssl.conf"
  echo "BlueSky will not connect to servers with self-signed or invalid certificates."
  echo "And configure /usr/local/bin/BlueSkyConnect/Server/emailHelper.sh with your preferred SMTP setup."
else
  if [[ ${SMTP_SERVER} && ${SMTP_AUTH} && ${SMTP_PASS} ]]; then
    # enable email alerts
    mv /usr/local/bin/BlueSkyConnect/Server/emailHelper-dist.sh /usr/local/bin/BlueSkyConnect/Server/emailHelper.sh 2>/dev/null
  fi
fi
exit 0
