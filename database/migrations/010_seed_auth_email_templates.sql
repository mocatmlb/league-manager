-- Migration: 010_seed_auth_email_templates.sql
-- Date: 2026-05-06
-- Description: Seeds the email_templates table with the four auth/registration
--              templates required by Epic 3:
--
--                - registration_verification        (user gets verify link)
--                - registration_account_verified    (admin notified of new active user)
--                - registration_invitation          (admin invites a coach)
--                - auth_password_reset              (user gets password-reset link)
--
--              Without these rows, EmailService::triggerNotification* logs an
--              error and returns false, which RegistrationService /
--              InvitationService convert into a thrown RuntimeException.
--              Result: registration, verification, invitation, and password
--              reset are all non-functional in production.
--
--              These templates use the new triggerNotificationToAddress()
--              path (recipient supplied per-call), so no email_recipients
--              rows are needed.
--
--              The {variable} placeholders are substituted by
--              EmailService::processTemplate() at send time.
--
-- Affected tables: email_templates (INSERT IGNORE)
-- Idempotent: Yes (UNIQUE template_name + INSERT IGNORE)
-- Compatibility: MySQL 8.0 / MariaDB

INSERT IGNORE INTO email_templates (template_name, subject_template, body_template, is_active) VALUES

-- Sent to a newly-registered user with a link to verify their email.
-- Context: {first_name}, {email}, {verification_link}, {token}, {user_id}
('registration_verification',
 'Verify your District 8 Travel League account',
 'Hello {first_name},

Welcome to the District 8 Travel League! Please verify your email address to activate your account.

Click the link below to verify (link expires in 48 hours):
{verification_link}

If you did not register for an account, you can safely ignore this email.

Best regards,
District 8 Travel League',
 1),

-- Operational notification to admin when a user verifies their account.
-- Context: {user_id}, {email}
('registration_account_verified',
 'New coach account verified — District 8 Travel League',
 'A new coach account has been verified.

User ID: {user_id}
Email: {email}
Verified: {current_date}

This is an automated notification.',
 1),

-- Sent to a coach when an admin invites them to register.
-- Context: {email}, {invitation_link}, {expires_at}
('registration_invitation',
 'You are invited to the District 8 Travel League',
 'Hello,

You have been invited to create a coach account for the District 8 Travel League.

Click the link below to register (link expires {expires_at}):
{invitation_link}

If you have any questions, please contact league administration.

Best regards,
District 8 Travel League',
 1),

-- Sent to a user who requested a password reset.
-- Context: {first_name}, {email}, {reset_link}, {token}, {user_id}
('auth_password_reset',
 'Reset your District 8 Travel League password',
 'Hello {first_name},

We received a request to reset the password for your District 8 Travel League account.

Click the link below to set a new password (link expires in 24 hours):
{reset_link}

If you did not request a password reset, you can safely ignore this email and your password will remain unchanged.

Best regards,
District 8 Travel League',
 1);

INSERT IGNORE INTO schema_migrations (version) VALUES ('010');
