-- Migration: 013_add_schedule_change_requests_user_id.sql
-- Adds submitted_by_user_id to schedule_change_requests so requests
-- can be linked to individual coach accounts and cancellation enforced.
-- Idempotent: guarded by information_schema check.

DROP PROCEDURE IF EXISTS _d8tl_migrate_013;
DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_013()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'schedule_change_requests'
      AND COLUMN_NAME  = 'submitted_by_user_id'
  ) THEN
    ALTER TABLE schedule_change_requests
      ADD COLUMN submitted_by_user_id INT NULL AFTER requested_by,
      ADD CONSTRAINT fk_scr_submitted_by
        FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
      ADD INDEX idx_scr_submitted_by (submitted_by_user_id);
  END IF;
END$$
DELIMITER ;
CALL _d8tl_migrate_013();
DROP PROCEDURE IF EXISTS _d8tl_migrate_013;
INSERT IGNORE INTO schema_migrations (version) VALUES ('013');
