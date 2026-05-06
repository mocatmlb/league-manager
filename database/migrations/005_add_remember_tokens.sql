-- Migration: 005_add_remember_tokens.sql
-- Date: 2026-05-05
-- Description: Ensures the remember_tokens table uses the architecture-canonical
--              token_hash VARCHAR(64) design (SHA-256 of raw token) rather than the
--              plain-token VARCHAR(100) column in user_accounts_schema.sql.
--
--              CONFLICT NOTE: user_accounts_schema.sql may have already created
--              remember_tokens with a 'token' column (plain token). This migration:
--              1. Creates the table with the correct spec if it does not exist.
--              2. If the table already exists, adds 'token_hash' and the required
--                 unique key and FK if not already present. The old 'token' column
--                 is left in place for backward compatibility; all new code MUST
--                 use 'token_hash' exclusively.
--
-- Affected tables: remember_tokens (CREATE or ALTER)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS; column/constraint additions guarded)
-- Compatibility: MySQL 8.0 (no ADD COLUMN IF NOT EXISTS / ADD CONSTRAINT IF NOT EXISTS)

-- Step 1: Create table with canonical design if it does not yet exist.
CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_remember_token (token_hash),
  INDEX idx_remember_tokens_user (user_id),
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: If the table already existed (from user_accounts_schema.sql),
--         add token_hash column and required constraints via guarded procedure.
DROP PROCEDURE IF EXISTS _d8tl_migrate_005;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_005()
BEGIN
  -- Add token_hash column if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'remember_tokens'
      AND COLUMN_NAME = 'token_hash'
  ) THEN
    ALTER TABLE remember_tokens
      ADD COLUMN token_hash VARCHAR(64) NULL AFTER user_id;
  END IF;

  -- Add unique key on token_hash if not present
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'remember_tokens'
      AND INDEX_NAME = 'uq_remember_token'
  ) THEN
    ALTER TABLE remember_tokens
      ADD UNIQUE KEY uq_remember_token (token_hash);
  END IF;

  -- Add user_id index if not present (may exist under a different name)
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'remember_tokens'
      AND INDEX_NAME = 'idx_remember_tokens_user'
  ) THEN
    ALTER TABLE remember_tokens
      ADD INDEX idx_remember_tokens_user (user_id);
  END IF;

  -- Add FK fk_remember_tokens_user if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'remember_tokens'
      AND CONSTRAINT_NAME = 'fk_remember_tokens_user'
  ) THEN
    ALTER TABLE remember_tokens
      ADD CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_005();
DROP PROCEDURE IF EXISTS _d8tl_migrate_005;

INSERT IGNORE INTO schema_migrations (version) VALUES ('005');
