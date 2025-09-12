<?php
/**
 * District 8 Travel League - Application Bootstrap - v2.0.0-MVP
 * 
 * Initialize the application and load core components
 */

// Load security bootstrap
require_once __DIR__ . '/security_bootstrap.php';

// Ensure EnvLoader is available before using it
require_once __DIR__ . '/env-loader.php';

// Load configuration using environment-aware path
require_once EnvLoader::getPath('includes/config.php');

// Load backwards compatibility functions first
require_once __DIR__ . '/compatibility.php';

// Load core classes and functions
require_once __DIR__ . '/enums.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/filter_helpers.php';

// Start session
Auth::startSession();

// Initialize database connection (this will create the connection)
// Only initialize if not already done to prevent memory issues
if (!isset($GLOBALS['d8tl_db_initialized'])) {
    $db = Database::getInstance();
    $GLOBALS['d8tl_db_initialized'] = true;

    // Initialize admin account from .htaccess env if needed
    try {
        // If no admin users exist, create default 'admin' using DEFAULT_ADMIN_PASSWORD
        $count = $db->fetchOne("SELECT COUNT(*) AS count FROM admin_users");
        if ($count && (int)$count['count'] === 0) {
            $defaultPass = defined('DEFAULT_ADMIN_PASSWORD') ? DEFAULT_ADMIN_PASSWORD : 'CHANGE_THIS_ADMIN_PASSWORD';
            if ($defaultPass && $defaultPass !== 'CHANGE_THIS_ADMIN_PASSWORD') {
                $hash = password_hash($defaultPass, PASSWORD_BCRYPT);
                $db->insert('admin_users', [
                    'username' => 'admin',
                    'password' => $hash,
                    'email' => 'admin@district8league.com',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'is_active' => 1,
                    'created_date' => date('Y-m-d H:i:s')
                ]);
                if (class_exists('Logger')) {
                    Logger::info('Seeded admin user from .htaccess DEFAULT_ADMIN_PASSWORD');
                }
            }
        } else {
            // If an 'admin' user exists with the factory default password hash from schema.sql, update it from env
            $admin = $db->fetchOne("SELECT id, username, password FROM admin_users WHERE username = 'admin' LIMIT 1");
            if ($admin) {
                $defaultPass = defined('DEFAULT_ADMIN_PASSWORD') ? DEFAULT_ADMIN_PASSWORD : '';
                if ($defaultPass && $defaultPass !== 'CHANGE_THIS_ADMIN_PASSWORD') {
                    // The schema seeds bcrypt hash for the literal string 'password'
                    if (password_verify('password', $admin['password'])) {
                        $hash = password_hash($defaultPass, PASSWORD_BCRYPT);
                        $db->update('admin_users', [
                            'password' => $hash,
                            'password_changed_at' => date('Y-m-d H:i:s')
                        ], 'id = :id', ['id' => $admin['id']]);
                        if (class_exists('Logger')) {
                            Logger::info('Initialized admin password from .htaccess DEFAULT_ADMIN_PASSWORD');
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Do not block app startup if initialization fails
        error_log('Admin initialization check failed: ' . $e->getMessage());
    }
}

// Process email queue occasionally (MVP approach - 2% chance on page load)
// In production, this should be handled by a proper cron job
// Further reduced frequency to prevent memory issues
if (rand(1, 100) <= 2) {
    try {
        // Only process if database is available and not already processing
        if (isset($GLOBALS['d8tl_db_initialized']) && !isset($GLOBALS['d8tl_email_processing'])) {
            $GLOBALS['d8tl_email_processing'] = true;
            
            // Check if there are pending emails
            $db = Database::getInstance();
            $pendingCount = $db->fetchOne("SELECT COUNT(*) as count FROM email_queue WHERE status = 'Pending'");
            
            if ($pendingCount && $pendingCount['count'] > 0) {
                // Include EmailService and process a few emails
                if (file_exists(__DIR__ . '/EmailService.php')) {
                    require_once __DIR__ . '/EmailService.php';
                    $emailService = new EmailService();
                    $emailService->processPendingEmails(1); // Process max 1 email per page load to reduce memory usage
                }
            }
            
            unset($GLOBALS['d8tl_email_processing']);
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page if email processing fails
        error_log("Email queue processing failed: " . $e->getMessage());
        unset($GLOBALS['d8tl_email_processing']);
    }
}
