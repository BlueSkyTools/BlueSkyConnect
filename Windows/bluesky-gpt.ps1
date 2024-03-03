$ourHome = "C:\bluesky"
$bVer = "2.3.1"

# Planting a debug flag runs PowerShell with -Verbose, so you get all the output
if (Test-Path "$ourHome\.debug") {
    $VerbosePreference = "Continue"
}

function logMe {
    param(
        [string]$logMsg
    )

    $logFile = "$ourHome\activity.txt"
    if (!(Test-Path $logFile)) {
        New-Item -Path $logFile -ItemType File
    }
    $dateStamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "$dateStamp - v$bVer - $logMsg"
    if (Test-Path "$ourHome\.debug") {
        Write-Output $logMsg
    }
}

function getAutoPid {
    $autoPid = Get-Content -Path "$ourHome\autossh.pid" -TotalCount 1
    $autoCheck = Get-Process -Id $autoPid -ErrorAction SilentlyContinue
    if ($autoCheck -eq $null) {
        Remove-Item -Path "$ourHome\autossh.pid" -ErrorAction SilentlyContinue
        logMe "autossh not present on saved pid"
        $autoPid = $null
        $autoProc = Get-Process | Where-Object { $_.ProcessName -eq "autossh" } -ErrorAction SilentlyContinue
        if ($autoProc) {
            $autoPid = $autoProc.Id
            Set-Content -Path "$ourHome\autossh.pid" -Value $autoPid
            logMe "found autossh rogue on $autoPid"
        }
    } else {
        logMe "found autossh running on $autoPid"
    }
}

function killShells {
    getAutoPid
    if ($autoPid) {
        Stop-Process -Id $autoPid -Force
    }
    $shellList = Get-Process | Where-Object { $_.ProcessName -eq "ssh" }
    $shellList | ForEach-Object {
        Stop-Process -Id $_.Id -Force
    }
    getAutoPid
    $shellList = Get-Process | Where-Object { $_.ProcessName -eq "ssh" }
    if ($shellList -or $autoPid) {
        Set-Content -Path "$ourHome\.getHelp" -Value "contractKiller"
        Start-Sleep -Seconds 1
    }
}

function rollLog {
    param(
        [string]$logName
    )

    if (Test-Path "$ourHome\$logName") {
        $rollCount = 5
        Remove-Item -Path "$ourHome\$logName.$rollCount" -ErrorAction SilentlyContinue
        while ($rollCount -gt 0) {
            $prevCount = $rollCount - 1
            if (Test-Path "$ourHome\$logName.$prevCount") {
                Move-Item -Path "$ourHome\$logName.$prevCount" -Destination "$ourHome\$logName.$rollCount"
            }
            if ($prevCount -eq 0) {
                Move-Item -Path "$ourHome\$logName" -Destination "$ourHome\$logName.$rollCount"
            }
            $rollCount = $prevCount
        }
        $timeStamp = Get-Date -Format "Log file created at yyyy-MM-dd HH:mm:ss"
        $timeStamp | Out-File -FilePath "$ourHome\$logName"
    }
}

function startMeUp {
    $env:AUTOSSH_PIDFILE = "$ourHome\autossh.pid"
    $env:AUTOSSH_LOGFILE = "$ourHome\autossh.log"
    # rollLog autossh.log
    $timeStamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "$timeStamp BlueSky starting AutoSSH"
    # check for alternate SSH port
    $altPort = [System.IO.File]::ReadAllText("$ourHome\settings.plist") | ConvertFrom-Json | Select-Object -ExpandProperty altport
    if ([string]::IsNullOrEmpty($altPort)) {
        $altPort = 22
    } else {
        logMe "SSH port is set to $altPort per settings"
    }
    # is this 10.6 which doesn't support UseRoaming or 10.12+ which doesn't need the flag?
    if ($env:osVersion -ne 6 -and [int]($env:osVersion -as [int]) -lt 12) {
        $noRoam = "-o UseRoaming=no"
    }
    ## main command right here
    & "$ourHome\autossh" -M $monport -f `
        -c $prefCipher -m $msgAuth `
        $kexAlg `
        -o HostKeyAlgorithms=$keyAlg `
        -nNT -R $sshport:127.0.0.1:$altPort -R $vncport:127.0.0.1:5900 -p 3122 `
        $noRoam `
        -i "$ourHome\.ssh\bluesky_client" "bluesky@$blueskyServer"
    # echo "$!" > "$ourHome/autossh.pid"
    # are we live?
    Start-Sleep -Seconds 5
    $autoTimer = 0
    while ($autoTimer -lt 35) {
        $sshProc = Get-Process | Where-Object { $_.ProcessName -eq "ssh" -and $_.MainWindowTitle -match "bluesky@" }
        if ($sshProc) {
            break
        }
        Start-Sleep -Seconds 1
        $autoTimer++
    }
    # looks like it started up, let's check
    getAutoPid
    if ([string]::IsNullOrEmpty($autoPid)) {
        logMe "ERROR - autossh won't start, check logs. Exiting."
        exit 1
    } else {
        $sshProc = Get-Process | Where-Object { $_.ProcessName -eq "ssh" -and $_.MainWindowTitle -match "bluesky@" }
        if ($sshProc) {
            logMe "autossh started successfully"
        } else {
            logMe "ERROR - autossh is running but no tunnel, check logs. Exiting."
            exit 1
        }
    }
}

function restartConnection {
    killShells
    startMeUp
}

function reKey {
    logMe "Running re-key sequence"
    Remove-Item -Path "$ourHome\.ssh\bluesky_client" -ErrorAction SilentlyContinue
    Remove-Item -Path "$ourHome\.ssh\bluesky_client.pub" -ErrorAction SilentlyContinue
    ssh-keygen -q -t $keyAlg -N "" -f "$ourHome\.ssh\bluesky_client" -C "$serialNum"
    $pubKey = Get-Content -Path "$ourHome\.ssh\bluesky_client.pub"
    if ([string]::IsNullOrEmpty($pubKey)) {
        logMe "ERROR - reKey failed, and we are broken. Please reinstall."
        exit 1
    }
    Set-Content -Path "$ourHome\.ssh\bluesky_client" -Value $pubKey
    $pubKey = "public key here" # Generate public key to send to the server (fill in the actual code)
    if ([string]::IsNullOrEmpty($pubKey)) {
        logMe "ERROR - reKey failed, and we are broken. Please reinstall."
        exit 1
    }
    $installResult = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "newpub=$pubKey"
    if ($installResult -ne "Installed") {
        logMe "ERROR - upload of new public key failed. Exiting."
        exit 1
    }
    # get sharing name and Watchman Monitoring client group if present
    $hostName = (Get-WmiObject -Class Win32_ComputerSystem).Name
    if ([string]::IsNullOrEmpty($hostName)) {
        $hostName = (Get-Content env:COMPUTERNAME)
    }
    $wmCG = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Wow6432Node\Watchman Monitoring\Defaults' -Name ClientGroup).ClientGroup
    if (![string]::IsNullOrEmpty($wmCG)) {
        $hostName = "$wmCG - $hostName"
    }
    $uploadResult = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "serialNum=$serialNum&actionStep=register&hostName=$hostName"
    if ($uploadResult -ne "Registered") {
        logMe "ERROR - registration with server failed. Exiting."
        exit 1
    }
    Set-ItemProperty -Path 'HKCU:\Software\BlueSky' -Name keytime -Value (Get-Date).ToBinary()
}

function serialMonster {
    # reads serial number in settings and checks it against hardware - helpful if we are cloned or blank logic board
    # sets serialNum for rest of the script
    $settings = Get-Content "$ourHome\settings.plist" -Raw | ConvertFrom-Json
    $savedNum = $settings.serial
    $hwNum = (Get-WmiObject -Class Win32_BIOS).SerialNumber
    if ([string]::IsNullOrEmpty($hwNum)) {
        $hwNum = (Get-WmiObject -Class Win32_ComputerSystemProduct).Version
    }
    $blankBoard = $false
    if ($hwNum -match "Available|Serial|Number|Blank" -or [string]::IsNullOrEmpty($hwNum)) {
        $blankBoard = $true
    }
    if ($savedNum -eq $hwNum -and ![string]::IsNullOrEmpty($hwNum)) {
        # That was easy
        $serialNum = $savedNum
    } else {
        if ($blankBoard -eq $true -and $savedNum -match "MacMSP") {
            # Using the old generated hash
            $serialNum = $savedNum
        } else {
            # Must be the first run or cloned, so reset
            if ($blankBoard -eq $true) {
                $hwNum = "MacMSP" + ([guid]::NewGuid()).ToString().Replace("-", "")
            }
            # This may be the first run or first after a clone
            $settings.serial = $hwNum
            $settings | ConvertTo-Json | Set-Content -Path "$ourHome\settings.plist"
            $serialNum = $hwNum
            reKey
        }
    }
}

# Make me a sandwich? Make it yourself
$userName = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
if ($userName -ne "bluesky") {
    logMe "ERROR - script called by the wrong user"
    exit 2
}

# Are our perms screwed up?
$scriptPerm = (Get-Acl "$ourHome\bluesky.ps1").Owner
if ($scriptPerm -ne "bluesky") {
    Set-Content -Path "$ourHome\.getHelp" -Value "fixPerms"
    Start-Sleep -Seconds 5
}

# Get server address
$blueskyServer = (Get-Content "$ourHome\server.plist" -Raw | ConvertFrom-Json).address
# Sanity check
if ([string]::IsNullOrEmpty($blueskyServer)) {
    logMe "ERROR: Fix the server address"
    exit 1
}

# Get the version of the OS so we can ensure compatibility
$osVersion = [System.Environment]::OSVersion.Version.Major

# Select all of our algorithms - treating Windows versions prior to 10 as insecure, defaulting to secure
if ($osVersion -lt 10) {
    $keyAlg = "ssh-rsa"
    $serverKey = "serverkeyrsa"
    $prefCipher = "aes256-ctr"
    $kexAlg = ""
    $msgAuth = "hmac-ripemd160"
} else {
    $keyAlg = "ssh-ed25519"
    $serverKey = "serverkey"
    $prefCipher = "chacha20-poly1305@openssh.com"
    $kexAlg = "-o KexAlgorithms=curve25519-sha256@libssh.org"
    $msgAuth = "hmac-sha2-512-etm@openssh.com"
}

# Server key will be pre-populated in the installer - put it into known hosts
$serverKey = Get-Content -Path "$ourHome\server.plist" -Raw | ConvertFrom-Json | Select-Object -ExpandProperty $serverKey
if ([string]::IsNullOrEmpty($serverKey)) {
    logMe "ERROR: Can't get server key - please reinstall"
    exit 1
} else {
    $serverKey | Set-Content -Path "$ourHome\.ssh\known_hosts"
}

# Are there any live network ports?
$activeNets = Get-WmiObject -Class Win32_NetworkAdapter | Where-Object { $_.NetConnectionStatus -eq 2 }
if ([string]::IsNullOrEmpty($activeNets)) {
    $netCounter = 0
    while ($activeNets.Count -eq 0) {
        Start-Sleep -Seconds 5
        $activeNets = Get-WmiObject -Class Win32_NetworkAdapter | Where-Object { $_.NetConnectionStatus -eq 2 }
        $netCounter++
        if ($netCounter -gt 25) {
            killShells
            logMe "No active network connections. Exiting"
            exit 0
        }
    }
}

# Get proxy info from system preferences
$proxyInfo = [System.Environment]::GetEnvironmentVariable("http_proxy")
if (![string]::IsNullOrEmpty($proxyInfo)) {
    $confProxy = $proxyInfo -replace "^.*:\/\/(.*:\d+)$", '$1'
} else {
    $confProxy = ""
}

if (![string]::IsNullOrEmpty($confProxy) -and !(Test-Path "$ourHome\.ssh\config")) {
    # If proxy exists and config is disabled, enable it, restart AutoSSH
    $confProxy | Out-File -FilePath "$ourHome\.ssh\config" -Encoding ascii
    # TODO - populate SERVER and OURHOME too
    restartConnection
} elseif ([string]::IsNullOrEmpty($confProxy) -and (Test-Path "$ourHome\.ssh\config")) {
    # If proxy is gone and config is enabled, disable it, restart AutoSSH
    Remove-Item -Path "$ourHome\.ssh\config"
    restartConnection
}

# If the keys aren't made at this point, we should make them
if (!(Test-Path "$ourHome\.ssh\bluesky_client")) {
    reKey
}

# Ensure AutoSSH is alive and restart if not
getAutoPid
if ([string]::IsNullOrEmpty($autoPid)) {
    restartConnection
}

# Ask server for the default username so we can pass it on to Watchman
$defaultUser = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "serialNum=$serialNum&actionStep=user"
if (![string]::IsNullOrEmpty($defaultUser)) {
    $settings.defaultuser = $defaultUser
    $settings | ConvertTo-Json | Set-Content -Path "$ourHome\settings.plist"
}

# AutoSSH is running - check against server
$connStat = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "serialNum=$serialNum&actionStep=status"
if ($connStat -ne "OK") {
    if ($connStat -eq "selfdestruct") {
        killShells
        "selfdestruct" | Set-Content -Path "$ourHome\.getHelp"
        exit 0
    }
    logMe "Server says we are down. Restarting tunnels. Server said $connStat"
    restartConnection
    Start-Sleep -Seconds 5
    $connStatRetry = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "serialNum=$serialNum&actionStep=status"
    if ($connStatRetry -ne "OK") {
        logMe "Server still says we are down. Trying reKey. Server said $connStat"
        reKey
        Start-Sleep -Seconds 5
        restartConnection
        Start-Sleep -Seconds 5
        $connStatLastTry = Invoke-RestMethod -Uri "https://$blueskyServer/cgi-bin/collector.php" -Method POST -Body "serialNum=$serialNum&actionStep=status"
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

exit 0
