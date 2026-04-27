-- Align games.game_status with application values (admin create uses Created/Scheduled, etc.).
-- Run once on databases created from an older schema.sql.
-- If this fails, check the server error log for the exact PDO message (Database::query masks it in the UI).

ALTER TABLE games MODIFY COLUMN game_status ENUM(
    'Active',
    'Created',
    'Scheduled',
    'Pending Change',
    'Completed',
    'Cancelled',
    'Postponed'
) NOT NULL DEFAULT 'Active';
