-- Migration: 031_add_sms_opt_in_to_users.sql
-- Description: Adds sms_opt_in column to users table for text message preferences.
--
-- Affected tables: users (ALTER, conditional)
-- Idempotent: Yes (guarded by information_schema check)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_031;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_031()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'sms_opt_in'
    ) THEN
        ALTER TABLE users ADD COLUMN sms_opt_in TINYINT(1) NOT NULL DEFAULT 0;
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_031();
DROP PROCEDURE IF EXISTS _d8tl_migrate_031;

INSERT IGNORE INTO schema_migrations (version) VALUES ('031');
