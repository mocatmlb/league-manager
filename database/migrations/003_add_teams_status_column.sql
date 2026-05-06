-- Migration: 003_add_teams_status_column.sql
-- Date: 2026-05-05
-- Description: Adds a status column to the teams table to track pending/active/inactive
--              state for the coach team registration workflow. All existing rows are
--              set to 'active' to preserve backward compatibility.
-- Affected tables: teams (ALTER)
-- Note: teams PK is team_id (not id) per schema.sql.
-- Idempotent: Yes (column addition guarded by information_schema check)
-- Compatibility: MySQL 8.0 (no ADD COLUMN IF NOT EXISTS)

DROP PROCEDURE IF EXISTS _d8tl_migrate_003;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_003()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'teams'
      AND COLUMN_NAME = 'status'
  ) THEN
    ALTER TABLE teams
      ADD COLUMN status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'
      AFTER division_id;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_003();
DROP PROCEDURE IF EXISTS _d8tl_migrate_003;

-- Backfill existing rows (safe no-op if already set)
UPDATE teams SET status = 'active' WHERE status IS NULL OR status = '';

INSERT IGNORE INTO schema_migrations (version) VALUES ('003');
