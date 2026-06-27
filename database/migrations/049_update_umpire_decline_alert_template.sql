-- Migration 049: Activate umpire decline alert template
-- Story 24.2: Umpire decline workflow
-- Note: Migration 041 created stub row; this migration updates it with real content

UPDATE email_templates
SET
  subject_template = 'Umpire Declined: {game_date} {game_time} — {slot_label}',
  body_template = '<p>Hello Assignor,</p>
<p>{umpire_name} declined the {slot_label} assignment.</p>
<p><strong>Game:</strong><br>
Date: {game_date}<br>
Time: {game_time}<br>
Field: {location}<br>
Division: {division_name}<br>
Assignment: {slot_label}<br>
Hours until game start: {hours_until_game_start}</p>
<p>The slot is now available in the unassigned queue for reassignment.</p>',
  is_active = 1
WHERE template_name = 'umpire_decline_alert';

INSERT IGNORE INTO schema_migrations (version) VALUES ('049');
