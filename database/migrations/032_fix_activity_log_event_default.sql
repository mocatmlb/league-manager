-- Migration: 032_fix_activity_log_event_default.sql
-- Date: 2026-05-29
-- Description: Ensures activity_log.event has NOT NULL DEFAULT '' so that
--              legacy logActivity() calls (which do not supply an event value)
--              succeed on databases where migration 007 added the column without
--              a DEFAULT, causing "Field 'event' doesn't have a default value"
--              errors on any INSERT that omits the column.
--
-- Affected tables: activity_log (ALTER, conditional)
-- Idempotent: Yes (guarded by information_schema check)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_032;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_032()
BEGIN
    -- Only act when the column exists but is missing its DEFAULT ''.
    -- Re-applying MODIFY with DEFAULT '' is safe even if the default is already set.
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'activity_log'
          AND COLUMN_NAME  = 'event'
    ) THEN
        ALTER TABLE activity_log
            MODIFY COLUMN event VARCHAR(100) NOT NULL DEFAULT '';
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_032();
DROP PROCEDURE IF EXISTS _d8tl_migrate_032;

INSERT IGNORE INTO schema_migrations (version) VALUES ('032');
