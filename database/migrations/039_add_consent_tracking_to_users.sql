-- Migration: 039_add_consent_tracking_to_users.sql
-- Description: Adds sms_consent_at and terms_accepted_at columns to users table
--              for SMS consent (A2P/TCPA) and Terms of Service acceptance audit trail.
--
-- Affected tables: users (ALTER, conditional)
-- Idempotent: Yes (guarded by information_schema check)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_039;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_039()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'sms_consent_at'
    ) THEN
        ALTER TABLE users ADD COLUMN sms_consent_at DATETIME NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'terms_accepted_at'
    ) THEN
        ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL;
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_039();
DROP PROCEDURE IF EXISTS _d8tl_migrate_039;

INSERT IGNORE INTO schema_migrations (version) VALUES ('039');
