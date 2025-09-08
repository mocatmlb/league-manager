<?php
/**
 * District 8 Travel League - Staging Configuration
 * 
 * Staging environment configuration settings for testing
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Database Configuration - Staging (MUST BE CONFIGURED)
define('DB_HOST', 'localhost'); // Usually localhost for shared hosting
define('DB_NAME', 'REPLACE_WITH_STAGING_DATABASE_NAME');
define('DB_USER', 'REPLACE_WITH_STAGING_DB_USERNAME');
define('DB_PASS', 'REPLACE_WITH_STAGING_DB_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration - Staging
define('APP_NAME', 'District 8 Travel League - STAGING');
define('APP_VERSION', '2.0.0-MVP-STAGING');
define('APP_URL', 'https://staging.district8travelleague.com');
define('APP_ENV', 'staging');

// Security Configuration - Staging
define('SESSION_TIMEOUT', 3600); // 1 hour for coaches
define('ADMIN_SESSION_TIMEOUT', 7200); // 2 hours for admin
define('CSRF_TOKEN_NAME', 'd8tl_csrf_token_staging');

// Staging Passwords (MUST BE CHANGED - DO NOT USE THESE VALUES)
define('DEFAULT_ADMIN_PASSWORD', 'CHANGE_THIS_STAGING_ADMIN_PASSWORD');
define('DEFAULT_COACHES_PASSWORD', 'CHANGE_THIS_STAGING_COACHES_PASSWORD');

// Email Configuration - Staging (use test email)
define('SMTP_HOST', 'mail.asmallorange.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'REPLACE_WITH_STAGING_EMAIL_ADDRESS');
define('SMTP_PASSWORD', 'REPLACE_WITH_STAGING_EMAIL_PASSWORD');
define('SMTP_FROM_EMAIL', 'staging@district8travelleague.com');
define('SMTP_FROM_NAME', 'District 8 Travel League - STAGING');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt']);

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting - Staging (enabled for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors_staging.log');

// Session Configuration - Staging
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS required
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Staging-specific settings
define('DEBUG_MODE', true);
define('STAGING_BANNER', true); // Show staging banner on pages
?>
