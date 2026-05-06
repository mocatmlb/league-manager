-- Migration: 001_add_league_list.sql
-- Date: 2026-05-05
-- Description: Creates the league_list table used to populate the coach
--              registration form's league dropdown. Admin-managed.
-- Affected tables: league_list (CREATE)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS, INSERT IGNORE)

CREATE TABLE IF NOT EXISTS league_list (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(100) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_league_list_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('001');
