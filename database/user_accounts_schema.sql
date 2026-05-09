-- District 8 Travel League - User Accounts System Database Schema
-- Phase 1 Implementation - New tables for user accounts system
-- IMPORTANT: This does NOT modify existing tables (admin_users, settings)

-- Use existing database
USE moc835_d8tl_prod;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (new user accounts system)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('unverified', 'active', 'disabled') NOT NULL DEFAULT 'unverified',
    verification_token VARCHAR(100) NULL,
    verification_expiry DATETIME NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expiry DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_verification_token (verification_token),
    INDEX idx_password_reset_token (password_reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team owners junction table
CREATE TABLE IF NOT EXISTS team_owners (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team officials junction table
CREATE TABLE IF NOT EXISTS team_officials (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role change requests table
CREATE TABLE IF NOT EXISTS role_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    requested_role VARCHAR(50) NOT NULL,
    team_id INT NULL, -- NULL if requesting Team Owner, NOT NULL if requesting Team Official
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_requested_role (requested_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User invitations table
CREATE TABLE IF NOT EXISTS user_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    invited_by INT NOT NULL,
    status ENUM('pending', 'completed', 'expired') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (invited_by) REFERENCES users(id),
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity log table (separate from existing activity_log)
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log table (for new user account emails)
CREATE TABLE IF NOT EXISTS email_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template VARCHAR(100) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    sent_at DATETIME NOT NULL,
    error_message TEXT NULL,
    INDEX idx_template (template),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    INDEX idx_recipient (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember tokens table (for "remember me" functionality)
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login attempts table (for security tracking)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration tracking table
CREATE TABLE IF NOT EXISTS migration_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'coach') NOT NULL,
    legacy_identifier VARCHAR(100) NOT NULL,
    new_user_id INT NULL,
    migration_status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    migrated_at DATETIME NULL,
    migration_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (new_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_type),
    INDEX idx_legacy_identifier (legacy_identifier),
    INDEX idx_migration_status (migration_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT IGNORE INTO roles (name, description) VALUES
('user', 'Default user role with basic permissions'),
('team_owner', 'Team owner with team management permissions'),
('team_official', 'Team official with limited team permissions'),
('administrator', 'Full system administrator with all permissions');

-- Insert default permissions
INSERT IGNORE INTO permissions (name, description) VALUES
-- Basic permissions
('view_public_content', 'View public schedules, standings, and information'),
('view_profile', 'View own user profile'),
('edit_profile', 'Edit own user profile'),
('change_password', 'Change own password'),

-- Team permissions
('request_team_owner_role', 'Request to become a team owner'),
('request_team_official_role', 'Request to become a team official'),
('register_team', 'Register a new team as team owner'),
('manage_team_officials', 'Manage team officials for owned teams'),
('submit_schedule_change', 'Submit schedule change requests'),
('input_game_scores', 'Input or edit game scores'),

-- Administrative permissions
('view_all_users', 'View all user accounts'),
('create_user', 'Create new user accounts'),
('edit_user', 'Edit user account information'),
('delete_user', 'Delete user accounts'),
('manage_user_status', 'Enable/disable user accounts'),
('send_invitations', 'Send user invitations'),
('manage_roles', 'Manage user roles and permissions'),
('view_audit_logs', 'View system audit logs'),
('manage_system_settings', 'Manage system configuration'),

-- Full admin permissions
('full_admin_access', 'Complete administrative access to all features');

-- Assign permissions to roles
INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'user' AND p.name IN (
    'view_public_content', 'view_profile', 'edit_profile', 'change_password',
    'request_team_owner_role', 'request_team_official_role'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'team_owner' AND p.name IN (
    'view_public_content', 'view_profile', 'edit_profile', 'change_password',
    'request_team_owner_role', 'request_team_official_role', 'register_team',
    'manage_team_officials', 'submit_schedule_change', 'input_game_scores'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'team_official' AND p.name IN (
    'view_public_content', 'view_profile', 'edit_profile', 'change_password',
    'request_team_owner_role', 'request_team_official_role', 'submit_schedule_change', 'input_game_scores'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'administrator';

-- Add new email templates for user accounts system
INSERT IGNORE INTO email_templates (template_name, subject_template, body_template) VALUES
('user_invitation', 'Invitation to District 8 Travel League', 
'Hello,

You have been invited to create an account for the District 8 Travel League system.

To complete your registration, please click the following link:
{invitation_link}

This invitation will expire on {expiration_date}.

If you have any questions, please contact us.

Best regards,
District 8 Travel League Administration'),

('user_registration_complete', 'Account Registration Successful', 
'Hello {first_name},

Your account registration for the District 8 Travel League system has been completed successfully.

Account Details:
- Username: {username}
- Email: {email}
- Role: {role_name}

You can now log in to the system using your username and password.

Best regards,
District 8 Travel League Administration'),

('password_reset_request', 'Password Reset Request', 
'Hello {first_name},

A password reset has been requested for your District 8 Travel League account.

To reset your password, please click the following link:
{reset_link}

This link will expire in 1 hour. If you did not request this password reset, please ignore this email.

Best regards,
District 8 Travel League Administration'),

('account_status_change', 'Account Status Update', 
'Hello {first_name},

Your District 8 Travel League account status has been updated.

New Status: {new_status}
{status_message}

If you have any questions, please contact the system administrator.

Best regards,
District 8 Travel League Administration');
