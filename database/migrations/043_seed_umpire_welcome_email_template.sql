-- Migration 043: Seed umpire_account_welcome email template
-- Description: Welcome email sent when a new umpire account is created outside migration mode

INSERT INTO email_templates (template_name, subject_template, body_template, is_active)
VALUES (
  'umpire_account_welcome',
  'Welcome to D8 Travel League — Your Umpire Account',
  '<p>Hi {first_name},</p>
<p>Your umpire account has been created for D8 Travel League.</p>
<p><strong>Email:</strong> {email}<br>
<strong>Temporary Password:</strong> {temp_password}</p>
<p>Please log in at <a href="{login_url}">{login_url}</a> and change your password when prompted.</p>
<p>If you have questions, contact your assignor.</p>',
  1
)
ON DUPLICATE KEY UPDATE
  subject_template = VALUES(subject_template),
  body_template    = VALUES(body_template),
  is_active        = VALUES(is_active);

INSERT IGNORE INTO schema_migrations (version) VALUES ('043');
