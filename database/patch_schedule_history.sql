-- Add schedule_history + views (safe on brownfield DBs that have schedules but no history yet).
-- Idempotent: CREATE TABLE IF NOT EXISTS; INSERT only where missing per game.

USE moc835_d8tl_prod;

CREATE TABLE IF NOT EXISTS schedule_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    schedule_type ENUM('Original', 'Changed') NOT NULL DEFAULT 'Original',
    game_date DATE NOT NULL,
    game_time TIME NOT NULL,
    location VARCHAR(100) NOT NULL,
    change_request_id INT NULL,
    created_by_type ENUM('System', 'Admin', 'Coach') DEFAULT 'System',
    created_by_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_current BOOLEAN DEFAULT FALSE,
    notes TEXT,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (change_request_id) REFERENCES schedule_change_requests(request_id) ON DELETE SET NULL,
    INDEX idx_game_version (game_id, version_number),
    INDEX idx_game_current (game_id, is_current),
    INDEX idx_change_request (change_request_id)
);

INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location, is_current, created_at, notes)
SELECT
    s.game_id,
    1,
    'Original',
    s.game_date,
    COALESCE(s.game_time, '00:00:00'),
    COALESCE(NULLIF(TRIM(s.location), ''), 'TBD'),
    TRUE,
    s.created_date,
    'Backfill from schedules (patch_schedule_history.sql)'
FROM schedules s
WHERE NOT EXISTS (SELECT 1 FROM schedule_history sh WHERE sh.game_id = s.game_id);

CREATE OR REPLACE VIEW current_schedules AS
SELECT
    sh.game_id,
    sh.game_date,
    sh.game_time,
    sh.location,
    sh.version_number,
    sh.schedule_type,
    sh.created_at AS modified_date
FROM schedule_history sh
WHERE sh.is_current = TRUE;

CREATE OR REPLACE VIEW original_schedules AS
SELECT
    sh.game_id,
    sh.game_date AS original_date,
    sh.game_time AS original_time,
    sh.location AS original_location,
    sh.created_at
FROM schedule_history sh
WHERE sh.version_number = 1 AND sh.schedule_type = 'Original';
