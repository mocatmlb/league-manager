<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');

PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php');

require_once EnvLoader::getPath('includes/UmpireAssignmentService.php');

$currentUser = Auth::getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);
if ($actorUserId < 1) { header('Location: /login.php'); exit; }

$svc = new UmpireAssignmentService();

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$pageError = '';

// ─── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $pageError = 'Invalid security token. Please try again.';
        } else {
            $raw = $_POST['unassigned_queue_days'] ?? '';
            if (!ctype_digit((string) $raw) || $raw === '') {
                $pageError = 'Queue window must be a non-negative integer.';
            } else {
                $days = (int) $raw;
                try {
                    $svc->saveQueueWindowDays($days, $actorUserId);
                    $_SESSION['flash_message'] = 'Queue window updated.';
                    header('Location: index.php'); exit;
                } catch (\InvalidArgumentException $e) {
                    $pageError = htmlspecialchars($e->getMessage());
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[index.php] saveQueueWindowDays error: ' . $e->getMessage());
                }
            }
        }
    }
}

// ─── GET: load queue ──────────────────────────────────────────────────────────
$windowDays = $svc->getQueueWindowDays();
$games      = $svc->getUnassignedQueue($windowDays);
$csrfToken  = Auth::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unassigned Games Queue — D8TL</title>
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-list-check me-2"></i>Unassigned Games Queue</h2>
        <a href="board.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-table-columns me-1"></i> Assignment Board
        </a>
    </div>

    <!-- Queue Window Settings -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="index.php" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_settings">
                <div class="col-auto">
                    <label class="form-label mb-1 fw-semibold">Queue Window</label>
                    <div class="input-group">
                        <span class="input-group-text">Show games up to</span>
                        <input type="number" name="unassigned_queue_days" class="form-control"
                            style="width:80px" min="0" step="1"
                            value="<?= (int) $windowDays ?>">
                        <span class="input-group-text">days ahead</span>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                    <div class="form-text">Set to 0 to show all games with open slots regardless of date.</div>
                </div>
            </form>
        </div>
    </div>

    <!-- Games Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($games)): ?>
                <p class="p-4 text-muted mb-0">
                    <?php if ($windowDays === 0): ?>
                        No games with open slots found.
                    <?php else: ?>
                        No games with open slots in the next <?= (int) $windowDays ?> days.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Home Team</th>
                                <th>Away Team</th>
                                <th>Location</th>
                                <th>Division</th>
                                <th>Slots Filled</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr data-game-id="<?= (int) $game['game_id'] ?>">
                                    <td><?= htmlspecialchars($game['game_date'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['game_time'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['home_team'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['away_team'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['location_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($game['division_name'] ?? '—') ?></td>
                                    <td data-slot-count><?= (int) $game['filled_slots'] ?>/2</td>
                                    <td>
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-assignment-drawer-trigger
                                                data-game-id="<?= (int) $game['game_id'] ?>">
                                            Assign
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="assignmentDrawer"
     aria-labelledby="assignmentDrawerTitle"
     data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-page-mode="queue">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="assignmentDrawerTitle">Assignment Drawer</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="assignmentDrawerBody" aria-live="polite"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assignment-drawer.js"></script>
</body>
</html>
