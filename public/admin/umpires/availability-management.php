<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireRosterService.php');
require_once EnvLoader::getPath('includes/UmpireAvailabilityService.php');

PermissionGuard::requireRole('umpire_assignor', '/login.php');

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeDatetimeLocal(string $value, string $label): string {
    $value = trim($value);
    if ($value === '') {
        throw new InvalidArgumentException($label . ' cannot be blank.');
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed instanceof DateTimeImmutable || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException($label . ' must be a valid date and time.');
    }

    return $parsed->format('Y-m-d H:i:s');
}

function formatForDatetimeLocal(?string $value): string {
    if (!$value) {
        return '';
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d\TH:i');
        }
    }

    return '';
}

function validateTargetUmpire(UmpireRosterService $rosterService, int $targetUmpireId): array {
    if ($targetUmpireId < 1) {
        throw new InvalidArgumentException('Select an active umpire.');
    }

    $targetUmpire = $rosterService->getUmpire($targetUmpireId);
    if ($targetUmpire === null || $targetUmpire['status'] !== 'active') {
        throw new InvalidArgumentException('Select an active umpire.');
    }

    return $targetUmpire;
}

$currentUser = Auth::getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);
if ($actorUserId < 1) {
    header('Location: /login.php');
    exit;
}

$rosterService = new UmpireRosterService();
$availabilityService = new UmpireAvailabilityService();
$activeUmpires = $rosterService->getRoster(true);

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$pageError = '';
$targetUmpireId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['umpire_user_id'] ?? 0)
    : (int) ($_GET['umpire_user_id'] ?? 0);
$targetUmpire = null;
$windows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrfInput = $_POST['csrf_token'] ?? '';
        if (!is_string($csrfInput) || !Auth::verifyCSRFToken($csrfInput)) {
            throw new InvalidArgumentException('Invalid security token. Please try again.');
        }

        $targetUmpire = validateTargetUmpire($rosterService, $targetUmpireId);
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $startsAt = normalizeDatetimeLocal($_POST['starts_at'] ?? '', 'Start time');
            $endsAt = normalizeDatetimeLocal($_POST['ends_at'] ?? '', 'End time');
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if (strlen($notes) > 255) {
                throw new InvalidArgumentException('Note cannot exceed 255 characters.');
            }
            $availabilityService->createWindow($targetUmpireId, $startsAt, $endsAt, $notes !== '' ? $notes : null, $actorUserId, 'admin_manual');
            $_SESSION['flash_message'] = 'Availability window added.';
        } elseif ($action === 'update') {
            $availabilityId = (int) ($_POST['availability_id'] ?? 0);
            if ($availabilityId < 1) {
                throw new InvalidArgumentException('Invalid availability window.');
            }
            $startsAt = normalizeDatetimeLocal($_POST['starts_at'] ?? '', 'Start time');
            $endsAt = normalizeDatetimeLocal($_POST['ends_at'] ?? '', 'End time');
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if (strlen($notes) > 255) {
                throw new InvalidArgumentException('Note cannot exceed 255 characters.');
            }
            $availabilityService->updateWindow($availabilityId, $targetUmpireId, $startsAt, $endsAt, $notes !== '' ? $notes : null, $actorUserId, 'admin_manual');
            $_SESSION['flash_message'] = 'Availability window updated.';
        } elseif ($action === 'delete') {
            $availabilityId = (int) ($_POST['availability_id'] ?? 0);
            if ($availabilityId < 1) {
                throw new InvalidArgumentException('Invalid availability window.');
            }
            $availabilityService->deleteWindow($availabilityId, $targetUmpireId, $actorUserId, 'admin_manual');
            $_SESSION['flash_message'] = 'Availability window deleted.';
        } else {
            throw new InvalidArgumentException('Unsupported availability action.');
        }

        header('Location: availability-management.php?umpire_user_id=' . $targetUmpireId);
        exit;
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: availability-management.php' . ($targetUmpireId > 0 ? '?umpire_user_id=' . $targetUmpireId : ''));
        exit;
    } catch (RuntimeException $e) {
        error_log('[availability-management.php] availability mutation error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Availability window not found or could not be changed.';
        header('Location: availability-management.php' . ($targetUmpireId > 0 ? '?umpire_user_id=' . $targetUmpireId : ''));
        exit;
    } catch (Throwable $e) {
        error_log('[availability-management.php] availability mutation error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'An unexpected error occurred. Please try again.';
        header('Location: availability-management.php' . ($targetUmpireId > 0 ? '?umpire_user_id=' . $targetUmpireId : ''));
        exit;
    }
}

if ($targetUmpireId > 0) {
    try {
        $targetUmpire = validateTargetUmpire($rosterService, $targetUmpireId);
        $windows = $availabilityService->listForUmpire($targetUmpireId);
    } catch (InvalidArgumentException $e) {
        $pageError = $e->getMessage();
        $targetUmpire = null;
        $targetUmpireId = 0;
    } catch (RuntimeException $e) {
        error_log('[availability-management.php] GET load error: ' . $e->getMessage());
        $pageError = 'Unable to load availability data. Please try again.';
        $targetUmpire = null;
        $targetUmpireId = 0;
    }
}

$csrfToken = Auth::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Umpire Availability - D8TL</title>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Manage Umpire Availability</h2>
        <a class="btn btn-outline-secondary btn-sm" href="roster.php">
            <i class="fas fa-id-card me-1"></i> Roster
        </a>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($pageError): ?>
        <div class="alert alert-danger" role="alert"><?= h($pageError) ?></div>
    <?php endif; ?>

    <form method="get" action="availability-management.php" class="row g-2 align-items-end mb-4">
        <div class="col-md-8 col-lg-6">
            <label for="umpire_user_id" class="form-label">Umpire</label>
            <select id="umpire_user_id" name="umpire_user_id" class="form-select" required>
                <option value="">Select an active umpire</option>
                <?php foreach ($activeUmpires as $umpire): ?>
                    <option value="<?= (int) $umpire['id'] ?>" <?= ((int) $umpire['id'] === $targetUmpireId) ? 'selected' : '' ?>>
                        <?= h(($umpire['last_name'] ?? '') . ', ' . ($umpire['first_name'] ?? '') . ' - ' . ($umpire['email'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search me-1"></i> View
            </button>
        </div>
    </form>

    <?php if ($targetUmpire): ?>
        <div class="mb-3">
            <h5 class="mb-1"><?= h(($targetUmpire['first_name'] ?? '') . ' ' . ($targetUmpire['last_name'] ?? '')) ?></h5>
            <div class="text-muted small">
                <?= h($targetUmpire['email'] ?? '') ?>
                <?php if (!empty($targetUmpire['phone'])): ?>
                    <span class="mx-1">|</span><?= h($targetUmpire['phone']) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Add Availability Window</strong></div>
            <div class="card-body">
                <form method="post" action="availability-management.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="umpire_user_id" value="<?= (int) $targetUmpireId ?>">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="starts_at" class="form-label">Start</label>
                            <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="ends_at" class="form-label">End</label>
                            <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="notes" class="form-label">Note</label>
                            <input type="text" id="notes" name="notes" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Availability Windows</strong></div>
            <div class="card-body p-0">
                <?php if (empty($windows)): ?>
                    <p class="p-4 text-muted mb-0">No availability windows found for this umpire.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Note</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($windows as $window): ?>
                                    <?php $windowId = (int) $window['availability_id']; ?>
                                    <tr>
                                        <td><?= h($window['starts_at'] ?? '') ?></td>
                                        <td><?= h($window['ends_at'] ?? '') ?></td>
                                        <td><?= h($window['notes'] ?? '') ?></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#editAvailability<?= $windowId ?>">
                                                Edit
                                            </button>
                                            <form method="post" action="availability-management.php" class="d-inline"
                                                onsubmit="return confirm('Delete this availability window?')">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="umpire_user_id" value="<?= (int) $targetUmpireId ?>">
                                                <input type="hidden" name="availability_id" value="<?= $windowId ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editAvailability<?= $windowId ?>" tabindex="-1"
                                        aria-labelledby="editAvailabilityLabel<?= $windowId ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editAvailabilityLabel<?= $windowId ?>">Edit Availability Window</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post" action="availability-management.php">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="umpire_user_id" value="<?= (int) $targetUmpireId ?>">
                                                        <input type="hidden" name="availability_id" value="<?= $windowId ?>">
                                                        <div class="mb-3">
                                                            <label for="editStart<?= $windowId ?>" class="form-label">Start</label>
                                                            <input type="datetime-local" id="editStart<?= $windowId ?>" name="starts_at"
                                                                class="form-control" required value="<?= h(formatForDatetimeLocal($window['starts_at'] ?? '')) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="editEnd<?= $windowId ?>" class="form-label">End</label>
                                                            <input type="datetime-local" id="editEnd<?= $windowId ?>" name="ends_at"
                                                                class="form-control" required value="<?= h(formatForDatetimeLocal($window['ends_at'] ?? '')) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="editNotes<?= $windowId ?>" class="form-label">Note</label>
                                                            <input type="text" id="editNotes<?= $windowId ?>" name="notes"
                                                                class="form-control" maxlength="255" value="<?= h($window['notes'] ?? '') ?>">
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
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
