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
                    header('Location: settings.php'); exit;
                } catch (\InvalidArgumentException $e) {
                    $pageError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[settings.php] saveQueueWindowDays error: ' . $e->getMessage());
                }
            }
        }
    }

    if ($action === 'save_decline_lockout') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $pageError = 'Invalid security token. Please try again.';
        } else {
            $raw = $_POST['decline_lockout_hours'] ?? '';
            if (!ctype_digit((string) $raw) || $raw === '') {
                $pageError = 'Decline lockout hours must be a non-negative integer.';
            } else {
                $hours = (int) $raw;
                try {
                    $svc->saveDeclineLockoutHours($hours, $actorUserId);
                    $_SESSION['flash_message'] = 'Decline lockout hours updated.';
                    header('Location: settings.php'); exit;
                } catch (\InvalidArgumentException $e) {
                    $pageError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                } catch (\Throwable $e) {
                    $pageError = 'An unexpected error occurred. Please try again.';
                    error_log('[settings.php] saveDeclineLockoutHours error: ' . $e->getMessage());
                }
            }
        }
    }

    if ($action === 'save_slot_labels') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $pageError = 'Invalid security token. Please try again.';
        } else {
            $slot1Label = trim((string) ($_POST['umpire_slot_1_label'] ?? ''));
            $slot2Label = trim((string) ($_POST['umpire_slot_2_label'] ?? ''));
            try {
                $svc->saveSlotLabels($slot1Label, $slot2Label, $actorUserId);
                $_SESSION['flash_message'] = 'Slot labels updated.';
                header('Location: settings.php'); exit;
            } catch (\InvalidArgumentException $e) {
                $pageError = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            } catch (\Throwable $e) {
                $pageError = 'An unexpected error occurred. Please try again.';
                error_log('[settings.php] saveSlotLabels error: ' . $e->getMessage());
            }
        }
    }
}

$windowDays = $svc->getQueueWindowDays();
$slotLabels = $svc->getSlotLabels();
$declineLockoutHours = $svc->getDeclineLockoutHours();
$csrfToken = Auth::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Settings - D8TL</title>
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
            <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($pageError): ?>
        <div class="alert alert-danger" role="alert">
            <?= $pageError ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-cog me-2"></i>Umpire Settings</h2>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list-check me-1"></i> Assignment Queue
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Assignment Settings</h5>

            <form method="POST" action="settings.php" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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

            <hr class="my-3">

            <form method="POST" action="settings.php" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_decline_lockout">
                <div class="col-auto">
                    <label class="form-label mb-1 fw-semibold">Decline Lockout</label>
                    <div class="input-group">
                        <span class="input-group-text">Block decline within</span>
                        <div style="width:80px">
                            <input type="number" name="decline_lockout_hours" class="form-control"
                                min="0" step="1"
                                value="<?= (int) $declineLockoutHours ?>">
                        </div>
                        <span class="input-group-text">hours of game start</span>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                    <div class="form-text">Umpires cannot decline a published assignment within this window. They'll see a lockout message with assignor contact.</div>
                </div>
            </form>

            <hr class="my-3">

            <form method="POST" action="settings.php" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_slot_labels">
                <div class="col-auto">
                    <label class="form-label mb-1 fw-semibold">Slot Labels</label>
                    <div class="input-group">
                        <span class="input-group-text">Slot 1</span>
                        <input type="text" name="umpire_slot_1_label" class="form-control"
                            style="width:140px" maxlength="64" required
                            value="<?= htmlspecialchars($slotLabels[0] ?? 'Umpire 1', ENT_QUOTES, 'UTF-8') ?>">
                        <span class="input-group-text">Slot 2</span>
                        <input type="text" name="umpire_slot_2_label" class="form-control"
                            style="width:140px" maxlength="64" required
                            value="<?= htmlspecialchars($slotLabels[1] ?? 'Umpire 2', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                    <div class="form-text">Labels appear on the assignment board, drawer, umpire portal, and assignment emails.</div>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
