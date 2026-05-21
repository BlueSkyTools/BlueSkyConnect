-- bs_categories.sort_order migration
--
-- Idempotent. Safe to run on:
--   * a fresh schema (creates the table)
--   * a schema where bs_categories already exists without sort_order
--   * a schema that has already been migrated
--
-- Apply on the bluesky LXC like:
--   docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" bluesky \
--     < ~/docker/stacks/bluesky/migrations/2026-05-03-categories-sort-order.sql
--
-- Then bounce the bluesky container so the PHP picks up any schema changes
-- the next request (no actual restart needed for SELECTs, but harmless):
--   docker compose restart bluesky

-- 1. Make sure the table exists. Pre-2026-05-02 schemas didn't have it at all.
CREATE TABLE IF NOT EXISTS bs_categories (
    name        VARCHAR(100) NOT NULL PRIMARY KEY,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add sort_order if the table existed but the column didn't. MySQL 5.7
-- doesn't support `IF NOT EXISTS` on ALTER TABLE ADD COLUMN, so use the
-- information_schema lookup pattern.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'bs_categories'
      AND COLUMN_NAME  = 'sort_order'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE bs_categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Backfill existing rows so they have monotonically increasing sort_order
-- ordered alphabetically by name. Only touches rows whose sort_order is the
-- DEFAULT 0; if you've already arranged them you won't lose your order.
SET @ord := 0;
UPDATE bs_categories
SET sort_order = (@ord := @ord + 1)
WHERE sort_order = 0
ORDER BY name;

-- 4. Backfill: if there are computers.category values that don't appear in
-- bs_categories yet, add them.
INSERT IGNORE INTO bs_categories (name, sort_order)
SELECT DISTINCT category, 0
FROM computers
WHERE category IS NOT NULL AND category <> '';
