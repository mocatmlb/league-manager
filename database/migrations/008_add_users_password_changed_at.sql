-- Migration: 008_add_users_password_changed_at.sql
-- Date: 2026-05-06
-- Description: Adds password_changed_at to the users table.
--
--              AuthService::authenticate() and AuthService::enforceSessionLifetime()
--              SELECT users.password_changed_at, but the column did not exist on
--              the users table (only on admin_users). Without this migration,
--              every coach login throws SQLSTATE[42S22] Unknown column
--              'password_changed_at'.
--
--              Also used by RegistrationService::completePasswordReset() to
--              invalidate active sessions on password reset by comparing the
--              session-stored timestamp against the DB value.
--
-- Affected tables: users (ALTER)
-- Idempotent: Yes (column-existence guarded via information_schema)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_008;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_008()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password_changed_at'
  ) THEN
    ALTER TABLE users
      ADD COLUMN password_changed_at DATETIME NULL AFTER updated_at;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_008();
DROP PROCEDURE IF EXISTS _d8tl_migrate_008;

INSERT IGNORE INTO schema_migrations (version) VALUES ('008');
