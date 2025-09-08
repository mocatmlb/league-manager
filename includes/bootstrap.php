<?php
/**
 * District 8 Travel League - Application Bootstrap
 * 
 * Initialize the application and load core components
 */

// Define application constant
define('D8TL_APP', true);

// Load configuration
require_once __DIR__ . '/config.php';

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
