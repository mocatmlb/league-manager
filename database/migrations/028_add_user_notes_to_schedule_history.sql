-- Migration: 028_add_user_notes_to_schedule_history.sql
-- Description: Adds user_notes column to schedule_history for coach-supplied notes
--              on reschedule requests.
--
-- Affected tables: schedule_history (ALTER, conditional)
-- Idempotent: Yes (guarded by information_schema check)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_028;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_028()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'schedule_history'
          AND COLUMN_NAME  = 'user_notes'
    ) THEN
        ALTER TABLE schedule_history ADD COLUMN user_notes TEXT NULL DEFAULT NULL;
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_028();
DROP PROCEDURE IF EXISTS _d8tl_migrate_028;

INSERT IGNORE INTO schema_migrations (version) VALUES ('028');
