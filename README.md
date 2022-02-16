# BlueSkyConnect macOS SSH tunnel

## Overview

BlueSkyConnect establishes and maintains an SSH tunnel initiated by your client’s computer to a BlueSkyConnect server. The tunnel allows two connections to come back to the computer from the server: SSH and VNC. The SSH and VNC services on the computer are the ones provided by the Sharing.prefpane.

You use an Admin app to connect via SSH to the BlueSkyConnect server and then follow the tunnel back to your client computer. You select which computer by referencing its BlueSky ID as shown in the web admin.

Apps are provided to connect you to remote Terminal (SSH), Screen Sharing (VNC), and File/Folder copying (SCP). You still need to be able to authenticate as a user on the target computer.

Since BlueSkyConnect from your client computers is an outgoing connection most SMB networks won’t block it. In enterprise environments, BlueSky can read the proxy configuration in System Preferences and send the tunnel through a proxy server.

Read more in the [Wiki](https://github.com/BlueSkyTools/BlueSkyConnect/wiki)

Visit the #bluesky channel of MacAdmins Slack for unofficial help.

## Docker Information

Information regarding running BlueSky with docker can be found [in this README](docker/README.md)

## License and Copyright

BlueSkyConnect is licensed under the [Apache License 2.0](LICENSE).

BlueSky was copyright 2011–2014 Best Macs, Inc., 2014–2015 Mac-MSP LLC, and 2016–2017 SolarWinds Worldwide, LLC. SolarWinds stopped responding to PR’s on the logicnow/BlueSky repo and bumped the active developers off; development has been moved here.
