-- Migration: 017_schema_integrity_fixes.sql
-- Date: 2026-05-10
-- Description: Fixes various schema issues reported in logs:
--              1. Missing team_registrations and team_locations tables.
--              2. remember_tokens.token column requiring a value when it should be nullable.
--              3. team_owners.assigned_by FK constraint failures with admin IDs.

-- 1. Missing Tables
CREATE TABLE IF NOT EXISTS team_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_locations (
    team_id INT NOT NULL,
    location_id INT NOT NULL,
    PRIMARY KEY (team_id, location_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(location_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Fix remember_tokens.token (make nullable)
DROP PROCEDURE IF EXISTS _d8tl_fix_remember_tokens;
DELIMITER $$
CREATE PROCEDURE _d8tl_fix_remember_tokens()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'remember_tokens' 
        AND COLUMN_NAME = 'token'
    ) THEN
        ALTER TABLE remember_tokens MODIFY token VARCHAR(100) NULL;
    END IF;
END$$
DELIMITER ;
CALL _d8tl_fix_remember_tokens();
DROP PROCEDURE IF EXISTS _d8tl_fix_remember_tokens;

-- 3. Fix team_owners.assigned_by (make nullable to avoid FK failure with admin IDs)
-- First drop existing FK if it exists to allow modification
DROP PROCEDURE IF EXISTS _d8tl_fix_team_owners_fk;
DELIMITER $$
CREATE PROCEDURE _d8tl_fix_team_owners_fk()
BEGIN
    -- Try to find the FK name. In user_accounts_schema.sql it's not named, so MySQL auto-names it (likely team_owners_ibfk_3)
    DECLARE fk_name VARCHAR(100);
    
    SELECT CONSTRAINT_NAME INTO fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'team_owners'
    AND COLUMN_NAME = 'assigned_by'
    AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1;

    IF fk_name IS NOT NULL THEN
        SET @s = CONCAT('ALTER TABLE team_owners DROP FOREIGN KEY ', fk_name);
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- Modify column to be nullable
    ALTER TABLE team_owners MODIFY assigned_by INT NULL;
    
    -- Re-add FK as nullable-friendly (SET NULL on delete or just leave it without ON DELETE)
    ALTER TABLE team_owners ADD CONSTRAINT fk_team_owners_assigned_by 
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;
END$$
DELIMITER ;
CALL _d8tl_fix_team_owners_fk();
DROP PROCEDURE IF EXISTS _d8tl_fix_team_owners_fk;

-- Similar fix for team_officials if it exists
DROP PROCEDURE IF EXISTS _d8tl_fix_team_officials_fk;
DELIMITER $$
CREATE PROCEDURE _d8tl_fix_team_officials_fk()
BEGIN
    DECLARE fk_name VARCHAR(100);
    
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_officials') THEN
        SELECT CONSTRAINT_NAME INTO fk_name
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'team_officials'
        AND COLUMN_NAME = 'assigned_by'
        AND REFERENCED_TABLE_NAME = 'users'
        LIMIT 1;

        IF fk_name IS NOT NULL THEN
            SET @s = CONCAT('ALTER TABLE team_officials DROP FOREIGN KEY ', fk_name);
            PREPARE stmt FROM @s;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
        
        ALTER TABLE team_officials MODIFY assigned_by INT NULL;
        ALTER TABLE team_officials ADD CONSTRAINT fk_team_officials_assigned_by 
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL _d8tl_fix_team_officials_fk();
DROP PROCEDURE IF EXISTS _d8tl_fix_team_officials_fk;

INSERT IGNORE INTO schema_migrations (version) VALUES ('017');
