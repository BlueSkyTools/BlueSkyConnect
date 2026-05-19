## BlueSky Docker

This is an attempt at getting BlueSky to run within docker.  This should allow it to be easily setup on a wide variety of systems that support docker containers.

### TL;DR

We have a full example setup utilizing Caddy for automatic SSL cert generation [at this page](https://github.com/BlueSkyTools/BlueSkyConnect/blob/master/docker/DOCKER_FULL_EXAMPLE.md).

We also have a Docker [troubleshooting page](https://github.com/BlueSkyTools/BlueSkyConnect/wiki/Docker-Troubleshooting).

> **Image location:** Releases are published to GitHub Container Registry as `ghcr.io/blueskytools/blueskyconnect`. The legacy Docker Hub repo (`sphen/bluesky`) is no longer updated — please switch any `docker pull` / `docker-compose` references.

### Environment variables

These variables can be overridden when you run the BlueSky docker container.

Variable | Default Value | Note
--- | --- | ---
SERVERFQDN | localhost | BlueSky FQDN
WEBADMINPASS | admin |
USE_HTTP | 0 | Set to 1 to serve plain HTTP (recommended when running behind a TLS-terminating reverse proxy such as Caddy/nginx; container honors X-Forwarded-Proto)
SSL_CERT | | Filename referring to your ssl cert file in /certs
SSL_KEY | | Filename referring to your ssl key file in /certs
FAIL2BAN | 1 | Set to 0 to disable fail2ban
MYSQLSERVER | db | IP/DNS of your MySQL server (docker link to db by default)
MYSQLROOTPASS | admin | Will use root pass from linked docker container if possible
EMAILALERT | root@localhost |
SMTP_SERVER | | SMTP Server (Required for email alerts) Port optional
SMTP_AUTH | | SMTP auth user (Required for email alerts)
SMTP_PASS | | SMTP auth pass (Required for email alerts)
TIMEZONE | Etc/UTC | Local Timezone [Reference](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones)
DEFAULT_USER | | Default username bluesky uses when connecting to a client
INSECURE_CIPHERS | | Set to any value to allow the use of chacha20-poly1305 ssh cipher (bluesky <= 2.3.2)

### Signing & notarization (optional)

By default the two installer pkgs built at container start (`BlueSky-<ver>.pkg`, `BlueSkyAdmin-<ver>.pkg`) are **unsigned**. If you have an Apple Developer account you can have the container sign + notarize them automatically by setting the env vars below.

Signing/notarization runs inside the Linux container via [`rcodesign`](https://github.com/indygreg/apple-platform-rs) — Apple's own `codesign`/`notarytool` are macOS-only and are not used.

You need:

- A **Developer ID Application** cert exported as `.p12` (signs the `.app` bundles and the bundled Mach-O binaries inside the Client pkg).
- A **Developer ID Installer** cert exported as `.p12` (signs the `.pkg` files themselves — this is a separate cert from the Application one).
- An **App Store Connect API key** for notarization. Generate one at [App Store Connect → Users and Access → Integrations → Team Keys](https://appstoreconnect.apple.com/access/integrations/api). You'll get a `.p8` file, a 10-character key ID, and a UUID issuer ID. This is NOT an Apple ID app-specific password — that path is locked to `notarytool` and isn't available on Linux.

Export the two `.p12` files from Keychain Access on a Mac (right-click each Developer ID identity → Export). Put all three files (the two `.p12`s and the `.p8`) in a directory and mount it into the container read-only.

| Variable | Required | Purpose |
| --- | --- | --- |
| `SIGN_PKG` | yes | Set to `1` to enable. Unset → current unsigned-pkg behavior. |
| `DEVID_APP_P12` | yes | Path inside container to Developer ID Application `.p12` |
| `DEVID_APP_P12_PASSWORD` | yes | Cert password |
| `DEVID_INSTALLER_P12` | yes | Path inside container to Developer ID Installer `.p12` |
| `DEVID_INSTALLER_P12_PASSWORD` | yes | Cert password |
| `NOTARY_API_KEY_P8` | yes¹ | Path inside container to the `.p8` API key |
| `NOTARY_API_KEY_ID` | yes¹ | 10-char key ID from App Store Connect |
| `NOTARY_API_ISSUER_ID` | yes¹ | UUID issuer ID from App Store Connect |
| `SIGN_SKIP_NOTARIZE` | no | Set to `1` to sign but skip notary submit (dev iteration). Notary env vars are not required when this is set. |

¹ Required unless `SIGN_SKIP_NOTARIZE=1`.

Sample `docker run` (add these to the standard run command from below):

```
-v /host/path/to/signing:/signing:ro \
-e SIGN_PKG=1 \
-e DEVID_APP_P12=/signing/devid-app.p12 \
-e DEVID_APP_P12_PASSWORD=appcertpass \
-e DEVID_INSTALLER_P12=/signing/devid-installer.p12 \
-e DEVID_INSTALLER_P12_PASSWORD=installcertpass \
-e NOTARY_API_KEY_P8=/signing/AuthKey_XXXXXXXXXX.p8 \
-e NOTARY_API_KEY_ID=XXXXXXXXXX \
-e NOTARY_API_ISSUER_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx \
```

Notarization typically adds ~30–90 seconds per pkg to container startup. If any required env var is missing the container exits with a clear error; if signing succeeds but notary submission fails (e.g. revoked key) `docker logs bluesky` will show the rcodesign output.

To verify the result on a Mac after the pkg appears in your mounted `/tmp/pkg/` volume:

```bash
spctl --assess --type install -v BlueSkyAdmin-<ver>.pkg   # → accepted, source=Notarized Developer ID
xcrun stapler validate BlueSkyAdmin-<ver>.pkg              # → The validate action worked!
```

### Docker volumes

The following locations are mappable locations within the container.  These will be used for data that needs to persist between runs.

Path | Note
--- | ---
/certs | BlueSky SSH keys & SSL cert
/home/admin/.ssh | BlueSky admin client public keys
/home/bluesky/.ssh | BlueSky client public keys
/tmp/pkg | Client install pkg

### Setting up Persistent Storage

#### MySQL

Because we are also using MySQL within docker in this example setup, we need to setup the local storage first.  In the example below we are mapping the `/var/docker/bluesky/db` directory on the host to have the persistent mysql data.

First create and set permissions on the local folder:

> _You may want to modify permissions depending on how you are running docker_

```
sudo mkdir -p /var/docker/bluesky/db
sudo chmod -R 777 /var/docker/bluesky
```

#### BlueSky

Because docker images do not keep their data between runs, we need to map locations (volumes) for persistent storage.  In the example below we are mapping various directories within `/var/docker/bluesky/` on the host to have the persistent key data.

First create and set permissions on the local folder:

> _You may want to modify permissions depending on how you are running docker_

```
sudo mkdir -p /var/docker/bluesky/certs
sudo mkdir -p /var/docker/bluesky/admin.ssh
sudo mkdir -p /var/docker/bluesky/bluesky.ssh
sudo mkdir -p /var/docker/bluesky/pkg
sudo chmod -R 777 /var/docker/bluesky
```

### Run BlueSky

Setup MySQL:

> _Note that currently the root password is embedded in this command.  If you use a complex password with offending characters you should enclose the password in ''.  Passwords do not work with a \ in them_

```
docker run -d --name bluesky_db \
  -v /var/docker/bluesky/db:/var/lib/mysql \
  -e MYSQL_ROOT_HOST=% \
  -e MYSQL_ROOT_PASSWORD=admin \
  mysql:5.7
```

Wait for the MySQL docker container to initialize... This could take a minute.

Setup BlueSky:

> _Note that if you use complex passwords with offending characters you should enclose the password in ''.  Passwords do not work with a \ in them_

```
docker run -d --name bluesky \
  --link bluesky_db:db \
  -e SERVERFQDN=bluesky.example.com \
  -e SSL_CERT=bluesky.example.com.crt \
  -e SSL_KEY=bluesky.example.com.key \
  -v /var/docker/bluesky/certs:/certs \
  -v /var/docker/bluesky/admin.ssh:/home/admin/.ssh \
  -v /var/docker/bluesky/bluesky.ssh:/home/bluesky/.ssh \
  -v /var/docker/bluesky/pkg:/tmp/pkg \
  --cap-add=NET_ADMIN \
  -p 80:80 \
  -p 443:443 \
  -p 3122:3122 \
  ghcr.io/blueskytools/blueskyconnect
```

> _The `--cap-add=NET_ADMIN` argument is only needed if using fail2ban (default)_

### HTTPS SSL Certificate Setup

If you are opting to use valid HTTPS within the docker container you need to map in valid SSL certificates.  By default with no action, the container will generate a self-signed certificate.  If you have a valid `pem` and `key` file that you would like to use, we expect a few things:
- You are mapping the `/certs` volume
- The `SSL_CERT` environment variable is set to the file name of your cert
- The `SSL_KEY` environment variable is set to the file name of your key

Keep in mind if you have a chain certificate you should combine the entire chain into a single `pem` file.

### Upgrading BlueSky Server

To update your BlueSky docker instance it is very simple
```
docker pull ghcr.io/blueskytools/blueskyconnect
docker rm -f bluesky
```

Then just issue the `docker run` command you originally used to build your specific container!
```
docker run -d --name bluesky <the rest of your arguments...> ghcr.io/blueskytools/blueskyconnect
```

### Troubleshooting

You can get the logs from a container to see if there are any issues.  For example:
```
docker logs bluesky_db
docker logs bluesky
```

You can also shell into the BlueSky container if needed.  For example:
```
docker exec -it bluesky bash
```

### Links

- Container image on GHCR: https://github.com/BlueSkyTools/BlueSkyConnect/pkgs/container/blueskyconnect
- Source repo on GitHub: https://github.com/BlueSkyTools/BlueSkyConnect
