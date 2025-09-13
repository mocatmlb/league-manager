<?php
/**
 * League Contacts Section
 */



// Get all contacts
$contacts = $db->fetchAll("
    SELECT * FROM league_officials 
    ORDER BY sort_order ASC, name ASC
");

// Get contact for editing if specified
$editContact = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editContact = $db->fetchOne("
        SELECT * FROM league_officials 
        WHERE official_id = ?", 
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
            case 'add_contact':
                $contactData = [
                    'name' => sanitize($_POST['name']),
                    'role' => sanitize($_POST['role']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'display_on_contact_page' => isset($_POST['display_on_contact_page']) ? 1 : 0,
                    'sort_order' => (int)$_POST['sort_order'],
                    'active_status' => isset($_POST['is_active']) ? 'Active' : 'Inactive'
                ];
                
                // Validate required fields
                if (empty($contactData['name']) || empty($contactData['role']) || empty($contactData['email'])) {
                    throw new Exception('Name, role, and email are required fields.');
                }
                
                // Validate email format
                if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format.');
                }
                
                $db->insert('league_officials', $contactData);
                logActivity('contact_added', "League contact added: {$contactData['name']}");
                $message = 'Contact added successfully!';
                break;

            case 'update_contact':
                $contactId = (int)$_POST['contact_id'];
                $contactData = [
                    'name' => sanitize($_POST['name']),
                    'role' => sanitize($_POST['role']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'display_on_contact_page' => isset($_POST['display_on_contact_page']) ? 1 : 0,
                    'sort_order' => (int)$_POST['sort_order'],
                    'active_status' => isset($_POST['is_active']) ? 'Active' : 'Inactive'
                ];
                
                // Validate required fields
                if (empty($contactData['name']) || empty($contactData['role']) || empty($contactData['email'])) {
                    throw new Exception('Name, role, and email are required fields.');
                }
                
                // Validate email format
                if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format.');
                }
                
                $db->update('league_officials', $contactData, 'official_id = ?', [$contactId]);
                logActivity('contact_updated', "League contact updated: {$contactData['name']}");
                $message = 'Contact updated successfully!';
                break;

            case 'delete_contact':
                $contactId = (int)$_POST['contact_id'];
                $contact = $db->fetchOne("SELECT name FROM league_officials WHERE official_id = ?", [$contactId]);
                
                if ($contact) {
                    $db->delete('league_officials', 'official_id = ?', [$contactId]);
                    logActivity('contact_deleted', "League contact deleted: {$contact['name']}");
                    $message = 'Contact deleted successfully!';
                }
                break;

            case 'reorder_contacts':
                $order = json_decode($_POST['order'], true);
                foreach ($order as $position => $contactId) {
                    $db->update(
                        'league_officials',
                        ['sort_order' => $position],
                        'official_id = ?',
                        [$contactId]
                    );
                }
                $message = 'Contact order updated successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// No predefined roles needed
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
        <!-- Contacts List -->
        <?php if (!$editContact): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">League Contacts</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addContactModal">
                        <i class="fas fa-plus"></i> Add Contact
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="contactsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contactsList">
                                <?php foreach ($contacts as $contact): ?>
                                    <tr data-contact-id="<?php echo $contact['official_id']; ?>">
                                        <td><?php echo sanitize($contact['name']); ?></td>
                                        <td><?php echo sanitize($contact['role']); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo sanitize($contact['email']); ?>">
                                                <?php echo sanitize($contact['email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($contact['phone']): ?>
                                                <a href="tel:<?php echo sanitize($contact['phone']); ?>">
                                                    <?php echo sanitize($contact['phone']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $contact['active_status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                <?php echo $contact['active_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="editContact(<?php echo htmlspecialchars(json_encode($contact)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteContact(<?php echo $contact['official_id']; ?>, '<?php echo addslashes($contact['name']); ?>')">
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
                <h5 class="card-title mb-0">Contact Management Help</h5>
            </div>
            <div class="card-body">
                <h6>Contact Information</h6>
                <ul class="list-unstyled">
                    <li><strong>Name:</strong> Full name of the contact person</li>
                    <li><strong>Role:</strong> Official role or position (e.g., District Administrator, Program Director)</li>
                    <li><strong>Email:</strong> Primary contact email</li>
                    <li><strong>Phone:</strong> Contact phone number (optional)</li>
                </ul>

                <h6 class="mt-4">Display Order</h6>
                <p>
                    Contacts are displayed in the order specified. Lower numbers appear first.
                    You can also drag and drop contacts to reorder them.
                </p>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    Contact information is displayed on the public website and used for email notifications.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add League Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_contact">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="Enter contact name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <input type="text" name="role" class="form-control" required
                               placeholder="Enter contact role">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="Enter email address">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               placeholder="Enter phone number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="addSortOrder" class="form-control" value="0"
                               min="0" step="1">
                        <div class="form-text">
                            Lower numbers appear first. Leave as 0 to add to the end.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="addIsActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="display_on_contact_page" id="addDisplayOnContactPage" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Display on Contact Page</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit League Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_contact">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="contact_id" id="editContactId">
                    
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required
                               placeholder="Enter contact name">
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <input type="text" name="role" id="editRole" class="form-control" required
                               placeholder="Enter contact role">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required
                               placeholder="Enter email address">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="editPhone" class="form-control"
                               placeholder="Enter phone number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control"
                               min="0" step="1">
                        <div class="form-text">
                            Lower numbers appear first.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="is_active" id="editIsActive" class="form-check-input" value="1">
                            <label class="form-check-label">Active</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="display_on_contact_page" id="editDisplayOnContactPage" class="form-check-input" value="1">
                            <label class="form-check-label">Display on Contact Page</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Contact Modal -->
<div class="modal fade" id="deleteContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Delete League Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_contact">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="contact_id" id="deleteContactId">
                    
                    <p>Are you sure you want to delete the contact: <strong id="deleteContactName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<script>
// Initialize DataTables
$(document).ready(function() {
    $('#contactsTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: 5 } // Actions column
        ]
    });

    // Initialize drag-and-drop sorting
    const contactsList = document.getElementById('contactsList');
    if (contactsList) {
        new Sortable(contactsList, {
            animation: 150,
            handle: 'tr',
            onEnd: function() {
                const order = Array.from(contactsList.children).map(row => row.dataset.contactId);
                
                // Update order in database
                const form = new FormData();
                form.append('action', 'reorder_contacts');
                form.append('csrf_token', '<?php echo Auth::generateCSRFToken(); ?>');
                form.append('order', JSON.stringify(order));
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: form
                }).then(response => response.text())
                  .then(() => window.location.reload())
                  .catch(error => console.error('Error:', error));
            }
        });
    }
});

function editContact(contact) {
    document.getElementById('editContactId').value = contact.official_id;
    document.getElementById('editName').value = contact.name;
    document.getElementById('editRole').value = contact.role;
    document.getElementById('editEmail').value = contact.email;
    document.getElementById('editPhone').value = contact.phone || '';
    document.getElementById('editSortOrder').value = contact.sort_order;
    document.getElementById('editIsActive').checked = contact.active_status === 'Active';
    document.getElementById('editDisplayOnContactPage').checked = contact.display_on_contact_page == 1;
    
    new bootstrap.Modal(document.getElementById('editContactModal')).show();
}

function deleteContact(contactId, contactName) {
    document.getElementById('deleteContactId').value = contactId;
    document.getElementById('deleteContactName').textContent = contactName;
    new bootstrap.Modal(document.getElementById('deleteContactModal')).show();
}
</script>