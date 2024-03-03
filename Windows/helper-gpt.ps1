$ourHome = "C:\bluesky"
$bVer = "2.3.1"

if (Test-Path "$ourHome\.debug") {
    Set-PSDebug -Trace 1
}

function logMe {
    param([string]$logMsg)

    $logFile = Join-Path $ourHome "activity.txt"

    if (-not (Test-Path $logFile)) {
        New-Item -Path $logFile -ItemType File
    }

    $dateStamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "$dateStamp - v$bVer - $logMsg" | Out-File -Append -FilePath $logFile

    if (Test-Path "$ourHome\.debug") {
        Write-Output $logMsg
    }
}

function Kill-Shells {
    Get-Process | Where-Object { $_.ProcessName -eq "autossh" -or $_.ProcessName -eq "ssh" } | ForEach-Object {
        $_.Kill()
        logMe "Killed stale shell on $($_.Id)"
    }
}

# Check if server.plist is not present, error, and exit
if (-not (Test-Path "$ourHome\server.plist")) {
    Write-Output "server.plist is not installed. Please double-check your setup."
    exit 2
}

# Check if BlueSky 1.5 is present and remove it
if (Test-Path "C:\Library\Mac-MSP\BlueSky\helper.sh" -or (Get-Package | Where-Object { $_.Name -eq "com.mac-msp.bluesky" })) {
    Kill-Shells
    Stop-Process -Name "autossh" -Force
    "picardAlphaTango" | Out-File "C:\Library\Mac-MSP\BlueSky\.getHelp"
    Start-Sleep -Seconds 5

    # Remove the old bluesky user
    Remove-LocalUser -Name "mac-msp-bluesky"

    # Clear out old package receipts
    Get-Package | Where-Object { $_.Name -eq "com.mac-msp.bluesky" } | ForEach-Object {
        Uninstall-Package -Name $_.Name
    }

    Unregister-ScheduledTask -TaskPath "\Microsoft\Windows\BlueSky" -Confirm:$false
}

if (Test-Path "$ourHome\.getHelp") {
    $helpWithWhat = Get-Content "$ourHome\.getHelp"
    Remove-Item "$ourHome\.getHelp"
}

# Initiate self-destruct
if ($helpWithWhat -eq "selfdestruct") {
    Kill-Shells
    Remove-Item -Recurse -Force $ourHome
    Remove-LocalUser -Name "bluesky"
    Uninstall-Package -Name "com.solarwindsmsp.bluesky.pkg"
    Unregister-ScheduledTask -TaskPath "\Microsoft\Windows\BlueSky" -Confirm:$false
    exit 0
}

# Get the version of the OS to ensure compatibility
$osRaw = (Get-ComputerInfo).WindowsVersion
$osVersion = $osRaw.Split(".")[1]

# Check if user exists and create if necessary
$userCheck = Get-LocalUser -Name "bluesky" -ErrorAction SilentlyContinue
if ($userCheck -eq $null) {
    # User doesn't exist, let's try to set it up
    logMe "Creating our user account"

    $uidTest = 491
    while ($true) {
        $uidCheck = Get-LocalUser -Filter "Enabled -eq 'True' -and PrincipalId -eq $uidTest" -ErrorAction SilentlyContinue
        if ($uidCheck -eq $null) {
            New-LocalUser -Name "bluesky" -NoPassword -UserMayNotChangePassword -Description "BlueSky User" -PassThru
            Set-LocalUser -Name "bluesky" -HomeDirectory $ourHome
            break
        } else {
            $uidTest = Get-Random -Minimum 400 -Maximum 490
        }
    }

    logMe "Created on UID $uidTest"
}

# Ensure the permissions are correct on our home
$ourHomeACL = Get-Acl -Path $ourHome
$ourHomeACL.SetOwner([System.Security.Principal.NTAccount]"bluesky")
Set-Acl -Path $ourHome -AclObject $ourHomeACL

# Enable Remote Desktop for bluesky
$rdpGroup = "Remote Desktop Users"
$blueskyGroup = "bluesky"
$rdpGroupSid = New-Object System.Security.Principal.SecurityIdentifier (Get-LocalGroupMember $rdpGroup).ObjectSid
$blueskySid = New-Object System.Security.Principal.SecurityIdentifier (Get-LocalUser $blueskyGroup).ObjectSid

# Grant permission to the Remote Desktop Users group
if ($rdpGroupSid -ne $null -and $blueskySid -ne $null) {
    $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule($blueskySid, "Modify", "ContainerInherit, ObjectInherit", "None", "Allow")
    $ourHomeACL.AddAccessRule($accessRule)
    $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule($rdpGroupSid, "Modify", "ContainerInherit, ObjectInherit", "None", "Allow")
    $ourHomeACL.AddAccessRule($accessRule)
    Set-Acl -Path $ourHome -AclObject $ourHomeACL
}

# Uncomment or modify additional PowerShell commands as needed for your environment

# Restart the computer for certain settings to take effect
# Restart-Computer -Force

# Start ARD agent if not running
# if (-not (Get-Service "Apple Remote Desktop") -or (Get-Service "Apple Remote Desktop").Status -ne "Running") {
#     logMe "Starting ARD agent"
#     Start-Service "Apple Remote Desktop"
# }

# Ensure the home folder permissions
# $ourHomeACL = Get-Acl -Path $ourHome
# $ourHomeACL.SetOwner([System.Security.Principal.NTAccount]"bluesky")
# Set-Acl -Path $ourHome -AclObject $ourHomeACL

# Uncomment or modify these commands as needed based on your requirements

# Fix SSH configuration on Windows if necessary
# $sshConfigFile = "$env:ProgramData\ssh\ssh_config"
# if (Test-Path $sshConfigFile -and $osVersion -ge 11) {
#     $sshConfig = Get-Content $sshConfigFile -Raw
#     $sshConfig = $sshConfig | Where-Object { $_ -notmatch "^GSSAPIKeyExchange|^GSSAPITrustDNS|^GSSAPIDelegateCredentials" }
#     $sshConfig | Set-Content $sshConfigFile
# }
