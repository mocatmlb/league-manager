<?php
/**
 * Email Templates Section
 */

require_once __DIR__ . '/../../../../includes/EmailService.php';

// Get all email templates
$templates = $db->fetchAll("
    SELECT t.*, 
           (SELECT COUNT(*) FROM email_recipients r WHERE r.template_name = t.template_name) as recipient_count
    FROM email_templates t
    ORDER BY t.template_name
");

// Get template for editing if specified
$editTemplate = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editTemplate = $db->fetchOne("
        SELECT * FROM email_templates 
        WHERE template_id = ?", 
        [(int)$_GET['edit']]
    );
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_template':
                $templateId = (int)$_POST['template_id'];
                $templateData = [
                    'subject_template' => trim($_POST['subject_template']),
                    'body_template' => trim($_POST['body_template']),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'updated_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $currentUser['id']
                ];
                
                // Validate required fields
                if (empty($templateData['subject_template']) || empty($templateData['body_template'])) {
                    throw new Exception('Subject and body templates are required.');
                }
                
                $db->update('email_templates', $templateData, 'template_id = ?', [$templateId]);
                
                logActivity('template_updated', "Email template updated: " . $_POST['template_name']);
                $message = 'Email template updated successfully!';
                break;

            case 'test_template':
                $templateId = (int)$_POST['template_id'];
                $testEmail = sanitize($_POST['test_email']);
                
                if (empty($testEmail)) {
                    throw new Exception('Test email address is required.');
                }
                
                // Get template
                $template = $db->fetchOne("SELECT * FROM email_templates WHERE template_id = ?", [$templateId]);
                if (!$template) {
                    throw new Exception('Template not found.');
                }

                // Check if template is active
                if (!$template['is_active']) {
                    throw new Exception('Cannot test inactive template.');
                }
                
                // Sample context for testing
                $sampleContext = [
                    'game_number' => '2024001',
                    'away_team' => 'Test Away Team',
                    'home_team' => 'Test Home Team',
                    'game_date' => date('Y-m-d'),
                    'game_time' => '19:00:00',
                    'location' => 'Test Field',
                    'away_score' => '5',
                    'home_score' => '3',
                    'requested_by' => 'Test User',
                    'reason' => 'Test reason for change',
                    'change_request_id' => '123',
                    'current_date' => date('Y-m-d H:i:s'),
                    'requested_date' => date('Y-m-d', strtotime('+1 week')),
                    'requested_time' => '18:00:00',
                    'new_location' => 'New Test Field',
                    'admin_comment' => 'Test admin comment',
                    'approval_date' => date('Y-m-d H:i:s'),
                    'submission_date' => date('Y-m-d H:i:s')
                ];
                
                // Initialize email service
                $emailService = new EmailService();
                
                // Process template with sample data
                $processedSubject = $emailService->processTemplate($template['subject_template'], $sampleContext);
                $processedBody = $emailService->processTemplate($template['body_template'], $sampleContext);
                
                // Send test email
                $result = $emailService->sendTestEmail(
                    $testEmail,
                    "[TEST] " . $processedSubject,
                    $processedBody
                );
                
                if ($result) {
                    logActivity('template_tested', "Test email sent for template: " . $template['template_name']);
                    $message = 'Test email sent successfully to ' . $testEmail;
                } else {
                    throw new Exception('Failed to send test email. Check SMTP configuration.');
                }
                break;

            case 'toggle_template':
                $templateId = (int)$_POST['template_id'];
                $isActive = (int)$_POST['is_active'];
                
                $db->update(
                    'email_templates',
                    [
                        'is_active' => $isActive,
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_by' => $currentUser['id']
                    ],
                    'template_id = ?',
                    [$templateId]
                );
                
                logActivity(
                    'template_' . ($isActive ? 'enabled' : 'disabled'),
                    "Email template " . ($isActive ? 'enabled' : 'disabled') . ": ID $templateId"
                );
                $message = 'Template ' . ($isActive ? 'enabled' : 'disabled') . ' successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get available template variables
$templateVariables = [
    'Game Information' => [
        'game_number' => 'Game number/identifier',
        'away_team' => 'Away team name',
        'home_team' => 'Home team name',
        'game_date' => 'Scheduled game date',
        'game_time' => 'Scheduled game time',
        'location' => 'Game location',
        'away_score' => 'Away team score',
        'home_score' => 'Home team score'
    ],
    'Schedule Changes' => [
        'requested_date' => 'Requested new date',
        'requested_time' => 'Requested new time',
        'new_location' => 'Requested new location',
        'reason' => 'Change request reason',
        'requested_by' => 'Name of requester',
        'change_request_id' => 'Change request ID'
    ],
    'Administrative' => [
        'admin_comment' => 'Admin comment/notes',
        'approval_date' => 'Date of approval/denial',
        'submission_date' => 'Date request was submitted',
        'current_date' => 'Current date/time'
    ]
];
?>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Template List -->
        <?php if (!$editTemplate): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Email Templates</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Template Name</th>
                                    <th>Subject</th>
                                    <th>Recipients</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td><?php echo sanitize($template['template_name']); ?></td>
                                        <td><?php echo sanitize(substr($template['subject_template'], 0, 50)) . '...'; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $template['recipient_count']; ?> recipient(s)
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?php echo $template['is_active'] ? 'disable' : 'enable'; ?> this template?');">
                                                <input type="hidden" name="action" value="toggle_template">
                                                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                                <input type="hidden" name="template_id" value="<?php echo $template['template_id']; ?>">
                                                <input type="hidden" name="is_active" value="<?php echo $template['is_active'] ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-sm btn-<?php echo $template['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?section=email-templates&edit=<?php echo $template['template_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <?php if ($template['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info"
                                                            onclick="showTestTemplate(<?php echo $template['template_id']; ?>, '<?php echo addslashes($template['template_name']); ?>')">
                                                        <i class="fas fa-paper-plane"></i> Test
                                                    </button>
                                                <?php endif; ?>
                                                <a href="?section=email-recipients&template=<?php echo urlencode($template['template_name']); ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-users"></i> Recipients
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Edit Template -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Template: <?php echo sanitize($editTemplate['template_name']); ?></h5>
                    <a href="?section=email-templates" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_template">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="template_id" value="<?php echo $editTemplate['template_id']; ?>">
                        <input type="hidden" name="template_name" value="<?php echo sanitize($editTemplate['template_name']); ?>">

                        <div class="mb-3">
                            <label class="form-label">Subject Template</label>
                            <input type="text" name="subject_template" class="form-control" required
                                   value="<?php echo sanitize($editTemplate['subject_template']); ?>">
                            <div class="form-text">
                                Use variables like {game_number} in curly braces.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Body Template</label>
                            <textarea name="body_template" class="form-control" rows="20" required><?php 
                                echo sanitize($editTemplate['body_template']); 
                            ?></textarea>
                            <div class="form-text">
                                HTML is supported. Use variables in curly braces.
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1"
                                       <?php echo $editTemplate['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label">Template Active</label>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Template
                            </button>
                            <?php if ($editTemplate['is_active']): ?>
                                <button type="button" class="btn btn-info"
                                        onclick="showTestTemplate(<?php echo $editTemplate['template_id']; ?>, '<?php echo addslashes($editTemplate['template_name']); ?>')">
                                    <i class="fas fa-paper-plane"></i> Test Template
                                </button>
                            <?php endif; ?>
                            <a href="?section=email-recipients&template=<?php echo urlencode($editTemplate['template_name']); ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-users"></i> Manage Recipients
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Template Variables -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Available Variables</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="variablesAccordion">
                    <?php foreach ($templateVariables as $category => $variables): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#vars<?php echo str_replace(' ', '', $category); ?>">
                                    <?php echo $category; ?>
                                </button>
                            </h2>
                            <div id="vars<?php echo str_replace(' ', '', $category); ?>" 
                                 class="accordion-collapse collapse" 
                                 data-bs-parent="#variablesAccordion">
                                <div class="accordion-body">
                                    <table class="table table-sm">
                                        <tbody>
                                            <?php foreach ($variables as $var => $desc): ?>
                                                <tr>
                                                    <td><code>{<?php echo $var; ?>}</code></td>
                                                    <td><?php echo $desc; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    Use these variables in curly braces (e.g., <code>{game_number}</code>) in your templates.
                </div>
            </div>
        </div>

        <!-- Template Tips -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Template Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success"></i> Use HTML for formatting</li>
                    <li><i class="fas fa-check text-success"></i> Test templates before activating</li>
                    <li><i class="fas fa-check text-success"></i> Keep subject lines concise</li>
                    <li><i class="fas fa-check text-success"></i> Include all relevant information</li>
                    <li><i class="fas fa-check text-success"></i> Use consistent styling</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Test Template Modal -->
<div class="modal fade" id="testTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Test Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="test_template">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="template_id" id="testTemplateId">
                    
                    <p>Send a test email using template: <strong id="testTemplateName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Send Test To</label>
                        <input type="email" name="test_email" class="form-control" required
                               placeholder="Enter email address">
                        <div class="form-text">
                            Sample data will be used to populate template variables.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTestTemplate(templateId, templateName) {
    document.getElementById('testTemplateId').value = templateId;
    document.getElementById('testTemplateName').textContent = templateName;
    new bootstrap.Modal(document.getElementById('testTemplateModal')).show();
}
</script>