<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');

PermissionGuard::requireRole('umpire_assignor', '/login.php');

require_once EnvLoader::getPath('includes/UmpireRosterService.php');

$currentUser = Auth::getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);
if ($actorUserId < 1) {
    header('Location: /login.php'); exit;
}

$svc = new UmpireRosterService();

// Consume flash messages
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$pageError = '';

// ─── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pageError = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            try {
                $result = $svc->createUmpire([
                    'first_name'    => $_POST['first_name']    ?? '',
                    'last_name'     => $_POST['last_name']     ?? '',
                    'email'         => $_POST['email']         ?? '',
                    'phone'         => $_POST['phone']         ?? '',
                    'umpire_level'  => $_POST['umpire_level']  ?? 'Blue Shirt',
                    'is_under_18'   => !empty($_POST['is_under_18']),
                    'date_of_birth' => $_POST['date_of_birth'] ?? '',
                ], $actorUserId);
                $_SESSION['flash_message'] = "Umpire account created. Temporary password: {$result['temp_password']}";
                header('Location: roster.php'); exit;
            } catch (DuplicateEmailException $e) {
                $pageError = 'That email address is already in use.';
            } catch (\InvalidArgumentException $e) {
                $pageError = htmlspecialchars($e->getMessage());
            } catch (\Throwable $e) {
                $pageError = 'An unexpected error occurred. Please try again.';
                error_log('[roster.php] createUmpire error: ' . $e->getMessage());
            }

        } elseif ($action === 'edit') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($targetId < 1) {
                $pageError = 'Invalid umpire ID.';
            } else {
                try {
                    $svc->updateProfile($targetId, [
                        'umpire_level'  => $_POST['umpire_level']  ?? 'Blue Shirt',
                        'is_under_18'   => !empty($_POST['is_under_18']),
                        'date_of_birth' => $_POST['date_of_birth'] ?? '',
                    ], $actorUserId);
                    $_SESSION['flash_message'] = 'Umpire profile updated.';
                    header('Location: roster.php'); exit;
                } catch (\InvalidArgumentException $e) {
                    $pageError = htmlspecialchars($e->getMessage());
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[roster.php] updateProfile error: ' . $e->getMessage());
                }
            }

        } elseif ($action === 'deactivate') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($targetId < 1) {
                $pageError = 'Invalid umpire ID.';
            } else {
                try {
                    $svc->deactivate($targetId, $actorUserId);
                    $_SESSION['flash_message'] = 'Umpire deactivated.';
                    header('Location: roster.php'); exit;
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[roster.php] deactivate error: ' . $e->getMessage());
                }
            }

        } elseif ($action === 'activate') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($targetId < 1) {
                $pageError = 'Invalid umpire ID.';
            } else {
                try {
                    $svc->activate($targetId, $actorUserId);
                    $_SESSION['flash_message'] = 'Umpire reactivated.';
                    $showInactive = !empty($_POST['show_inactive_after']) ? '?show_inactive=1' : '';
                    header('Location: roster.php' . $showInactive); exit;
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[roster.php] activate error: ' . $e->getMessage());
                }
            }

        } elseif ($action === 'toggle_migration_mode') {
            if ($svc->isMigrationMode()) {
                $svc->disableMigrationMode();
                $_SESSION['flash_message'] = 'Migration mode disabled. Emails will be sent normally.';
            } else {
                $svc->enableMigrationMode();
                $_SESSION['flash_message'] = 'Migration mode enabled. No emails will be sent for this session.';
            }
            header('Location: roster.php'); exit;
        }
    }
}

// ─── GET: build roster ────────────────────────────────────────────────────────
$showInactive = !empty($_GET['show_inactive']);
$roster = $svc->getRoster(!$showInactive);

$csrfToken = Auth::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Roster — D8TL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$__nav = file_exists(__DIR__ . '/../../includes/nav.php')
    ? __DIR__ . '/../../includes/nav.php'
    : __DIR__ . '/../../../includes/nav.php';
include $__nav;
unset($__nav);
?>

<div class="container mt-4">

    <?php if ($flashMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($pageError): ?>
        <div class="alert alert-danger" role="alert">
            <?= $pageError ?>
        </div>
    <?php endif; ?>

    <?php if ($svc->isMigrationMode()): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3" role="alert">
        <div>
            <i class="fas fa-tools me-2"></i>
            <strong>Migration Mode Active</strong> — No welcome emails will be sent when creating umpire accounts this session.
        </div>
        <form method="post" class="mb-0">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="toggle_migration_mode">
            <button type="submit" class="btn btn-sm btn-outline-dark">Disable Migration Mode</button>
        </form>
    </div>
    <?php else: ?>
    <div class="mb-3 text-end">
        <form method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="toggle_migration_mode">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-tools me-1"></i> Enable Migration Mode
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Umpire Roster</h2>
        <div class="d-flex gap-2 align-items-center">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="showInactiveToggle"
                    <?= $showInactive ? 'checked' : '' ?>>
                <label class="form-check-label" for="showInactiveToggle">Show inactive</label>
            </div>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse"
                data-bs-target="#addUmpireForm" aria-expanded="false">
                + Add Umpire
            </button>
        </div>
    </div>

    <!-- Add Umpire Form -->
    <div class="collapse mb-4" id="addUmpireForm">
        <div class="card">
            <div class="card-header"><strong>Add New Umpire</strong></div>
            <div class="card-body">
                <form method="POST" action="roster.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required
                                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required
                                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" required
                                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Level <span class="text-danger">*</span></label>
                            <select name="umpire_level" class="form-select" required>
                                <option value="Blue Shirt" <?= (($_POST['umpire_level'] ?? '') === 'Blue Shirt') ? 'selected' : '' ?>>Blue Shirt</option>
                                <option value="Black Shirt" <?= (($_POST['umpire_level'] ?? '') === 'Black Shirt') ? 'selected' : '' ?>>Black Shirt</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Under 18?</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_under_18"
                                    id="createUnder18" value="1"
                                    <?= !empty($_POST['is_under_18']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="createUnder18">Under 18</label>
                            </div>
                        </div>
                        <div class="col-md-4" id="createDobField" style="<?= !empty($_POST['is_under_18']) ? '' : 'display:none' ?>">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Create Umpire</button>
                        <button type="button" class="btn btn-outline-secondary ms-2"
                            data-bs-toggle="collapse" data-bs-target="#addUmpireForm">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Roster Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($roster)): ?>
                <p class="p-4 text-muted mb-0">
                    <?= $showInactive ? 'No umpires found.' : 'No active umpires found. Toggle "Show inactive" to see deactivated accounts.' ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Level</th>
                                <th>Flags</th>
                                <th>Status</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roster as $umpire): ?>
                                <?php
                                $levelBadge = $umpire['umpire_level'] === 'Black Shirt'
                                    ? '<span class="badge bg-dark">Black Shirt</span>'
                                    : '<span class="badge bg-primary">Blue Shirt</span>';

                                $under18Badge = $umpire['is_under_18']
                                    ? '<span class="badge bg-warning text-dark ms-1">U-18</span>'
                                    : '';

                                $statusBadge = $umpire['status'] === 'active'
                                    ? '<span class="badge bg-success">Active</span>'
                                    : '<span class="badge bg-secondary">Inactive</span>';

                                $name = htmlspecialchars($umpire['first_name'] . ' ' . $umpire['last_name']);
                                ?>
                                <tr>
                                    <td><?= $name ?></td>
                                    <td><?= $levelBadge ?></td>
                                    <td><?= $under18Badge ?: '<span class="text-muted small">—</span>' ?></td>
                                    <td><?= $statusBadge ?></td>
                                    <td><?= htmlspecialchars($umpire['email']) ?></td>
                                    <td><?= htmlspecialchars($umpire['phone'] ?? '') ?></td>
                                    <td>
                                        <button class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal<?= (int) $umpire['id'] ?>">
                                            Edit
                                        </button>

                                        <?php if ($umpire['status'] === 'active'): ?>
                                            <form method="POST" action="roster.php" class="d-inline"
                                                onsubmit="return confirm('Deactivate <?= addslashes($name) ?>?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="user_id" value="<?= (int) $umpire['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="roster.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?= (int) $umpire['id'] ?>">
                                                <?php if ($showInactive): ?>
                                                    <input type="hidden" name="show_inactive_after" value="1">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-outline-success btn-sm">Reactivate</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= (int) $umpire['id'] ?>"
                                    tabindex="-1" aria-labelledby="editModalLabel<?= (int) $umpire['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?= (int) $umpire['id'] ?>">
                                                    Edit <?= $name ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="roster.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="user_id" value="<?= (int) $umpire['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Level</label>
                                                        <select name="umpire_level" class="form-select">
                                                            <option value="Blue Shirt" <?= $umpire['umpire_level'] === 'Blue Shirt' ? 'selected' : '' ?>>Blue Shirt</option>
                                                            <option value="Black Shirt" <?= $umpire['umpire_level'] === 'Black Shirt' ? 'selected' : '' ?>>Black Shirt</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input edit-under18"
                                                                type="checkbox" name="is_under_18" value="1"
                                                                id="editUnder18_<?= (int) $umpire['id'] ?>"
                                                                data-target="editDob_<?= (int) $umpire['id'] ?>"
                                                                <?= $umpire['is_under_18'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="editUnder18_<?= (int) $umpire['id'] ?>">Under 18</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3" id="editDob_<?= (int) $umpire['id'] ?>"
                                                        style="<?= $umpire['is_under_18'] ? '' : 'display:none' ?>">
                                                        <label class="form-label">Date of Birth</label>
                                                        <input type="date" name="date_of_birth" class="form-control"
                                                            value="<?= htmlspecialchars($umpire['date_of_birth'] ?? '') ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show/hide DOB field on "Create Umpire" form
document.getElementById('createUnder18').addEventListener('change', function () {
    document.getElementById('createDobField').style.display = this.checked ? '' : 'none';
});

// Show/hide DOB field on each "Edit" modal
document.querySelectorAll('.edit-under18').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        var targetId = this.dataset.target;
        document.getElementById(targetId).style.display = this.checked ? '' : 'none';
    });
});

// Show inactive toggle: reload page with/without ?show_inactive=1
document.getElementById('showInactiveToggle').addEventListener('change', function () {
    var url = new URL(window.location.href);
    if (this.checked) {
        url.searchParams.set('show_inactive', '1');
    } else {
        url.searchParams.delete('show_inactive');
    }
    window.location.href = url.toString();
});
</script>
</body>
</html>
