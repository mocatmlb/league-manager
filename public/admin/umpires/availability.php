<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireAvailabilityService.php');
require_once EnvLoader::getPath('includes/UmpireConflictChecker.php');

PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php');

$currentUser = Auth::getCurrentUser();
$actorUserId = (int)($currentUser['id'] ?? 0);
if ($actorUserId < 1) {
    header('Location: /login.php');
    exit;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function parseAvailabilityQueryStart(string $date, string $time): DateTimeImmutable {
    $date = trim($date);
    $time = trim($time);
    if ($date === '' || $time === '') {
        throw new InvalidArgumentException('Date and time are required.');
    }

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
        throw new InvalidArgumentException('Time must be a valid HH:MM value.');
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed instanceof DateTimeImmutable || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('Date and time could not be parsed.');
    }

    return $parsed;
}

function formatQueryDateTime(DateTimeInterface $datetime): string {
    return $datetime->format('D, M j, Y g:i A');
}

$queryDate = trim((string)($_GET['date'] ?? ''));
$queryTime = trim((string)($_GET['time'] ?? ''));
$hasQuery = $queryDate !== '' || $queryTime !== '';
$pageError = '';
$results = [];
$queryStart = null;
$queryEnd = null;

if ($hasQuery) {
    try {
        $queryStart = parseAvailabilityQueryStart($queryDate, $queryTime);
        $windowSeconds = (int)UmpireConflictChecker::assignmentWindowSeconds();
        $queryEnd = $queryStart->modify('+' . $windowSeconds . ' seconds');
        if (!$queryEnd) {
            throw new RuntimeException('Failed to calculate query window.');
        }
        $results = (new UmpireAvailabilityService())->getAvailabilityPoolForWindow($queryStart, $queryEnd);
        $windowDurationMinutes = round($windowSeconds / 60);
    } catch (InvalidArgumentException $e) {
        $pageError = $e->getMessage();
    } catch (Throwable $e) {
        $pageError = 'Availability query failed. Please try again.';
        error_log('[admin/umpires/availability.php] query failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Query - D8TL</title>
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-search me-2"></i>Availability Query</h2>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list-check me-1"></i> Assignment Queue
            </a>
            <a href="availability-management.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-calendar-check me-1"></i> Manage Availability
            </a>
        </div>
    </div>

    <?php if ($pageError): ?>
        <div class="alert alert-danger" role="alert">
            <?= h($pageError) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="availability.php" class="row g-3 align-items-end">
                <div class="col-12 col-sm-5 col-md-4 col-lg-3">
                    <label for="date" class="form-label fw-semibold">Game Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?= h($queryDate) ?>" required>
                </div>
                <div class="col-12 col-sm-4 col-md-3 col-lg-2">
                    <label for="time" class="form-label fw-semibold">Game Time</label>
                    <input type="time" id="time" name="time" class="form-control" value="<?= h($queryTime) ?>" required>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Query Availability
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($hasQuery && !$pageError && $queryStart && $queryEnd): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h5 class="mb-1">Available Umpires</h5>
                <div class="text-muted">
                    Query window: <?= h(formatQueryDateTime($queryStart)) ?> to <?= h(formatQueryDateTime($queryEnd)) ?>
                    (<?= (int)$windowDurationMinutes ?> minute duration)
                </div>
            </div>
            <span class="badge bg-primary"><?= count($results) ?> available</span>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($results)): ?>
                    <p class="p-4 text-muted mb-0">No umpires are available for the selected date and time.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Level</th>
                                    <th>Under 18</th>
                                    <th>Current Load</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $umpire): ?>
                                    <?php $name = trim(($umpire['first_name'] ?? '') . ' ' . ($umpire['last_name'] ?? '')); ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($name ?: 'Unnamed Umpire') ?></td>
                                        <td><?= h($umpire['umpire_level'] ?? '') ?></td>
                                        <td>
                                            <?php if ((int)($umpire['is_under_18'] ?? 0) === 1): ?>
                                                <span class="badge bg-warning text-dark">Under 18</span>
                                            <?php else: ?>
                                                <span class="text-muted">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)($umpire['current_game_load'] ?? 0) ?></td>
                                        <td>
                                            <?php if (!empty($umpire['phone'])): ?>
                                                <a href="tel:<?= h(preg_replace('/(?!^\+)\D+/', '', (string)$umpire['phone'])) ?>"><?= h($umpire['phone']) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($umpire['email'])): ?>
                                                <a href="mailto:<?= h($umpire['email']) ?>"><?= h($umpire['email']) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
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
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
