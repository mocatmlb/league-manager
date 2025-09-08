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
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/filter_helpers.php';

// Start session
Auth::startSession();

// Initialize database connection (this will create the connection)
$db = Database::getInstance();

// Process email queue occasionally (MVP approach - 5% chance on page load)
// In production, this should be handled by a proper cron job
// Reduced frequency to prevent repetitive processing
if (rand(1, 100) <= 5) {
    try {
        // Check if there are pending emails
        $pendingCount = $db->fetchOne("SELECT COUNT(*) as count FROM email_queue WHERE status = 'Pending'");
        
        if ($pendingCount && $pendingCount['count'] > 0) {
            // Include EmailService and process a few emails
            if (file_exists(__DIR__ . '/EmailService.php')) {
                require_once __DIR__ . '/EmailService.php';
                $emailService = new EmailService();
                $emailService->processPendingEmails(2); // Process max 2 emails per page load
            }
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page if email processing fails
        error_log("Email queue processing failed: " . $e->getMessage());
    }
}
