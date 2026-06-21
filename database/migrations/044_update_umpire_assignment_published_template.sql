-- Migration 044: Activate umpire assignment published template and queue Reply-To metadata
-- Story 23.4: Publish assignments and email notification

SET @db_name := DATABASE();

SET @reply_to_email_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'email_queue'
    AND COLUMN_NAME = 'reply_to_email'
);
SET @reply_to_email_sql := IF(
  @reply_to_email_exists = 0,
  'ALTER TABLE email_queue ADD COLUMN reply_to_email VARCHAR(255) NULL AFTER schedule_change_id',
  'SELECT 1'
);
PREPARE stmt FROM @reply_to_email_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reply_to_name_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'email_queue'
    AND COLUMN_NAME = 'reply_to_name'
);
SET @reply_to_name_sql := IF(
  @reply_to_name_exists = 0,
  'ALTER TABLE email_queue ADD COLUMN reply_to_name VARCHAR(255) NULL AFTER reply_to_email',
  'SELECT 1'
);
PREPARE stmt FROM @reply_to_name_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'umpire_assignment_published',
  'D8 Assignment: {game_date} {game_time} — {slot_label}',
  '<p>Hello {umpire_name},</p>
<p>Your District 8 umpire assignment has been published.</p>
<p><strong>Game:</strong> {game_number}<br>
<strong>Date:</strong> {game_date}<br>
<strong>Time:</strong> {game_time}<br>
<strong>Field:</strong> {location}<br>
<strong>Division:</strong> {division_name}<br>
<strong>Teams:</strong> {away_team} at {home_team}<br>
<strong>Assignment:</strong> {slot_label}<br>
<strong>Fee:</strong> {fee_per_team}</p>
<p><strong>Assignor:</strong> {assignor_name}<br>
<strong>Email:</strong> {assignor_email}<br>
<strong>Phone:</strong> <a href="{assignor_phone_tel}">{assignor_phone}</a></p>
<p>Please reply to this email if you have questions about the assignment.</p>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template = VALUES(body_template),
  is_active = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('044');
