-- Email Notification System - MVP Implementation
-- Creates the remaining tables needed for email notifications

USE moc835_d8tl_prod;

-- Email Recipients Table
-- Defines who receives each type of email notification
CREATE TABLE IF NOT EXISTS email_recipients (
    recipient_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    recipient_type ENUM('Team_Based', 'Static_To', 'Static_CC', 'Static_BCC') NOT NULL,
    recipient_source ENUM('Home_Team_Manager', 'Away_Team_Manager', 'Both_Team_Managers', 'Static_Email', 'Assigned_Umpires', 'Assigned_Umpire_1', 'Assigned_Umpire_2', 'League_Contacts', 'League_Contact') NOT NULL,
    email_address VARCHAR(255) NULL, -- Only for static recipients
    league_official_id INT NULL, -- Only for a specific league contact recipient
    is_active BOOLEAN DEFAULT TRUE,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template_name (template_name),
    INDEX idx_active (is_active),
    FOREIGN KEY (template_name) REFERENCES email_templates(template_name) ON UPDATE CASCADE
);

-- Email Queue Table
-- Stores emails to be sent (simple queue for MVP)
CREATE TABLE IF NOT EXISTS email_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    to_addresses JSON NOT NULL,
    cc_addresses JSON NULL,
    bcc_addresses JSON NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    game_id INT NULL,
    schedule_change_id INT NULL,
    status ENUM('Pending', 'Sent', 'Failed', 'Cancelled') DEFAULT 'Pending',
    scheduled_send_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_time TIMESTAMP NULL,
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled_send (scheduled_send_time),
    INDEX idx_game_id (game_id),
    INDEX idx_schedule_change_id (schedule_change_id),
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_change_id) REFERENCES schedule_change_requests(request_id) ON DELETE CASCADE
);

-- SMTP Configuration Table
-- Stores email server settings (only one active config allowed)
CREATE TABLE IF NOT EXISTS smtp_configuration (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_user VARCHAR(255) NOT NULL,
    smtp_password TEXT NOT NULL, -- Will be encrypted
    use_ssl BOOLEAN DEFAULT FALSE,
    use_tls BOOLEAN DEFAULT TRUE,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT 'District 8 Travel League',
    reply_to_email VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_tested_date TIMESTAMP NULL,
    last_test_result ENUM('Success', 'Failed') NULL,
    test_error_message TEXT NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);

-- Insert default recipients for existing templates
INSERT IGNORE INTO email_recipients (template_name, recipient_type, recipient_source) VALUES
-- Schedule Change Request - notify admins and team managers
('onScheduleChangeRequest', 'Team_Based', 'Both_Team_Managers'),
('onScheduleChangeRequest', 'Static_To', 'Static_Email'),

-- Schedule Change Approve - notify team managers and requester
('onScheduleChangeApprove', 'Team_Based', 'Both_Team_Managers'),

-- Game Score Update - notify team managers
('onGameScoreUpdate', 'Team_Based', 'Both_Team_Managers'),
('onGameScoreUpdate', 'Static_CC', 'Static_Email'),

-- Game Addition - notify team managers
('onGameAddition', 'Team_Based', 'Both_Team_Managers'),
('onGameAddition', 'Static_CC', 'Static_Email');

-- Insert default SMTP configuration (to be updated by admin)
INSERT IGNORE INTO smtp_configuration (smtp_host, smtp_port, smtp_user, smtp_password, from_email, from_name, is_active) VALUES
('smtp.gmail.com', 587, 'your-email@gmail.com', 'ENCRYPTED_PASSWORD_PLACEHOLDER', 'noreply@district8league.com', 'District 8 Travel League', FALSE);

-- Add some helpful indexes for performance (MySQL doesn't support IF NOT EXISTS for indexes)
-- These will fail silently if indexes already exist
ALTER TABLE email_templates ADD INDEX idx_active_status (is_active);
ALTER TABLE email_templates ADD INDEX idx_name_lookup (template_name);
