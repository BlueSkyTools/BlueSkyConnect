#!/bin/bash

# BlueSkyConnect macOS SSH tunnel
#
# if you want any kind of email alerting, please configure this script
# it will receive a subject as "$1" and a body as "$2"
# it has been configured to pull the "To:" address from the global settings in the web admin
# RENAME to emailHelper.sh to activate after configuring the variables below
#
# See https://github.com/BlueSkyTools/BlueSkyConnect
# Licensed under the Apache License, Version 2.0

fromAddress="EMAILADDRESS"
subjectLine="$1"
messageBody="$2"
smtpServer=""
smtpAuth=""
smtpPass=""

if [[ ${IN_DOCKER} && ${SMTP_SERVER} && ${SMTP_AUTH} && ${SMTP_PASS} ]]; then
	smtpServer=$SMTP_SERVER
	smtpAuth=$SMTP_AUTH
	smtpPass=$SMTP_PASS
fi

## bail on this is if the server variable isn't set
if [ "$smtpServer" == "" ]; then
  echo "No server set up. Please edit emailHelper and try again."
  exit 2
fi

## get the To address from mySql
myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"
myQry="select defaultemail from global"
toAddress=`$myCmd "$myQry"`


## substitute in your preferred email method

/usr/bin/swaks -tls -a -au "$smtpAuth" -ap "$smtpPass" --server "$smtpServer" -f "$fromAddress" -t "$toAddress" --h-Subject "$subjectLine" --body "$messageBody"
