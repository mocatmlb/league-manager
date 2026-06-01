-- Migration: 033_drop_email_templates_updated_by_fk.sql
-- Date: 2026-05-29
-- Description: Drops the FK constraint on email_templates.updated_by that references
--              admin_users(id). Coach-type users granted the administrator role log in
--              via the users table, so their users.id gets stored in updated_by, which
--              violates the constraint and causes "Database query failed" on every
--              template save or toggle.
--
-- Affected tables: email_templates (DROP FOREIGN KEY, conditional)
-- Idempotent: Yes (guarded by information_schema check)
-- Compatibility: MySQL 8.0 / MariaDB

DROP PROCEDURE IF EXISTS _d8tl_migrate_033;

DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_033()
BEGIN
    DECLARE v_constraint VARCHAR(255) DEFAULT NULL;

    SELECT CONSTRAINT_NAME INTO v_constraint
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'email_templates'
      AND COLUMN_NAME  = 'updated_by'
      AND REFERENCED_TABLE_NAME = 'admin_users'
    LIMIT 1;

    IF v_constraint IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE email_templates DROP FOREIGN KEY `', v_constraint, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL _d8tl_migrate_033();
DROP PROCEDURE IF EXISTS _d8tl_migrate_033;

INSERT IGNORE INTO schema_migrations (version) VALUES ('033');
