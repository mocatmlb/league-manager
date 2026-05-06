-- Migration: 004_add_locations_submission_columns.sql
-- Date: 2026-05-05
-- Description: Adds submitted_by_user_id and status columns to the locations table
--              to support coach-submitted home field locations during team registration.
--              FK references users(id) with ON DELETE SET NULL.
-- Affected tables: locations (ALTER)
-- Note: locations PK is location_id; users PK is id (per respective schema files).
-- Idempotent: Yes (all alterations guarded by information_schema checks)
-- Compatibility: MySQL 8.0 (no ADD COLUMN IF NOT EXISTS / ADD CONSTRAINT IF NOT EXISTS)

DROP PROCEDURE IF EXISTS _d8tl_migrate_004;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_004()
BEGIN
  -- Add submitted_by_user_id column if missing.
  -- NOTE: users.id is INT (signed) per user_accounts_schema.sql, so this column
  --       must also be INT (signed) to satisfy the FK type compatibility requirement.
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'submitted_by_user_id'
  ) THEN
    ALTER TABLE locations
      ADD COLUMN submitted_by_user_id INT NULL;
  END IF;

  -- Add status column if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'status'
  ) THEN
    ALTER TABLE locations
      ADD COLUMN status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active';
  END IF;

  -- Ensure submitted_by_user_id is INT (not UNSIGNED) to match users.id type.
  -- Handles the case where an earlier partial run created the column as INT UNSIGNED.
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'submitted_by_user_id'
      AND COLUMN_TYPE LIKE '%unsigned%'
  ) THEN
    ALTER TABLE locations MODIFY COLUMN submitted_by_user_id INT NULL;
  END IF;

  -- Add FK fk_locations_submitted_by if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'locations'
      AND CONSTRAINT_NAME = 'fk_locations_submitted_by'
  ) THEN
    ALTER TABLE locations
      ADD CONSTRAINT fk_locations_submitted_by
        FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_004();
DROP PROCEDURE IF EXISTS _d8tl_migrate_004;

-- Backfill existing rows (safe no-op if already set)
UPDATE locations SET status = 'active' WHERE status IS NULL OR status = '';

INSERT IGNORE INTO schema_migrations (version) VALUES ('004');
