-- Migration: 018_update_verification_email_html.sql
-- Date: 2026-05-10
-- Description: Updates the registration_verification email template to use
--              an HTML layout with a styled "Click To Verify Email" button,
--              proper spacing/signature separation, and a raw-link fallback
--              below the signature for clients that don't render HTML buttons.
--
--              Uses ON DUPLICATE KEY UPDATE so this is safe to re-run.
--
-- Affected tables: email_templates (UPDATE via INSERT ... ON DUPLICATE KEY)
-- Idempotent: Yes
-- Compatibility: MySQL 8.0 / MariaDB

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'registration_verification',
  'Verify your District 8 Travel League account',
  '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
  <h2 style="color:#1a1a1a;">Verify Your Email Address</h2>
  <p>Hello {first_name},</p>
  <p>Welcome to the District 8 Travel League! Please verify your email address to activate your account.</p>
  <p style="margin:30px 0;">
    <a href="{verification_link}"
       style="background-color:#0d6efd;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-size:16px;font-weight:bold;display:inline-block;">
      Click To Verify Email
    </a>
  </p>
  <p style="color:#555;font-size:14px;">This link expires in 48 hours. If you did not register for an account, you can safely ignore this email.</p>
  <hr style="border:none;border-top:1px solid #ddd;margin:24px 0;">
  <p style="color:#333;">Best regards,<br>District 8 Travel League</p>
  <p style="font-size:12px;color:#888;">If the button above doesn''t work, copy and paste this link into your browser:<br>{verification_link}</p>
</div>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template    = VALUES(body_template),
  is_active        = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('018');
