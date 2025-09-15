<?php
/**
 * District 8 Travel League - Invitation Manager
 * 
 * Manages user invitations for the new user accounts system
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/UserAccountEmailService.php';

class InvitationManager {
    private $db;
    private $emailService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->emailService = new UserAccountEmailService();
    }
    
    /**
     * Send invitation to create user account
     */
    public function sendInvitation($email, $roleId, $invitedBy) {
        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address format");
            }
            
            // Check if email already exists in users table
            $existingUser = $this->db->fetchOne(
                "SELECT id FROM users WHERE email = ?",
                [$email]
            );
            
            if ($existingUser) {
                throw new Exception("A user with this email address already exists");
            }
            
            // Check if there's already a pending invitation
            $existingInvitation = $this->db->fetchOne(
                "SELECT id FROM user_invitations WHERE email = ? AND status = 'pending' AND expires_at > NOW()",
                [$email]
            );
            
            if ($existingInvitation) {
                throw new Exception("A pending invitation already exists for this email address");
            }
            
            // Validate role exists
            $role = $this->db->fetchOne("SELECT * FROM roles WHERE id = ?", [$roleId]);
            if (!$role) {
                throw new Exception("Invalid role specified");
            }
            
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));
            
            // Insert invitation record
            $invitationId = $this->db->insert('user_invitations', [
                'email' => $email,
                'token' => $token,
                'role_id' => $roleId,
                'invited_by' => $invitedBy,
                'expires_at' => $expiresAt
            ]);
            
            // Get inviter information
            $inviter = $this->db->fetchOne(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$invitedBy]
            );
            $inviterName = $inviter ? 
                $inviter['first_name'] . ' ' . $inviter['last_name'] : 
                'System Administrator';
            
            // Send invitation email
            $emailSent = $this->emailService->sendInvitation(
                $email,
                $token,
                $role['name'],
                $inviterName
            );
            
            if (!$emailSent) {
                throw new Exception("Failed to send invitation email");
            }
            
            Logger::info("User invitation sent", [
                'invitation_id' => $invitationId,
                'email' => $email,
                'role_id' => $roleId,
                'invited_by' => $invitedBy
            ]);
            
            return [
                'success' => true,
                'invitation_id' => $invitationId,
                'token' => $token,
                'expires_at' => $expiresAt
            ];
            
        } catch (Exception $e) {
            Logger::error("Failed to send invitation", [
                'email' => $email,
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate invitation token
     */
    public function validateInvitation($token) {
        $invitation = $this->db->fetchOne(
            "SELECT ui.*, r.name as role_name 
             FROM user_invitations ui 
             JOIN roles r ON ui.role_id = r.id 
             WHERE ui.token = ? AND ui.status = 'pending'",
            [$token]
        );
        
        if (!$invitation) {
            return ['valid' => false, 'error' => 'Invalid invitation token'];
        }
        
        if (strtotime($invitation['expires_at']) < time()) {
            // Mark as expired
            $this->db->update(
                'user_invitations',
                ['status' => 'expired'],
                'id = :id',
                ['id' => $invitation['id']]
            );
            
            return ['valid' => false, 'error' => 'Invitation has expired'];
        }
        
        return ['valid' => true, 'invitation' => $invitation];
    }
    
    /**
     * Complete invitation (mark as used)
     */
    public function completeInvitation($token, $userId) {
        try {
            $invitation = $this->db->fetchOne(
                "SELECT * FROM user_invitations WHERE token = ? AND status = 'pending'",
                [$token]
            );
            
            if (!$invitation) {
                throw new Exception("Invalid invitation token");
            }
            
            // Mark invitation as completed
            $this->db->update(
                'user_invitations',
                [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $invitation['id']]
            );
            
            Logger::info("Invitation completed", [
                'invitation_id' => $invitation['id'],
                'user_id' => $userId,
                'email' => $invitation['email']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Failed to complete invitation", [
                'token' => $token,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Resend invitation
     */
    public function resendInvitation($invitationId, $resendBy) {
        try {
            $invitation = $this->db->fetchOne(
                "SELECT ui.*, r.name as role_name 
                 FROM user_invitations ui 
                 JOIN roles r ON ui.role_id = r.id 
                 WHERE ui.id = ? AND ui.status = 'pending'",
                [$invitationId]
            );
            
            if (!$invitation) {
                throw new Exception("Invitation not found or already completed");
            }
            
            // Update expiration date
            $newExpiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));
            $this->db->update(
                'user_invitations',
                ['expires_at' => $newExpiresAt],
                'id = :id',
                ['id' => $invitationId]
            );
            
            // Get resender information
            $resender = $this->db->fetchOne(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$resendBy]
            );
            $resenderName = $resender ? 
                $resender['first_name'] . ' ' . $resender['last_name'] : 
                'System Administrator';
            
            // Resend email
            $emailSent = $this->emailService->sendInvitation(
                $invitation['email'],
                $invitation['token'],
                $invitation['role_name'],
                $resenderName
            );
            
            if (!$emailSent) {
                throw new Exception("Failed to resend invitation email");
            }
            
            Logger::info("Invitation resent", [
                'invitation_id' => $invitationId,
                'email' => $invitation['email'],
                'resent_by' => $resendBy
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Failed to resend invitation", [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Cancel invitation
     */
    public function cancelInvitation($invitationId) {
        try {
            $invitation = $this->db->fetchOne(
                "SELECT * FROM user_invitations WHERE id = ? AND status = 'pending'",
                [$invitationId]
            );
            
            if (!$invitation) {
                throw new Exception("Invitation not found or already processed");
            }
            
            // Mark as expired (cancelled)
            $this->db->update(
                'user_invitations',
                ['status' => 'expired'],
                'id = :id',
                ['id' => $invitationId]
            );
            
            Logger::info("Invitation cancelled", [
                'invitation_id' => $invitationId,
                'email' => $invitation['email']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Failed to cancel invitation", [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get all invitations with pagination
     */
    public function getInvitations($limit = 50, $offset = 0, $filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "ui.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['role_id'])) {
            $where[] = "ui.role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['email'])) {
            $where[] = "ui.email LIKE ?";
            $params[] = '%' . $filters['email'] . '%';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT ui.*, r.name as role_name,
                       u.first_name as inviter_first_name, u.last_name as inviter_last_name
                FROM user_invitations ui
                JOIN roles r ON ui.role_id = r.id
                LEFT JOIN users u ON ui.invited_by = u.id
                $whereClause
                ORDER BY ui.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get invitation count
     */
    public function getInvitationCount($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['role_id'])) {
            $where[] = "role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['email'])) {
            $where[] = "email LIKE ?";
            $params[] = '%' . $filters['email'] . '%';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM user_invitations $whereClause", $params);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Clean up expired invitations
     */
    public function cleanupExpiredInvitations() {
        $result = $this->db->query(
            "UPDATE user_invitations SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()"
        );
        
        $affectedRows = $result->rowCount();
        
        if ($affectedRows > 0) {
            Logger::info("Cleaned up expired invitations", ['count' => $affectedRows]);
        }
        
        return $affectedRows;
    }
}
