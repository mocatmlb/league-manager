-- Migration: 031_add_sms_opt_in_to_users.sql
-- Description: Adds sms_opt_in column to users table for text message preferences

ALTER TABLE users
  ADD COLUMN sms_opt_in TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO schema_migrations (version) VALUES ('031');
