-- Migration: 014_add_users_preferred_name.sql
-- Date: 2026-05-09
-- Description: Adds preferred_name column to users table for coach profile display.
-- Affected tables: users (ALTER)
-- Idempotent: Yes (column-existence guarded via information_schema)

DROP PROCEDURE IF EXISTS _d8tl_migrate_014;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_014()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'preferred_name'
  ) THEN
    ALTER TABLE users
      ADD COLUMN preferred_name VARCHAR(50) NULL AFTER last_name;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_014();
DROP PROCEDURE IF EXISTS _d8tl_migrate_014;

INSERT IGNORE INTO schema_migrations (version) VALUES ('014');
