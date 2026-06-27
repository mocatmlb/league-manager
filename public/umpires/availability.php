<?php
/**
 * District 8 Travel League - Umpire Portal: My Availability
 *
 * Story 25.1: Umpire Availability Entry
 */
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireAvailabilityService.php');

PermissionGuard::requireRole('umpire', '/login.php');

$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

$service = new UmpireAvailabilityService();
$flashSuccess = (string) ($_SESSION['flash_success'] ?? '');
$flashError = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $startsAt = trim($_POST['starts_at'] ?? '');
            $endsAt = trim($_POST['ends_at'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            // Normalization from datetime-local YYYY-MM-DDTHH:MM to MySQL YYYY-MM-DD HH:MM:SS
            $startsAt = str_replace('T', ' ', $startsAt);
            $endsAt = str_replace('T', ' ', $endsAt);
            if (strlen($startsAt) === 16) $startsAt .= ':00';
            if (strlen($endsAt) === 16) $endsAt .= ':00';

            $service->createWindow($userId, $startsAt, $endsAt, $notes);
            $_SESSION['flash_success'] = 'Availability window added.';
        } elseif ($action === 'delete') {
            $availabilityId = (int) ($_POST['availability_id'] ?? 0);
            $service->deleteWindow($availabilityId, $userId);
            $_SESSION['flash_success'] = 'Availability window removed.';
        }

        header('Location: /umpires/availability.php');
        exit;
    } catch (Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$windows = $service->listForUmpire($userId);
$csrfToken = Auth::generateCSRFToken();

function availabilityFormatDateTime(?string $datetime): string {
    $ts = $datetime !== null && trim($datetime) !== '' ? strtotime($datetime) : false;
    return $ts !== false ? date('m/d/Y g:i A', $ts) : 'TBD';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Availability — District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .availability-card {
            transition: transform 0.2s;
        }
        .availability-card:hover {
            border-color: #0d6efd;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>My Availability</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWindowModal">
                        <i class="fas fa-plus me-1"></i>Add Window
                    </button>
                </div>

                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-calendar-check me-2"></i>Current Availability Windows</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($windows)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-calendar-times fa-3x mb-3 opacity-25"></i>
                                <p>You haven't entered any availability windows yet.</p>
                                <p class="small mb-0">Adding windows helps assignors prioritize you for games that fit your schedule.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Starts At</th>
                                            <th>Ends At</th>
                                            <th>Notes</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($windows as $w): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(availabilityFormatDateTime($w['starts_at']), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(availabilityFormatDateTime($w['ends_at']), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-muted small"><?= htmlspecialchars($w['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end">
                                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove this availability window?');" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="availability_id" value="<?= (int)$w['availability_id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Window">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="alert alert-info shadow-sm">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>How it works:</strong> Assignors will see you as available for any games that fall entirely within one of your windows. 
                    If you have overlapping games already assigned, you will be automatically excluded from the availability pool for that specific time.
                </div>
            </div>
        </div>
    </div>

    <!-- Add Window Modal -->
    <div class="modal fade" id="addWindowModal" tabindex="-1" aria-labelledby="addWindowModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="needs-validation">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWindowModalLabel">Add Availability Window</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="starts_at" class="form-label">Starts At</label>
                            <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" required>
                        </div>
                        <div class="mb-3">
                            <label for="ends_at" class="form-label">Ends At</label>
                            <input type="datetime-local" class="form-control" id="ends_at" name="ends_at" required>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g. Afternoon only, or specific field preference"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Window</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple client-side validation for start < end
        document.querySelector('form.needs-validation').addEventListener('submit', function (event) {
            const start = new Date(document.getElementById('starts_at').value);
            const end = new Date(document.getElementById('ends_at').value);
            
            if (start >= end) {
                event.preventDefault();
                alert('End time must be after start time.');
            }
        });
    </script>
</body>
</html>
