<?php
/**
 * District 8 Travel League — Admin User List
 *
 * Story 8.2: paginated, filterable list of all user accounts.
 */

$__dir   = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    if (file_exists($__dir . '/includes/env-loader.php')) {
        require_once $__dir . '/includes/env-loader.php';
        $__found = true;
        break;
    }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
        $__found = true;
    }
}
if (!$__found) {
    error_log('D8TL ERROR: Unable to locate includes/env-loader.php from ' . __FILE__);
    http_response_code(500);
    exit('Configuration error: env-loader not found');
}
unset($__dir, $__found, $__i);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
Auth::requireAdmin();

$adminUserId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminUserId < 1) {
    $_SESSION['flash_error'] = 'Your admin session is invalid. Please sign in again.';
    header('Location: ../login.php');
    exit;
}

// ----------------------------------------------------------------------------
// Filter + pagination params from GET
// ----------------------------------------------------------------------------
$search  = trim($_GET['search']   ?? '');
$role    = trim($_GET['role']     ?? '');
$status  = trim($_GET['status']   ?? '');
$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = 25;

$filters = [];
if ($search !== '')  { $filters['search'] = $search; }
if ($role   !== '')  { $filters['role']   = $role;   }
if ($status !== '')  { $filters['status'] = $status; }

if (!class_exists('UserManagementService')) {
    require_once EnvLoader::getPath('includes/UserManagementService.php');
}
$service = new UserManagementService();
$result  = $service->getList($filters, $page, $perPage);

$users      = $result['users'];
$totalCount = (int) $result['total_count'];
$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;

// Flash messages
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// Helper: build pagination query string preserving current filters
function buildPaginationUrl(int $targetPage, string $search, string $role, string $status): string {
    $params = ['page' => $targetPage];
    if ($search !== '') { $params['search'] = $search; }
    if ($role   !== '') { $params['role']   = $role;   }
    if ($status !== '') { $params['status'] = $status; }
    return 'index.php?' . http_build_query($params);
}

// Helper: role badge HTML
function roleBadgeHtml(string $role): string {
    switch ($role) {
        case 'administrator': return '<span class="badge bg-danger">Administrator</span>';
        case 'team_owner':    return '<span class="badge status-team-owner">Team Owner</span>';
        default:              return '<span class="badge bg-secondary">User</span>';
    }
}

// Helper: status badge HTML
function statusBadgeHtml(string $status): string {
    switch ($status) {
        case 'active':     return '<span class="badge bg-success">Active</span>';
        case 'disabled':   return '<span class="badge bg-danger">Disabled</span>';
        case 'unverified': return '<span class="badge status-unverified">Unverified</span>';
        default:           return '<span class="badge bg-secondary">' . sanitize($status) . '</span>';
    }
}

$pageTitle = 'User Management — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-team-owner  { background-color: #198754; color: #fff; }
        .status-unverified  { background-color: #ffc107; color: #000; }
    </style>
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $__nav;
    unset($__nav);
    ?>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">User Management</h1>
                <p class="text-muted mb-0"><?php echo $totalCount; ?> user<?php echo $totalCount !== 1 ? 's' : ''; ?> found</p>
            </div>
        </div>

        <?php if ($flashMessage): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo sanitize($flashMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($flashError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" action="index.php" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label form-label-sm mb-1">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Name, username, or email"
                               value="<?php echo sanitize($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1">Role</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="">All Roles</option>
                            <option value="user"          <?php echo $role === 'user'          ? 'selected' : ''; ?>>User</option>
                            <option value="team_owner"    <?php echo $role === 'team_owner'    ? 'selected' : ''; ?>>Team Owner</option>
                            <option value="administrator" <?php echo $role === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="active"     <?php echo $status === 'active'     ? 'selected' : ''; ?>>Active</option>
                            <option value="disabled"   <?php echo $status === 'disabled'   ? 'selected' : ''; ?>>Disabled</option>
                            <option value="unverified" <?php echo $status === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <?php if ($search !== '' || $role !== '' || $status !== ''): ?>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Table -->
        <?php if (empty($users)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No users match your search. Try adjusting the filter.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u):
                                $displayName = !empty($u['preferred_name'])
                                    ? sanitize($u['preferred_name'] . ' ' . $u['last_name'])
                                    : sanitize($u['first_name']     . ' ' . $u['last_name']);
                                $roleName   = $u['role_name']  ?? 'user';
                                $userStatus = $u['status']     ?? 'unverified';
                                $registered = !empty($u['created_at'])
                                    ? date('M j, Y', strtotime($u['created_at']))
                                    : '—';
                            ?>
                                <tr>
                                    <td><?php echo $displayName; ?></td>
                                    <td><?php echo sanitize($u['username']); ?></td>
                                    <td><?php echo sanitize($u['email']); ?></td>
                                    <td><?php echo roleBadgeHtml($roleName); ?></td>
                                    <td><?php echo statusBadgeHtml($userStatus); ?></td>
                                    <td><?php echo $registered; ?></td>
                                    <td>
                                        <a href="detail.php?id=<?php echo (int) $u['id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="User list pagination">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            (<?php echo $totalCount; ?> total)
                        </small>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $search, $role, $status); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            for ($p = $start; $p <= $end; $p++):
                            ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildPaginationUrl($p, $search, $role, $status); ?>">
                                        <?php echo $p; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $search, $role, $status); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
