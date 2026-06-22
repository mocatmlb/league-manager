-- Migration 049: Activate umpire decline alert template
-- Story 24.2: Umpire decline workflow

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'umpire_decline_alert',
  'Umpire Declined: {game_date} {game_time} — {slot_label}',
  '<p>Hello Assignor,</p>
<p>{umpire_name} declined the {slot_label} assignment.</p>
<p><strong>Game:</strong><br>
Date: {game_date}<br>
Time: {game_time}<br>
Field: {location}<br>
Division: {division_name}<br>
Assignment: {slot_label}<br>
Hours until game start: {hours_until_game_start}</p>
<p>The slot is now available in the unassigned queue for reassignment.</p>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template = VALUES(body_template),
  is_active = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('049');
