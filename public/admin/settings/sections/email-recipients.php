<?php
/**
 * Email Recipients Section
 */

// Get template filter if specified
$filterTemplate = $_GET['template'] ?? null;

// Get all templates for dropdown
$templates = $db->fetchAll("
    SELECT template_id, template_name 
    FROM email_templates 
    ORDER BY template_name
");

// Get recipient for editing if specified
$editRecipient = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editRecipient = $db->fetchOne("
        SELECT * FROM email_recipients 
        WHERE recipient_id = ?", 
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
            case 'add_recipient':
                $recipientData = [
                    'template_name' => sanitize($_POST['template_name']),
                    'recipient_type' => sanitize($_POST['recipient_type']),
                    'recipient_source' => sanitize($_POST['recipient_source']),
                    'email_address' => sanitize($_POST['email_address']),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'created_date' => date('Y-m-d H:i:s')
                ];

                // Validate required fields
                if (empty($recipientData['template_name'])) {
                    throw new Exception('Template name is required');
                }

                if ($recipientData['recipient_source'] === 'Static_Email' && empty($recipientData['email_address'])) {
                    throw new Exception('Email address is required for static recipients');
                }

                $db->insert('email_recipients', $recipientData);
                logActivity('recipient_added', "Email recipient added for template: {$recipientData['template_name']}");
                $message = 'Email recipient added successfully!';
                break;

            case 'update_recipient':
                $recipientId = (int)$_POST['recipient_id'];
                $recipientData = [
                    'recipient_type' => sanitize($_POST['recipient_type']),
                    'recipient_source' => sanitize($_POST['recipient_source']),
                    'email_address' => sanitize($_POST['email_address']),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];

                if ($recipientData['recipient_source'] === 'Static_Email' && empty($recipientData['email_address'])) {
                    throw new Exception('Email address is required for static recipients');
                }

                $db->update('email_recipients', $recipientData, 'recipient_id = ?', [$recipientId]);
                logActivity('recipient_updated', "Email recipient updated: ID {$recipientId}");
                $message = 'Email recipient updated successfully!';
                break;

            case 'delete_recipient':
                $recipientId = (int)$_POST['recipient_id'];
                $db->delete('email_recipients', 'recipient_id = ?', [$recipientId]);
                logActivity('recipient_deleted', "Email recipient deleted: ID {$recipientId}");
                $message = 'Email recipient deleted successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all recipients with template names
$recipientsQuery = "
    SELECT r.*, t.template_name 
    FROM email_recipients r
    JOIN email_templates t ON r.template_name = t.template_name
";

if ($filterTemplate) {
    $recipientsQuery .= " WHERE t.template_name = ?";
    $recipients = $db->fetchAll($recipientsQuery . " ORDER BY t.template_name, r.recipient_type", [$filterTemplate]);
} else {
    $recipients = $db->fetchAll($recipientsQuery . " ORDER BY t.template_name, r.recipient_type");
}

// Define recipient types and sources
$recipientTypes = [
    'Team_Based' => 'Team Based',
    'Static_To' => 'Static To',
    'Static_CC' => 'Static CC',
    'Static_BCC' => 'Static BCC'
];

$recipientSources = [
    'Home_Team_Manager' => 'Home Team Manager',
    'Away_Team_Manager' => 'Away Team Manager',
    'Both_Team_Managers' => 'Both Team Managers',
    'Static_Email' => 'Static Email Address'
];
?>

<?php if (isset($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Recipients List -->
        <?php if (!$editRecipient): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">
                            Email Recipients
                            <?php if ($filterTemplate): ?>
                                for <?php echo sanitize($filterTemplate); ?>
                            <?php endif; ?>
                        </h5>
                        <?php if ($filterTemplate): ?>
                            <a href="?section=email-templates" class="btn btn-link btn-sm ps-0">
                                <i class="fas fa-arrow-left"></i> Back to Templates
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRecipientModal">
                        <i class="fas fa-plus"></i> Add Recipient
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="recipientsTable">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipients as $recipient): ?>
                                    <tr>
                                        <td><?php echo sanitize($recipient['template_name']); ?></td>
                                        <td><?php echo $recipientTypes[$recipient['recipient_type']] ?? $recipient['recipient_type']; ?></td>
                                        <td><?php echo $recipientSources[$recipient['recipient_source']] ?? $recipient['recipient_source']; ?></td>
                                        <td>
                                            <?php if ($recipient['recipient_source'] === 'Static_Email'): ?>
                                                <?php echo sanitize($recipient['email_address']); ?>
                                            <?php else: ?>
                                                <em class="text-muted">Dynamic</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $recipient['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $recipient['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="editRecipient(<?php echo htmlspecialchars(json_encode($recipient)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteRecipient(<?php echo $recipient['recipient_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Help Section -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recipient Configuration Help</h5>
            </div>
            <div class="card-body">
                <h6>Recipient Types</h6>
                <ul class="list-unstyled">
                    <li><strong>Team Based:</strong> Automatically determined from game data</li>
                    <li><strong>Static To:</strong> Primary recipients</li>
                    <li><strong>Static CC:</strong> Carbon copy recipients</li>
                    <li><strong>Static BCC:</strong> Blind carbon copy recipients</li>
                </ul>

                <h6 class="mt-4">Recipient Sources</h6>
                <ul class="list-unstyled">
                    <li><strong>Home Team Manager:</strong> Uses home team's manager email</li>
                    <li><strong>Away Team Manager:</strong> Uses away team's manager email</li>
                    <li><strong>Both Team Managers:</strong> Includes both managers</li>
                    <li><strong>Static Email:</strong> Uses specified email address</li>
                </ul>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    Configure recipients for each email template to ensure notifications reach the right people.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Recipient Modal -->
<div class="modal fade" id="addRecipientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Email Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_recipient">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Template *</label>
                        <select name="template_name" class="form-select" required>
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo sanitize($template['template_name']); ?>"
                                        <?php echo $filterTemplate === $template['template_name'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($template['template_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recipient Type *</label>
                        <select name="recipient_type" class="form-select" required>
                            <?php foreach ($recipientTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recipient Source *</label>
                        <select name="recipient_source" class="form-select" required onchange="toggleEmailField(this.value)">
                            <?php foreach ($recipientSources as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="emailField" style="display: none;">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email_address" class="form-control"
                               placeholder="Enter email address">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Recipient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Recipient Modal -->
<div class="modal fade" id="editRecipientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Email Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_recipient">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="recipient_id" id="editRecipientId">
                    
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <input type="text" id="editTemplateName" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recipient Type *</label>
                        <select name="recipient_type" id="editRecipientType" class="form-select" required>
                            <?php foreach ($recipientTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recipient Source *</label>
                        <select name="recipient_source" id="editRecipientSource" class="form-select" required 
                                onchange="toggleEmailField(this.value, 'edit')">
                            <?php foreach ($recipientSources as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="editEmailField" style="display: none;">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email_address" id="editEmailAddress" class="form-control"
                               placeholder="Enter email address">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="editIsActive" class="form-check-input" value="1">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Recipient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Recipient Modal -->
<div class="modal fade" id="deleteRecipientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Email Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_recipient">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="recipient_id" id="deleteRecipientId">
                    
                    <p>Are you sure you want to delete this recipient?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Recipient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleEmailField(source, mode = 'add') {
    const prefix = mode === 'edit' ? 'edit' : '';
    const emailField = document.getElementById(prefix + 'EmailField');
    const emailInput = emailField.querySelector('input[type="email"]');
    
    if (source === 'Static_Email') {
        emailField.style.display = 'block';
        emailInput.required = true;
    } else {
        emailField.style.display = 'none';
        emailInput.required = false;
    }
}

function editRecipient(recipient) {
    document.getElementById('editRecipientId').value = recipient.recipient_id;
    document.getElementById('editTemplateName').value = recipient.template_name;
    document.getElementById('editRecipientType').value = recipient.recipient_type;
    document.getElementById('editRecipientSource').value = recipient.recipient_source;
    document.getElementById('editEmailAddress').value = recipient.email_address || '';
    document.getElementById('editIsActive').checked = recipient.is_active == 1;
    
    toggleEmailField(recipient.recipient_source, 'edit');
    
    new bootstrap.Modal(document.getElementById('editRecipientModal')).show();
}

function deleteRecipient(recipientId) {
    document.getElementById('deleteRecipientId').value = recipientId;
    new bootstrap.Modal(document.getElementById('deleteRecipientModal')).show();
}

// Initialize email fields on page load
// Initialize DataTables
$(document).ready(function() {
    $('#recipientsTable').DataTable({
        order: [[0, 'asc'], [1, 'asc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: 5 } // Actions column
        ]
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const addSource = document.querySelector('select[name="recipient_source"]');
    if (addSource) {
        toggleEmailField(addSource.value);
    }
});
</script>
