-- Migration: 038_create_league_special_dates.sql
-- Description: Creates league_special_dates table for admin-managed calendar markers.
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS league_special_dates (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    season_id     INT NULL DEFAULT NULL,
    date          DATE NOT NULL,
    label         VARCHAR(100) NOT NULL,
    date_type     ENUM('milestone', 'holiday', 'deadline', 'other') NOT NULL DEFAULT 'other',
    display_color VARCHAR(7) NOT NULL DEFAULT '#475569',
    created_by    INT NULL,
    created_date  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(season_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_special_dates_season (season_id),
    INDEX idx_special_dates_date (date)
);

INSERT IGNORE INTO schema_migrations (version) VALUES ('038');
