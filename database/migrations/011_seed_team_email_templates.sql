-- Migration: 011_seed_team_email_templates.sql
-- Date: 2026-05-07
-- Description: Seeds operational email templates for Epic 4 team flows:
--                - team_registration_approved (coach notified when pending reg is approved)
--                - team_assignment_notification (coach notified on admin assign)
--                - team_removal_notification (coach notified when assignment removed)
--
--              Uses triggerNotificationToAddress(); no email_recipients rows needed.
--
-- Affected tables: email_templates (INSERT IGNORE)
-- Idempotent: Yes (UNIQUE template_name + INSERT IGNORE)
-- Compatibility: MySQL 8.0 / MariaDB

INSERT IGNORE INTO email_templates (template_name, subject_template, body_template, is_active) VALUES

('team_registration_approved',
 'Your team registration was approved — District 8 Travel League',
 'Hello {first_name},

Your team registration has been approved. You are now set up as Team Owner for your team in the District 8 Travel League.

If you have questions, please contact league administration.

Best regards,
District 8 Travel League',
 1),

('team_assignment_notification',
 'You have been assigned to a team — District 8 Travel League',
 'Hello {first_name},

An administrator has assigned you to a team (team ID {team_id}) in the District 8 Travel League.

If you did not expect this change, please contact league administration.

Best regards,
District 8 Travel League',
 1),

('team_removal_notification',
 'Your team assignment was removed — District 8 Travel League',
 'Hello {first_name},

Your assignment to team ID {team_id} has been removed by an administrator.

If you have questions, please contact league administration.

Best regards,
District 8 Travel League',
 1);

INSERT IGNORE INTO schema_migrations (version) VALUES ('011');
