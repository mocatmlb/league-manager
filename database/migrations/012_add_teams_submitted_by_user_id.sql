-- Migration: 012_add_teams_submitted_by_user_id.sql
-- Date: 2026-05-08
-- Description: Adds submitted_by_user_id to the teams table so a pending team
--              can be linked back to the coach who submitted it without relying
--              on manager_email matching. Required by coaches/dashboard.php and
--              TeamRegistrationService::registerTeam().
-- Affected tables: teams (ALTER)
-- Idempotent: Yes (guarded by information_schema check)

DROP PROCEDURE IF EXISTS _d8tl_migrate_012;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_012()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'teams'
      AND COLUMN_NAME  = 'submitted_by_user_id'
  ) THEN
    ALTER TABLE teams
      ADD COLUMN submitted_by_user_id INT NULL
      AFTER status;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_012();
DROP PROCEDURE IF EXISTS _d8tl_migrate_012;

INSERT IGNORE INTO schema_migrations (version) VALUES ('012');
