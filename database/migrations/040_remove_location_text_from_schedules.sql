-- Migration 040: Drop redundant schedules.location text column
-- location_id (FK → locations) is the single source of truth.
-- Zero mismatches confirmed in production (2026-06-12).

DROP PROCEDURE IF EXISTS _d8tl_migrate_040_fix;
DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_040_fix()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schedules' AND COLUMN_NAME = 'location'
  ) THEN
    ALTER TABLE schedules DROP COLUMN location;
  END IF;
END$$
DELIMITER ;
CALL _d8tl_migrate_040_fix();
DROP PROCEDURE IF EXISTS _d8tl_migrate_040_fix;
INSERT IGNORE INTO schema_migrations (version) VALUES ('040');
