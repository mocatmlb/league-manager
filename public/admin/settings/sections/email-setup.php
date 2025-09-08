<?php
/**
 * Email Setup Section
 */

require_once __DIR__ . '/../../../../includes/EmailService.php';

// Get current SMTP configuration
$smtpConfig = $db->fetchOne("
    SELECT * FROM smtp_configuration 
    WHERE is_active = 1 
    ORDER BY config_id DESC 
    LIMIT 1
");

// Get global email setting
$emailEnabled = getSetting('email_system_enabled', '1');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'toggle_email_system':
                $enabled = isset($_POST['email_system_enabled']) ? '1' : '0';
                updateSetting('email_system_enabled', $enabled);
                logActivity('email_system_' . ($enabled ? 'enabled' : 'disabled'), 
                          'Email system ' . ($enabled ? 'enabled' : 'disabled'));
                $message = 'Email system ' . ($enabled ? 'enabled' : 'disabled') . ' successfully!';
                break;

            case 'update_smtp':
                if ($emailEnabled !== '1') {
                    throw new Exception('Email system is currently disabled');
                }

                $configData = [
                    'smtp_host' => sanitize($_POST['smtp_host']),
                    'smtp_port' => (int)$_POST['smtp_port'],
                    'smtp_user' => sanitize($_POST['smtp_user']),
                    'use_ssl' => isset($_POST['use_ssl']) ? 1 : 0,
                    'use_tls' => isset($_POST['use_tls']) ? 1 : 0,
                    'from_email' => sanitize($_POST['from_email']),
                    'from_name' => sanitize($_POST['from_name']),
                    'reply_to_email' => sanitize($_POST['reply_to_email']),
                    'is_active' => 1,
                    'updated_date' => date('Y-m-d H:i:s')
                ];

                // Only update password if provided
                if (!empty($_POST['smtp_password'])) {
                    $configData['smtp_password'] = password_hash($_POST['smtp_password'], PASSWORD_DEFAULT);
                }

                // Validate required fields
                if (empty($configData['smtp_host']) || empty($configData['smtp_user']) || 
                    empty($configData['from_email']) || empty($configData['from_name'])) {
                    throw new Exception('All required fields must be filled out');
                }

                $db->beginTransaction();
                $db->query("UPDATE smtp_configuration SET is_active = 0");

                if ($smtpConfig) {
                    $db->update('smtp_configuration', $configData, 'config_id = ?', [$smtpConfig['config_id']]);
                } else {
                    $db->insert('smtp_configuration', $configData);
                }

                $db->commit();
                $message = 'SMTP configuration updated successfully!';

                // Refresh configuration
                $smtpConfig = $db->fetchOne("
                    SELECT * FROM smtp_configuration 
                    WHERE is_active = 1 
                    ORDER BY config_id DESC 
                    LIMIT 1
                ");
                break;

            case 'test_smtp':
                if ($emailEnabled !== '1') {
                    throw new Exception('Email system is currently disabled');
                }

                $testEmail = sanitize($_POST['test_email']);
                if (empty($testEmail)) {
                    throw new Exception('Test email address is required');
                }

                $emailService = new EmailService();
                $result = $emailService->sendTestEmail(
                    $testEmail,
                    'Test Email from ' . APP_NAME,
                    "This is a test email from your " . APP_NAME . " installation.\n\n" .
                    "If you received this email, your SMTP configuration is working correctly.\n\n" .
                    "Sent at: " . date('Y-m-d H:i:s')
                );

                if ($result) {
                    $db->update(
                        'smtp_configuration',
                        [
                            'last_tested_date' => date('Y-m-d H:i:s'),
                            'last_test_result' => 'Success',
                            'test_error_message' => null
                        ],
                        'is_active = 1'
                    );
                    $message = 'Test email sent successfully to ' . $testEmail;
                } else {
                    throw new Exception('Failed to send test email');
                }
                break;
        }
    } catch (Exception $e) {
        if ($action === 'test_smtp') {
            $db->update(
                'smtp_configuration',
                [
                    'last_tested_date' => date('Y-m-d H:i:s'),
                    'last_test_result' => 'Failed',
                    'test_error_message' => $e->getMessage()
                ],
                'is_active = 1'
            );
        }
        $error = $e->getMessage();
    }
}
?>

<div class="row">
    <div class="col-12 mb-4">
        <!-- Global Email System Toggle -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Email System Status</h5>
                <div class="form-check form-switch">
                    <form method="POST" id="emailSystemForm">
                        <input type="hidden" name="action" value="toggle_email_system">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="checkbox" name="email_system_enabled" class="form-check-input" 
                               id="emailSystemToggle" <?php echo $emailEnabled === '1' ? 'checked' : ''; ?>
                               onchange="document.getElementById('emailSystemForm').submit();">
                        <label class="form-check-label" for="emailSystemToggle">
                            <?php echo $emailEnabled === '1' ? 'System Enabled' : 'System Disabled'; ?>
                        </label>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if ($emailEnabled !== '1'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Email System Disabled:</strong> No emails will be sent until the system is enabled.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Email System Enabled:</strong> The system will send emails based on configured triggers.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($emailEnabled === '1'): ?>
        <div class="col-lg-8">
            <!-- SMTP Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">SMTP Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_smtp">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host *</label>
                                    <input type="text" name="smtp_host" class="form-control" required
                                           value="<?php echo sanitize($smtpConfig['smtp_host'] ?? ''); ?>"
                                           placeholder="e.g., smtp.gmail.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Port *</label>
                                    <input type="number" name="smtp_port" class="form-control" required
                                           value="<?php echo $smtpConfig['smtp_port'] ?? '587'; ?>"
                                           placeholder="587">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Username *</label>
                                    <input type="text" name="smtp_user" class="form-control" required
                                           value="<?php echo sanitize($smtpConfig['smtp_user'] ?? ''); ?>"
                                           placeholder="your-email@example.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Password <?php echo $smtpConfig ? '(leave blank to keep current)' : '*'; ?></label>
                                    <input type="password" name="smtp_password" class="form-control"
                                           <?php echo $smtpConfig ? '' : 'required'; ?>>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Email *</label>
                                    <input type="email" name="from_email" class="form-control" required
                                           value="<?php echo sanitize($smtpConfig['from_email'] ?? ''); ?>"
                                           placeholder="noreply@yourdomain.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Name *</label>
                                    <input type="text" name="from_name" class="form-control" required
                                           value="<?php echo sanitize($smtpConfig['from_name'] ?? APP_NAME); ?>"
                                           placeholder="<?php echo APP_NAME; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reply-To Email</label>
                            <input type="email" name="reply_to_email" class="form-control"
                                   value="<?php echo sanitize($smtpConfig['reply_to_email'] ?? ''); ?>"
                                   placeholder="Optional reply-to address">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="use_ssl" class="form-check-input" value="1"
                                           <?php echo ($smtpConfig['use_ssl'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Use SSL</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="use_tls" class="form-check-input" value="1"
                                           <?php echo ($smtpConfig['use_tls'] ?? true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Use TLS</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>

            <!-- Test Email -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Test Email Configuration</h5>
                </div>
                <div class="card-body">
                    <?php if ($smtpConfig): ?>
                        <form method="POST" class="row g-3 align-items-end">
                            <input type="hidden" name="action" value="test_smtp">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

                            <div class="col-md-8">
                                <label class="form-label">Send Test Email To</label>
                                <input type="email" name="test_email" class="form-control" required
                                       placeholder="Enter email address for test">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane"></i> Send Test
                                </button>
                            </div>
                        </form>

                        <?php if ($smtpConfig['last_tested_date']): ?>
                            <div class="mt-3">
                                <p>
                                    <strong>Last Test:</strong> 
                                    <?php echo formatDateTime($smtpConfig['last_tested_date']); ?>
                                    <span class="badge bg-<?php echo $smtpConfig['last_test_result'] === 'Success' ? 'success' : 'danger'; ?>">
                                        <?php echo $smtpConfig['last_test_result']; ?>
                                    </span>
                                </p>
                                <?php if ($smtpConfig['test_error_message']): ?>
                                    <div class="alert alert-danger">
                                        <?php echo sanitize($smtpConfig['test_error_message']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please configure and save SMTP settings before testing.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Help Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Email Setup Help</h5>
                </div>
                <div class="card-body">
                    <h6>Common SMTP Settings</h6>
                    <div class="accordion" id="smtpHelp">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#gmailHelp">
                                    Gmail Settings
                                </button>
                            </h2>
                            <div id="gmailHelp" class="accordion-collapse collapse" data-bs-parent="#smtpHelp">
                                <div class="accordion-body">
                                    <ul class="list-unstyled">
                                        <li><strong>SMTP Host:</strong> smtp.gmail.com</li>
                                        <li><strong>SMTP Port:</strong> 587</li>
                                        <li><strong>Use TLS:</strong> Yes</li>
                                        <li><strong>Username:</strong> Your Gmail address</li>
                                        <li><strong>Password:</strong> App-specific password</li>
                                    </ul>
                                    <small class="text-muted">Note: You need to enable 2-factor auth and generate an app password.</small>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#office365Help">
                                    Office 365 Settings
                                </button>
                            </h2>
                            <div id="office365Help" class="accordion-collapse collapse" data-bs-parent="#smtpHelp">
                                <div class="accordion-body">
                                    <ul class="list-unstyled">
                                        <li><strong>SMTP Host:</strong> smtp.office365.com</li>
                                        <li><strong>SMTP Port:</strong> 587</li>
                                        <li><strong>Use TLS:</strong> Yes</li>
                                        <li><strong>Username:</strong> Your Office 365 email</li>
                                        <li><strong>Password:</strong> Your Office 365 password</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4">Troubleshooting</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Verify all required fields are filled</li>
                        <li><i class="fas fa-check text-success"></i> Check port numbers match encryption settings</li>
                        <li><i class="fas fa-check text-success"></i> Ensure From email matches SMTP credentials</li>
                        <li><i class="fas fa-check text-success"></i> Test with a known working email address</li>
                    </ul>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        After saving settings, use the test email function to verify your configuration.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>