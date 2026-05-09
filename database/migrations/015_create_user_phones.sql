-- Migration: 015_create_user_phones.sql
-- Date: 2026-05-09
-- Description: Creates user_phones table for multi-phone support (primary/secondary).
-- Affected tables: user_phones (CREATE)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS user_phones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  phone VARCHAR(20) NOT NULL,
  type ENUM('Home','Work','Cell') NOT NULL,
  role ENUM('primary','secondary') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_phones_user_role (user_id, role),
  INDEX idx_user_phones_user_id (user_id),
  CONSTRAINT fk_user_phones_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('015');
