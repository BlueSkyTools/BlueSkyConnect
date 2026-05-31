## Docker Full example setup

This page shows an example setup consisting of several docker containers working together for easy deployment.

The main documentation on how to use the bluesky container is in our main [README](https://github.com/BlueSkyTools/BlueSkyConnect/blob/master/docker/README.md)

### Assumptions for this example

We will be using these docker containers:
- [mysql:5.7](https://hub.docker.com/_/mysql/)
- [ghcr.io/blueskytools/blueskyconnect](https://github.com/BlueSkyTools/BlueSkyConnect/pkgs/container/blueskyconnect)
- [caddy:2](https://hub.docker.com/_/caddy)

These are the values being used below - and will need modification from you:

| Setting | Value |
| --- | --- |
| MySQL root password | admin |
| Server FQDN | bluesky.example.com |
| Web admin pass | admin |
| Email alert address | email@example.com |
| SMTP Server | smtp.office365.com:587 |
| SMTP user | email@example.com |
| SMTP pass | yourpassword |

These are the local directories for persistent data:
```
/var/docker/bluesky/db
/var/docker/bluesky/admin.ssh
/var/docker/bluesky/bluesky.ssh
/var/docker/caddy/config
/var/docker/caddy/data
```

### Steps

#### Create the local storage directories

> _Note you may want to set more secure permissions_

```
mkdir -p /var/docker/bluesky/db \
  /var/docker/bluesky/admin.ssh \
  /var/docker/bluesky/bluesky.ssh \
  /var/docker/caddy/config \
  /var/docker/caddy/data
```

#### Create a shared docker network

Caddy needs to reach the bluesky container by name. A user-defined network gives us DNS-based service discovery (and replaces the deprecated `--link` flag).

```
docker network create bluesky_net
```

#### Create MySQL container

> _Note that the MySQL root password is in this command. If you use a complex password with offending characters you should enclose the password in ''. Passwords do not work with a \ in them_

```
docker run -d --name bluesky_db \
  --network bluesky_net \
  -v /var/docker/bluesky/db:/var/lib/mysql \
  -e MYSQL_ROOT_HOST=% \
  -e MYSQL_ROOT_PASSWORD=admin \
  --restart always \
  mysql/mysql-server:5.7
```

**Wait a minute or two for the MySQL container to initialize.** You can get the status of the container by running `docker ps -a`.  Wait until you see it with a status of **(healthy)**

#### Create BlueSky container

> **Note:** _All the variables that need to change.  If you use complex passwords with offending characters you should enclose the password in single quotes.  Passwords do not work with a backslash in them_

> **Note:** _`USE_HTTP=1` tells the bluesky container to serve plain HTTP on port 80 only. The Caddy container in front terminates TLS, so we don't need a cert inside this container and we don't publish port 443 from it. Apache inside the container honors `X-Forwarded-Proto: https` from Caddy so AppGini still generates correct `https://` redirects._

```
docker run -d --name bluesky \
  --network bluesky_net \
  -e SERVERFQDN=bluesky.example.com \
  -e WEBADMINPASS=admin \
  -e MYSQLROOTPASS=admin \
  -e MYSQLSERVER=bluesky_db \
  -e EMAILALERT=email@example.com \
  -e SMTP_SERVER=smtp.office365.com:587 \
  -e SMTP_AUTH=email@example.com \
  -e SMTP_PASS=yourpassword \
  -e USE_HTTP=1 \
  -v /var/docker/bluesky/admin.ssh:/home/admin/.ssh \
  -v /var/docker/bluesky/bluesky.ssh:/home/bluesky/.ssh \
  --cap-add=NET_ADMIN \
  -p 3122:3122 \
  --restart always \
  ghcr.io/blueskytools/blueskyconnect
```

#### Create Caddyfile

> **Note:** _Change the FQDN and the email address. The `email` directive enables automatic Let's Encrypt certificate issuance for that hostname._

```
cat <<EOF > /var/docker/caddy/Caddyfile
{
  email email@example.com
}

bluesky.example.com {
  reverse_proxy http://bluesky {
    header_up Host {host}
    header_up X-Forwarded-Proto https
    header_up X-Forwarded-For {remote_host}
  }
}
EOF
```

#### Create Caddy container

```
docker run -d --name caddy \
  --network bluesky_net \
  -p 80:80 \
  -p 443:443 \
  -v /var/docker/caddy/Caddyfile:/etc/caddy/Caddyfile \
  -v /var/docker/caddy/data:/data \
  -v /var/docker/caddy/config:/config \
  --restart always \
  caddy:2
```

Caddy will request and renew a Let's Encrypt certificate for `bluesky.example.com` automatically. The `/data` volume persists those certs across restarts.

### Upgrading from the legacy `abiosoft/caddy` + HTTPS-upstream setup

Earlier versions of this example proxied to the bluesky container over HTTPS (with `insecure_skip_verify`) because AppGini's PHP would otherwise generate `http://` redirects. The container now honors `X-Forwarded-Proto` when `USE_HTTP=1` is set, so the cleaner pattern above is preferred. To migrate:

1. Add `-e USE_HTTP=1` to your bluesky `docker run` and drop the `-p 443:443` and `-v ...:/certs` flags.
2. Replace the `abiosoft/caddy` container with `caddy:2` and rewrite the Caddyfile to v2 syntax (above) — note the `proxy /` directive from Caddy v1 is now `reverse_proxy`, and the `Caddyfile` lives at `/etc/caddy/Caddyfile` in the official image.
3. Recreate both containers. ACME certs will re-issue on first boot; subsequent restarts reuse the cert from `/data`.
