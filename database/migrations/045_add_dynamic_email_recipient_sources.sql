-- Migration 045: Add dynamic email recipient sources for email templates
-- Allows email_recipients rows to target assigned umpire slots and league contacts.

ALTER TABLE email_recipients
  MODIFY recipient_source ENUM(
    'Home_Team_Manager',
    'Away_Team_Manager',
    'Both_Team_Managers',
    'Static_Email',
    'Assigned_Umpires',
    'Assigned_Umpire_1',
    'Assigned_Umpire_2',
    'League_Contacts',
    'League_Contact'
  ) NOT NULL;

SET @db_name := DATABASE();

SET @league_official_id_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'email_recipients'
    AND COLUMN_NAME = 'league_official_id'
);
SET @league_official_id_sql := IF(
  @league_official_id_exists = 0,
  'ALTER TABLE email_recipients ADD COLUMN league_official_id INT NULL AFTER email_address',
  'SELECT 1'
);
PREPARE stmt FROM @league_official_id_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (version) VALUES ('045');
