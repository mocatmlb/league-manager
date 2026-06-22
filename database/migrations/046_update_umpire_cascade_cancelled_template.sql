-- Migration 046: Activate umpire cascade release notification template
-- Story 23.5: release umpires when schedule/status changes cancel active assignments.

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'umpire_cascade_cancelled',
  'Assignment Released: {game_date} {game_time}',
  '<p>Hello {umpire_name},</p>
<p>You are released from your {slot_label} assignment for game {game_number}.</p>
<p><strong>Original game:</strong><br>
Date: {game_date}<br>
Time: {game_time}<br>
Field: {location}<br>
Division: {division_name}<br>
Teams: {away_team} at {home_team}<br>
Status: {game_status}</p>
<p>This message only releases you from the original assignment. It does not assign you to a new or rescheduled game. The assignor will create and publish any replacement assignment separately.</p>
<p>Questions? Reply to this email to contact {assignor_name} at {assignor_email}.</p>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template = VALUES(body_template),
  is_active = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('046');
