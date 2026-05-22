-- Migration: Add location_id to schedule_history for better auditability
-- Story 14.2 AC 5 requirement

DROP PROCEDURE IF EXISTS _d8tl_migrate_019_fix;
DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_019_fix()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schedule_history' AND COLUMN_NAME = 'location_id'
  ) THEN
    ALTER TABLE schedule_history ADD COLUMN location_id INT NULL AFTER location;
  END IF;
END$$
DELIMITER ;
CALL _d8tl_migrate_019_fix();
DROP PROCEDURE IF EXISTS _d8tl_migrate_019_fix;

INSERT IGNORE INTO schema_migrations (version) VALUES ('019');
