-- Migration: 007_create_activity_log.sql
-- Date: 2026-05-05
-- Description: Ensures the activity_log table has the architecture-canonical schema
--              used by ActivityLogger (Story 1.3): event (dot-notation string) and
--              context (JSON) columns. This is compatible with — and extends — the
--              existing activity_log table in schema.sql.
--
--              The existing schema.sql activity_log columns (log_id, user_id,
--              user_type, action, details, ip_address, user_agent, created_at) are
--              preserved. This migration only adds the NEW 'event' and 'context'
--              columns (and their indexes) required by ActivityLogger, using guarded
--              ALTER statements so existing data is never touched.
--
--              ActivityLogger::log() writes ONLY to 'event' and 'context'. The old
--              columns remain for any existing code that uses them.
--
-- Affected tables: activity_log (ALTER — adds event, context columns and indexes)
-- Idempotent: Yes (column/index additions guarded by information_schema checks)
-- Compatibility: MySQL 8.0

DROP PROCEDURE IF EXISTS _d8tl_migrate_007;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_007()
BEGIN
  -- Add 'event' column if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_log'
      AND COLUMN_NAME = 'event'
  ) THEN
    ALTER TABLE activity_log
      ADD COLUMN event VARCHAR(100) NOT NULL DEFAULT '';
  END IF;

  -- Add 'context' column if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_log'
      AND COLUMN_NAME = 'context'
  ) THEN
    ALTER TABLE activity_log
      ADD COLUMN context JSON NULL;
  END IF;

  -- Add index on 'event' if missing
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_log'
      AND INDEX_NAME = 'idx_activity_log_event'
  ) THEN
    ALTER TABLE activity_log ADD INDEX idx_activity_log_event (event);
  END IF;

  -- Add index on 'created_at' if missing
  -- Note: schema.sql already creates idx_log_date on created_at; skip if any index covers it.
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'activity_log'
      AND INDEX_NAME = 'idx_activity_log_created_at'
  ) THEN
    -- Only add if no existing index on created_at with a different name
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'activity_log'
        AND COLUMN_NAME = 'created_at'
        AND INDEX_NAME != 'PRIMARY'
    ) THEN
      ALTER TABLE activity_log ADD INDEX idx_activity_log_created_at (created_at);
    END IF;
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_007();
DROP PROCEDURE IF EXISTS _d8tl_migrate_007;

INSERT IGNORE INTO schema_migrations (version) VALUES ('007');
