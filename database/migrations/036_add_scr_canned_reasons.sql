-- Seed default canned reasons for Schedule Change Requests
INSERT IGNORE INTO settings (setting_key, setting_value, description)
VALUES (
    'scr_postpone_reasons',
    '["Rain\/Weather","Field Unavailable","Team Emergency","Scheduling Conflict","Umpire Unavailable"]',
    'JSON array of canned reason options shown to coaches when submitting a game postponement.'
);

INSERT IGNORE INTO settings (setting_key, setting_value, description)
VALUES (
    'scr_reschedule_reasons',
    '["Rain\/Weather","Field Unavailable","Team Emergency","Scheduling Conflict","Rescheduled by League"]',
    'JSON array of canned reason options shown to coaches when submitting a reschedule request.'
);

INSERT IGNORE INTO schema_migrations (version) VALUES ('036');
