-- Migration 016: Add force_password_change and session_invalidated_at columns to users table
-- Story 8.1: UserManagementService Full CRUD

INSERT INTO schema_migrations (version, name, applied_at)
SELECT '016', 'add_force_password_change_and_session_invalidated', NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM schema_migrations WHERE version = '016'
);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS session_invalidated_at DATETIME NULL DEFAULT NULL;
