-- onSchedulePostponed template
INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
    'onSchedulePostponed',
    'Game {game_number} Postponed — District 8 Travel League',
    'Game {game_number} has been postponed.

GAME: {away_team} @ {home_team}

ORIGINAL SCHEDULE:
Date: {game_date}
Time: {game_time}
Location: {location}

REASON: {reason}

The game will be rescheduled. You will be notified once a new date is confirmed.

If you have questions, please contact league administration.',
    1
)
ON DUPLICATE KEY UPDATE
    subject_template = VALUES(subject_template),
    body_template    = VALUES(body_template),
    is_active        = VALUES(is_active);

-- onSchedulePostponed recipients: both team coaches
INSERT INTO email_recipients (template_name, recipient_type, recipient_source)
SELECT 'onSchedulePostponed', 'Team_Based', 'Both_Team_Managers'
WHERE NOT EXISTS (
    SELECT 1 FROM email_recipients
    WHERE template_name    = 'onSchedulePostponed'
      AND recipient_type   = 'Team_Based'
      AND recipient_source = 'Both_Team_Managers'
);

-- onScheduleCancellation template
INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
    'onScheduleCancellation',
    'Game {game_number} Cancelled — District 8 Travel League',
    'Game {game_number} has been cancelled.

GAME: {away_team} @ {home_team}

SCHEDULED DATE:
Date: {game_date}
Time: {game_time}
Location: {location}

REASON: {reason}

If you have questions, please contact league administration.',
    1
)
ON DUPLICATE KEY UPDATE
    subject_template = VALUES(subject_template),
    body_template    = VALUES(body_template),
    is_active        = VALUES(is_active);

-- onScheduleCancellation recipients: both team coaches
INSERT INTO email_recipients (template_name, recipient_type, recipient_source)
SELECT 'onScheduleCancellation', 'Team_Based', 'Both_Team_Managers'
WHERE NOT EXISTS (
    SELECT 1 FROM email_recipients
    WHERE template_name    = 'onScheduleCancellation'
      AND recipient_type   = 'Team_Based'
      AND recipient_source = 'Both_Team_Managers'
);

INSERT IGNORE INTO schema_migrations (version) VALUES ('037');
