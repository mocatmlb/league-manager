-- Migration: 016_add_force_password_change_and_session_invalidated.sql
-- Description: Adds force_password_change and session_invalidated_at columns to users table
-- Story 8.1: UserManagementService Full CRUD

ALTER TABLE users
  ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN session_invalidated_at DATETIME NULL DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (version) VALUES ('016');
