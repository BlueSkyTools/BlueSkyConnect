# Vendored: BlueConnect-Admin server payload

These files are a **vendored copy** of the server-side payload from
[echoparkbaby/BlueConnect-Admin](https://github.com/echoparkbaby/BlueConnect-Admin),
which backs the BlueConnect-Admin macOS app. They are copied verbatim — do not edit
them here; change them upstream and re-vendor.

## Pinned version

- **Ref:** `v1.1.3`
- **Commit:** `834dbb33b1b82b3d8791f73abb2950a499dc67b1`

## Files and destinations

The endpoints live in the served docroot (`Server/html/` → `/var/www/html/`); the
migration lives here, outside the docroot, so the `.sql` is not web-downloadable.

| Upstream (`server/`)                                  | Vendored to                                                  |
| ----------------------------------------------------- | ------------------------------------------------------------ |
| `bs_categories.json.php`                              | `Server/html/bs_categories.json.php`                         |
| `bs_health.json.php`                                  | `Server/html/bs_health.json.php`                             |
| `bs_host_action.json.php`                             | `Server/html/bs_host_action.json.php`                        |
| `bs_host_update.json.php`                             | `Server/html/bs_host_update.json.php`                        |
| `bs_hosts.json.php`                                   | `Server/html/bs_hosts.json.php`                              |
| `migrations/2026-05-14-computers-blueconnect-columns.sql` | `Server/blueconnect/migrations/2026-05-14-computers-blueconnect-columns.sql` |
| `migrations/2026-05-03-categories-sort-order.sql`     | `Server/blueconnect/migrations/2026-05-03-categories-sort-order.sql`         |

## Notes

- **Docker-only.** Integration is wired through `docker/run` (gated on
  `ENABLE_BLUECONNECT=1`) and the `Dockerfile` env default. Bare-metal deploy is not
  supported here — upstream documents its own SCP-based deploy for that.
- The endpoints read `WEBADMINPASS`, `MYSQLROOTPASS`, `MYSQLSERVER`, and
  `BLUESKY_VERSION` from the environment; our image already exports these (and
  `docker/run` exports `MYSQLROOTPASS` for the apache → mod_php chain when enabled).
- `docker/run` applies every `.sql` in this directory in filename order at container
  start. Each migration is idempotent and self-contained, so a failure is logged and
  re-runs cleanly on the next boot rather than blocking the others.
- Known upstream wrinkle: `2026-05-03-categories-sort-order.sql` runs before
  `2026-05-14-computers-blueconnect-columns.sql` (filename order), but its final
  statement seeds `bs_categories` from `computers.category` — a column the `05-14`
  migration adds. On a first boot that one statement errors ("Unknown column
  'category'"); the table/`sort_order` parts still apply, and the seed completes on
  the next boot once the column exists. Harmless (nothing to seed on a fresh install),
  and tracked upstream rather than worked around here:
  https://github.com/echoparkbaby/BlueConnect-Admin/issues/3
- `bs_categories.json.php` also self-creates the `bs_categories` table and heals the
  `sort_order` column on each request, but does not do the `05-03` backfills.
- **Out of scope** (not vendored): `catalog.php`, the MunkiReport module, and the
  Swift app.

## Refreshing

```
tools/refresh-blueconnect.sh [upstream-ref]   # defaults to the pinned ref above
```

Then update the **Ref** and **Commit** fields above (the script prints the resolved
commit) and the `DEFAULT_REF` in `tools/refresh-blueconnect.sh`.
