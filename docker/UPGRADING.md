# Upgrading a Docker BlueSky server

One place to check everything that changed for Docker deployments between the
long-lived **v2.3.2** image and the current release. Individual items were called
out in the [release notes](https://github.com/BlueSkyTools/BlueSkyConnect/releases)
as they shipped; this consolidates them so an upgrade that skips several versions
doesn't miss one.

Find the row for the version you are coming **from** — everything at or below that
row applies to you:

Upgrading from | What applies
--- | ---
≤ 2.3.2 | Everything below
2.5.0 | Image registry move, plus everything for 2.5.1+
2.5.1 | `LEGACY_CLIENT`, client pkg re-download, background pkg builds
2.6.0+ | Nothing breaking — pull and restart

## The checklist

- [ ] **Change `-p 3122:22` to `-p 3122:3122`** in your `docker run` command or compose file *(from ≤ 2.3.2)*
- [ ] **Pull from GHCR, not Docker Hub** — `ghcr.io/blueskytools/blueskyconnect` *(from ≤ 2.5.0)*
- [ ] **Set `INSECURE_CIPHERS=1`** if any ≤ 2.3.2 clients still need to connect *(from ≤ 2.3.2)*
- [ ] **Set `LEGACY_CLIENT=1`** if you manage Macs on macOS < 10.14 *(from ≤ 2.5.1)*
- [ ] **Confirm your persistent volumes are mounted** — especially `/certs` *(all upgrades)*
- [ ] **Re-download and push the client pkg** from the web admin after the upgrade *(from ≤ 2.5.1)*
- [ ] **Run the post-upgrade verification** at the bottom of this page *(all upgrades)*

Details on each item follow.

## 1. SSH tunnel port mapping changed (v2.5.0) — ⚠️ breaks every tunnel if missed

Through v2.3.2 the container's sshd listened on port **22** and the documented
mapping was `-p 3122:22`. Since v2.5.0 sshd listens on **3122 inside the
container**, and the mapping must be:

```
-p 3122:3122
```

If you reuse a pre-2.5.0 `docker run` command or compose file, host port 3122
forwards to container port 22 where nothing listens. The failure is deceptive:

- Client check-ins, registration, and the web admin all keep working (port 443).
- No client — old or new — can establish a tunnel; `autossh` starts and retries
  silently forever.
- The server log fills with
  `ssh: connect to host localhost port 22NNN: Cannot assign requested address`
  (the per-check-in tunnel test hitting an unbound port).
- The only log with the real error ("Connection refused") is
  `/var/bluesky/autossh.log` on a client Mac.

## 2. Image moved to GitHub Container Registry (v2.5.1)

The Docker Hub repo (`sphen/bluesky`) is no longer updated. Reference:

```
ghcr.io/blueskytools/blueskyconnect:latest
```

Images are built for **linux/amd64** only. On an ARM host (e.g. Apple Silicon
running Docker Desktop) add `--platform linux/amd64`.

## 3. SSH cipher hardening (v2.5.0) — ≤ 2.3.2 clients can't connect by default

`sshd_config` now enables only `aes256-gcm@openssh.com` +
`hmac-sha2-512-etm@openssh.com`. v2.3.2 and older clients negotiate
`chacha20-poly1305@openssh.com`, so they are rejected.

While your client rollout is in progress, set on the **bluesky container**:

```
-e INSECURE_CIPHERS=1
```

Notes:

- This is **not** a default — omit it and old clients cannot connect.
- The check is "is the variable set", so `INSECURE_CIPHERS=0` also enables it.
  To turn it off, **remove the variable** and restart the container.
- This only rescues 2.3.2 clients on **macOS 10.11+**. Clients on 10.10 or
  older used `aes256-ctr` + `hmac-ripemd160` + `ssh-rsa`; modern OpenSSH removed
  `hmac-ripemd160` entirely, so no server setting can readmit them. Those Macs
  need a client update (see `LEGACY_CLIENT` below) or are out of support.

## 4. `LEGACY_CLIENT` for pre-Mojave Macs (v2.6.0)

The client pkg targets macOS 10.14+ by default and omits the bundled
`curl`/`openssl`. If you still manage Macs on 10.9–10.13, set
`-e LEGACY_CLIENT=1` so the pkg includes them again. This affects **pkg
contents only** — it has no effect on server-side connectivity or ciphers.

## 5. Persistent volumes — what actually must survive the upgrade

Path | Why it matters
--- | ---
`/certs` | sshd **host keys** plus the SMIME/tunnel-check keys. If this isn't persisted, a new container generates new host keys and **every deployed client rejects the server** (pinned key mismatch).
`/home/admin/.ssh` | Enrolled admin public keys.
`/home/bluesky/.ssh` | Enrolled client public keys — lose this and every client must re-enroll.
`/var/lib/mysql` (db container) | The `computers`/`connections` database.
`/tmp/pkg` | Optional; caches pkg builds/notarization across restarts.

The install-path rename (`/usr/local/bin/BlueSky` → `/usr/local/bin/BlueSkyConnect`,
v2.5.0) is handled automatically: on first boot the container rewrites the
forced-command paths in both `authorized_keys` files and drops a
`.authorized_keys_migrated` sentinel. No operator action needed.

## 6. Client pkg update

After the container is up, re-download the client (and admin) pkg from the web
admin and push it to managed Macs. The 2.6.0 client brings the modern cipher
set, launchd fixes for macOS 11+, hostName sync, and the StrictModes permission
fix — and lets you eventually drop `INSECURE_CIPHERS`.

Since v2.6.0 the pkgs build **in the background** after container start, so the
download links appear a few minutes in (longer when notarization is enabled).
An empty download page right after `docker run` is normal — wait, don't debug.

If device names drifted before 2.6.0's hostName sync, see the optional SQL
reconcile in the [v2.6.0 release notes](https://github.com/BlueSkyTools/BlueSkyConnect/releases/tag/v2.6.0).

## Post-upgrade verification

Run these before calling the upgrade done:

```bash
# 1. sshd is listening on 3122 inside the container
docker exec bluesky ss -tlnp | grep sshd        # expect *:3122

# 2. host port 3122 maps to container port 3122
docker port bluesky                              # expect 3122/tcp -> 0.0.0.0:3122

# 3. cipher policy is what you intend
docker exec bluesky grep -E '^(Ciphers|MACs|Port)' /etc/ssh/sshd_config

# 4. from any Mac: the tunnel port answers with an SSH banner
nc -vz your.server.fqdn 3122
```

Then watch one known client: within ~5 minutes its status in the web admin
should return to "Connection is good". If it doesn't, read
`/var/bluesky/autossh.log` **on that Mac** — that's where ssh reports the real
reason (connection refused, cipher mismatch, host key rejection). The server's
`Cannot assign requested address` lines only tell you a tunnel isn't up, not why.

## New optional features (no action required)

`USE_HTTP=1` reverse-proxy support, pkg signing/notarization (`SIGN_PKG=1`),
log rotation (`LOG_ROTATE_SIZE`/`LOG_ROTATE_KEEP`), and BlueConnect-Admin
endpoints (`ENABLE_BLUECONNECT=1`) all default off/safe. See
[docker/README.md](README.md).

## Not on Docker?

Bare-metal upgrades must fetch the **new** `update-from-git.sh` first — the old
one on the box doesn't know about newer migrations:

```bash
sudo curl -fsSL https://raw.githubusercontent.com/BlueSkyTools/BlueSkyConnect/master/Server/update-from-git.sh \
  -o /tmp/update-from-git.sh
sudo bash /tmp/update-from-git.sh
```
