<#
.SYNOPSIS
	Establishes BlueSky connection
.DESCRIPTION
	Ensures that the connection to BlueSky is up and running, attempts repair if there is a problem.
.EXAMPLE
	PS> ./bluesky.ps1
.LINK
	https://github.com/bestmacs/bluesky
.NOTES
	Author: Brian Best | License: Apache 2.0
	Should not run with administrator privileges
	Can we create a service user in Windows?
#>

#Requires -Version 2.0

# Set this to a different location if you'd prefer it live somewhere else
$ourHome = "C:\Program Files\BlueSky" 

$bVer = "2.3.1"

# planting a debug flag runs bash in -x so you get all the output
# TBD

function logMe([string]$logMsg)
{
	$logFile = "$ourHome\activity.txt"
	If (-not(test-path -Path $logFile)) {
		New-Item -Path $logFile -ItemType File
	}  
	$dateStamp = Get-Date -Year -Month -Day -Hour -Minute -Second
	Add-content $logFile -value "$dateStamp - v$bVer - $logMsg"
	# Add debug
}

function killShells {
}

