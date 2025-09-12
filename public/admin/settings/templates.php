<?php
/**
 * District 8 Travel League - Email Template Management
 */

// Environment-aware bootstrap include (production vs development)
$__bootstrap = file_exists(__DIR__ . '/../../includes/bootstrap.php')
    ? __DIR__ . '/../../includes/bootstrap.php'      // Production: /admin/settings -> ../../includes
    : __DIR__ . '/../../../includes/bootstrap.php';   // Development: /public/admin/settings -> ../../../includes
require_once $__bootstrap;
unset($__bootstrap);

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';

// Include EmailService for testing
require_once EnvLoader::getPath('includes/EmailService.php');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_template':
                try {
                    $templateId = (int)$_POST['template_id'];
                    $templateData = [
                        'subject_template' => trim($_POST['subject_template']),
                        'body_template' => trim($_POST['body_template']),
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        'updated_date' => date('Y-m-d H:i:s')
                    ];
                    
                    // Validate required fields
                    if (empty($templateData['subject_template']) || empty($templateData['body_template'])) {
                        throw new Exception('Subject and body templates are required.');
                    }
                    
                    $db->update('email_templates', $templateData, 'template_id = :template_id', ['template_id' => $templateId]);
                    
                    logActivity('template_updated', "Email template updated: " . $_POST['template_name'], null);
                    $message = 'Email template updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating template: ' . $e->getMessage();
                }
                break;
                
            case 'create_template':
                try {
                    $templateData = [
                        'template_name' => trim($_POST['template_name']),
                        'subject_template' => trim($_POST['subject_template']),
                        'body_template' => trim($_POST['body_template']),
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        'created_date' => date('Y-m-d H:i:s'),
                        'updated_date' => date('Y-m-d H:i:s')
                    ];
                    
                    // Validate required fields
                    if (empty($templateData['template_name']) || empty($templateData['subject_template']) || empty($templateData['body_template'])) {
                        throw new Exception('Template name, subject, and body are required.');
                    }
                    
                    // Check if template name already exists
                    $existing = $db->fetchOne("SELECT template_id FROM email_templates WHERE template_name = ?", [$templateData['template_name']]);
                    if ($existing) {
                        throw new Exception('A template with this name already exists.');
                    }
                    
                    $db->insert('email_templates', $templateData);
                    
                    logActivity('template_created', "Email template created: " . $templateData['template_name'], null);
                    $message = 'Email template created successfully!';
                } catch (Exception $e) {
                    $error = 'Error creating template: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_active':
                try {
                    $templateId = (int)$_POST['template_id'];
                    $currentStatus = (int)$_POST['current_status'];
                    $newStatus = $currentStatus ? 0 : 1;
                    
                    $db->update('email_templates', [
                        'is_active' => $newStatus,
                        'updated_date' => date('Y-m-d H:i:s')
                    ], 'template_id = :template_id', ['template_id' => $templateId]);
                    
                    $statusText = $newStatus ? 'activated' : 'deactivated';
                    logActivity('template_status_changed', "Email template {$statusText}: " . $_POST['template_name'], null);
                    $message = "Template {$statusText} successfully!";
                } catch (Exception $e) {
                    $error = 'Error updating template status: ' . $e->getMessage();
                }
                break;
                
            case 'test_template':
                try {
                    $templateId = (int)$_POST['template_id'];
                    $testEmail = trim($_POST['test_email']);
                    
                    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Please provide a valid test email address.');
                    }
                    
                    // Get template
                    $template = $db->fetchOne("SELECT * FROM email_templates WHERE template_id = ?", [$templateId]);
                    if (!$template) {
                        throw new Exception('Template not found.');
                    }
                    
                    // Create sample context data
                    $sampleContext = [
                        'game_number' => '2025999',
                        'game_date' => date('m/d/Y'),
                        'game_time' => '7:00 PM',
                        'home_team' => 'Sample Home Team',
                        'away_team' => 'Sample Away Team',
                        'location' => 'Sample Field Location',
                        'home_score' => '5',
                        'away_score' => '3',
                        'change_request_id' => '999',
                        'requested_date' => date('Y-m-d'),
                        'requested_time' => '6:30 PM',
                        'new_location' => 'New Sample Location',
                        'reason' => 'Sample reason for change',
                        'requested_by' => 'Sample Coach (555-1234, coach@example.com)',
                        'admin_comment' => 'Sample admin comment',
                        'approval_date' => date('Y-m-d g:i A'),
                        'submission_date' => date('Y-m-d g:i A'),
                        'current_date' => date('Y-m-d H:i:s'),
                        'league_name' => 'District 8 Travel League'
                    ];
                    
                    // Use EmailService to send test email
                    $emailService = new EmailService();
                    
                    // Use reflection to access private method
                    $reflection = new ReflectionClass($emailService);
                    $method = $reflection->getMethod('processTemplate');
                    $method->setAccessible(true);
                    
                    $processedSubject = $method->invoke($emailService, $template['subject_template'], $sampleContext);
                    $processedBody = $method->invoke($emailService, $template['body_template'], $sampleContext);
                    
                    // Send test email using the public method
                    $testResult = $emailService->sendTestEmail(
                        $testEmail,
                        "[TEST] " . $processedSubject,
                        "*** THIS IS A TEST EMAIL ***\n\n" . $processedBody . "\n\n*** END TEST EMAIL ***"
                    );
                    
                    if ($testResult) {
                        logActivity('template_tested', "Test email sent for template: " . $template['template_name'] . " to " . $testEmail, null);
                        $message = 'Test email sent successfully to ' . $testEmail . '!';
                    } else {
                        throw new Exception('Failed to send test email. Check SMTP configuration.');
                    }
                } catch (Exception $e) {
                    $error = 'Error sending test email: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all templates
$templates = $db->fetchAll("
    SELECT * FROM email_templates 
    ORDER BY template_name
");

// Get template for editing if specified
$editTemplate = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editTemplate = $db->fetchOne("SELECT * FROM email_templates WHERE template_id = ?", [(int)$_GET['edit']]);
}

// Available system variables for helper
$systemVariables = [
    'Game Variables' => [
        '{game_number}' => 'Unique game identifier',
        '{game_date}' => 'Game date (MM/DD/YYYY format)',
        '{game_time}' => 'Game time (12-hour format)',
        '{home_team}' => 'Home team name',
        '{away_team}' => 'Away team name',
        '{location}' => 'Game location/field',
        '{home_score}' => 'Home team score',
        '{away_score}' => 'Away team score',
        '{game_status}' => 'Game status (Active, Completed, Cancelled)'
    ],
    'Schedule Change Variables' => [
        '{change_request_id}' => 'Schedule change request ID',
        '{requested_date}' => 'Requested new date',
        '{requested_time}' => 'Requested new time',
        '{new_location}' => 'Requested new location',
        '{reason}' => 'Reason for schedule change',
        '{requested_by}' => 'Person requesting change',
        '{admin_comment}' => 'Administrator comment',
        '{approval_date}' => 'Date/time of approval',
        '{submission_date}' => 'Date/time of original request'
    ],
    'System Variables' => [
        '{current_date}' => 'Current date and time',
        '{league_name}' => 'League name (District 8 Travel League)'
    ],
    'HTML Formatting' => [
        '<strong>text</strong>' => 'Bold text',
        '<em>text</em>' => 'Italic text',
        '<br>' => 'Line break',
        '<p>text</p>' => 'Paragraph',
        '<h2>title</h2>' => 'Heading',
        '<table><tr><td>cell</td></tr></table>' => 'Table structure',
        '<div class="alert alert-info">message</div>' => 'Info alert box',
        '<div class="alert alert-success">message</div>' => 'Success alert box',
        '<div class="alert alert-warning">message</div>' => 'Warning alert box'
    ]
];

$pageTitle = 'Email Template Management - District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .template-card {
            transition: all 0.3s ease;
        }
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .variable-helper {
            max-height: 300px;
            overflow-y: auto;
        }
        .variable-item {
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 3px;
            margin: 2px 0;
        }
        .variable-item:hover {
            background-color: #f8f9fa;
        }
        .template-preview {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/settings -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/settings -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-envelope-open-text"></i> Email Template Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Settings</a></li>
                            <li class="breadcrumb-item active">Email Templates</li>
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
                                    <a href="email.php" class="btn btn-outline-primary">
                                        <i class="fas fa-envelope"></i> Email Configuration
                                    </a>
                                    <a href="templates.php" class="btn btn-outline-primary active">
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
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Template List -->
                <?php if (!$editTemplate): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3>Email Templates</h3>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                                <i class="fas fa-plus"></i> Create New Template
                            </button>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($templates as $template): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card template-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($template['template_name']); ?></h6>
                                        <span class="badge <?php echo $template['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Subject:</strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($template['subject_template'], 0, 60)) . (strlen($template['subject_template']) > 60 ? '...' : ''); ?></small>
                                        </p>
                                        <p class="card-text">
                                            <strong>Body Preview:</strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($template['body_template'], 0, 100)) . (strlen($template['body_template']) > 100 ? '...' : ''); ?></small>
                                        </p>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Last updated: <?php echo date('M j, Y g:i A', strtotime($template['updated_date'])); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group w-100" role="group">
                                            <a href="?edit=<?php echo $template['template_id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-info btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#testTemplateModal"
                                                    data-template-id="<?php echo $template['template_id']; ?>"
                                                    data-template-name="<?php echo htmlspecialchars($template['template_name']); ?>">
                                                <i class="fas fa-paper-plane"></i> Test
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="template_id" value="<?php echo $template['template_id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $template['is_active']; ?>">
                                                <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($template['template_name']); ?>">
                                                <button type="submit" class="btn btn-outline-<?php echo $template['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                                        onclick="return confirm('Are you sure you want to <?php echo $template['is_active'] ? 'deactivate' : 'activate'; ?> this template?')">
                                                    <i class="fas fa-<?php echo $template['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    <?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Template Editor -->
                <?php if ($editTemplate): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Edit Template: <?php echo htmlspecialchars($editTemplate['template_name']); ?></h5>
                                <a href="templates.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Back to Templates
                                </a>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="update_template">
                                    <input type="hidden" name="template_id" value="<?php echo $editTemplate['template_id']; ?>">
                                    <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($editTemplate['template_name']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="subject_template" class="form-label">Subject Template</label>
                                        <input type="text" class="form-control" id="subject_template" name="subject_template" 
                                               value="<?php echo htmlspecialchars($editTemplate['subject_template']); ?>" required>
                                        <div class="form-text">Use variables like {game_number}, {home_team}, etc.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="body_template" class="form-label">Body Template (HTML)</label>
                                        <textarea class="form-control" id="body_template" name="body_template" rows="20" required style="font-family: 'Courier New', monospace; font-size: 14px;"><?php echo htmlspecialchars($editTemplate['body_template']); ?></textarea>
                                        <div class="form-text">
                                            <strong>HTML Email Template:</strong> Use HTML tags for formatting. Variables like {game_number} will be replaced automatically.
                                            <br><strong>Tip:</strong> Use &lt;br&gt; for line breaks, &lt;strong&gt; for bold, &lt;table&gt; for structured data.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                               <?php echo $editTemplate['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Template is active (will send notifications)
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Template
                                        </button>
                                        <button type="button" class="btn btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#testTemplateModal"
                                                data-template-id="<?php echo $editTemplate['template_id']; ?>"
                                                data-template-name="<?php echo htmlspecialchars($editTemplate['template_name']); ?>">
                                            <i class="fas fa-paper-plane"></i> Test Template
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Available Variables</h6>
                            </div>
                            <div class="card-body variable-helper">
                                <?php foreach ($systemVariables as $category => $variables): ?>
                                <h6 class="text-primary"><?php echo $category; ?></h6>
                                <?php foreach ($variables as $variable => $description): ?>
                                <div class="variable-item" onclick="insertVariable(<?php echo htmlspecialchars(json_encode($variable)); ?>)">
                                    <strong><?php echo htmlspecialchars($variable); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($description); ?></small>
                                </div>
                                <?php endforeach; ?>
                                <hr>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div class="modal fade" id="createTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="create_template">
                        
                        <div class="mb-3">
                            <label for="new_template_name" class="form-label">Template Name</label>
                            <input type="text" class="form-control" id="new_template_name" name="template_name" 
                                   placeholder="e.g., onScheduleChangeDenied" required>
                            <div class="form-text">Use camelCase naming convention (e.g., onGameCancellation)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_subject_template" class="form-label">Subject Template</label>
                            <input type="text" class="form-control" id="new_subject_template" name="subject_template" 
                                   placeholder="e.g., Game Cancelled: {game_number}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_body_template" class="form-label">Body Template (HTML)</label>
                            <textarea class="form-control" id="new_body_template" name="body_template" rows="15" 
                                      placeholder="Enter your HTML email template content here..." required style="font-family: 'Courier New', monospace; font-size: 14px;"></textarea>
                            <div class="form-text">Use HTML tags for professional formatting. Variables will be replaced automatically.</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="new_is_active" name="is_active" checked>
                            <label class="form-check-label" for="new_is_active">
                                Template is active
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Test Template Modal -->
    <div class="modal fade" id="testTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="test_template">
                        <input type="hidden" name="template_id" id="test_template_id">
                        
                        <p>Send a test email for template: <strong id="test_template_name"></strong></p>
                        
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" 
                                   placeholder="your-email@example.com" required>
                            <div class="form-text">The test email will include sample data for all variables.</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This will send a real email with sample data to test the template formatting.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Test Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle test template modal
        document.getElementById('testTemplateModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var templateId = button.getAttribute('data-template-id');
            var templateName = button.getAttribute('data-template-name');
            
            document.getElementById('test_template_id').value = templateId;
            document.getElementById('test_template_name').textContent = templateName;
        });
        
        // Insert variable into active textarea
        function insertVariable(variable) {
            var activeElement = document.activeElement;
            if (activeElement && (activeElement.id === 'body_template' || activeElement.id === 'subject_template' || 
                                 activeElement.id === 'new_body_template' || activeElement.id === 'new_subject_template')) {
                var start = activeElement.selectionStart;
                var end = activeElement.selectionEnd;
                var text = activeElement.value;
                
                activeElement.value = text.substring(0, start) + variable + text.substring(end);
                activeElement.selectionStart = activeElement.selectionEnd = start + variable.length;
                activeElement.focus();
            } else {
                // If no textarea is focused, copy to clipboard
                navigator.clipboard.writeText(variable).then(function() {
                    // Show a brief notification
                    var notification = document.createElement('div');
                    notification.className = 'alert alert-success position-fixed';
                    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; opacity: 0.9;';
                    notification.innerHTML = '<i class="fas fa-copy"></i> Variable copied to clipboard!';
                    document.body.appendChild(notification);
                    
                    setTimeout(function() {
                        document.body.removeChild(notification);
                    }, 2000);
                });
            }
        }
        
        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            var textareas = document.querySelectorAll('textarea');
            textareas.forEach(function(textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
    </script>
</body>
</html>
