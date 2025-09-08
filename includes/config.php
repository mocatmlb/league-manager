<?php
/**
 * District 8 Travel League - MVP Configuration
 * 
 * Core configuration settings for the MVP application
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'moc835_d8tl_prod');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'District 8 Travel League');
define('APP_VERSION', '2.0.0-MVP');
define('APP_URL', 'http://localhost/d8tl-mvp');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour for coaches
define('ADMIN_SESSION_TIMEOUT', 7200); // 2 hours for admin
define('CSRF_TOKEN_NAME', 'd8tl_csrf_token');

// Default Passwords (CHANGE IN PRODUCTION)
define('DEFAULT_ADMIN_PASSWORD', 'admin');
define('DEFAULT_COACHES_PASSWORD', 'coaches');

// Email Configuration (to be configured by admin)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'District 8 Travel League');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt']);

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
