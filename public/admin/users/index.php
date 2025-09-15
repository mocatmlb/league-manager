<?php
/**
 * District 8 Travel League - User Management (Admin)
 * 
 * Admin interface for managing user accounts in the new system
 */

define('D8TL_APP', true);
require_once '../../../includes/bootstrap.php';
require_once '../../../includes/AuthService.php';
require_once '../../../includes/InvitationManager.php';
require_once '../../../includes/AdminMigrationManager.php';

$authService = new AuthService();
$userAccountManager = $authService->getUserAccountManager();
$invitationManager = new InvitationManager();
$migrationManager = new AdminMigrationManager();

// Require admin authentication
$authService->requireAdmin();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authService->validateForm();
        
        switch ($action) {
            case 'send_invitation':
                $email = trim($_POST['email'] ?? '');
                $roleId = intval($_POST['role_id'] ?? 0);
                $currentUser = $authService->getCurrentUser();
                $invitedBy = $currentUser['id'] ?? 1; // Fallback for legacy admin
                
                $result = $invitationManager->sendInvitation($email, $roleId, $invitedBy);
                
                if ($result['success']) {
                    $success = "Invitation sent successfully to $email";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'migrate_admins':
                $result = $migrationManager->migrateAdminAccounts();
                
                if ($result['success']) {
                    $success = "Migration completed: {$result['migrated']} migrated, {$result['skipped']} skipped";
                    if (!empty($result['errors'])) {
                        $error = "Some errors occurred: " . implode(', ', $result['errors']);
                    }
                } else {
                    $error = "Migration failed: " . $result['error'];
                }
                break;
                
            case 'update_status':
                $userId = intval($_POST['user_id'] ?? 0);
                $newStatus = $_POST['status'] ?? '';
                
                if ($userId && in_array($newStatus, ['active', 'disabled'])) {
                    $userAccountManager->updateUser($userId, ['status' => $newStatus]);
                    $success = "User status updated successfully";
                } else {
                    $error = "Invalid user or status";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data for display
$db = Database::getInstance();

// Get roles for invitation form
$roles = $db->fetchAll("SELECT * FROM roles ORDER BY name");

// Get users with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filters = [
    'status' => $_GET['status'] ?? '',
    'role_id' => $_GET['role_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$users = $userAccountManager->getAllUsers($limit, $offset, $filters);
$totalUsers = $userAccountManager->getUserCount($filters);
$totalPages = ceil($totalUsers / $limit);

// Get pending invitations
$pendingInvitations = $invitationManager->getInvitations(10, 0, ['status' => 'pending']);

// Get migration status
$migrationStatus = $migrationManager->getMigrationStatus();

include '../../../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include '../../../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>User Management</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inviteModal">
                        Send Invitation
                    </button>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <!-- Migration Status Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Admin Account Migration Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-primary"><?= $migrationStatus['total_admins'] ?></h4>
                                <small>Total Admins</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-success"><?= $migrationStatus['migrated'] ?></h4>
                                <small>Migrated</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-warning"><?= $migrationStatus['pending'] ?></h4>
                                <small>Pending</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-danger"><?= $migrationStatus['failed'] ?></h4>
                                <small>Failed</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($migrationStatus['pending'] > 0 || $migrationStatus['failed'] > 0): ?>
                        <div class="mt-3">
                            <form method="POST" action="?action=migrate_admins" class="d-inline">
                                <?= $authService->csrfTokenField() ?>
                                <button type="submit" class="btn btn-warning" 
                                        onclick="return confirm('This will migrate existing admin accounts to the new system. Continue?')">
                                    Migrate Admin Accounts
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="disabled" <?= $filters['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                <option value="unverified" <?= $filters['status'] === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role_id" class="form-select">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $filters['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   value="<?= htmlspecialchars($filters['search']) ?>" 
                                   placeholder="Username, email, or name">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="?" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Users (<?= $totalUsers ?> total)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($user['role_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'disabled' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="editUser(<?= $user['id'] ?>)">
                                                    Edit
                                                </button>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button class="btn btn-outline-warning" 
                                                            onclick="updateStatus(<?= $user['id'] ?>, 'disabled')">
                                                        Disable
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="updateStatus(<?= $user['id'] ?>, 'active')">
                                                        Enable
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-3">
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Invitations -->
            <?php if (!empty($pendingInvitations)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Invitations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Invited By</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingInvitations as $invitation): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($invitation['email']) ?></td>
                                            <td><?= htmlspecialchars($invitation['role_name']) ?></td>
                                            <td><?= htmlspecialchars($invitation['inviter_first_name'] . ' ' . $invitation['inviter_last_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($invitation['expires_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="resendInvitation(<?= $invitation['id'] ?>)">
                                                    Resend
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
    </div>
</div>

<!-- Send Invitation Modal -->
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=send_invitation">
                <?= $authService->csrfTokenField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Send User Invitation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Form (Hidden) -->
<form id="statusForm" method="POST" action="?action=update_status" style="display: none;">
    <?= $authService->csrfTokenField() ?>
    <input type="hidden" id="statusUserId" name="user_id">
    <input type="hidden" id="statusValue" name="status">
</form>

<script>
function updateStatus(userId, status) {
    if (confirm('Are you sure you want to ' + status + ' this user?')) {
        document.getElementById('statusUserId').value = userId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function editUser(userId) {
    // TODO: Implement user editing modal
    alert('User editing will be implemented in the next phase');
}

function resendInvitation(invitationId) {
    // TODO: Implement resend invitation
    alert('Resend invitation will be implemented in the next phase');
}
</script>

<?php include '../../../includes/admin_footer.php'; ?>
