-- Migration 041: Create umpire assignment tables and seed roles
-- Creates: game_umpire_assignments, umpire_pending_notifications
-- Seeds: umpire and umpire_assignor roles
-- Seeds: 4 umpire assignment settings
-- Seeds: 3 placeholder email templates for umpire notifications

-- Table: game_umpire_assignments
CREATE TABLE IF NOT EXISTS game_umpire_assignments (
  assignment_id       INT AUTO_INCREMENT PRIMARY KEY,
  game_id             INT NOT NULL,
  umpire_user_id      INT NULL,
  slot_index          TINYINT UNSIGNED NOT NULL,
  slot_type           VARCHAR(32) NOT NULL DEFAULT 'general',
  assignment_status   ENUM('Open','Draft','Published','Declined','Cancelled') NOT NULL DEFAULT 'Open',
  published           TINYINT(1) NOT NULL DEFAULT 0,
  migration_mode      TINYINT(1) NOT NULL DEFAULT 0,
  assigned_by_user_id INT NULL,
  assigned_at         DATETIME NULL,
  last_notified_at    DATETIME NULL,
  last_notified_hash  CHAR(64) NULL,
  notes               TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  FOREIGN KEY (umpire_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_game_slot (game_id, slot_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: umpire_pending_notifications
CREATE TABLE IF NOT EXISTS umpire_pending_notifications (
  notification_id   INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id     INT NOT NULL,
  umpire_user_id    INT NOT NULL,
  notification_type ENUM('assignment_published','cascade_cancelled','assignor_alert') NOT NULL,
  trigger_event_ref VARCHAR(128) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at           DATETIME NULL,
  failed_at         DATETIME NULL,
  failure_reason    TEXT NULL,
  FOREIGN KEY (assignment_id) REFERENCES game_umpire_assignments(assignment_id) ON DELETE CASCADE,
  FOREIGN KEY (umpire_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pending_unsent (sent_at, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed roles
INSERT IGNORE INTO roles (name, description) VALUES
  ('umpire_assignor', 'Umpire assignor — access to assignment board, roster, and publish actions; no game/score write or admin settings'),
  ('umpire', 'Umpire — read access to own published assignments portal only');

-- Seed umpire assignment settings
INSERT INTO settings (setting_key, setting_value, description)
VALUES
  ('unassigned_queue_days',        '14',      'Days ahead to show games in unassigned queue (0 = no date filter)'),
  ('umpire_decline_lockout_hours', '48',      'Hours before game start within which umpire decline is blocked'),
  ('umpire_slot_1_label',          'Umpire 1','Label for umpire slot 1 in assignment drawer and emails'),
  ('umpire_slot_2_label',          'Umpire 2','Label for umpire slot 2 in assignment drawer and emails')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Seed placeholder email templates (bodies are stubs — filled in Story 23.4/23.5/24.2)
INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES ('umpire_assignment_published', 'D8 Assignment: {game_date} {game_time} — {slot_label}', '[Stub — implemented in Story 23.4]', 0),
       ('umpire_cascade_cancelled',    'Assignment Released: {game_date} {game_time}',            '[Stub — implemented in Story 23.5]', 0),
       ('umpire_decline_alert',        'Umpire Declined: {game_date} {game_time} — {slot_label}', 'Umpire {umpire_name} declined the {slot_label} assignment for {division_name} on {game_date} at {game_time} at {location}. There are {hours_until_game_start} hours remaining before game start. Please reassign this slot from the umpire assignment queue.', 1)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template = VALUES(body_template),
  is_active = VALUES(is_active);

-- Record migration completion
INSERT IGNORE INTO schema_migrations (version) VALUES ('041');
