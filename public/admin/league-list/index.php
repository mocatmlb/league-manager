<?php
/**
 * District 8 Travel League - Admin League List Management
 *
 * Admins can create, edit, reorder, and deactivate league entries
 * used in the coach registration dropdown.
 *
 * Story 2.2 — Admin League List Management Page
 */

// Robust EnvLoader include: locate includes/env-loader.php regardless of layout
$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) {
        require_once $__candidate;
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
unset($__dir, $__found, $__i, $__candidate);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

require_once EnvLoader::getPath('includes/LeagueListManager.php');
require_once EnvLoader::getPath('includes/ActivityLogger.php');

$currentUser = Auth::getCurrentUser();

// ---------------------------------------------------------------------------
// POST handler — PRG pattern
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
        header('Location: ' . EnvLoader::getBaseUrl() . '/admin/league-list/');
        exit;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {

        case 'add':
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            if ($displayName === '') {
                $_SESSION['flash_error'] = 'League name is required.';
            } else {
                try {
                    LeagueListManager::create($displayName);
                    ActivityLogger::log('admin.league_list_created', [
                        'display_name' => $displayName,
                        'admin_user_id' => $currentUser['id'] ?? null,
                    ]);
                    $_SESSION['flash_success'] = 'League "' . htmlspecialchars($displayName) . '" added successfully.';
                } catch (Exception $e) {
                    error_log('[LeagueList] Create failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Failed to add league. Please try again.';
                }
            }
            break;

        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            if ($id <= 0 || $displayName === '') {
                $_SESSION['flash_error'] = 'Invalid edit request.';
            } else {
                try {
                    $updated = LeagueListManager::update($id, $displayName);
                    if ($updated) {
                        ActivityLogger::log('admin.league_list_edited', [
                            'id'           => $id,
                            'display_name' => $displayName,
                            'admin_user_id' => $currentUser['id'] ?? null,
                        ]);
                        $_SESSION['flash_success'] = 'League updated to "' . htmlspecialchars($displayName) . '".';
                    } else {
                        $_SESSION['flash_error'] = 'League entry not found.';
                    }
                } catch (Exception $e) {
                    error_log('[LeagueList] Update failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Failed to update league. Please try again.';
                }
            }
            break;

        case 'deactivate':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $_SESSION['flash_error'] = 'Invalid request.';
            } else {
                try {
                    $deactivated = LeagueListManager::deactivate($id);
                    if ($deactivated) {
                        ActivityLogger::log('admin.league_list_deactivated', [
                            'id'            => $id,
                            'admin_user_id' => $currentUser['id'] ?? null,
                        ]);
                        $_SESSION['flash_success'] = 'League entry deactivated.';
                    } else {
                        $_SESSION['flash_error'] = 'League entry not found.';
                    }
                } catch (Exception $e) {
                    error_log('[LeagueList] Deactivate failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Failed to deactivate league. Please try again.';
                }
            }
            break;

        case 'reactivate':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $_SESSION['flash_error'] = 'Invalid request.';
            } else {
                try {
                    $reactivated = LeagueListManager::reactivate($id);
                    if ($reactivated) {
                        ActivityLogger::log('admin.league_list_reactivated', [
                            'id'            => $id,
                            'admin_user_id' => $currentUser['id'] ?? null,
                        ]);
                        $_SESSION['flash_success'] = 'League entry reactivated.';
                    } else {
                        $_SESSION['flash_error'] = 'League entry not found.';
                    }
                } catch (Exception $e) {
                    error_log('[LeagueList] Reactivate failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Failed to reactivate league. Please try again.';
                }
            }
            break;

        case 'reorder':
            $rawIds = $_POST['ordered_ids'] ?? '';
            $orderedIds = array_filter(
                array_map('intval', explode(',', $rawIds)),
                fn($id) => $id > 0
            );
            if (empty($orderedIds)) {
                $_SESSION['flash_error'] = 'No order data received.';
            } else {
                try {
                    $orderedIds = array_values($orderedIds);
                    $activeIds = array_map(
                        fn($entry) => (int)$entry['id'],
                        LeagueListManager::getActiveList()
                    );

                    $submittedIds = $orderedIds;
                    sort($submittedIds);
                    $expectedIds = $activeIds;
                    sort($expectedIds);

                    if (count(array_unique($orderedIds)) !== count($orderedIds)) {
                        throw new InvalidArgumentException('Duplicate IDs in reorder payload.');
                    }
                    if ($submittedIds !== $expectedIds) {
                        throw new InvalidArgumentException('Reorder payload does not match active league entries.');
                    }

                    LeagueListManager::reorder($orderedIds);
                    ActivityLogger::log('admin.league_list_reordered', [
                        'ordered_ids'   => $orderedIds,
                        'admin_user_id' => $currentUser['id'] ?? null,
                    ]);
                    $_SESSION['flash_success'] = 'League order saved.';
                } catch (Exception $e) {
                    error_log('[LeagueList] Reorder failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Failed to save order. Please refresh and try again.';
                }
            }
            break;

        default:
            $_SESSION['flash_error'] = 'Unknown action.';
            break;
    }

    header('Location: ' . EnvLoader::getBaseUrl() . '/admin/league-list/');
    exit;
}

// ---------------------------------------------------------------------------
// GET — load data for display
// ---------------------------------------------------------------------------

$allEntries = LeagueListManager::getAll();
$activeEntries = array_values(array_filter($allEntries, fn($e) => (int)$e['is_active'] === 1));
$inactiveEntries = array_values(array_filter($allEntries, fn($e) => (int)$e['is_active'] === 0));

// Read-and-clear flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrfToken = Auth::generateCSRFToken();
$pageTitle = 'League List Management';
$baseUrl   = EnvLoader::getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $baseUrl; ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .drag-handle { cursor: grab; color: #adb5bd; }
        .drag-handle:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; background: #e9ecef; }
        .league-row td { vertical-align: middle; }
        .inactive-row td { opacity: 0.7; }
        .inactive-name { text-decoration: line-through; color: #6c757d; }
    </style>
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    if (file_exists($__nav)) include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">League List Management</h1>
                        <p class="text-muted mb-0">Manage the league options shown on the coach registration form.</p>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($flashSuccess); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($flashError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Add League Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-plus-circle me-2"></i>Add League</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo $baseUrl; ?>/admin/league-list/" class="row g-2 align-items-end">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <div class="col-sm-8 col-md-6">
                                <label for="new-league-name" class="form-label">League Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       id="new-league-name"
                                       name="display_name"
                                       class="form-control"
                                       placeholder="e.g. Maple Park"
                                       maxlength="100"
                                       required
                                       aria-describedby="league-name-help">
                                <div id="league-name-help" class="form-text">Short name shown as-is in the registration dropdown.</div>
                            </div>
                            <div class="col-sm-4 col-md-auto">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Add League
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Active Leagues -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Active Leagues
                            <span class="badge bg-primary ms-1"><?php echo count($activeEntries); ?></span>
                        </h5>
                        <?php if (count($activeEntries) > 1): ?>
                            <form method="POST" action="<?php echo $baseUrl; ?>/admin/league-list/" id="reorder-form" class="d-inline">
                                <input type="hidden" name="action" value="reorder">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="ordered_ids" id="ordered-ids-input" value="">
                                <button type="submit" id="save-order-btn" class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="fas fa-save me-1"></i>Save Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activeEntries)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                No leagues configured yet. Add the first one above.
                            </div>
                        <?php else: ?>
                            <table class="table table-hover mb-0" id="active-leagues-table">
                                <thead class="table-light">
                                    <tr>
                                        <?php if (count($activeEntries) > 1): ?>
                                            <th style="width: 40px;" class="text-center" title="Drag to reorder"><i class="fas fa-grip-vertical text-muted"></i></th>
                                        <?php endif; ?>
                                        <th>#</th>
                                        <th>League Name</th>
                                        <th style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="sortable-leagues">
                                    <?php foreach ($activeEntries as $i => $entry): ?>
                                        <tr class="league-row" data-id="<?php echo (int)$entry['id']; ?>">
                                            <?php if (count($activeEntries) > 1): ?>
                                                <td class="text-center">
                                                    <span class="drag-handle" title="Drag to reorder" aria-label="Drag to reorder">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-muted small row-number"><?php echo $i + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($entry['display_name']); ?></strong>
                                            </td>
                                            <td>
                                                <!-- Edit button (triggers inline modal) -->
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary me-1"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#edit-modal"
                                                        data-id="<?php echo (int)$entry['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($entry['display_name'], ENT_QUOTES); ?>"
                                                        aria-label="Edit <?php echo htmlspecialchars($entry['display_name']); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <!-- Deactivate button (triggers confirm modal) -->
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-warning"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deactivate-modal"
                                                        data-id="<?php echo (int)$entry['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($entry['display_name'], ENT_QUOTES); ?>"
                                                        aria-label="Deactivate <?php echo htmlspecialchars($entry['display_name']); ?>">
                                                    <i class="fas fa-ban"></i> Deactivate
                                                </button>
                                                <?php if (count($activeEntries) > 1): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary ms-1"
                                                            data-move="up"
                                                            title="Move up"
                                                            aria-label="Move <?php echo htmlspecialchars($entry['display_name']); ?> up">
                                                        <i class="fas fa-arrow-up"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary ms-1"
                                                            data-move="down"
                                                            title="Move down"
                                                            aria-label="Move <?php echo htmlspecialchars($entry['display_name']); ?> down">
                                                        <i class="fas fa-arrow-down"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Deactivated Leagues -->
                <?php if (!empty($inactiveEntries)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0 text-muted">
                            <i class="fas fa-archive me-2"></i>Deactivated Leagues
                            <span class="badge bg-secondary ms-1"><?php echo count($inactiveEntries); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>League Name</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inactiveEntries as $entry): ?>
                                    <tr class="inactive-row">
                                        <td>
                                            <span class="inactive-name"><?php echo htmlspecialchars($entry['display_name']); ?></span>
                                            <span class="badge bg-secondary ms-2">Deactivated</span>
                                        </td>
                                        <td>
                                            <!-- Reactivate form (inline POST) -->
                                            <form method="POST" action="<?php echo $baseUrl; ?>/admin/league-list/" class="d-inline">
                                                <input type="hidden" name="action" value="reactivate">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$entry['id']; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-success"
                                                        aria-label="Reactivate <?php echo htmlspecialchars($entry['display_name']); ?>">
                                                    <i class="fas fa-redo"></i> Reactivate
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ========================
         Edit Modal
    ======================== -->
    <div class="modal fade" id="edit-modal" tabindex="-1" aria-labelledby="edit-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo $baseUrl; ?>/admin/league-list/" id="edit-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="edit-modal-label">Edit League</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-display-name" class="form-label">League Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   id="edit-display-name"
                                   name="display_name"
                                   class="form-control form-control-lg"
                                   maxlength="100"
                                   required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================
         Deactivate Confirm Modal
    ======================== -->
    <div class="modal fade" id="deactivate-modal" tabindex="-1" aria-labelledby="deactivate-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo $baseUrl; ?>/admin/league-list/" id="deactivate-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivate-modal-label">Deactivate League</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" id="deactivate-id">
                        <p>Deactivate <strong id="deactivate-name"></strong>?</p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            This entry will no longer appear in the registration dropdown but will remain in the database for historical reference.
                            You can reactivate it at any time.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-ban me-1"></i>Deactivate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SortableJS for drag-and-drop reordering (no jQuery UI dependency) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/admin-league-list.js"></script>

    <script>
        // Populate and show edit modal
        document.getElementById('edit-modal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit-id').value = button.getAttribute('data-id');
            document.getElementById('edit-display-name').value = button.getAttribute('data-name');
        });

        // Populate and show deactivate modal
        document.getElementById('deactivate-modal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('deactivate-id').value = button.getAttribute('data-id');
            document.getElementById('deactivate-name').textContent = button.getAttribute('data-name');
        });
    </script>
</body>
</html>
