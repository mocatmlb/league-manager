<?php
/**
 * Final Email Test - Use your own email address
 */

define('D8TL_APP', true);
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/Logger.php';
require_once 'includes/EmailService.php';

echo "=== Final Email System Test ===\n";

try {
    $db = Database::getInstance();
    $emailService = new EmailService();
    
    // Get current SMTP configuration
    $smtpConfig = $db->fetchOne("SELECT * FROM smtp_configuration WHERE is_active = 1");
    
    if (!$smtpConfig) {
        die("❌ No active SMTP configuration found.\n");
    }
    
    echo "✅ Using SMTP Configuration:\n";
    echo "   Host: " . $smtpConfig['smtp_host'] . ":" . $smtpConfig['smtp_port'] . "\n";
    echo "   From: " . $smtpConfig['from_email'] . "\n";
    echo "   TLS: " . ($smtpConfig['use_tls'] ? 'Yes' : 'No') . "\n\n";
    
    // Test with your actual email address
    $yourEmail = 'your-email@gmail.com'; // CHANGE THIS TO YOUR REAL EMAIL
    
    echo "🔧 IMPORTANT: Change \$yourEmail in this script to your real email address!\n";
    echo "   Current test email: $yourEmail\n\n";
    
    if ($yourEmail === 'your-email@gmail.com') {
        echo "⚠️  Please edit this script and change \$yourEmail to your real email address.\n";
        echo "   Then run the test again.\n";
        exit(1);
    }
    
    // Test configuration
    $testConfig = [
        'smtp_host' => $smtpConfig['smtp_host'],
        'smtp_port' => $smtpConfig['smtp_port'],
        'smtp_user' => $smtpConfig['smtp_user'],
        'smtp_password' => base64_decode($smtpConfig['smtp_password']),
        'use_ssl' => $smtpConfig['use_ssl'],
        'use_tls' => $smtpConfig['use_tls'],
        'from_email' => $smtpConfig['from_email'],
        'from_name' => $smtpConfig['from_name']
    ];
    
    echo "📧 Testing email to: $yourEmail\n";
    
    $result = $emailService->testSmtpConfiguration($testConfig, $yourEmail);
    
    if ($result['success']) {
        echo "✅ SUCCESS! Test email sent successfully!\n";
        echo "   Check your inbox for the test message.\n\n";
        
        // Update database with success
        $db->update('smtp_configuration', [
            'last_tested_date' => date('Y-m-d H:i:s'),
            'last_test_result' => 'Success',
            'test_error_message' => null
        ], 'config_id = :config_id', ['config_id' => $smtpConfig['config_id']]);
        
        echo "🎉 Email system is working correctly!\n";
        echo "   You can now use the email notifications in the admin interface.\n";
        
    } else {
        echo "❌ FAILED: " . $result['error'] . "\n";
        echo "\n🔍 Troubleshooting:\n";
        echo "1. Make sure the email address exists\n";
        echo "2. Check if your hosting provider allows external email sending\n";
        echo "3. Verify SMTP credentials are correct\n";
        echo "4. Contact your hosting provider for SMTP settings\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Instructions for Use ===\n";
echo "1. Edit this script and change \$yourEmail to your real email address\n";
echo "2. Run: php test_email_final.php\n";
echo "3. If successful, the email system is ready to use!\n";
echo "4. Go to Admin → Settings → Email Configuration to manage settings\n";
echo "5. Email notifications will be sent automatically for:\n";
echo "   - Schedule change requests\n";
echo "   - Schedule change approvals\n";
echo "   - Game score updates\n";
echo "   - New game additions\n";
echo "   - Game modifications\n";
?>
