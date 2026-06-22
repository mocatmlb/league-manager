<?php
/**
 * District 8 Travel League - Umpire Portal: Decline Assignment
 */
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireAssignmentService.php');

PermissionGuard::requireRole('umpire', '/login.php');

$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

$service = new UmpireAssignmentService();
$assignmentId = (int) ($_POST['assignment_id'] ?? $_GET['assignment_id'] ?? 0);
$flashError = '';
$assignment = null;

function declineFormatDate(?string $date): string {
    $timestamp = $date !== null && trim($date) !== '' ? strtotime($date) : false;
    return $timestamp !== false ? date('m/d/Y', $timestamp) : 'TBD';
}

function declineFormatTime(?string $time): string {
    $timestamp = $time !== null && trim($time) !== '' ? strtotime($time) : false;
    return $timestamp !== false ? date('g:i A', $timestamp) : 'TBD';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }

    try {
        $service->declineAssignment($assignmentId, $userId);
        $_SESSION['flash_success'] = 'Assignment declined.';
        header('Location: /umpires/index.php');
        exit;
    } catch (UmpireAssignmentDeclineLockoutException $e) {
        $payload = $e->getPayload();
        $flashError = 'Decline not available within ' . (int) ($payload['lockout_hours'] ?? 48)
            . ' hours of game start. Contact: ' . (string) ($payload['assignor_contact'] ?? 'your assignor.');
    } catch (Throwable $e) {
        $flashError = $e->getMessage();
    }
}

try {
    if ($assignmentId <= 0) {
        throw new InvalidArgumentException('Invalid assignment.');
    }
    $assignment = $service->getDeclineAssignmentPreview($assignmentId, $userId);
} catch (Throwable $e) {
    $flashError = $flashError ?: $e->getMessage();
}

$csrfToken = Auth::generateCSRFToken();
$assignor_contact = $assignment['assignor_contact'] ?? 'your assignor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decline Assignment - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .decline-action {
            min-height: 44px;
            min-width: 44px;
        }
        .lockout-message {
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="mb-0">Decline Assignment</h1>
                    <a class="btn btn-outline-secondary" href="/umpires/index.php">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>

<?php if ($flashError): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
                </div>
<?php endif; ?>

<?php if ($assignment): ?>
                <div class="bg-white border rounded p-3 mb-3">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars(declineFormatDate($assignment['game_date'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Time</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars(declineFormatTime($assignment['game_time'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Field</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($assignment['location_name'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Division</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($assignment['division_name'] ?? 'TBD', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($assignment['slot_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Assignor</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($assignor_contact, ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                </div>

<?php if (!($assignment['decline_allowed'] ?? false)): ?>
                <div class="alert alert-warning lockout-message" role="status" tabindex="0">
                    Decline not available within <?= htmlspecialchars((string) ($assignment['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') ?>
                    hours of game start. Contact <?= htmlspecialchars($assignor_contact, ENT_QUOTES, 'UTF-8') ?>.
                </div>
                <button type="button" class="btn btn-danger decline-action" disabled aria-disabled="true">
                    Decline
                </button>
<?php else: ?>
                <form method="post" action="/umpires/decline.php">
                    <input type="hidden" name="assignment_id" value="<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-danger decline-action">
                        <i class="fas fa-times-circle me-1"></i>Decline
                    </button>
                </form>
<?php endif; ?>
<?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
