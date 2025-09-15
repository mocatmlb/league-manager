<?php
/**
 * District 8 Travel League - Legacy Authentication Manager
 * 
 * Wrapper for existing authentication system to maintain backward compatibility
 * during the transition to the new user accounts system.
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class LegacyAuthManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Authenticate using legacy system (admin_users table or coaches password)
     */
    public function authenticate($username, $password) {
        // Try admin authentication first
        $admin = $this->authenticateAdmin($username, $password);
        if ($admin) {
            return $admin;
        }
        
        // Try coach authentication
        $coach = $this->authenticateCoach($password);
        if ($coach) {
            return $coach;
        }
        
        return false;
    }
    
    /**
     * Authenticate admin user from legacy admin_users table
     */
    private function authenticateAdmin($username, $password) {
        $admin = $this->db->fetchOne(
            "SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1", 
            [$username, $username]
        );
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Update last login
            $this->db->update(
                'admin_users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                'id = :admin_id', 
                ['admin_id' => $admin['id']]
            );
            
            // Log successful login
            Logger::info("Legacy admin login successful", [
                'username' => $username, 
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'type' => 'legacy_admin',
                'role' => 'administrator',
                'auth_method' => 'legacy'
            ];
        }
        
        return false;
    }
    
    /**
     * Authenticate coach using legacy coaches password from settings
     */
    private function authenticateCoach($password) {
        $setting = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'coaches_password'"
        );
        $coachesPassword = $setting ? $setting['setting_value'] : DEFAULT_COACHES_PASSWORD;
        
        if (password_verify($password, $coachesPassword)) {
            Logger::info("Legacy coach login successful", [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return [
                'id' => 0, // No specific ID for shared coach account
                'username' => 'coach',
                'type' => 'legacy_coach',
                'role' => 'coach',
                'auth_method' => 'legacy'
            ];
        }
        
        return false;
    }
    
    /**
     * Check if user has permission in legacy system
     */
    public function hasPermission($user, $permission) {
        if (!$user || $user['auth_method'] !== 'legacy') {
            return false;
        }
        
        // Map new permissions to legacy roles
        switch ($user['role']) {
            case 'administrator':
                return true; // Admins have all permissions
                
            case 'coach':
                // Coach permissions in legacy system
                $coachPermissions = [
                    'view_public_content',
                    'submit_schedule_change',
                    'input_game_scores'
                ];
                return in_array($permission, $coachPermissions);
                
            default:
                // Public user permissions
                return $permission === 'view_public_content';
        }
    }
    
    /**
     * Get user info for legacy authenticated user
     */
    public function getCurrentUser() {
        if (!isset($_SESSION['user_type'])) {
            return null;
        }
        
        switch ($_SESSION['user_type']) {
            case 'admin':
                return [
                    'id' => $_SESSION['admin_id'] ?? 0,
                    'username' => $_SESSION['admin_username'] ?? 'admin',
                    'type' => 'legacy_admin',
                    'role' => 'administrator',
                    'auth_method' => 'legacy'
                ];
                
            case 'coach':
                return [
                    'id' => 0,
                    'username' => 'coach',
                    'type' => 'legacy_coach',
                    'role' => 'coach',
                    'auth_method' => 'legacy'
                ];
                
            default:
                return null;
        }
    }
    
    /**
     * Check if user is authenticated in legacy system
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_type'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['expires']) && time() > $_SESSION['expires']) {
            return false;
        }
        
        return in_array($_SESSION['user_type'], ['admin', 'coach']);
    }
    
    /**
     * Check if user is admin in legacy system
     */
    public function isAdmin() {
        return isset($_SESSION['user_type']) && 
               $_SESSION['user_type'] === 'admin' &&
               (!isset($_SESSION['expires']) || time() <= $_SESSION['expires']);
    }
    
    /**
     * Check if user is coach in legacy system (includes admins)
     */
    public function isCoach() {
        if ($this->isAdmin()) {
            return true; // Admins can access coach sections
        }
        
        return isset($_SESSION['user_type']) && 
               $_SESSION['user_type'] === 'coach' &&
               (!isset($_SESSION['expires']) || time() <= $_SESSION['expires']);
    }
    
    /**
     * Logout from legacy system
     */
    public function logout() {
        if (isset($_SESSION['user_type'])) {
            Logger::info("Legacy logout", [
                'user_type' => $_SESSION['user_type'],
                'username' => $_SESSION['admin_username'] ?? 'coach'
            ]);
        }
        
        // Clear legacy session variables
        unset($_SESSION['user_type']);
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['login_time']);
        unset($_SESSION['expires']);
    }
}
