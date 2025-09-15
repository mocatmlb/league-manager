<?php
/**
 * District 8 Travel League - Unified Authentication Service
 * 
 * Unified authentication service that can handle both the legacy authentication
 * system and the new user accounts system during the transition period.
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/LegacyAuthManager.php';
require_once __DIR__ . '/UserAccountManager.php';

class AuthService {
    private $legacyAuth;
    private $userAccountManager;
    
    public function __construct() {
        $this->legacyAuth = new LegacyAuthManager();
        $this->userAccountManager = new UserAccountManager();
        
        // Initialize secure session
        $this->initSession();
    }
    
    /**
     * Initialize secure session with proper settings
     */
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');
            
            // Enable secure flag in production with HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            // Custom session name
            session_name('d8tl_session');
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created_at']) || time() - $_SESSION['created_at'] > 3600) {
                session_regenerate_id(true);
                $_SESSION['created_at'] = time();
            }
        }
    }
    
    /**
     * Authenticate user with either system
     */
    public function authenticate($username, $password) {
        // Try new user accounts system first
        $user = $this->userAccountManager->authenticate($username, $password);
        if ($user) {
            $this->createSession($user);
            return $user;
        }
        
        // Fall back to legacy system
        $legacyUser = $this->legacyAuth->authenticate($username, $password);
        if ($legacyUser) {
            $this->createLegacySession($legacyUser);
            return $legacyUser;
        }
        
        return false;
    }
    
    /**
     * Create session for new user accounts system
     */
    private function createSession($user) {
        $_SESSION['auth_method'] = 'new_system';
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['user_data'] = $user;
        $_SESSION['login_time'] = time();
        $_SESSION['expires'] = time() + SESSION_TIMEOUT;
        
        // Clear any legacy session variables
        unset($_SESSION['user_type']);
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
    }
    
    /**
     * Create session for legacy system
     */
    private function createLegacySession($user) {
        $_SESSION['auth_method'] = 'legacy';
        $_SESSION['user_type'] = ($user['role'] === 'administrator') ? 'admin' : 'coach';
        $_SESSION['login_time'] = time();
        
        if ($user['role'] === 'administrator') {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['expires'] = time() + ADMIN_SESSION_TIMEOUT;
        } else {
            $_SESSION['expires'] = time() + SESSION_TIMEOUT;
        }
        
        // Clear new system session variables
        unset($_SESSION['user_id']);
        unset($_SESSION['role_id']);
        unset($_SESSION['role_name']);
        unset($_SESSION['user_data']);
    }
    
    /**
     * Check if user is authenticated (either system)
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['auth_method'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['expires']) && time() > $_SESSION['expires']) {
            $this->logout();
            return false;
        }
        
        if ($_SESSION['auth_method'] === 'new_system') {
            return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        } else {
            return $this->legacyAuth->isAuthenticated();
        }
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        if ($_SESSION['auth_method'] === 'new_system') {
            $user = $_SESSION['user_data'] ?? null;
            return $this->userAccountManager->hasPermission($user, $permission);
        } else {
            $user = $this->legacyAuth->getCurrentUser();
            return $this->legacyAuth->hasPermission($user, $permission);
        }
    }
    
    /**
     * Check if user is admin (either system)
     */
    public function isAdmin() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        if ($_SESSION['auth_method'] === 'new_system') {
            return $this->hasPermission('full_admin_access');
        } else {
            return $this->legacyAuth->isAdmin();
        }
    }
    
    /**
     * Check if user is coach (either system)
     */
    public function isCoach() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Admins can access coach sections
        if ($this->isAdmin()) {
            return true;
        }
        
        if ($_SESSION['auth_method'] === 'new_system') {
            return $this->hasPermission('submit_schedule_change') || 
                   $this->hasPermission('input_game_scores');
        } else {
            return $this->legacyAuth->isCoach();
        }
    }
    
    /**
     * Get current user information
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if ($_SESSION['auth_method'] === 'new_system') {
            return $_SESSION['user_data'] ?? null;
        } else {
            return $this->legacyAuth->getCurrentUser();
        }
    }
    
    /**
     * Require authentication (redirect if not authenticated)
     */
    public function requireAuth($redirectPath = '/public/login.php') {
        if (!$this->isAuthenticated()) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }
    
    /**
     * Require admin authentication
     */
    public function requireAdmin($redirectPath = '/public/admin/login.php') {
        if (!$this->isAdmin()) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }
    
    /**
     * Require coach authentication (includes admins)
     */
    public function requireCoach($redirectPath = '/public/coaches/login.php') {
        if (!$this->isCoach()) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }
    
    /**
     * Logout from either system
     */
    public function logout() {
        if (isset($_SESSION['auth_method'])) {
            if ($_SESSION['auth_method'] === 'legacy') {
                $this->legacyAuth->logout();
            }
            
            // Clear all session data
            session_destroy();
            session_start();
            session_regenerate_id(true);
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Get HTML for CSRF token field
     */
    public function csrfTokenField() {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate form with CSRF protection
     */
    public function validateForm() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCSRFToken($_POST['csrf_token'])) {
            Logger::warn("CSRF validation failed", [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            throw new Exception("Form submission error: Invalid security token. Please try again.");
        }
        return true;
    }
    
    /**
     * Get authentication method for current session
     */
    public function getAuthMethod() {
        return $_SESSION['auth_method'] ?? null;
    }
    
    /**
     * Check if user is using legacy authentication
     */
    public function isLegacyAuth() {
        return $this->getAuthMethod() === 'legacy';
    }
    
    /**
     * Check if user is using new user accounts system
     */
    public function isNewAuth() {
        return $this->getAuthMethod() === 'new_system';
    }
    
    /**
     * Get user account manager instance
     */
    public function getUserAccountManager() {
        return $this->userAccountManager;
    }
    
    /**
     * Get legacy auth manager instance
     */
    public function getLegacyAuthManager() {
        return $this->legacyAuth;
    }
}
