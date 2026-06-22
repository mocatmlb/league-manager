<?php
/**
 * District 8 Travel League - Umpire Portal: My Assignments
 *
 * Story 24.1: Displays published assignments for the authenticated umpire.
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
$assignments = $service->getUmpireAssignments($userId);
$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$currentUser = Auth::getCurrentUser();
$name = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));

function formatAssignmentDate(?string $date): string {
    $timestamp = $date !== null && trim($date) !== '' ? strtotime($date) : false;
    return $timestamp !== false ? date('m/d/Y', $timestamp) : 'TBD';
}

function formatAssignmentTime(?string $time): string {
    $timestamp = $time !== null && trim($time) !== '' ? strtotime($time) : false;
    return $timestamp !== false ? date('g:i A', $timestamp) : 'TBD';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments — District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .decline-action {
            min-height: 44px;
            min-width: 44px;
        }
    </style>
</head>
<body class="bg-light">

    <?php
    $navPath = file_exists(__DIR__ . '/../includes/nav.php')
        ? __DIR__ . '/../includes/nav.php'
        : __DIR__ . '/../../includes/nav.php';
    include $navPath;
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-3">My Assignments</h1>

<?php if ($flashSuccess): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (empty($assignments)): ?>
                <div class="alert alert-info">You have no published assignments.</div>
<?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Division</th>
                                <th>Role</th>
                                <th>Fee</th>
                                <th>Assignor</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($assignments as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars(formatAssignmentDate($a['game_date'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(formatAssignmentTime($a['game_time'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($a['location_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['division_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['slot_label'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['fee_text'] ?? '') ?></td>
                                <td>
                                    <?php $assignorName = htmlspecialchars($a['assignor_name'] ?? 'Contact your assignor'); ?>
                                    <?= $assignorName ?>
                                    <?php if (!empty($a['assignor_email'])): ?>
                                        <br><small><a href="mailto:<?= htmlspecialchars($a['assignor_email']) ?>"><?= htmlspecialchars($a['assignor_email']) ?></a></small>
                                    <?php endif; ?>
                                    <?php if (!empty($a['assignor_phone'])): ?>
                                        <br><small><a href="<?= htmlspecialchars($a['assignor_phone_tel']) ?>"><?= htmlspecialchars($a['assignor_phone']) ?></a></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['decline_allowed'])): ?>
                                        <a class="btn btn-outline-danger btn-sm decline-action d-inline-flex align-items-center"
                                           href="/umpires/decline.php?assignment_id=<?= htmlspecialchars((string) ($a['assignment_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-times-circle me-1"></i>Decline
                                        </a>
                                    <?php else: ?>
                                        <span class="d-block small text-muted mb-1" tabindex="0">
                                            Decline not available within <?= htmlspecialchars((string) ($a['decline_lockout_hours'] ?? 48), ENT_QUOTES, 'UTF-8') ?> hours. Contact your assignor.
                                        </span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm decline-action" disabled aria-disabled="true">Decline</button>
                                    <?php endif; ?>
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

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME ?? 'District 8 Travel League', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
