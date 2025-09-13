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

# This script runs on Windows, runs as a scheduled task at startup, looping with sleep at 1 minute intervals

# putting the version here for detection purposes - also change the bVer variable to match
# v3.0.20250913

#Set-PSDebug -Trace 2

## Define the functions

# this function will look in the registry for settings that may have been put there by Intune to override local settings.json
function check_reg {
	param (
		[string]$keyname
	)
	#check registry for settings pushed by Intune
	$regPath = "HKLM:\Software\BluecConnect"
	if (Test-Path $regPath) {
		$regValue = Get-ItemPropertyValue -Path $regPath -Name $keyname -ErrorAction SilentlyContinue
		}
		else {
		$regValue = ""
		}
	
	if ($regValue -ne "") {
			return "$regValue"
		}
		else {
			return $settings.$keyname
		}
}

#write info to a log file, prepend the date/time
function logMe {
    param(
        [string]$logMsg
    )

    $logFile = "$ourHome\activity.txt"
    if (!(Test-Path $logFile)) {
        New-Item -Path $logFile -ItemType File
    }
    
    # we are writing the date
    $dateStamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    
    # if the message is the same as the last line we don't need to repeat that
    $lastLine = Get-Content -Path $logFile -Tail 1
    if ($lastLine -like "*repeats*"){
    	# this is a repeat message, we need to assess what it's repeating
    	$lastTwo = Get-Content -Path $logFile -Tail 2
		# Index 0 = second to last, index 1 = last
		$lastLine = $lastTwo[0]
		$repeatExists = 1
    }
    
    if ($lastLine -like "*$logMsg" ) {
    	# we are writing the same thing again
    	$newRepeat = "$dateStamp - v$bVer - The previous entry repeats every minute since"
    	if ($repeatExists -eq 1){
    		# the last line was a repeat so we need to replace it first
			# Read all lines
			$lines = Get-Content -Path $logFile
			# Remove the last line and add the replacement
			$lines[0..($lines.Count - 2)] + $newRepeat | Set-Content -Path $logFile
    	} else {
    		# no repeat exists, so just add it
    		Add-Content -Path $logFile -Value $newRepeat
    	}
    } else {
    	# this is a new message
    Add-Content -Path $logFile -Value "$dateStamp - v$bVer - $logMsg"
    }

    #debug help for running in real time
    if (Test-Path "$ourHome\.debug") {
        Write-Output $logMsg
    }
}

# this function will stop and remove the tunnel job
function killShells {
	Get-Job -Name "BlueConnect Tunnel" | Where-Object { $_.State -eq 'Running' } | Stop-Job
	Remove-Job -Name "BlueConnect Tunnel"
}

# our service app TBD should take care of this for us and we only used it for Autossh
#function rollLog {
#    param(
#        [string]$logName
#    )
#
#    if (Test-Path "$ourHome\$logName") {
#        $rollCount = 5
#        Remove-Item -Path "$ourHome\$logName.$rollCount" -ErrorAction SilentlyContinue
#        while ($rollCount -gt 0) {
#            $prevCount = $rollCount - 1
#            if (Test-Path "$ourHome\$logName.$prevCount") {
#                Move-Item -Path "$ourHome\$logName.$prevCount" -Destination "$ourHome\$logName.$rollCount"
#            }
#            if ($prevCount -eq 0) {
#                Move-Item -Path "$ourHome\$logName" -Destination "$ourHome\$logName.$rollCount"
#            }
#            $rollCount = $prevCount
#        }
#        $timeStamp = Get-Date -Format "Log file created at yyyy-MM-dd HH:mm:ss" 
#        $timeStamp | Out-File -FilePath "$ourHome\$logName"
#    }
#}

function startMeUp {
    # check for alternate SSH port
    $altPort = [System.IO.File]::ReadAllText("$ourHome\settings.json") | ConvertFrom-Json | Select-Object -ExpandProperty altport
    if ([string]::IsNullOrEmpty($altPort)) {
        $altPort = 22
    } else {
        logMe "SSH port is set to $altPort per settings"
    }

    ## main command right here
    # TODO for some reason SSH won't take the profile folder as a variable so it's hard coded here
	$sshReverseString = [string]$sshport+":127.0.0.1:"+[string]$altPort
	$rdpReverseString = [string]$vncport+":127.0.0.1:3389"
	$make_tunnel = Start-Job -Name "BlueConnect Tunnel" -ScriptBlock {
		param($prefCipher, $msgAuth, $kexAlg, $keyAlg, $sshReverseString, $rdpReverseString, $blueskyServer, $ourHome)
		ssh `
        -c $prefCipher -m $msgAuth `
        $kexAlg `
        -o HostKeyAlgorithms=$keyAlg `
        -nNT -R $sshReverseString -R $rdpReverseString -p 3122 `
        -i C:\Users\BlueConnect\.ssh\bluesky_client bluesky@$blueskyServer 
	} -ArgumentList $prefCipher, $msgAuth, $kexAlg, $keyAlg, $sshReverseString, $rdpReverseString, $blueskyServer

    #wait 5 seconds for it to establish the tunnel
    sleep 5
    
    # check on the job to see if it's still up
	if ($make_tunnel.State -ne 'Running') {
		logMe "ERROR - ssh won't start, check logs. Exiting."
        throw "SSH outbound tunnel won't stay running."
	} else {
		logMe "SSH outbound tunnel process is running."
	}
}

function restartConnection {
    killShells
    startMeUp
}

function reKey {
    logMe "Running re-key sequence"
    Remove-Item -Path "$profileFolder\.ssh\bluesky_client" -ErrorAction SilentlyContinue
    Remove-Item -Path "$profileFolder\.ssh\bluesky_client.pub" -ErrorAction SilentlyContinue
    ssh-keygen -q -t $keyAlg -N '""' -f "$profileFolder\.ssh\bluesky_client" -C "$serialNum"
    $pubKey = Get-Content -Path "$profileFolder\.ssh\bluesky_client.pub"
    if ([string]::IsNullOrEmpty($pubKey)) {
        logMe "ERROR - reKey failed, and we are broken. Please reinstall."      
        throw "reKey failed, and we are broken. Please reinstall."
    }
# TODO set permissions on pki here?
	#openssl is required for this part - making the path a variable in case I want to change the distribution
	$openssl_path = "C:\Program Files\FireDaemon OpenSSL 3\bin\openssl.exe"
    $enc_crlf_pub = (& "$openssl_path" smime -encrypt -aes256 -in "$profileFolder/.ssh/bluesky_client.pub" -outform PEM "$ourHome/blue_signing.pub" )
    # Windows is going to create CRLF line endings, these need to convert to LF for the server
    $enc_lf_pub = ($enc_crlf_pub -join "`n") -replace "`r", ""
    # and finally make sure it's encoded properly to upload
    $enc_pub = [System.Web.HttpUtility]::UrlEncode($enc_lf_pub)
    if ([string]::IsNullOrEmpty($enc_pub)) {
        logMe "ERROR - reKey failed, and we are broken. Please reinstall."      
        throw "reKey failed, and we are broken. Please reinstall."
    }
    $installResult = (Invoke-WebRequest -Uri $server_url -Method POST -Body "newpub=$enc_pub" -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
    if ($installResult -ne "Installed") {
        logMe "ERROR - upload of new public key failed. Exiting."
        throw "upload of new public key failed."
    }
#    # get sharing name
    $hostName = (Get-WmiObject -Class Win32_ComputerSystem).Name
    if ([string]::IsNullOrEmpty($hostName)) {
        $hostName = (Get-Content env:COMPUTERNAME)
    }
    $uploadResult = (Invoke-WebRequest -Uri $server_url -Method POST -Body "serialNum=$serialNum&actionStep=register&hostName=$hostName" -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
    if ($uploadResult -ne "Registered") {
        logMe "ERROR - registration with server failed. Exiting."
        catch  "registration with server failed."
    }
    #set the keytime in settings
    $recorded_value = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
	$settings | Add-Member -NotePropertyName keytime -NotePropertyValue $recorded_value -Force
	$settings | ConvertTo-Json -Depth 100 | Set-Content "$ourHome/settings.json"
}

# not going to use serialMonster in version 3 and/or on Windows because we'll fetch it dynamically
# if there's still a problem of blank boards we'll re-adjust

#function serialMonster {
#    # reads serial number in settings and checks it against hardware - helpful if we are cloned or blank logic board
#    # sets serialNum for rest of the script
#    $settings = Get-Content "$ourHome\settings.json" -Raw | ConvertFrom-Json   
#    $savedNum = $settings.serial
#    $hwNum = (Get-WmiObject -Class Win32_BIOS).SerialNumber
#
#    if ([string]::IsNullOrEmpty($hwNum)) {
#        $hwNum = (Get-WmiObject -Class Win32_ComputerSystemProduct).Version     
#    }
#    $blankBoard = $false
#    if ($hwNum -match "Available|Serial|Number|Blank" -or [string]::IsNullOrEmpty($hwNum)) {
#        $blankBoard = $true
#    }
#    if ($savedNum -eq $hwNum -and ![string]::IsNullOrEmpty($hwNum)) {
#        # That was easy
#        $serialNum = $savedNum
#    } else {
#        if ($blankBoard -eq $true -and $savedNum -match "MacMSP") {
#            # Using the old generated hash
#            $serialNum = $savedNum
#        } else {
#            # Must be the first run or cloned, so reset
#            if ($blankBoard -eq $true) {
#                $hwNum = "MacMSP" + ([guid]::NewGuid()).ToString().Replace("-", "")
#            }
#            # This may be the first run or first after a clone
#            $settings.serial = $hwNum
#            $settings | ConvertTo-Json | Set-Content -Path "$ourHome\settings.json"
#            $serialNum = $hwNum
#            reKey
#        }
#    }
#}

# Planting a debug flag runs PowerShell with -Verbose, so you get all the output
# TODO - alter this to be another setting instead of debug
#if (Test-Path "$ourHome\.debug") {
#    $VerbosePreference = "Continue"
#}

## Define some variables pre-loop
$ourHome = "C:\Program Files\BlueConnect"
$profileFolder = "C:\Users\BlueConnect"
$bVer = "3.0.20250913"
$expectedName="$env:COMPUTERNAME\BlueConnect"

# Make me a sandwich? Make it yourself
# because we are storing known_hosts and authorized_keys we need a specific %USERPROFILE%/.ssh
$userName = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name      
if ($userName -ne $expectedName) {
	logMe "ERROR - script called by the wrong user"
	exit 2
}

#TODO - figure out how helper is going to play into Windows perms
#TODO - do we even need this part?
# Are our perms screwed up?
#$scriptPerm = (Get-Acl "$ourHome\bluesky.ps1").Owner
#if ($scriptPerm -ne "bluesky") {
#	Set-Content -Path "$ourHome\.getHelp" -Value "fixPerms"
#	Start-Sleep -Seconds 5
#}

#get the hardware serial number that Blue uses to identify
$serialNum = (Get-CimInstance -ClassName Win32_BIOS).SerialNumber

# Pull settings from file - make one if there isn't one
$settingsPath = "$ourHome\settings.json"
if (Test-Path $settingsPath) {
	$settings = Get-Content $settingsPath | ConvertFrom-Json
} else {
	# the settings file doesn't exist yet, so make one
	$settings = @{
version = [string]$bVer
serial = [string]$serialNum
}
 $settings | ConvertTo-Json | Out-File -FilePath $settingsPath
}

# Pull server settings from file - not making one of these, it will mean a reinstall
$server_set_path = "$ourHome\server.json"
$server_set = Get-Content $server_set_path | ConvertFrom-Json
# Get server address
$blueskyServer = $server_set.address
# Sanity check
if ([string]::IsNullOrEmpty($blueskyServer)) {
	logMe "ERROR: Fix the server address"
	exit 1
}

# let's set the URL for all WebRequest commands
$server_url = "https://$blueskyServer/cgi-bin/collector.php"

# Server key will be pre-populated in the installer - put it into known hosts   
$serverKey = $server_set.serverkey
if ([string]::IsNullOrEmpty($serverKey)) {
	logMe "ERROR: Can't get server key - please reinstall"
	exit 1 
} else {
	# puts the server key into known_hosts
	# the .ssh folder should already be there from setup
	#New-Item -Path "$profileFolder\.ssh" -ItemType Directory -Force | Out-Null
	$serverKey | Set-Content -Path "$profileFolder\.ssh\known_hosts"
}

# Select all of our algorithms
$keyAlg = "ssh-ed25519"
$serverKey = "serverkey"
$prefCipher = "chacha20-poly1305@openssh.com"
$kexAlg = "-o KexAlgorithms=curve25519-sha256@libssh.org"
$msgAuth = "hmac-sha2-512-etm@openssh.com"

## Loop so the script (and outbound tunnel via Start-Job) stays running
while ($true) {
    try {
	
	# Are there any live network ports?
	$activeNets = Get-NetConnectionProfile | Where-Object {$_.NetworkCategory -ne 'Disconnected'}
	if ([string]::IsNullOrEmpty($activeNets)) {
	# TODO - test this with a device that's offline
		# we are going to wait up to 2 minutes for a network to come alive before we bail
		$netCounter = 0
		while ($activeNets.Count -eq 0) {
			Start-Sleep -Seconds 5
			$activeNets = Get-NetConnectionProfile | Where-Object {$_.NetworkCategory -ne 'Disconnected'}
			$netCounter++
			if ($netCounter -gt 25) {
				killShells
				logMe "No active network connections. Exiting"
				throw "No active network connections after 30 seconds"
			}
		}
		throw "No active network connections. Exiting."
	}
	
	# We are commenting out proxy info from 3.0 and/or Windows
	# leaving the $confProxy variable present for the "curl" command in case we put it back
	# there will be a clean up someday where this block gets reinstated or removed entirely
	# Get proxy info from system preferences
	#$proxyInfo = [System.Environment]::GetEnvironmentVariable("http_proxy")
	#if (![string]::IsNullOrEmpty($proxyInfo)) {
	#    $confProxy = $proxyInfo -replace "^.*:\/\/(.*:\d+)$", '$1'
	#} else {
		$confProxy = ""
	#}
	
	#if (![string]::IsNullOrEmpty($confProxy) -and !(Test-Path "$ourHome\.ssh\config")) {
	#    # If proxy exists and config is disabled, enable it, restart AutoSSH        
	#    $confProxy | Out-File -FilePath "$ourHome\.ssh\config" -Encoding ascii      
	#    # TODO - populate SERVER and OURHOME too
	#    restartConnection
	#} elseif ([string]::IsNullOrEmpty($confProxy) -and (Test-Path "$ourHome\.ssh\config")) {
	#    # If proxy is gone and config is enabled, disable it, restart AutoSSH       
	#    Remove-Item -Path "$ourHome\.ssh\config"
	#    restartConnection
	#}
	
	
	# Attempt to get our port - this is dynamic and might change
	$web_req_body = "serialNum=$SerialNum&actionStep=port"	
	try {
		$blue_port = (Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
	}
	catch {
		# if the webrequest failed, the server is probably down or unreachable, try again on the next cycle
		killShells
		logMe "ERROR - cant get to server. Exiting"
		throw "cant reach server while trying to get port"
	}
	
	# Is collector returning a database connection error?
	if ($blue_port -eq "ERROR: cant get dbc") {
		logMe "ERROR - server has a database problem. Exiting."
		throw "server has a database problem"
	}
	
	# Did port check pass?
	if (-not $blue_port) {
		# try running off cached copy
		try {
			$blue_port = (Get-Content "$ourHome\settings.json" -Raw | ConvertFrom-Json).port
		}
		catch {
			logMe "No cached Blue ID in settings"
		}
		if (-not $blue_port) {
			#no cached copy either, try rekey
			reKey
			sleep 5
			# try the server again after re-key
			try {
				blue_port = (Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
			}
			catch {
				logMe "Failed to get Blue ID from server"
			}
			if (-not $blue_port) {
				logMe "ERROR - cant reach server and have no port."
				throw "Cant get to server and have no port. Will try again next cycle."
			} else {
				#save cached port for next time
				$settings | Add-Member -NotePropertyName portcache -NotePropertyValue $blue_port -Force
				$settings | ConvertTo-Json -Depth 100 | Set-Content $settingsPath
			}
		}
	} else {
		#save cached port for next time
		$settings | Add-Member -NotePropertyName portcache -NotePropertyValue $blue_port -Force
		$settings | ConvertTo-Json -Depth 100 | Set-Content $settingsPath
	}
	
	# add the big numbers to make the actual ports we'll use
	$sshport = 22000 + $blue_port
	$vncport = 24000 + $blue_port
	## not doing autossh so don't need this
	#$monport = 26000 + $blue_port
		
	# If the keys aren't made at this point, we should make them
	if (!(Test-Path "$profileFolder\.ssh\bluesky_client")) {
		reKey
	}
	
	# Ensure the outbound tunnel is alive and restart if not
	$tunnel_alive = Get-Job -Name "BlueConnect Tunnel"
	if (!($tunnel_alive) -Or $tunnel_alive.State -ne 'Running') {
		restartConnection
	}
		
	# since we got here, SSH is running - check against server
	$web_req_body = "serialNum=$SerialNum&actionStep=status"	
	$connStat = (Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
	if ($connStat -ne "OK") {
		if ($connStat -eq "selfdestruct") {
			killShells
			#no permission to affect big changes but killing these will at least kill our ability to shell without reinstall
			Remove-Item "$ourHome\server.json" -Force
			Remove-Item "$profileFolder\.ssh" -Force -Recurse
			Remove-Item "$ourHome\blue_signing.pub" -Force
			Remove-Item $ourHome -Force -Recurse
			Remove-LocalUser -Name "BlueConnect"
			# these next two will probably fail and leave a mess, but don't seem to affect reinstall
			Remove-Item $profileFolder -Force -Recurse
			Unregister-ScheduledTask -TaskName 'BlueConnect'
			# note I'm leaving the firewall rule as a safety check since I'm not uninstalling OpenSSH server
			# tell the server we are done
			$web_req_body = "serialNum=$SerialNum&actionStep=deleted"
			Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded"
			exit 0
		}
		logMe "Server says we are down. Restarting tunnels. Server said $connStat"  
		restartConnection
		Start-Sleep -Seconds 5
		$connStatRetry = (Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()
		if ($connStatRetry -ne "OK") {
			logMe "Server still says we are down. Trying reKey. Server said $connStat"
			reKey
			Start-Sleep -Seconds 5
			restartConnection
			Start-Sleep -Seconds 5
			$connStatLastTry = (Invoke-WebRequest -Uri $server_url -Method POST -Body $web_req_body -ContentType "application/x-www-form-urlencoded").Content.TrimEnd()   
			if ($connStatLastTry -ne "OK") {
				logMe "ERROR - Server still says we are down. Needs manual intervention. Server said $connStat"
				exit 1
			} else {
				logMe "Rekey worked. All good!"
			}
		} else {
			logMe "Reconnect worked. All good!"
		}
	} else {
		logMe "Server sees our connection. All good!"
	}
	
# here's the loop exception to receive all the throws
    } catch {
        Add-Content "$ourHome\error.log" "$($_.Exception.Message)"
    }
    Start-Sleep -Seconds 60
}
