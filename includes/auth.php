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
     * Admin Authentication
     */
    public static function authenticateAdmin($username, $password) {
        $db = Database::getInstance();
        
        $admin = $db->fetchOne("SELECT * FROM admin_users WHERE username = ?", [$username]);
        
                    if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['role'] = 'administrator';
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

        // Admins can access coach sections.
        if (self::isAdmin()) {
            return true;
        }

        // Attempt remember-me re-authentication when no session exists.
        if ((!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'coach') && !empty($_COOKIE['d8tl_remember'])) {
            if (file_exists(__DIR__ . '/AuthService.php')) {
                require_once __DIR__ . '/AuthService.php';
                if (AuthService::attemptRememberLogin()) {
                    return true;
                }
            }
        }

        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'coach') {
            return false;
        }

        // Enforce sliding inactivity timeout + password-changed invalidation.
        // No hard absolute "expires" cap — purely activity-based per Story 3-4 AC8.
        if (file_exists(__DIR__ . '/AuthService.php')) {
            require_once __DIR__ . '/AuthService.php';
            if (!AuthService::enforceSessionLifetime()) {
                self::logout();
                return false;
            }
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
                $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
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
     * Logout user.
     *
     * Standard logout sequence: clear $_SESSION array contents, expire the
     * session cookie at the client, then destroy server-side session storage.
     * Do NOT call session_start() again afterward — that resurrects an
     * authenticated cookie at the client and leaks $_SESSION contents to
     * the new session id.
     */
    public static function logout() {
        self::startSession();

        // Service-level cleanup (invalidate remember-me token, log auth.logout
        // for coach sessions). Done first so it can read $_SESSION values.
        if (file_exists(__DIR__ . '/AuthService.php')) {
            require_once __DIR__ . '/AuthService.php';
            AuthService::logout();
        }

        // Clear all session data.
        $_SESSION = [];

        // Expire the session cookie at the client.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        // Destroy server-side storage.
        session_destroy();
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
