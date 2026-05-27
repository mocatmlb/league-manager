-- Add onScheduleChangeDeny email template and recipient configuration
-- Idempotent: template upsert + conditional recipient insert
INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
    'onScheduleChangeDeny',
    'Schedule Change Request Denied - Game {game_number}',
    'Your schedule change request for Game {game_number} has been denied.

GAME: {away_team} @ {home_team}

REQUESTED CHANGE:
Date: {new_date}
Time: {new_time}
Location: {new_location}

DENIAL REASON:
{admin_comment}

The original schedule remains in effect:
Date: {original_date}
Time: {original_time}
Location: {original_location}

If you have questions, please contact league administration.',
    1
)
ON DUPLICATE KEY UPDATE
    subject_template = VALUES(subject_template),
    body_template = VALUES(body_template),
    is_active = VALUES(is_active);

INSERT INTO email_recipients (template_name, recipient_type, recipient_source)
SELECT 'onScheduleChangeDeny', 'Team_Based', 'Both_Team_Managers'
WHERE NOT EXISTS (
    SELECT 1
    FROM email_recipients
    WHERE template_name = 'onScheduleChangeDeny'
      AND recipient_type = 'Team_Based'
      AND recipient_source = 'Both_Team_Managers'
);

INSERT IGNORE INTO schema_migrations (version) VALUES ('030');
