-- Fix for Schedule Change History Problem
-- This creates a proper schedule versioning system

USE moc835_d8tl_prod;

-- Create schedule_history table to track all schedule versions
CREATE TABLE IF NOT EXISTS schedule_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    schedule_type ENUM('Original', 'Changed') NOT NULL DEFAULT 'Original',
    game_date DATE NOT NULL,
    game_time TIME NOT NULL,
    location VARCHAR(100) NOT NULL,
    change_request_id INT NULL, -- Links to the request that caused this change
    created_by_type ENUM('System', 'Admin', 'Coach') DEFAULT 'System',
    created_by_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_current BOOLEAN DEFAULT FALSE, -- Only one record per game should be current
    notes TEXT,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (change_request_id) REFERENCES schedule_change_requests(request_id) ON DELETE SET NULL,
    INDEX idx_game_version (game_id, version_number),
    INDEX idx_game_current (game_id, is_current),
    INDEX idx_change_request (change_request_id)
);

-- Migrate existing schedule data to history table
-- First, insert all current schedules as "Original" version 1
INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location, is_current, created_at, notes)
SELECT 
    s.game_id,
    1 as version_number,
    'Original' as schedule_type,
    s.game_date,
    s.game_time,
    s.location,
    TRUE as is_current,
    s.created_date,
    'Migrated from original schedules table'
FROM schedules s
WHERE NOT EXISTS (
    SELECT 1 FROM schedule_history sh WHERE sh.game_id = s.game_id
);

-- Now we need to reconstruct the history from approved schedule change requests
-- This is tricky because we've lost the original data, but we can make educated guesses

-- For games that have approved changes, we need to:
-- 1. Find the earliest change request for each game
-- 2. Use the "original_date/time/location" from that request as the true original
-- 3. Create history entries for each approved change

-- First, let's identify games that have been changed
DROP TEMPORARY TABLE IF EXISTS changed_games;
CREATE TEMPORARY TABLE changed_games AS
SELECT DISTINCT game_id 
FROM schedule_change_requests 
WHERE request_status = 'Approved';

-- For each changed game, reconstruct the history
-- This is a complex operation, so we'll do it step by step

-- Step 1: Update the "Original" entries with the true original data from the first change request
UPDATE schedule_history sh
JOIN (
    SELECT 
        scr.game_id,
        scr.original_date,
        scr.original_time,
        scr.original_location,
        ROW_NUMBER() OVER (PARTITION BY scr.game_id ORDER BY scr.created_date ASC) as rn
    FROM schedule_change_requests scr
    WHERE scr.request_status = 'Approved'
) first_change ON sh.game_id = first_change.game_id AND first_change.rn = 1
SET 
    sh.game_date = first_change.original_date,
    sh.game_time = first_change.original_time,
    sh.location = first_change.original_location,
    sh.is_current = FALSE,
    sh.notes = 'True original schedule recovered from first change request'
WHERE sh.version_number = 1 
AND sh.schedule_type = 'Original'
AND sh.game_id IN (SELECT game_id FROM changed_games);

-- Step 2: Create history entries for each approved change
INSERT INTO schedule_history (
    game_id, 
    version_number, 
    schedule_type, 
    game_date, 
    game_time, 
    location, 
    change_request_id,
    created_by_type,
    created_by_id,
    created_at,
    is_current,
    notes
)
SELECT 
    scr.game_id,
    ROW_NUMBER() OVER (PARTITION BY scr.game_id ORDER BY scr.created_date ASC) + 1 as version_number,
    'Changed' as schedule_type,
    scr.requested_date,
    scr.requested_time,
    scr.requested_location,
    scr.request_id,
    'Admin' as created_by_type,
    scr.reviewed_by,
    scr.reviewed_at,
    FALSE as is_current, -- We'll set the last one to current in the next step
    CONCAT('Schedule changed via request #', scr.request_id, 
           CASE WHEN scr.review_notes IS NOT NULL 
                THEN CONCAT('. Notes: ', scr.review_notes) 
                ELSE '' END)
FROM schedule_change_requests scr
WHERE scr.request_status = 'Approved'
ORDER BY scr.game_id, scr.created_date;

-- Step 3: Mark the latest version of each game as current
UPDATE schedule_history sh1
JOIN (
    SELECT 
        game_id,
        MAX(version_number) as max_version
    FROM schedule_history
    GROUP BY game_id
) latest ON sh1.game_id = latest.game_id AND sh1.version_number = latest.max_version
SET sh1.is_current = TRUE;

-- Create a view for easy access to current schedules
CREATE OR REPLACE VIEW current_schedules AS
SELECT 
    sh.game_id,
    sh.game_date,
    sh.game_time,
    sh.location,
    sh.version_number,
    sh.schedule_type,
    sh.created_at as modified_date
FROM schedule_history sh
WHERE sh.is_current = TRUE;

-- Create a view for original schedules
CREATE OR REPLACE VIEW original_schedules AS
SELECT 
    sh.game_id,
    sh.game_date as original_date,
    sh.game_time as original_time,
    sh.location as original_location,
    sh.created_at
FROM schedule_history sh
WHERE sh.version_number = 1 AND sh.schedule_type = 'Original';

-- Add some helpful indexes
ALTER TABLE schedule_change_requests 
ADD INDEX idx_game_created (game_id, created_date);

-- Display summary of what we found
SELECT 
    'Migration Summary' as info,
    COUNT(DISTINCT sh.game_id) as total_games,
    COUNT(*) as total_schedule_versions,
    SUM(CASE WHEN sh.schedule_type = 'Original' THEN 1 ELSE 0 END) as original_schedules,
    SUM(CASE WHEN sh.schedule_type = 'Changed' THEN 1 ELSE 0 END) as changed_schedules,
    SUM(CASE WHEN sh.is_current = TRUE THEN 1 ELSE 0 END) as current_schedules
FROM schedule_history sh;

-- Show games with multiple schedule changes
SELECT 
    g.game_number,
    COUNT(sh.history_id) as total_versions,
    COUNT(CASE WHEN sh.schedule_type = 'Changed' THEN 1 END) as changes_made
FROM schedule_history sh
JOIN games g ON sh.game_id = g.game_id
GROUP BY g.game_id, g.game_number
HAVING total_versions > 1
ORDER BY changes_made DESC, g.game_number;
