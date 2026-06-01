-- Extend request_type ENUM to include Postponement
ALTER TABLE schedule_change_requests
  MODIFY COLUMN request_type ENUM('Reschedule', 'Cancel', 'Location Change', 'Postponement') NOT NULL;

-- Seed postponement_auto_approve setting (default ON)
INSERT IGNORE INTO settings (setting_key, setting_value, description)
VALUES (
    'postponement_auto_approve',
    '1',
    'When 1, coach postponement requests immediately set game_status=Postponed (auto-approved). When 0, requests require admin approval in the schedule change queue.'
);

INSERT IGNORE INTO schema_migrations (version) VALUES ('035');
