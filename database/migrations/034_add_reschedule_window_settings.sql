-- Migration: 034_add_reschedule_window_settings.sql
-- Description: Adds reschedule_cutoff_date to seasons and seeds hour-window settings.
--
-- Affected tables: seasons (ALTER, conditional), settings (INSERT/UPDATE)
-- Idempotent: Yes (guarded by information_schema check + ON DUPLICATE KEY)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_034;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_034()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'seasons'
          AND COLUMN_NAME  = 'reschedule_cutoff_date'
    ) THEN
        ALTER TABLE seasons
            ADD COLUMN reschedule_cutoff_date DATE NULL DEFAULT NULL
                COMMENT 'Latest date a coach may request a reschedule to; NULL = no cutoff';
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_034();
DROP PROCEDURE IF EXISTS _d8tl_migrate_034;

-- Seed global hour-window settings (0 = disabled)
INSERT INTO settings (setting_key, setting_value, description)
VALUES
    ('reschedule_pre_game_hours',  '0', 'Hours before game start during which coaches cannot submit reschedule requests (0 = disabled)'),
    ('reschedule_post_game_hours', '0', 'Hours after game start during which coaches can still submit reschedule requests (0 = disabled)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO schema_migrations (version) VALUES ('034');
