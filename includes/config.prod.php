<?php
/**
 * District 8 Travel League - Production Configuration
 * 
 * Production environment configuration settings
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Database Configuration - Production (MUST BE CONFIGURED)
define('DB_HOST', 'localhost'); // Usually localhost for shared hosting
define('DB_NAME', 'REPLACE_WITH_ACTUAL_DATABASE_NAME');
define('DB_USER', 'REPLACE_WITH_ACTUAL_DB_USERNAME');
define('DB_PASS', 'REPLACE_WITH_ACTUAL_DB_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration - Production
define('APP_NAME', 'District 8 Travel League');
define('APP_VERSION', '2.0.0-MVP');
define('APP_URL', 'https://district8travelleague.com');
define('APP_ENV', 'production');

// Security Configuration - Production
define('SESSION_TIMEOUT', 3600); // 1 hour for coaches
define('ADMIN_SESSION_TIMEOUT', 7200); // 2 hours for admin
define('CSRF_TOKEN_NAME', 'd8tl_csrf_token');

// Production Passwords (CRITICAL: MUST BE CHANGED BEFORE DEPLOYMENT)
// Use strong passwords with mixed case, numbers, and special characters
define('DEFAULT_ADMIN_PASSWORD', 'REPLACE_WITH_STRONG_ADMIN_PASSWORD_MIN_12_CHARS');
define('DEFAULT_COACHES_PASSWORD', 'REPLACE_WITH_STRONG_COACHES_PASSWORD_MIN_12_CHARS');

// Email Configuration - Production
define('SMTP_HOST', 'mail.asmallorange.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'REPLACE_WITH_ACTUAL_EMAIL_ADDRESS');
define('SMTP_PASSWORD', 'REPLACE_WITH_ACTUAL_EMAIL_PASSWORD');
define('SMTP_FROM_EMAIL', 'noreply@district8travelleague.com');
define('SMTP_FROM_NAME', 'District 8 Travel League');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt']);

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting - Production (disabled for security)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Session Configuration - Production (secure)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS required
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Additional Production Security
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
?>
