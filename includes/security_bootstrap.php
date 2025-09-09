<?php
/**
 * District 8 Travel League - Security Bootstrap
 * 
 * Handles security checks and access control for all PHP files
 */

// Prevent direct access to includes directory
$scriptPath = $_SERVER['SCRIPT_FILENAME'];
$includePath = dirname($scriptPath);

// Check if we're in the includes directory
if (strpos($includePath, '/includes') !== false || basename($includePath) === 'includes') {
    header('HTTP/1.0 403 Forbidden');
    die('Direct access not permitted');
}

// Define application constant
if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

// Set error reporting based on environment
if (strpos($scriptPath, '/public_html/') !== false) {
    // Production environment
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
} else {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set secure headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Prevent access to .git, .env, etc.
$forbidden_paths = array('.git', '.env', '.htaccess', 'composer.json', 'composer.lock');
foreach ($forbidden_paths as $path) {
    if (strpos($scriptPath, $path) !== false) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// Set default timezone
date_default_timezone_set('America/New_York');

// Set session cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    ini_set('session.cookie_samesite', 'Lax');
}
?>
