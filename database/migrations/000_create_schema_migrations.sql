-- Migration: 000_create_schema_migrations.sql
-- Date: 2026-05-05
-- Description: Creates the schema_migrations tracking table used to record
--              which migrations have been applied to this database.
-- Affected tables: schema_migrations (CREATE)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS, INSERT IGNORE)

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(20) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version) VALUES ('000');
