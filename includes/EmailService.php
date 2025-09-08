<?php
/**
 * District 8 Travel League - Email Notification Service
 * 
 * Handles email template processing, recipient resolution, and email sending
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
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        // Make sure Logger class is loaded
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/Logger.php';
        }
    }
    
    /**
     * Trigger an email notification
     * 
     * @param string $templateName The email template to use
     * @param array $context Context data for template variables
     * @return bool Success status
     */
    public function triggerNotification($templateName, $context = []) {
        try {
            // Get the email template
            $template = $this->getEmailTemplate($templateName);
            if (!$template) {
                throw new Exception("Email template '{$templateName}' not found");
            }
            
            if (!$template['is_active']) {
                Logger::info("Email template '{$templateName}' is inactive, skipping notification");
                return true; // Not an error, just inactive
            }
            
            // Get recipients for this template
            $recipients = $this->resolveRecipients($templateName, $context);
            if (empty($recipients['to']) && empty($recipients['cc']) && empty($recipients['bcc'])) {
                Logger::warn("No recipients found for template '{$templateName}'");
                return true; // Not an error, just no recipients
            }
            
            // Process template variables
            $processedSubject = $this->processTemplate($template['subject_template'], $context);
            $processedBody = $this->processTemplate($template['body_template'], $context);
            
            // Queue the email
            $queueId = $this->queueEmail([
                'template_name' => $templateName,
                'to_addresses' => json_encode($recipients['to']),
                'cc_addresses' => json_encode($recipients['cc']),
                'bcc_addresses' => json_encode($recipients['bcc']),
                'subject' => $processedSubject,
                'body' => $processedBody,
                'game_id' => $context['game_id'] ?? null,
                'schedule_change_id' => $context['schedule_change_id'] ?? null
            ]);
            
            // For MVP, process the email immediately (no background jobs)
            $this->processQueuedEmail($queueId);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Email notification failed for template '{$templateName}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template by name
     */
    private function getEmailTemplate($templateName) {
        return $this->db->fetchOne(
            "SELECT * FROM email_templates WHERE template_name = ?",
            [$templateName]
        );
    }
    
    /**
     * Resolve recipients based on template configuration and context
     */
    private function resolveRecipients($templateName, $context) {
        $recipients = ['to' => [], 'cc' => [], 'bcc' => []];
        
        // Get recipient configurations for this template
        $recipientConfigs = $this->db->fetchAll(
            "SELECT * FROM email_recipients WHERE template_name = ? AND is_active = 1",
            [$templateName]
        );
        
        foreach ($recipientConfigs as $config) {
            $emails = $this->resolveRecipientEmails($config, $context);
            
            // Add emails to appropriate recipient type
            switch ($config['recipient_type']) {
                case 'Team_Based':
                case 'Static_To':
                    $recipients['to'] = array_merge($recipients['to'], $emails);
                    break;
                case 'Static_CC':
                    $recipients['cc'] = array_merge($recipients['cc'], $emails);
                    break;
                case 'Static_BCC':
                    $recipients['bcc'] = array_merge($recipients['bcc'], $emails);
                    break;
            }
        }
        
        // Remove duplicates and empty values
        $recipients['to'] = array_unique(array_filter($recipients['to']));
        $recipients['cc'] = array_unique(array_filter($recipients['cc']));
        $recipients['bcc'] = array_unique(array_filter($recipients['bcc']));
        
        return $recipients;
    }
    
    /**
     * Resolve individual recipient emails based on source type
     */
    private function resolveRecipientEmails($config, $context) {
        $emails = [];
        
        switch ($config['recipient_source']) {
            case 'Static_Email':
                if (!empty($config['email_address'])) {
                    $emails[] = $config['email_address'];
                }
                break;
                
            case 'Home_Team_Manager':
                if (isset($context['game_id'])) {
                    $email = $this->getTeamManagerEmail($context['game_id'], 'home');
                    if ($email) $emails[] = $email;
                }
                break;
                
            case 'Away_Team_Manager':
                if (isset($context['game_id'])) {
                    $email = $this->getTeamManagerEmail($context['game_id'], 'away');
                    if ($email) $emails[] = $email;
                }
                break;
                
            case 'Both_Team_Managers':
                if (isset($context['game_id'])) {
                    $homeEmail = $this->getTeamManagerEmail($context['game_id'], 'home');
                    $awayEmail = $this->getTeamManagerEmail($context['game_id'], 'away');
                    if ($homeEmail) $emails[] = $homeEmail;
                    if ($awayEmail) $emails[] = $awayEmail;
                }
                break;
        }
        
        return $emails;
    }
    
    /**
     * Get team manager email for a specific game
     */
    private function getTeamManagerEmail($gameId, $teamType) {
        $teamField = $teamType === 'home' ? 'home_team_id' : 'away_team_id';
        
        // Build the query safely with the field name
        $sql = "
            SELECT t.manager_email 
            FROM games g 
            JOIN teams t ON g.{$teamField} = t.team_id 
            WHERE g.game_id = ? AND t.manager_email IS NOT NULL AND t.manager_email != ''
        ";
        
        $result = $this->db->fetchOne($sql, [$gameId]);
        
        return $result ? $result['manager_email'] : null;
    }
    
    /**
     * Process template with variable substitution
     */
    private function processTemplate($template, $context) {
        // Get schedule change data if schedule_change_id is provided
        if (isset($context['schedule_change_id'])) {
            $changeData = $this->getScheduleChangeData($context['schedule_change_id']);
            $context = array_merge($context, $changeData);
            
            // Also get the game data for the schedule change
            if (isset($changeData['game_id'])) {
                $gameData = $this->getGameData($changeData['game_id']);
                $context = array_merge($context, $gameData);
            }
        }
        
        // Get game data if game_id is provided (and not already loaded from schedule change)
        if (isset($context['game_id']) && !isset($context['game_number'])) {
            $gameData = $this->getGameData($context['game_id']);
            $context = array_merge($context, $gameData);
        }
        
        // Add system variables
        $context['current_date'] = date('Y-m-d H:i:s');
        $context['league_name'] = 'District 8 Travel League';
        
        // Add game status display name
        if (isset($context['game_status'])) {
            $statusMap = [
                'Active' => 'Active',
                'Completed' => 'Completed',
                'Cancelled' => 'Cancelled',
                'Postponed' => 'Postponed'
            ];
            $context['game_status'] = $statusMap[$context['game_status']] ?? $context['game_status'];
        }
        
        // Replace variables in template
        $processed = $template;
        foreach ($context as $key => $value) {
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }
    
    /**
     * Get game data for template variables
     */
    private function getGameData($gameId) {
        $game = $this->db->fetchOne("
            SELECT g.*, sch.game_date, sch.game_time, sch.location,
                   ht.team_name as home_team, 
                   at.team_name as away_team,
                   s.season_name,
                   d.division_name
            FROM games g
            LEFT JOIN schedules sch ON g.game_id = sch.game_id
            JOIN teams ht ON g.home_team_id = ht.team_id
            JOIN teams at ON g.away_team_id = at.team_id
            JOIN seasons s ON g.season_id = s.season_id
            LEFT JOIN divisions d ON g.division_id = d.division_id
            WHERE g.game_id = ?
        ", [$gameId]);
        
        if (!$game) return [];
        
        return [
            'game_number' => $game['game_number'],
            'game_date' => $game['game_date'] ? date('m/d/Y', strtotime($game['game_date'])) : 'TBD',
            'game_time' => $game['game_time'] ? date('g:i A', strtotime($game['game_time'])) : 'TBD',
            'home_team' => $game['home_team'],
            'away_team' => $game['away_team'],
            'location' => $game['location'] ?? 'TBD',
            'home_score' => $game['home_score'] ?? 0,
            'away_score' => $game['away_score'] ?? 0,
            'game_status' => $game['game_status'] ?? 'Active',
            'season_name' => $game['season_name'],
            'division_name' => $game['division_name']
        ];
    }
    
    /**
     * Get schedule change data for template variables
     */
    private function getScheduleChangeData($scheduleChangeId) {
        $change = $this->db->fetchOne("
            SELECT scr.*, g.game_number,
                   ht.team_name as home_team,
                   at.team_name as away_team
            FROM schedule_change_requests scr
            JOIN games g ON scr.game_id = g.game_id
            JOIN teams ht ON g.home_team_id = ht.team_id
            JOIN teams at ON g.away_team_id = at.team_id
            WHERE scr.request_id = ?
        ", [$scheduleChangeId]);
        
        if (!$change) return [];
        
        return [
            'game_id' => $change['game_id'],
            'change_request_id' => $change['request_id'],
            'change_type' => $change['request_type'],
            'requested_date' => $change['requested_date'] ? date('Y-m-d', strtotime($change['requested_date'])) : 'TBD',
            'requested_time' => $change['requested_time'] ? date('g:i A', strtotime($change['requested_time'])) : 'TBD',
            'new_date' => $change['requested_date'] ? date('F j, Y', strtotime($change['requested_date'])) : 'TBD',
            'new_time' => $change['requested_time'] ? date('g:i A', strtotime($change['requested_time'])) : 'TBD',
            'new_location' => $change['requested_location'] ?? 'TBD',
            'reason' => $change['reason'] ?? 'No reason provided',
            'requested_by' => $change['requested_by'] ?? 'Unknown',
            'admin_comment' => $change['review_notes'] ?? 'No comment provided',
            'approval_date' => $change['reviewed_at'] ? date('Y-m-d g:i A', strtotime($change['reviewed_at'])) : 'TBD',
            'reviewer_name' => 'Admin',
            'request_status' => $change['request_status'] ?? 'Pending',
            'submission_date' => $change['created_date'] ? date('Y-m-d g:i A', strtotime($change['created_date'])) : 'TBD'
        ];
    }
    
    /**
     * Queue an email for sending
     */
    private function queueEmail($emailData) {
        return $this->db->insert('email_queue', $emailData);
    }
    
    /**
     * Process a queued email (send it)
     */
    public function processQueuedEmail($queueId) {
        try {
            // Get the queued email
            $queuedEmail = $this->db->fetchOne(
                "SELECT * FROM email_queue WHERE queue_id = ? AND status = 'Pending'",
                [$queueId]
            );
            
            if (!$queuedEmail) {
                return false;
            }
            
            // Get SMTP configuration
            $smtpConfig = $this->getActiveSmtpConfig();
            if (!$smtpConfig) {
                throw new Exception("No active SMTP configuration found");
            }
            
            // Send the email
            $result = $this->sendEmail($queuedEmail, $smtpConfig);
            
            if ($result['success']) {
                // Mark as sent
                $this->db->update('email_queue', [
                    'status' => 'Sent',
                    'sent_time' => date('Y-m-d H:i:s'),
                    'error_message' => null
                ], 'queue_id = :queue_id', ['queue_id' => $queueId]);
                
                Logger::info("Email sent successfully: Queue ID {$queueId}");
                return true;
            } else {
                // Mark as failed
                $this->db->update('email_queue', [
                    'status' => 'Failed',
                    'error_message' => $result['error'],
                    'retry_count' => $queuedEmail['retry_count'] + 1
                ], 'queue_id = :queue_id', ['queue_id' => $queueId]);
                
                Logger::error("Email failed: Queue ID {$queueId}, Error: " . $result['error']);
                return false;
            }
            
        } catch (Exception $e) {
            Logger::error("Error processing queued email {$queueId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active SMTP configuration
     */
    private function getActiveSmtpConfig() {
        return $this->db->fetchOne(
            "SELECT * FROM smtp_configuration WHERE is_active = 1 LIMIT 1"
        );
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmail($queuedEmail, $smtpConfig) {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['smtp_user'];
            $mail->Password = $this->decryptPassword($smtpConfig['smtp_password']);
            $mail->SMTPSecure = $smtpConfig['use_ssl'] ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpConfig['smtp_port'];
            
            // Recipients
            $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
            
            if ($smtpConfig['reply_to_email']) {
                $mail->addReplyTo($smtpConfig['reply_to_email'], $smtpConfig['from_name']);
            }
            
            // Add TO recipients
            $toAddresses = json_decode($queuedEmail['to_addresses'], true) ?: [];
            foreach ($toAddresses as $email) {
                $mail->addAddress($email);
            }
            
            // Add CC recipients
            $ccAddresses = json_decode($queuedEmail['cc_addresses'], true) ?: [];
            foreach ($ccAddresses as $email) {
                $mail->addCC($email);
            }
            
            // Add BCC recipients
            $bccAddresses = json_decode($queuedEmail['bcc_addresses'], true) ?: [];
            foreach ($bccAddresses as $email) {
                $mail->addBCC($email);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $queuedEmail['subject'];
            $mail->Body = $queuedEmail['body']; // Allow HTML content
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $queuedEmail['body']));
            
            $mail->send();
            
            return ['success' => true, 'message_id' => $mail->getLastMessageID()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Simple password decryption (for MVP - in production use proper encryption)
     */
    private function decryptPassword($encryptedPassword) {
        // For MVP, we'll use base64 encoding (not secure, but functional)
        // In production, use proper encryption with openssl_decrypt
        if ($encryptedPassword === null || $encryptedPassword === 'ENCRYPTED_PASSWORD_PLACEHOLDER') {
            return ''; // Return empty for placeholder or null
        }
        
        return base64_decode($encryptedPassword);
    }
    
    /**
     * Simple password encryption (for MVP)
     */
    public static function encryptPassword($password) {
        // For MVP, we'll use base64 encoding (not secure, but functional)
        // In production, use proper encryption with openssl_encrypt
        return base64_encode($password);
    }
    
    /**
     * Test SMTP configuration
     */
    public function testSmtpConfiguration($config, $testEmail) {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_password']; // Plain password for testing
            $mail->SMTPSecure = $config['use_ssl'] ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp_port'];
            
            // Test message
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($testEmail);
            $mail->Subject = 'SMTP Configuration Test - District 8 Travel League';
            $mail->Body = 'This is a test email to verify SMTP configuration. If you receive this message, your email settings are working correctly.';
            
            $mail->send();
            
            return ['success' => true, 'message' => 'Test email sent successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send a test email with processed template content
     */
    public function sendTestEmail($toEmail, $subject, $body) {
        try {
            // Get active SMTP configuration
            $smtpConfig = $this->getActiveSmtpConfig();
            if (!$smtpConfig) {
                throw new Exception('No active SMTP configuration found');
            }
            
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['smtp_user'];
            $mail->Password = $this->decryptPassword($smtpConfig['smtp_password']);
            $mail->SMTPSecure = $smtpConfig['use_ssl'] ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpConfig['smtp_port'];
            
            // Recipients
            $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
            $mail->addAddress($toEmail);
            if (!empty($smtpConfig['reply_to_email'])) {
                $mail->addReplyTo($smtpConfig['reply_to_email'], $smtpConfig['from_name']);
            }
            
            // Content - Enable HTML
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Create plain text version for better compatibility
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            
            $mail->send();
            Logger::info("Test email sent successfully", ['to' => $toEmail, 'subject' => $subject]);
            return true;
        } catch (Exception $e) {
            Logger::error("Test email failed", ['error' => $e->getMessage(), 'to' => $toEmail]);
            return false;
        }
    }
    
    /**
     * Process pending emails in queue (for manual processing)
     */
    public function processPendingEmails($limit = 10) {
        $pendingEmails = $this->db->fetchAll(
            "SELECT queue_id FROM email_queue WHERE status = 'Pending' ORDER BY scheduled_send_time ASC LIMIT ?",
            [$limit]
        );
        
        $processed = 0;
        foreach ($pendingEmails as $email) {
            if ($this->processQueuedEmail($email['queue_id'])) {
                $processed++;
            }
        }
        
        return $processed;
    }
}
?>
