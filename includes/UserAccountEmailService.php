<?php
/**
 * District 8 Travel League - User Account Email Service
 * 
 * Email service specifically for user accounts system notifications
 * including invitations, registrations, password resets, etc.
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Check if autoloader exists (for PHPMailer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class UserAccountEmailService {
    private $db;
    private $mailer;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->initializeMailer();
    }
    
    /**
     * Initialize PHPMailer with SMTP settings
     */
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Get SMTP settings from database
            $smtpSettings = $this->getSmtpSettings();
            
            if ($smtpSettings && !empty($smtpSettings['smtp_host'])) {
                // Server settings
                $this->mailer->isSMTP();
                $this->mailer->Host = $smtpSettings['smtp_host'];
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $smtpSettings['smtp_username'];
                $this->mailer->Password = $smtpSettings['smtp_password'];
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $this->mailer->Port = intval($smtpSettings['smtp_port'] ?: 587);
                
                // Set from address
                $this->mailer->setFrom(
                    $smtpSettings['smtp_from_email'] ?: 'noreply@district8league.com',
                    $smtpSettings['smtp_from_name'] ?: 'District 8 Travel League'
                );
            } else {
                // Fallback to PHP mail() function
                $this->mailer->isMail();
                $this->mailer->setFrom('noreply@district8league.com', 'District 8 Travel League');
            }
            
            $this->mailer->isHTML(true);
            
        } catch (PHPMailerException $e) {
            Logger::error("Failed to initialize mailer", ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get SMTP settings from database
     */
    private function getSmtpSettings() {
        $settings = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'"
        );
        
        $smtpConfig = [];
        foreach ($settings as $setting) {
            $smtpConfig[$setting['setting_key']] = $setting['setting_value'];
        }
        
        return $smtpConfig;
    }
    
    /**
     * Send user invitation email
     */
    public function sendInvitation($email, $token, $roleName, $invitedBy) {
        try {
            $invitationLink = $this->buildInvitationLink($token);
            $expirationDate = date('F j, Y', strtotime('+14 days'));
            
            $data = [
                'invitation_link' => $invitationLink,
                'expiration_date' => $expirationDate,
                'role_name' => $roleName,
                'invited_by' => $invitedBy
            ];
            
            $result = $this->sendTemplateEmail(
                $email,
                'user_invitation',
                $data
            );
            
            if ($result) {
                Logger::info("Invitation email sent", [
                    'email' => $email,
                    'role' => $roleName,
                    'invited_by' => $invitedBy
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send invitation email", [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send registration completion email
     */
    public function sendRegistrationComplete($user) {
        try {
            $data = [
                'first_name' => $user['first_name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role_name' => $user['role_name'] ?? 'User'
            ];
            
            $result = $this->sendTemplateEmail(
                $user['email'],
                'user_registration_complete',
                $data
            );
            
            if ($result) {
                Logger::info("Registration complete email sent", [
                    'user_id' => $user['id'],
                    'email' => $user['email']
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send registration complete email", [
                'user_id' => $user['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($user, $resetToken) {
        try {
            $resetLink = $this->buildPasswordResetLink($resetToken);
            
            $data = [
                'first_name' => $user['first_name'],
                'reset_link' => $resetLink
            ];
            
            $result = $this->sendTemplateEmail(
                $user['email'],
                'password_reset_request',
                $data
            );
            
            if ($result) {
                Logger::info("Password reset email sent", [
                    'user_id' => $user['id'],
                    'email' => $user['email']
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send password reset email", [
                'user_id' => $user['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send account status change notification
     */
    public function sendAccountStatusChange($user, $newStatus, $message = '') {
        try {
            $statusMessages = [
                'active' => 'Your account has been activated and you can now log in.',
                'disabled' => 'Your account has been disabled. Please contact an administrator if you believe this is an error.',
                'unverified' => 'Your account is pending verification.'
            ];
            
            $statusMessage = $message ?: ($statusMessages[$newStatus] ?? '');
            
            $data = [
                'first_name' => $user['first_name'],
                'new_status' => ucfirst($newStatus),
                'status_message' => $statusMessage
            ];
            
            $result = $this->sendTemplateEmail(
                $user['email'],
                'account_status_change',
                $data
            );
            
            if ($result) {
                Logger::info("Account status change email sent", [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'new_status' => $newStatus
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send account status change email", [
                'user_id' => $user['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send email using template
     */
    private function sendTemplateEmail($to, $templateName, $data) {
        try {
            // Get template from database
            $template = $this->db->fetchOne(
                "SELECT subject_template, body_template FROM email_templates WHERE template_name = ? AND is_active = 1",
                [$templateName]
            );
            
            if (!$template) {
                throw new Exception("Email template not found: $templateName");
            }
            
            // Replace variables in subject and body
            $subject = $this->replaceVariables($template['subject_template'], $data);
            $body = $this->replaceVariables($template['body_template'], $data);
            
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Set subject and body
            $this->mailer->Subject = $subject;
            $this->mailer->Body = nl2br(htmlspecialchars($body));
            $this->mailer->AltBody = strip_tags($body);
            
            // Send email
            $result = $this->mailer->send();
            
            // Log email attempt
            $this->logEmail($to, $subject, $templateName, $result);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send template email", [
                'template' => $templateName,
                'recipient' => $to,
                'error' => $e->getMessage()
            ]);
            
            // Log failed email attempt
            $this->logEmail($to, $subject ?? 'Unknown', $templateName, false, $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Replace variables in template content
     */
    private function replaceVariables($content, $data) {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }
    
    /**
     * Build invitation link
     */
    private function buildInvitationLink($token) {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/register.php?token=' . urlencode($token);
    }
    
    /**
     * Build password reset link
     */
    private function buildPasswordResetLink($token) {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/public/reset-password.php?token=' . urlencode($token);
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    /**
     * Log email attempt
     */
    private function logEmail($recipient, $subject, $template, $success, $errorMessage = null) {
        try {
            $this->db->insert('email_log', [
                'recipient' => $recipient,
                'subject' => $subject,
                'template' => $template,
                'status' => $success ? 'sent' : 'failed',
                'error_message' => $success ? null : ($errorMessage ?: 'Email sending failed')
            ]);
        } catch (Exception $e) {
            Logger::error("Failed to log email", ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get email log for a specific recipient
     */
    public function getEmailLog($recipient, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM email_log WHERE recipient = ? ORDER BY sent_at DESC LIMIT ?",
            [$recipient, $limit]
        );
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats($days = 30) {
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
             FROM email_log 
             WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        
        return [
            'total' => $stats['total_emails'] ?? 0,
            'sent' => $stats['sent_count'] ?? 0,
            'failed' => $stats['failed_count'] ?? 0,
            'success_rate' => $stats['total_emails'] > 0 ? 
                round(($stats['sent_count'] / $stats['total_emails']) * 100, 2) : 0
        ];
    }
}
