-- Adds the `blocked_serials` table and the `bc_block_rogue_insert`
-- BEFORE INSERT trigger on `computers` that bs_host_action.json.php's
-- "block" action relies on. v1.3.0 used to create both objects inline
-- in PHP on every block call; moving them here makes the schema
-- single-source-of-truth alongside the other BSC migrations.
--
-- Idempotent — safe to re-run.
--
-- Apply on the BSC LXC like:
--   docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" bluesky \
--     < ~/docker/stacks/bluesky/migrations/2026-05-27-blocked-serials.sql
--
-- Then bounce the bluesky container so PHP picks up any related changes:
--   docker compose restart bluesky
--
-- ─── table ───────────────────────────────────────────────────────────
-- One row per permanently blocked Mac. `serial` is the hardware
-- serial number; `blueskyid_at_block` records which DB row the host
-- had when it was blocked (for forensics). `note` is the admin's
-- free-text reason (sold, returned, decommissioned, etc.).
CREATE TABLE IF NOT EXISTS blocked_serials (
    serial             VARCHAR(64) NOT NULL PRIMARY KEY,
    added_at           DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blueskyid_at_block INT         NULL,
    note               VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── trigger ─────────────────────────────────────────────────────────
-- Rejects any `INSERT INTO computers` whose serialnum appears in
-- `blocked_serials`. The PHP-side guard in bs_host_action.json.php
-- removes the row when an admin blocks a host; this trigger keeps
-- subsequent re-registrations out for good. Requires SUPER privilege
-- to install on some MySQL builds — if it fails to install, the
-- cron sweeper (examples/bluesky/scripts/purge-blocked.sh) acts as
-- belt-and-suspenders.
DROP TRIGGER IF EXISTS bc_block_rogue_insert;

DELIMITER //
CREATE TRIGGER bc_block_rogue_insert
BEFORE INSERT ON computers
FOR EACH ROW
BEGIN
    IF (NEW.serialnum IS NOT NULL
        AND NEW.serialnum <> ''
        AND EXISTS (SELECT 1 FROM blocked_serials WHERE serial = NEW.serialnum)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'serial is on BlueConnect blocked_serials list';
    END IF;
END//
DELIMITER ;
