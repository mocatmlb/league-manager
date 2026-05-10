-- Migration: 017_add_teams_rejected_status_and_rejection_email.sql
-- Date: 2026-05-10
-- Description: (1) Adds 'rejected' to teams.status ENUM so admins can reject pending
--                  registrations (Story 11.4).
--              (2) Seeds the team_registration_rejected email template.
-- Affected tables: teams (ALTER), email_templates (INSERT IGNORE)
-- Idempotent: Yes
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_017;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_017()
BEGIN
  -- Only alter if 'rejected' is not already in the ENUM
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'teams'
      AND COLUMN_NAME  = 'status'
      AND COLUMN_TYPE LIKE '%rejected%'
  ) THEN
    ALTER TABLE teams
      MODIFY COLUMN status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'active';
  END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_017();
DROP PROCEDURE IF EXISTS _d8tl_migrate_017;

INSERT IGNORE INTO email_templates (template_name, subject_template, body_template, is_active) VALUES
('team_registration_rejected',
 'Your team registration was not approved — District 8 Travel League',
 'Hello {first_name},

We regret to inform you that your team registration for {team_name} in {season_name} was not approved.

{reason}

If you have questions or would like to reapply, please contact league administration.

Best regards,
District 8 Travel League',
 1);

INSERT IGNORE INTO schema_migrations (version) VALUES ('017');
