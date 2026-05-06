-- Migration: 009_invitations_drop_fk_and_add_cancelled.sql
-- Date: 2026-05-06
-- Description: Two structural fixes to user_invitations:
--
--   1. DROP FK on invited_by — the column references users(id), but admin
--      sessions provide admin_users.id. Every admin-sent invitation hit a
--      foreign-key violation. Per Epic 3 review decision, we drop the FK
--      (column kept for record-keeping; activity log captures admin id).
--      Epic 8/9 will revisit when users/admin_users are unified.
--
--   2. ADD 'cancelled' to status enum — InvitationService::cancel() was
--      forced to write 'expired' because the original enum lacked 'cancelled',
--      making cancelled invitations indistinguishable from naturally-expired
--      in the admin UI.
--
-- Affected tables: user_invitations (ALTER)
-- Idempotent: Yes (FK-existence and enum-shape both guarded)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_009;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_009()
BEGIN
  DECLARE fk_name VARCHAR(64);

  -- Step 1: Find and drop the FK on user_invitations.invited_by
  SELECT CONSTRAINT_NAME INTO fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_invitations'
      AND COLUMN_NAME = 'invited_by'
      AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1;

  IF fk_name IS NOT NULL THEN
    SET @sql = CONCAT('ALTER TABLE user_invitations DROP FOREIGN KEY ', fk_name);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  -- Step 2: Add 'cancelled' to the status enum (only if not already present)
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_invitations'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%cancelled%'
  ) THEN
    ALTER TABLE user_invitations
      MODIFY COLUMN status ENUM('pending','completed','cancelled','expired')
        NOT NULL DEFAULT 'pending';
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_009();
DROP PROCEDURE IF EXISTS _d8tl_migrate_009;

INSERT IGNORE INTO schema_migrations (version) VALUES ('009');
