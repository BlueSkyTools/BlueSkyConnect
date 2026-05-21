# Vendored: BlueConnect-Admin server payload

These files are a **pristine vendored copy** of the server-side payload from
[echoparkbaby/BlueConnect-Admin](https://github.com/echoparkbaby/BlueConnect-Admin),
which backs the BlueConnect-Admin macOS app. They are fetched verbatim by
`tools/refresh-blueconnect.sh` and committed unmodified — there are no local patches.
Don't hand-edit the vendored files; change them upstream and re-vendor.

## Pinned version

- **Ref:** `v1.2.0`
- **Commit:** `6cfa0c09095fab1838cec5bef77e07f3ed30a20b`

## Files and destinations

The docroot PHP lives in the served docroot (`Server/html/` → `/var/www/html/`); the
migrations live here, outside the docroot, so the `.sql` is not web-downloadable.

| Upstream (`server/`)                                      | Vendored to                                                                  |
| --------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `bs_auth.php`                                             | `Server/html/bs_auth.php`                                                    |
| `bs_authkeys_audit.json.php`                              | `Server/html/bs_authkeys_audit.json.php`                                     |
| `bs_categories.json.php`                                  | `Server/html/bs_categories.json.php`                                         |
| `bs_health.json.php`                                      | `Server/html/bs_health.json.php`                                             |
| `bs_host_action.json.php`                                 | `Server/html/bs_host_action.json.php`                                        |
| `bs_host_update.json.php`                                 | `Server/html/bs_host_update.json.php`                                        |
| `bs_hosts.json.php`                                       | `Server/html/bs_hosts.json.php`                                              |
| `migrations/2026-05-03-categories-sort-order.sql`         | `Server/blueconnect/migrations/2026-05-03-categories-sort-order.sql`         |
| `migrations/2026-05-14-computers-blueconnect-columns.sql` | `Server/blueconnect/migrations/2026-05-14-computers-blueconnect-columns.sql` |

## Authentication

`bs_auth.php` is the shared HTTP Basic helper the authenticated endpoints `require`.
Upstream supports two modes via the `WEBADMIN_AUTH` env var:

- **`WEBADMINPASS` (upstream default)** — compare the supplied password against the
  `WEBADMINPASS` env var. That var is only a snapshot taken at container start, so it
  goes stale the moment the password is changed in the AppGini web admin.
- **`WEBADMIN_AUTH=db`** — verify the supplied username/password against
  `membership_users.passMD5` (plain `md5`, matching AppGini's own login in
  `incCommon.php`), restricted to approved, non-banned accounts, so the endpoints
  always honor the live web-admin password.

**We run `db` mode:** `docker/run` exports `WEBADMIN_AUTH=db` (overridable) when
`ENABLE_BLUECONNECT=1`. This DB-backed mode was originally a local patch here; it was
upstreamed in [PR #5](https://github.com/echoparkbaby/BlueConnect-Admin/pull/5)
(closing [#4](https://github.com/echoparkbaby/BlueConnect-Admin/issues/4)) and shipped
in v1.2.0, so we now vendor it pristine and just flip the env var. `bs_health.json.php`
is intentionally unauthenticated and does not include `bs_auth.php`.

## Notes

- **Docker-only.** Integration is wired through `docker/run` (gated on
  `ENABLE_BLUECONNECT=1`) and the `Dockerfile` env default. Bare-metal deploy is not
  supported here — upstream documents its own SCP-based deploy for that.
- The endpoints read `MYSQLROOTPASS`, `MYSQLSERVER`, `WEBADMIN_AUTH`, and
  `BLUESKY_VERSION` from the environment; our image already exports `MYSQLSERVER` and
  `BLUESKY_VERSION`, and `docker/run` exports `MYSQLROOTPASS` and `WEBADMIN_AUTH` for
  the apache → mod_php chain when enabled. With `WEBADMIN_AUTH=db`, `WEBADMINPASS` is
  not used for endpoint auth (it still seeds the DB password at first setup via
  `server-config.sh`).
- `docker/run` applies every `.sql` in `migrations/` in filename order at container
  start. Each migration is idempotent and self-contained, so a failure is logged and
  re-runs cleanly on the next boot rather than blocking the others. As of v1.2.0 the
  `05-03` seed is guarded on `computers.category` existing (information_schema check),
  so the old filename-ordering wrinkle — it ran before the `05-14` migration that adds
  the column — no longer errors on a fresh DB
  ([#3](https://github.com/echoparkbaby/BlueConnect-Admin/issues/3), fixed upstream).
- `bs_hosts.json.php` self-applies the `computers` BlueConnect columns on each request
  (idempotent information_schema guard), and `bs_categories.json.php` self-creates the
  `bs_categories` table and heals `sort_order`. The startup migrations are still run so
  the `05-03` category backfill (which the endpoints don't do) happens.
- `bs_authkeys_audit.json.php` is read-only: it lists the BSC reverse-tunnel
  `authorized_keys` entries and flags orphans. If PHP (`www-data`) can't read the
  `0600`-mode keys file owned by the in-container `bluesky` user, it returns
  `{"readable": false, ...}` with a fix hint rather than 500 — we deliberately do not
  loosen those key-file permissions.
- **Out of scope** (not vendored): `catalog.php`, the MunkiReport module, and the
  Swift app.

## Refreshing

```
tools/refresh-blueconnect.sh [upstream-ref]   # defaults to the pinned ref above
```

Then update the **Ref** and **Commit** fields above (the script prints the resolved
commit) and the `DEFAULT_REF` in `tools/refresh-blueconnect.sh`.
