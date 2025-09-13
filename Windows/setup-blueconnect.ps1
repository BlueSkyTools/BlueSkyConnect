# Copyright 2025 Best Practices LLC.  
# Licensed under the Apache License, Version 2.0 (the "License");
#   you may not use this file except in compliance with the License.
#   You may obtain a copy of the License at

#       http://www.apache.org/licenses/LICENSE-2.0

#   Unless required by applicable law or agreed to in writing, software
#   distributed under the License is distributed on an "AS IS" BASIS,
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#   See the License for the specific language governing permissions and
#   limitations under the License.

# v1.0.20250829

# This script runs on Windows, sets up BlueConnect user and task and runs it

## Define the functions

# Function to set ownership and permissions
function Set-OwnershipAndPermissionsRecursively {
    param (
        [string]$path,
        [string]$user
    )
	$acl = Get-Acl $path
	$acl.SetOwner([System.Security.Principal.NTAccount]::new($user))
	Set-Acl -Path $path -AclObject $acl
	
	Get-ChildItem $path -Recurse | ForEach-Object {
		$acl = Get-Acl $_.FullName
		$acl.SetOwner([System.Security.Principal.NTAccount]::new($user))
		Set-Acl -Path $_.FullName -AclObject $acl
	}
}

## 

# Define some variables
$username = "BlueConnect"
$profileFolder = "C:\Users\$username"
$targetUser = "$env:COMPUTERNAME\$username"
$ourHome = "C:\Program Files\$username"

# disabled IE first run garbage
Write-Output "Disabling the IE first run grabage"
try {
	$keyPath = 'Registry::HKEY_LOCAL_MACHINE\Software\Policies\Microsoft\Internet Explorer\Main'
	if (!(Test-Path $keyPath)) { New-Item $keyPath -Force | Out-Null }
	Set-ItemProperty -Path $keyPath -Name "DisableFirstRunCustomize" -Value 1
}
catch {
	Write-Output "Failed to disable the IE first run grabage"
	Write-Output $_
	exit 2
}

# download the zip
Write-Output "Downloading the zip"
try {
	$zipUrl = "https://HOSTNAME/BlueConnect-Win-RANDOM.zip"
	$outfilePath = "C:\Users\Public\Downloads\BlueConnect.zip"
	Invoke-WebRequest -Uri $zipUrl -OutFile $outfilePath
	# validate it against the md5
	$md5_url = "https://HOSTNAME/BlueConnect-Win-RANDOM.md5"
	$zipMD5 = (Get-FileHash -Path $outfilePath -Algorithm MD5).Hash
	$valid_md5 = [System.Text.Encoding]::UTF8.GetString((Invoke-WebRequest -Uri $md5_url).RawContentStream.ToArray()).TrimEnd()
	if ($zipMD5 -eq $valid_md5){
		# unzip in to Program Files, deleting whatever is there
		Expand-Archive -Path $outfilePath -DestinationPath $ourHome -Force
		Remove-Item $outfilePath -Force
	} else {
		Write-Output "Bad download"
		exit 2
	}
} 
catch {
	Write-Output "Failure in the download and unzip"
	Write-Output $_
	exit 2
}

# Create password
Write-Output "Creating temporary password"
try {
	Add-Type -AssemblyName System.Web
	$passwordHash = [System.Web.Security.Membership]::GeneratePassword(20, 4)
	$securePassword = ConvertTo-SecureString $passwordHash -AsPlainText -Force
}
catch {
	Write-Output "Failed to create a password, try again"
	Write-Output $_
	exit 2
}


# Create the user account
Write-Output "Creating the service account"
try {
	if (-Not (Get-LocalUser -Name $username -ErrorAction SilentlyContinue)) {
		New-LocalUser -Name "$username" -Description "Service account for BlueConnect" -Password $securePassword
	} else {
		Remove-LocalUser -Name "BlueConnect"
		#TODO: figure out a better way to delete the user and kill the folder, Remove-Item often fails because of files in use
		Remove-Item $profileFolder -Force -Recurse
		New-LocalUser -Name "$username" -Description "Service account for BlueConnect" -Password $securePassword
	}
}
catch {
	Write-Output "Failed to create the service account"
	Write-Output $_
	exit 2
}

# sanity check on the user folder
# if there's a c:\Users\BlueConnect here things get really messy
if (Test-Path $profileFolder) {
	Write-Output "Manually delete the BlueConnect folder in Users and try again"
	exit 2
}

Write-Output "Setting permissions on the program files"
# set perms
try {
	Set-OwnershipAndPermissionsRecursively -path "C:\Program Files\BlueConnect" -user $targetUser
}
catch {
	Write-Output "Failed to set perms on the program files"
	Write-Output $_
	exit 2
}

Write-Output "Disabling login on the service account"
# block GUI/RDP access
# https://github.com/blakedrumm/SCOM-Scripts-and-SQL/blob/master/Powershell/General%20Functions/Set-UserRights.ps1
# https://blakedrumm.com/blog/set-and-check-user-rights-assignment/
try {
	& 'C:\Program Files\BlueConnect\Set-UserRights.ps1' -AddRight -Username "$targetUser" -UserRight SeBatchLogonRight
	& 'C:\Program Files\BlueConnect\Set-UserRights.ps1' -AddRight -Username "$targetUser" -UserRight SeDenyInteractiveLogonRight
}
catch {
	Write-Output "Failed to disable login on the service account"
	Write-Output $_
}

Write-Output "Installing OpenSSH Server - this can take a while"
# install OpenSSH server
try {
	Get-WindowsCapability -Online | Where-Object Name -like 'OpenSSH.Server*' | Add-WindowsCapability -Online
	Set-Service -Name sshd -StartupType 'Automatic'
}
catch {
	Write-Output "Failed to install OpenSSH Server"
	Write-Output $_
}

Write-Output "Setting up OpenSSH Server"
# setup OpenSSH server
try {
	# if it's already running
	Stop-Service sshd
	Stop-Service ssh-agent
	taskkill /f /im sshd.exe
	taskkill /f /im ssh-agent.exe
	
	# allow pub key auth
	(Get-Content 'C:\ProgramData\ssh\sshd_config') -replace '^\s*#\s*(PubkeyAuthentication\s+yes)', '$1' | Set-Content 'C:\ProgramData\ssh\sshd_config'
	(Get-Content 'C:\ProgramData\ssh\sshd_config') -replace '^\s*#?\s*StrictModes\s+\w+', 'StrictModes no' | Set-Content 'C:\ProgramData\ssh\sshd_config'
	
	# now start it
	Start-Service sshd
}
catch {
	Write-Output "Failed to setup and run OpenSSH Server"
	Write-Output $_
	exit 2
}

Write-Output "Installing OpenSSL"
# setup OpenSSL
# FireDaemon winget failed a few times, adding an installer in the zip to run locally as a fallback
try {
	if (-not (Test-Path "C:\Program Files\FireDaemon OpenSSL 3\bin\openssl.exe" -PathType Leaf)) {
		try {
			winget install --id=FireDaemon.OpenSSL -e --silent --accept-source-agreements
		}
		catch {
			Start-Process "C:\Program Files\BlueConnect\FireDaemon-OpenSSL-x64-3.5.2.exe" -ArgumentList "/exenoui", "/exelog fdopenssl3.log", "/qn", "/norestart", "REBOOT=ReallySuppress"
			#& 'C:\Program Files\BlueConnect\FireDaemon-OpenSSL-x64-3.5.2.exe /exenoui /exelog fdopenssl3.log /qn /norestart REBOOT=ReallySuppress APPDIR="C:\Program Files\FireDaemon OpenSSL 3" ADJUSTSYSTEMPATHENV=yes'
		}
	}
}
catch {
	Write-Output "Failed to install OpenSSL"
	Write-Output $_
	exit 2
}

# waiting for install to finish as the exe install exits immediately
while (-not (Test-Path "C:\Program Files\FireDaemon OpenSSL 3\bin\openssl.exe")) {
	Write-Output "waiting for openssl installer to finish"
	sleep 5
}

#sanity check - if OpenSSL isn't there, BlueConnect is going try to rekey over and over and over
if (-not (Test-Path "C:\Program Files\FireDaemon OpenSSL 3\bin\openssl.exe" -PathType Leaf)) {
	Write-Output "Manually install OpenSSL and try again"
	exit 2
}


Write-Output "Registering and initial start of task"
# activate blueconnect.ps1
try {
	$task_action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument '-NoProfile -ExecutionPolicy Bypass -File "C:\Program Files\BlueConnect\blueconnect.ps1"'
	$task_trigger = New-ScheduledTaskTrigger -AtStartup
	$task_settings = New-ScheduledTaskSettingsSet
	$task_settings.DisallowStartIfOnBatteries = $false
	$task_settings.StopIfGoingOnBatteries = $false
	$task_settings.StartWhenAvailable = $true
	$task_settings.ExecutionTimeLimit = "PT0S"
	Register-ScheduledTask -TaskName "BlueConnect" -Action $task_action -Trigger $task_trigger -Settings $task_settings -User "$targetUser" -Password "$passwordHash" -RunLevel Highest -Force
	Start-ScheduledTask -TaskName 'BlueConnect'
}
catch {
	Write-Output "Failed to register and start task"
	Write-Output $_
	exit 2
}

# waiting for home folder
while (-not (Test-Path $profileFolder)) {
	Write-Output "waiting on home folder to be created"
	sleep 5
}

Write-Output "Setting up home folder"
try {
	Stop-ScheduledTask -TaskName 'BlueConnect'
	
	# the activation of the scheduled task finally makes a home folder
	# make .ssh in the home folder
	New-Item -Path "$profileFolder" -Name ".ssh" -ItemType Directory
	
	# copy authorized_keys there
	$authorizedKey = Get-Content -Path "c:\Program Files\BlueConnect\blueskyd.pub"
	$auth_key_string = 'command="type \"c:\Program Files\BlueConnect\settings.json\"",no-pty,no-agent-forwarding,no-X11-forwarding '
	New-Item -Force -ItemType Directory -Path $profileFolder\.ssh; Add-Content -Force -Path $profileFolder\.ssh\authorized_keys -Value "$auth_key_string $authorizedKey"
	
	# set perms on .ssh
	Set-OwnershipAndPermissionsRecursively -path "$profileFolder\.ssh" -user $targetUser
	
	# hide the home folder
	if (Test-Path $profileFolder) {
		attrib +h $profileFolder
	}
}
catch {
	Write-Output "Failed to setup the service account profile"
	Write-Output $_
	exit 2
}

Write-Output "FIRE ME UP"
try {
	Start-ScheduledTask -TaskName 'BlueConnect'
}
catch {
	Write-Output "Failed to start the service at the end"
	Write-Output $_
	exit 2
}

Write-Output "Firewall port 22"
try {
	New-NetFirewallRule -Name sshd -DisplayName 'OpenSSH Server BlueConnect Only' -Direction Inbound -Protocol TCP -Action Block -LocalPort 22
}
catch {
	Write-Output "Failed to block port 22"
	Write-Output $_
	exit 2
}


exit 0