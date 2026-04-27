-- District 8 Travel League - MVP Database Schema
-- MySQL Database Schema for MVP Application

-- Create database
CREATE DATABASE IF NOT EXISTS moc835_d8tl_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moc835_d8tl_prod;

-- Programs table (top level - sports programs)
CREATE TABLE programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(100) NOT NULL,
    program_code VARCHAR(10) NOT NULL UNIQUE,
    sport_type VARCHAR(50) NOT NULL,
    age_min INT,
    age_max INT,
    default_season_type ENUM('Spring', 'Summer', 'Fall', 'Year-round') DEFAULT 'Spring',
    game_format TEXT,
    active_status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seasons table (time-bound instances of programs)
CREATE TABLE seasons (
    season_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    season_name VARCHAR(100) NOT NULL,
    season_year YEAR NOT NULL,
    start_date DATE,
    end_date DATE,
    registration_start DATE,
    registration_end DATE,
    season_status ENUM('Planning', 'Registration', 'Active', 'Completed', 'Archived') DEFAULT 'Planning',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
    INDEX idx_season_program (program_id),
    INDEX idx_season_status (season_status)
);

-- Divisions table (organizational groupings within seasons)
CREATE TABLE divisions (
    division_id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    division_name VARCHAR(100) NOT NULL,
    division_code VARCHAR(10),
    max_teams INT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(season_id) ON DELETE CASCADE,
    INDEX idx_division_season (season_id)
);

-- Teams table (participating teams)
CREATE TABLE teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    division_id INT,
    league_name VARCHAR(100) NOT NULL,
    team_name VARCHAR(100),
    manager_first_name VARCHAR(50) NOT NULL,
    manager_last_name VARCHAR(50) NOT NULL,
    manager_phone VARCHAR(20),
    manager_email VARCHAR(100),
    home_field VARCHAR(100),
    home_field_5070 VARCHAR(100),
    avail_weekend BOOLEAN DEFAULT TRUE,
    avail_weekday_4pm BOOLEAN DEFAULT FALSE,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active_status ENUM('Active', 'Inactive', 'Withdrawn') DEFAULT 'Active',
    registration_fee_paid BOOLEAN DEFAULT FALSE,
    late_fee_applied BOOLEAN DEFAULT FALSE,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(season_id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(division_id) ON DELETE SET NULL,
    INDEX idx_team_season (season_id),
    INDEX idx_team_division (division_id),
    INDEX idx_team_manager_email (manager_email)
);

-- Locations table (field locations)
CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    address VARCHAR(200),
    city VARCHAR(50),
    state VARCHAR(20),
    zip_code VARCHAR(10),
    gps_coordinates VARCHAR(50),
    notes TEXT,
    active_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location_name (location_name),
    INDEX idx_location_status (active_status)
);

-- Games table (individual games)
CREATE TABLE games (
    game_id INT AUTO_INCREMENT PRIMARY KEY,
    game_number VARCHAR(20) NOT NULL UNIQUE,
    season_id INT NOT NULL,
    division_id INT,
    home_team_id INT NOT NULL,
    away_team_id INT NOT NULL,
    home_score INT DEFAULT NULL,
    away_score INT DEFAULT NULL,
    game_status ENUM(
        'Active',
        'Created',
        'Scheduled',
        'Pending Change',
        'Completed',
        'Cancelled',
        'Postponed'
    ) DEFAULT 'Active',
    score_submitted_by VARCHAR(50),
    score_submitted_at TIMESTAMP NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(season_id) ON DELETE CASCADE,
    FOREIGN KEY (division_id) REFERENCES divisions(division_id) ON DELETE SET NULL,
    FOREIGN KEY (home_team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (away_team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    INDEX idx_game_season (season_id),
    INDEX idx_game_teams (home_team_id, away_team_id),
    INDEX idx_game_status (game_status)
);

-- Schedules table (date/time/location assignments for games)
CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    game_date DATE NOT NULL,
    game_time TIME,
    location VARCHAR(100),
    location_id INT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(location_id) ON DELETE SET NULL,
    INDEX idx_schedule_game (game_id),
    INDEX idx_schedule_date (game_date),
    INDEX idx_schedule_location (location_id)
);

-- Schedule change requests table
CREATE TABLE schedule_change_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    requested_by VARCHAR(100),
    request_type ENUM('Reschedule', 'Cancel', 'Location Change') NOT NULL,
    original_date DATE,
    original_time TIME,
    original_location VARCHAR(100),
    requested_date DATE,
    requested_time TIME,
    requested_location VARCHAR(100),
    reason TEXT,
    request_status ENUM('Pending', 'Approved', 'Denied') DEFAULT 'Pending',
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    INDEX idx_request_game (game_id),
    INDEX idx_request_status (request_status)
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    password_changed_at TIMESTAMP NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admin_username (username)
);

-- Settings table (application configuration)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Documents table (uploaded files)
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT,
    file_type VARCHAR(50),
    is_public BOOLEAN DEFAULT TRUE,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_document_public (is_public),
    INDEX idx_document_upload_date (upload_date)
);

-- Activity log table (audit trail)
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('admin', 'coach', 'public') NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_user (user_id, user_type),
    INDEX idx_log_action (action),
    INDEX idx_log_date (created_at)
);

-- Email templates table
CREATE TABLE email_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    subject_template TEXT NOT NULL,
    body_template TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_template_name (template_name),
    INDEX idx_template_active (is_active)
);

-- Insert default data

-- Default admin user (password: admin)
INSERT INTO admin_users (username, password, email, first_name, last_name) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@district8league.com', 'System', 'Administrator');

-- Default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('coaches_password', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hashed coaches password (default: coaches)'),
('league_name', 'District 8 Travel League', 'Official league name'),
('current_season_id', '1', 'ID of the currently active season'),
('smtp_host', '', 'SMTP server hostname'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username'),
('smtp_password', '', 'SMTP password'),
('smtp_from_email', '', 'From email address'),
('smtp_from_name', 'District 8 Travel League', 'From name for emails');

-- Sample program
INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max) 
VALUES ('Junior Baseball', 'JR', 'Baseball', 9, 12);

-- Sample season
INSERT INTO seasons (program_id, season_name, season_year, season_status) 
VALUES (1, '2024 Junior Baseball Spring', 2024, 'Active');

-- Sample division
INSERT INTO divisions (season_id, division_name, division_code) 
VALUES (1, 'American League', 'AL');

-- Sample locations
INSERT INTO locations (location_name, address, city, state) VALUES
('Central Park Field 1', '123 Park Ave', 'Hometown', 'NY'),
('Riverside Complex Field A', '456 River Rd', 'Riverside', 'NY'),
('Memorial Stadium', '789 Memorial Dr', 'Hometown', 'NY');

-- Default email templates
INSERT INTO email_templates (template_name, subject_template, body_template) VALUES
('onScheduleChangeRequest', 'Schedule Change Request - Game {game_number}', 'A schedule change has been requested for Game {game_number} on {game_date}.\n\nDetails:\nHome Team: {home_team}\nAway Team: {away_team}\nRequested by: {requested_by}\nReason: {reason}\n\nPlease review this request in the admin console.'),
('onScheduleChangeApprove', 'Schedule Change Approved - Game {game_number}', 'The schedule change for Game {game_number} has been approved.\n\nNew Details:\nDate: {new_date}\nTime: {new_time}\nLocation: {new_location}\n\nPlease update your calendars accordingly.'),
('onGameScoreUpdate', 'Game Score Submitted - Game {game_number}', 'A score has been submitted for Game {game_number}.\n\nFinal Score:\n{away_team}: {away_score}\n{home_team}: {home_score}\n\nStandings have been updated automatically.');