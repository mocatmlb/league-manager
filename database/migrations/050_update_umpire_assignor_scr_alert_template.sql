-- Migration 050: Activate umpire assignor SCR alert template
-- Story 24.6: Assignor SCR alert when published assignments are released

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'umpire_assignor_scr_alert',
  'SCR Alert: Umpires Released — Game {game_number}',
  '<p>Hello {assignor_name},</p>
<p>An approved schedule change request ({trigger_event_ref}) released published umpire assignments for game {game_number}.</p>
<p><strong>Original game:</strong><br>
Date: {original_game_date}<br>
Time: {original_game_time}<br>
Field: {original_location}<br>
Division: {division_name}<br>
Teams: {away_team} at {home_team}</p>
<p><strong>New game details:</strong><br>
Date: {new_game_date}<br>
Time: {new_game_time}<br>
Field: {new_location}</p>
<p><strong>Released umpires:</strong> {released_umpires}</p>
<p>The rescheduled game needs manual reassignment. Published assignments remain cancelled and are not transferred automatically.</p>
<p>Reply to this email if you need help coordinating coverage.</p>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template = VALUES(body_template),
  is_active = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('050');
