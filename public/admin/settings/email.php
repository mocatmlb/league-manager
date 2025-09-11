<?php
/**
 * District 8 Travel League - Email Configuration
 */

require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.php';

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';

// Include EmailService for testing
require_once '../../../includes/EmailService.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_smtp':
                try {
                    $smtpData = [
                        'smtp_host' => trim($_POST['smtp_host']),
                        'smtp_port' => (int)$_POST['smtp_port'],
                        'smtp_user' => trim($_POST['smtp_user']),
                        'use_ssl' => isset($_POST['use_ssl']) ? 1 : 0,
                        'use_tls' => isset($_POST['use_tls']) ? 1 : 0,
                        'from_email' => trim($_POST['from_email']),
                        'from_name' => trim($_POST['from_name']),
                        'reply_to_email' => trim($_POST['reply_to_email']),
                        'updated_date' => date('Y-m-d H:i:s')
                    ];
                    
                    // Only update password if provided
                    if (!empty($_POST['smtp_password'])) {
                        $smtpData['smtp_password'] = EmailService::encryptPassword($_POST['smtp_password']);
                    }
                    
                    // Validate required fields
                    if (empty($smtpData['smtp_host']) || empty($smtpData['smtp_user']) || empty($smtpData['from_email'])) {
                        throw new Exception('SMTP Host, Username, and From Email are required.');
                    }
                    
                    if (!filter_var($smtpData['from_email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid From Email address.');
                    }
                    
                    if (!empty($smtpData['reply_to_email']) && !filter_var($smtpData['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid Reply-To Email address.');
                    }
                    
                    // Check if configuration exists
                    $existingConfig = $db->fetchOne("SELECT config_id FROM smtp_configuration WHERE is_active = 1");
                    
                    if ($existingConfig) {
                        // Update existing configuration
                        $db->update('smtp_configuration', $smtpData, 'config_id = :config_id', ['config_id' => $existingConfig['config_id']]);
                    } else {
                        // Create new configuration
                        $smtpData['is_active'] = 1;
                        $db->insert('smtp_configuration', $smtpData);
                    }
                    
                    logActivity('smtp_config_updated', 'SMTP configuration updated', Auth::getCurrentUser()['id']);
                    $message = 'SMTP configuration updated successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error updating SMTP configuration: ' . $e->getMessage();
                }
                break;
                
            case 'process_queue':
                try {
                    $emailService = new EmailService();
                    $processed = $emailService->processPendingEmails(20);
                    
                    if ($processed > 0) {
                        $message = "Successfully processed {$processed} pending emails!";
                        logActivity('email_queue_processed', "Manually processed {$processed} emails", Auth::getCurrentUser()['id']);
                    } else {
                        $message = 'No pending emails to process.';
                    }
                    
                } catch (Exception $e) {
                    $error = 'Error processing email queue: ' . $e->getMessage();
                }
                break;
                
            case 'test_smtp':
                try {
                    $testEmail = trim($_POST['test_email']);
                    
                    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Please provide a valid test email address.');
                    }
                    
                    // Get current SMTP configuration
                    $smtpConfig = $db->fetchOne("SELECT * FROM smtp_configuration WHERE is_active = 1");
                    
                    if (!$smtpConfig) {
                        throw new Exception('No SMTP configuration found. Please save your settings first.');
                    }
                    
                    // Prepare test configuration
                    $testConfig = [
                        'smtp_host' => $smtpConfig['smtp_host'],
                        'smtp_port' => $smtpConfig['smtp_port'],
                        'smtp_user' => $smtpConfig['smtp_user'],
                        'smtp_password' => '', // Will be decrypted by EmailService
                        'use_ssl' => $smtpConfig['use_ssl'],
                        'use_tls' => $smtpConfig['use_tls'],
                        'from_email' => $smtpConfig['from_email'],
                        'from_name' => $smtpConfig['from_name']
                    ];
                    
                    // If password provided in form, use it; otherwise use stored password
                    if (!empty($_POST['smtp_password'])) {
                        $testConfig['smtp_password'] = $_POST['smtp_password'];
                    } else {
                        // Decrypt stored password
                        $emailService = new EmailService();
                        $testConfig['smtp_password'] = base64_decode($smtpConfig['smtp_password']);
                    }
                    
                    // Test the configuration
                    $emailService = new EmailService();
                    $result = $emailService->testSmtpConfiguration($testConfig, $testEmail);
                    
                    // Update test results in database
                    $db->update('smtp_configuration', [
                        'last_tested_date' => date('Y-m-d H:i:s'),
                        'last_test_result' => $result['success'] ? 'Success' : 'Failed',
                        'test_error_message' => $result['success'] ? null : $result['error']
                    ], 'config_id = :config_id', ['config_id' => $smtpConfig['config_id']]);
                    
                    if ($result['success']) {
                        $message = 'Test email sent successfully to ' . $testEmail . '!';
                        logActivity('smtp_test_success', "SMTP test successful to {$testEmail}", Auth::getCurrentUser()['id']);
                    } else {
                        $error = 'Test email failed: ' . $result['error'];
                        logActivity('smtp_test_failed', "SMTP test failed: " . $result['error'], Auth::getCurrentUser()['id']);
                    }
                    
                } catch (Exception $e) {
                    $error = 'Error testing SMTP: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get current SMTP configuration
$smtpConfig = $db->fetchOne("SELECT * FROM smtp_configuration WHERE is_active = 1");

// Get email queue statistics
$queueStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_emails,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed
    FROM email_queue
");

$pageTitle = "Email Configuration - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../../includes/nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-envelope-open-text"></i> Email Configuration</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Settings</a></li>
                            <li class="breadcrumb-item active">Email Configuration</li>
                        </ol>
                    </nav>
                </div>

                <!-- Settings Navigation -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="text-muted mb-3">Settings Categories</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="index.php" class="btn btn-outline-primary">
                                        <i class="fas fa-cog"></i> General Settings
                                    </a>
                                    <a href="email.php" class="btn btn-outline-primary active">
                                        <i class="fas fa-envelope"></i> Email Configuration
                                    </a>
                                    <a href="templates.php" class="btn btn-outline-primary">
                                        <i class="fas fa-file-alt"></i> Email Templates
                                    </a>
                                    <a href="#" class="btn btn-outline-secondary disabled">
                                        <i class="fas fa-users"></i> User Management
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Email Queue Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Emails</h6>
                                        <h3><?php echo $queueStats['total_emails'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-envelope fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Pending</h6>
                                        <h3><?php echo $queueStats['pending'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Sent</h6>
                                        <h3><?php echo $queueStats['sent'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Failed</h6>
                                        <h3><?php echo $queueStats['failed'] ?? 0; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMTP Configuration Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-server"></i> SMTP Server Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_smtp">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                        <input type="text" name="smtp_host" class="form-control" 
                                               value="<?php echo sanitize($smtpConfig['smtp_host'] ?? ''); ?>" 
                                               placeholder="smtp.gmail.com" required>
                                        <div class="form-text">Your email provider's SMTP server address</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Port <span class="text-danger">*</span></label>
                                        <select name="smtp_port" class="form-select" required>
                                            <option value="587" <?php echo ($smtpConfig['smtp_port'] ?? 587) == 587 ? 'selected' : ''; ?>>587 (TLS - Recommended)</option>
                                            <option value="465" <?php echo ($smtpConfig['smtp_port'] ?? 587) == 465 ? 'selected' : ''; ?>>465 (SSL)</option>
                                            <option value="25" <?php echo ($smtpConfig['smtp_port'] ?? 587) == 25 ? 'selected' : ''; ?>>25 (Plain)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username <span class="text-danger">*</span></label>
                                        <input type="text" name="smtp_user" class="form-control" 
                                               value="<?php echo sanitize($smtpConfig['smtp_user'] ?? ''); ?>" 
                                               placeholder="your-email@gmail.com" required>
                                        <div class="form-text">Your email account username</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="smtp_password" class="form-control" 
                                               placeholder="<?php echo $smtpConfig ? 'Leave blank to keep current password' : 'Enter your email password'; ?>">
                                        <div class="form-text">
                                            <?php if ($smtpConfig): ?>
                                                Leave blank to keep current password
                                            <?php else: ?>
                                                Your email account password or app-specific password
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">From Email <span class="text-danger">*</span></label>
                                        <input type="email" name="from_email" class="form-control" 
                                               value="<?php echo sanitize($smtpConfig['from_email'] ?? ''); ?>" 
                                               placeholder="noreply@district8league.com" required>
                                        <div class="form-text">Email address that appears as sender</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">From Name</label>
                                        <input type="text" name="from_name" class="form-control" 
                                               value="<?php echo sanitize($smtpConfig['from_name'] ?? 'District 8 Travel League'); ?>" 
                                               placeholder="District 8 Travel League">
                                        <div class="form-text">Display name for outgoing emails</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Reply-To Email</label>
                                        <input type="email" name="reply_to_email" class="form-control" 
                                               value="<?php echo sanitize($smtpConfig['reply_to_email'] ?? ''); ?>" 
                                               placeholder="admin@district8league.com">
                                        <div class="form-text">Email address for replies (optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Security Settings</label>
                                        <div class="form-check">
                                            <input type="checkbox" name="use_tls" class="form-check-input" id="use_tls" 
                                                   <?php echo ($smtpConfig['use_tls'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="use_tls">
                                                Use TLS Encryption (Recommended)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="use_ssl" class="form-check-input" id="use_ssl" 
                                                   <?php echo ($smtpConfig['use_ssl'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="use_ssl">
                                                Use SSL Encryption
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                                <button type="button" class="btn btn-info" onclick="showTestModal()">
                                    <i class="fas fa-paper-plane"></i> Test Email
                                </button>
                            </div>
                        </form>
                        
                        <!-- Queue Processing Form -->
                        <?php if ($queueStats['pending'] > 0): ?>
                        <hr>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="process_queue">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-cogs"></i> Process Pending Emails (<?php echo $queueStats['pending']; ?>)
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Test Results -->
                <?php if ($smtpConfig && $smtpConfig['last_tested_date']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-vial"></i> Last Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Last Tested:</strong> <?php echo date('F j, Y g:i A', strtotime($smtpConfig['last_tested_date'])); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Result:</strong> 
                                <?php if ($smtpConfig['last_test_result'] === 'Success'): ?>
                                    <span class="badge bg-success">Success</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($smtpConfig['test_error_message']): ?>
                            <div class="mt-2">
                                <strong>Error Message:</strong>
                                <div class="alert alert-danger mt-1">
                                    <?php echo sanitize($smtpConfig['test_error_message']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Test Email Modal -->
    <div class="modal fade" id="testEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Test Email Configuration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="test_smtp">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Test Email Address</label>
                            <input type="email" name="test_email" class="form-control" 
                                   placeholder="your-email@example.com" required>
                            <div class="form-text">Enter an email address to receive the test message</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">SMTP Password (if changed)</label>
                            <input type="password" name="smtp_password" class="form-control" 
                                   placeholder="Leave blank to use saved password">
                            <div class="form-text">Only enter if you changed the password above</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTestModal() {
            var testModal = new bootstrap.Modal(document.getElementById('testEmailModal'));
            testModal.show();
        }
    </script>
</body>
</html>
