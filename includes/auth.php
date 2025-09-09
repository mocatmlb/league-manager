<?php
/**
 * District 8 Travel League - Authentication Functions
 * 
 * Three-tier authentication system: Public, Coaches, Admin
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class Auth {
    
    /**
     * Start secure session
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Coach Authentication
     */
    public static function authenticateCoach($password) {
        $db = Database::getInstance();
        
        // Get current coaches password from settings
        $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'coaches_password'");
        $coachesPassword = $setting ? $setting['setting_value'] : DEFAULT_COACHES_PASSWORD;
        
        // Only allow hashed password verification
        if (password_verify($password, $coachesPassword)) {
            $_SESSION['user_type'] = 'coach';
            $_SESSION['login_time'] = time();
            $_SESSION['expires'] = time() + SESSION_TIMEOUT;
            return true;
        }
        
        return false;
    }
    
    /**
     * Admin Authentication
     */
    public static function authenticateAdmin($username, $password) {
        $db = Database::getInstance();
        
        $admin = $db->fetchOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
        
                    if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['login_time'] = time();
                $_SESSION['expires'] = time() + ADMIN_SESSION_TIMEOUT;
                
                // Update last login
                $updateData = ['last_login' => date('Y-m-d H:i:s')];
                $whereClause = 'id = :admin_id';
                $whereParams = ['admin_id' => $admin['id']];
                $db->update('admin_users', $updateData, $whereClause, $whereParams);
                
                Logger::info("Admin login successful", ['username' => $username, 'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown']);
                return true;
            }
            
            Logger::warn("Admin login failed", ['username' => $username, 'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown']);
            return false;
    }
    
    /**
     * Check if user is authenticated as coach
     * Note: Admins are also considered coaches for access purposes
     */
    public static function isCoach() {
        self::startSession();
        
        // Check if user is an admin (admins can access coach sections)
        if (self::isAdmin()) {
            return true;
        }
        
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'coach') {
            return false;
        }
        
        // Check session timeout
        if (time() > $_SESSION['expires']) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user is authenticated as admin
     */
    public static function isAdmin() {
        self::startSession();
        
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            return false;
        }
        
        // Check session timeout
        if (time() > $_SESSION['expires']) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Require coach authentication
     * Note: Admins can also access coach sections
     */
    public static function requireCoach() {
        if (!self::isCoach()) {
            // If not logged in at all, redirect to coach login
            if (!self::isLoggedIn()) {
                $loginPath = EnvLoader::isProduction() ? '/coaches/login.php' : '/public/coaches/login.php';
                header('Location: ' . $loginPath);
                exit;
            }
            // If logged in but not as coach/admin, redirect to home
            $homePath = EnvLoader::isProduction() ? '/index.php' : '/public/index.php';
            header('Location: ' . $homePath);
            exit;
        }
    }
    
    /**
     * Require admin authentication
     */
    public static function requireAdmin() {
        if (!self::isAdmin()) {
            header('Location: /admin/login.php');
            exit;
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        self::startSession();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Check if user is logged in (either as admin or coach)
     */
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'coach');
    }

    /**
     * Get current user info
     */
    public static function getCurrentUser() {
        self::startSession();
        
        if (self::isAdmin()) {
            return [
                'type' => UserType::ADMIN,
                'username' => isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '',
                'id' => isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0,
                'user_type_enum' => UserType::ADMIN
            ];
        } elseif (self::isCoach()) {
            return [
                'type' => UserType::COACH,
                'username' => 'Coach', // Generic username for coaches
                'user_type_enum' => UserType::COACH
            ];
        }
        
        return [
            'type' => UserType::PUBLIC_USER,
            'user_type_enum' => UserType::PUBLIC_USER
        ];
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
