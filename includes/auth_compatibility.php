<?php
/**
 * District 8 Travel League - Authentication Compatibility Layer
 * 
 * Provides backward compatibility for existing code while integrating
 * with the new unified authentication system.
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/AuthService.php';

// Global AuthService instance for compatibility
$GLOBALS['authService'] = new AuthService();

/**
 * Compatibility wrapper for Auth class methods
 * These functions maintain the same interface as the original Auth class
 * but delegate to the new unified AuthService
 */

/**
 * Start secure session (compatibility)
 */
function startSession() {
    // AuthService handles session initialization automatically
    return true;
}

/**
 * Generate CSRF token (compatibility)
 */
function generateCSRFToken() {
    return $GLOBALS['authService']->generateCSRFToken();
}

/**
 * Verify CSRF token (compatibility)
 */
function verifyCSRFToken($token) {
    return $GLOBALS['authService']->verifyCSRFToken($token);
}

/**
 * Coach Authentication (compatibility)
 */
function authenticateCoach($password) {
    // Try to authenticate using "coach" username with provided password
    $user = $GLOBALS['authService']->authenticate('coach', $password);
    return $user !== false;
}

/**
 * Admin Authentication (compatibility)
 */
function authenticateAdmin($username, $password) {
    $user = $GLOBALS['authService']->authenticate($username, $password);
    
    // Check if authenticated user is an admin
    if ($user && $GLOBALS['authService']->isAdmin()) {
        return true;
    }
    
    return false;
}

/**
 * Check if user is authenticated as coach (compatibility)
 */
function isCoach() {
    return $GLOBALS['authService']->isCoach();
}

/**
 * Check if user is authenticated as admin (compatibility)
 */
function isAdmin() {
    return $GLOBALS['authService']->isAdmin();
}

/**
 * Require coach authentication (compatibility)
 */
function requireCoach() {
    $GLOBALS['authService']->requireCoach();
}

/**
 * Require admin authentication (compatibility)
 */
function requireAdmin() {
    $GLOBALS['authService']->requireAdmin();
}

/**
 * Logout user (compatibility)
 */
function logout() {
    $GLOBALS['authService']->logout();
}

/**
 * Check if user is logged in (compatibility)
 */
function isLoggedIn() {
    return $GLOBALS['authService']->isAuthenticated();
}

/**
 * Get current user info (compatibility)
 */
function getCurrentUser() {
    return $GLOBALS['authService']->getCurrentUser();
}

/**
 * Hash password (compatibility)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Enhanced Auth class that extends the original functionality
 * while maintaining backward compatibility
 */
if (!class_exists('Auth')) {
class Auth {
    
    /**
     * Start secure session
     */
    public static function startSession() {
        return startSession();
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        return generateCSRFToken();
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return verifyCSRFToken($token);
    }
    
    /**
     * Coach Authentication
     */
    public static function authenticateCoach($password) {
        return authenticateCoach($password);
    }
    
    /**
     * Admin Authentication
     */
    public static function authenticateAdmin($username, $password) {
        return authenticateAdmin($username, $password);
    }
    
    /**
     * Check if user is authenticated as coach
     */
    public static function isCoach() {
        return isCoach();
    }
    
    /**
     * Check if user is authenticated as admin
     */
    public static function isAdmin() {
        return isAdmin();
    }
    
    /**
     * Require coach authentication
     */
    public static function requireCoach() {
        requireCoach();
    }
    
    /**
     * Require admin authentication
     */
    public static function requireAdmin() {
        requireAdmin();
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        logout();
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isLoggedIn();
    }
    
    /**
     * Get current user info
     */
    public static function getCurrentUser() {
        return getCurrentUser();
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return hashPassword($password);
    }
    
    /**
     * NEW METHODS - Additional functionality from unified system
     */
    
    /**
     * Get the unified AuthService instance
     */
    public static function getAuthService() {
        return $GLOBALS['authService'];
    }
    
    /**
     * Check if user has specific permission
     */
    public static function hasPermission($permission) {
        return $GLOBALS['authService']->hasPermission($permission);
    }
    
    /**
     * Check if using new authentication system
     */
    public static function isNewAuth() {
        return $GLOBALS['authService']->isNewAuth();
    }
    
    /**
     * Check if using legacy authentication system
     */
    public static function isLegacyAuth() {
        return $GLOBALS['authService']->isLegacyAuth();
    }
    
    /**
     * Get CSRF token field HTML
     */
    public static function csrfTokenField() {
        return $GLOBALS['authService']->csrfTokenField();
    }
    
    /**
     * Validate form with CSRF protection
     */
    public static function validateForm() {
        return $GLOBALS['authService']->validateForm();
    }
    
    /**
     * Require general authentication (either system)
     */
    public static function requireAuth($redirectPath = '/public/login.php') {
        $GLOBALS['authService']->requireAuth($redirectPath);
    }
}
}
