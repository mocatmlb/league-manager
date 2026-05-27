-- Update onScheduleChangeApprove email template to show both original and new schedule
-- Idempotent: creates template when missing, updates when present
INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
    'onScheduleChangeApprove',
    'Schedule Change Approved - Game {game_number}',
    'The schedule change for Game {game_number} has been approved.

PREVIOUS SCHEDULE:
Date: {original_date}
Time: {original_time}
Location: {original_location}

NEW SCHEDULE:
Date: {new_date}
Time: {new_time}
Location: {new_location}

Please update your calendars accordingly.

If you have questions, contact league administration.',
    1
)
ON DUPLICATE KEY UPDATE
    subject_template = VALUES(subject_template),
    body_template = VALUES(body_template),
    is_active = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('029');
