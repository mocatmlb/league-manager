<?php
/**
 * District 8 Travel League - Production Configuration - v2.0.0-MVP
 * 
 * Production environment configuration settings
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Load environment variables from .htaccess SetEnv directives
require_once __DIR__ . '/env-loader.php';

// Database Configuration - Load from environment variables set in .htaccess
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_NAME', EnvLoader::get('DB_NAME', 'moc835_d8tl_prod'));
define('DB_USER', EnvLoader::get('DB_USER', 'REPLACE_WITH_ACTUAL_DB_USERNAME'));
define('DB_PASS', EnvLoader::get('DB_PASS', 'REPLACE_WITH_ACTUAL_DB_PASSWORD'));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Application Configuration - Load from environment variables
define('APP_NAME', EnvLoader::get('APP_NAME', 'District 8 Travel League'));
define('APP_VERSION', EnvLoader::get('APP_VERSION', '2.0.0-MVP'));
define('APP_URL', EnvLoader::get('APP_URL', 'http://district8travelleague.com'));
define('APP_ENV', EnvLoader::get('APP_ENV', 'production'));

// Security Configuration - Load from environment variables
define('SESSION_TIMEOUT', EnvLoader::getInt('SESSION_TIMEOUT', 3600)); // 1 hour for coaches
define('ADMIN_SESSION_TIMEOUT', EnvLoader::getInt('ADMIN_SESSION_TIMEOUT', 7200)); // 2 hours for admin
define('CSRF_TOKEN_NAME', EnvLoader::get('CSRF_TOKEN_NAME', 'd8tl_csrf_token'));

// Default Passwords - Load from environment variables (CHANGE IN .htaccess!)
define('DEFAULT_ADMIN_PASSWORD', EnvLoader::get('DEFAULT_ADMIN_PASSWORD', 'CHANGE_THIS_ADMIN_PASSWORD'));
define('DEFAULT_COACHES_PASSWORD', EnvLoader::get('DEFAULT_COACHES_PASSWORD', 'CHANGE_THIS_COACHES_PASSWORD'));

// Email Configuration - Load from environment variables
define('SMTP_HOST', EnvLoader::get('SMTP_HOST', 'mail.asmallorange.com'));
define('SMTP_PORT', EnvLoader::getInt('SMTP_PORT', 587));
define('SMTP_USERNAME', EnvLoader::get('SMTP_USERNAME', 'REPLACE_WITH_ACTUAL_EMAIL_ADDRESS'));
define('SMTP_PASSWORD', EnvLoader::get('SMTP_PASSWORD', 'REPLACE_WITH_ACTUAL_EMAIL_PASSWORD'));
define('SMTP_FROM_EMAIL', EnvLoader::get('SMTP_FROM_EMAIL', 'noreply@district8travelleague.com'));
define('SMTP_FROM_NAME', EnvLoader::get('SMTP_FROM_NAME', 'District 8 Travel League'));

// File Upload Configuration
// Use JSON-encoded string for allowed file types, since define() cannot use arrays
// To use: json_decode(ALLOWED_FILE_TYPES, true)
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', '["pdf","doc","docx","txt"]');

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting - Production (disabled for security)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// PHP 8.1 specific settings - Production
ini_set('zend.assertions', -1); // Disable assertions in production for performance

// Note: OPcache and JIT settings should be configured in php.ini for production
// These runtime settings are commented out as they cannot be changed at runtime:
// opcache.enable=1
// opcache.jit_buffer_size=256M
// opcache.jit=tracing
// opcache.validate_timestamps=0

// Session Configuration - Production (HTTP compatible)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 when HTTPS is enabled
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax'); // Lax for HTTP compatibility

// Additional Production Security
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
?>
