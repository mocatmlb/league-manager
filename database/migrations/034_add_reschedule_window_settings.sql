-- Add per-season reschedule cutoff date column
ALTER TABLE seasons
    ADD COLUMN reschedule_cutoff_date DATE NULL DEFAULT NULL
        COMMENT 'Latest date a coach may request a reschedule to; NULL = no cutoff';

-- Seed global hour-window settings (0 = disabled)
INSERT INTO settings (setting_key, setting_value, description)
VALUES
    ('reschedule_pre_game_hours',  '0', 'Hours before game start during which coaches cannot submit reschedule requests (0 = disabled)'),
    ('reschedule_post_game_hours', '0', 'Hours after game start during which coaches can still submit reschedule requests (0 = disabled)')
ON DUPLICATE KEY UPDATE description = VALUES(description);
