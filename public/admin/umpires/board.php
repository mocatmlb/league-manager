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

$svc   = new UmpireAssignmentService();
$games = $svc->getAssignmentBoard();
$csrfToken = Auth::generateCSRFToken();

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Board — D8TL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .board-row { cursor: pointer; }
        .board-row:hover { background-color: rgba(0,0,0,.04); }
    </style>
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-table-columns me-2"></i>Assignment Board</h2>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Queue
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($games)): ?>
                <p class="p-4 text-muted mb-0">No scheduled games found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Home</th>
                                <th>Away</th>
                                <th>Location</th>
                                <th>Division</th>
                                <th>Slots Filled</th>
                                <th>Umpire 1</th>
                                <th>Umpire 2</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <?php
                                $slot1 = $game['slots'][0] ?? null;
                                $slot2 = $game['slots'][1] ?? null;
                                ?>
                                <tr class="board-row"
                                    id="game-<?= (int) $game['game_id'] ?>"
                                    tabindex="0"
                                    role="button"
                                    data-assignment-drawer-trigger
                                    data-game-id="<?= (int) $game['game_id'] ?>">
                                    <td><?= htmlspecialchars($game['game_date'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['game_time'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['home_team'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['away_team'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($game['location_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($game['division_name'] ?? '—') ?></td>
                                    <td data-board-filled><?= (int) ($game['filled_slots'] ?? 0) ?>/2</td>
                                    <td data-board-slot="0">
                                        <?php if ($slot1): ?>
                                            <?= htmlspecialchars($slot1['name']) ?>
                                            <span class="badge bg-<?= $slot1['status'] === 'Published' ? 'success' : 'warning text-dark' ?> ms-1">
                                                <?= htmlspecialchars($slot1['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-board-slot="1">
                                        <?php if ($slot2): ?>
                                            <?= htmlspecialchars($slot2['name']) ?>
                                            <span class="badge bg-<?= $slot2['status'] === 'Published' ? 'success' : 'warning text-dark' ?> ms-1">
                                                <?= htmlspecialchars($slot2['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-board-status>
                                        <span class="badge bg-<?= htmlspecialchars($game['status_class']) ?>">
                                            <?= htmlspecialchars($game['board_status']) ?>
                                        </span>
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
     data-page-mode="board">
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
