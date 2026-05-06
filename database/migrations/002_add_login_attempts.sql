-- Migration: 002_add_login_attempts.sql
-- Date: 2026-05-05
-- Description: Ensures login_attempts table uses the architecture-canonical
--              column design: identifier (not username), no success boolean,
--              composite indexes for lockout and CAPTCHA queries.
--
--              CONFLICT NOTE: user_accounts_schema.sql may have already created
--              a login_attempts table with columns (username, ip_address,
--              attempted_at, success). This migration reconciles the schema:
--              1. If the table does not exist, it is created with the correct spec.
--              2. If the table exists but lacks 'identifier', that column is added.
--              3. The required composite indexes are added if not already present.
--
-- Affected tables: login_attempts (CREATE or ALTER)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS; column/index additions guarded by procedure)
-- Compatibility: MySQL 8.0 (no ADD COLUMN IF NOT EXISTS; uses information_schema checks)

-- Step 1: Create table with architecture-canonical design if it does not exist.
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
  INDEX idx_login_attempts_identifier_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Add 'identifier' column and required indexes via procedure
--         (idempotent — checks information_schema before altering).
DROP PROCEDURE IF EXISTS _d8tl_migrate_002;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_002()
BEGIN
  -- Add 'identifier' column if missing (table may have old 'username' column)
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'login_attempts'
      AND COLUMN_NAME = 'identifier'
  ) THEN
    ALTER TABLE login_attempts
      ADD COLUMN identifier VARCHAR(255) NOT NULL DEFAULT '' AFTER id;
  END IF;

  -- Add composite index on (ip_address, attempted_at) if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'login_attempts'
      AND INDEX_NAME = 'idx_login_attempts_ip_time'
  ) THEN
    ALTER TABLE login_attempts
      ADD INDEX idx_login_attempts_ip_time (ip_address, attempted_at);
  END IF;

  -- Add composite index on (identifier, attempted_at) if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'login_attempts'
      AND INDEX_NAME = 'idx_login_attempts_identifier_time'
  ) THEN
    ALTER TABLE login_attempts
      ADD INDEX idx_login_attempts_identifier_time (identifier, attempted_at);
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_002();
DROP PROCEDURE IF EXISTS _d8tl_migrate_002;

INSERT IGNORE INTO schema_migrations (version) VALUES ('002');
