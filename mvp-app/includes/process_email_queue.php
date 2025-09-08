<?php
/**
 * District 8 Travel League - Email Queue Processor
 * 
 * Simple email queue processor for MVP
 * Can be called via cron job or manually
 */

// Set up environment
define('D8TL_APP', true);
require_once __DIR__ . '/bootstrap.php';

// Include EmailService
require_once __DIR__ . '/EmailService.php';

// Create logger for this process
$logger = new Logger();
$logger->log('info', 'Email queue processor started');

try {
    $emailService = new EmailService();
    
    // Process pending emails (limit to 50 at a time for shared hosting)
    $processed = $emailService->processPendingEmails(50);
    
    if ($processed > 0) {
        $logger->log('info', "Email queue processor completed: {$processed} emails processed");
        echo "Processed {$processed} emails successfully.\n";
    } else {
        $logger->log('info', 'Email queue processor completed: No pending emails');
        echo "No pending emails to process.\n";
    }
    
} catch (Exception $e) {
    $logger->log('error', 'Email queue processor failed: ' . $e->getMessage());
    echo "Error processing email queue: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
?>
