-- Adds the BlueConnect-specific columns to the stock BSC `computers`
-- table. Idempotent — safe to re-run.
--
-- The PHP endpoints (bs_hosts.json.php, bs_host_update.json.php) read
-- and write these columns. Stock `sphen/bluesky` ships only:
--   id, blueskyid, hostname, sharingname, username, status, datetime,
--   timestamp, ip, port, version, key
-- Everything else BlueConnect needs is added here.
--
-- Apply on the BSC LXC like:
--   docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" bluesky \
--     < ~/docker/stacks/bluesky/migrations/2026-05-14-computers-blueconnect-columns.sql
--
-- Then bounce the bluesky container so the PHP picks up the new schema:
--   docker compose restart bluesky
--
-- MySQL 5.7 doesn't support `IF NOT EXISTS` on ALTER TABLE ADD COLUMN,
-- so each ADD goes through the information_schema lookup pattern.

-- Helper macro: add a column only if it doesn't already exist.
-- (Repeated inline below since SQL doesn't have real macros.)

-- ─── category ───────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'category'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN category VARCHAR(100) NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── favorite ───────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'favorite'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN favorite TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── notes ──────────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'notes'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN notes TEXT NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── serialnum ──────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'serialnum'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN serialnum VARCHAR(64) NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── notify ─────────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'notify'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN notify TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── alert ──────────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'alert'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN alert TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── email ──────────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND COLUMN_NAME  = 'email'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE computers ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on category so the host list grouped-by-category query is fast
-- on big fleets. Idempotent.
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'computers'
      AND INDEX_NAME   = 'idx_computers_category'
);
SET @ddl := IF(@idx_exists = 0,
    'CREATE INDEX idx_computers_category ON computers (category)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
