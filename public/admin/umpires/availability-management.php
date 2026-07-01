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

function isAllDayAvailabilityWindow(string $startsAt, string $endsAt): bool {
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startsAt);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt);
    if (!$start || !$end) {
        return false;
    }

    $expectedEnd = $start->modify('+1 day')->setTime(0, 0, 0);
    return $start->format('H:i:s') === '00:00:00' && $end == $expectedEnd;
}

function formatCalendarTime(string $datetime): string {
    $ts = strtotime($datetime);
    return $ts !== false ? date('g:i A', $ts) : '';
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
    $action = $_POST['action'] ?? '';
    $isAjaxBatch = $action === 'batch_create';

    try {
        $csrfInput = $_POST['csrf_token'] ?? '';
        if (!is_string($csrfInput) || !Auth::verifyCSRFToken($csrfInput)) {
            throw new InvalidArgumentException('Invalid security token. Please try again.');
        }

        $targetUmpire = validateTargetUmpire($rosterService, $targetUmpireId);

        if ($action === 'batch_create') {
            $dates = $_POST['dates'] ?? [];
            if (!is_array($dates)) {
                $dates = [$dates];
            }

            $isAllDay = (string) ($_POST['is_all_day'] ?? '1') === '1';
            $startTime = $isAllDay ? null : trim((string) ($_POST['start_time'] ?? ''));
            $endTime = $isAllDay ? null : trim((string) ($_POST['end_time'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if (strlen($notes) > 255) {
                throw new InvalidArgumentException('Note cannot exceed 255 characters.');
            }

            $result = $availabilityService->createWindowsForDates(
                $targetUmpireId,
                $dates,
                $startTime,
                $endTime,
                $notes !== '' ? $notes : null,
                $actorUserId,
                'admin_manual'
            );
            if (empty($result['created']) && empty($result['skipped'])) {
                throw new InvalidArgumentException('No availability windows were created.');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors']
            ]);
            exit;
        } elseif ($action === 'create') {
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
            $isAllDay = (string) ($_POST['is_all_day'] ?? '0') === '1';
            if ($isAllDay) {
                [$startsAt, $endsAt] = (new UmpireAvailabilityService())->normalizeAllDayFromDate($_POST['starts_at'] ?? '');
            } else {
                $startsAt = normalizeDatetimeLocal($_POST['starts_at'] ?? '', 'Start time');
                $endsAt = normalizeDatetimeLocal($_POST['ends_at'] ?? '', 'End time');
            }
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
        if ($isAjaxBatch) {
            http_response_code($e->getMessage() === 'Invalid security token. Please try again.' ? 403 : 400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: availability-management.php' . ($targetUmpireId > 0 ? '?umpire_user_id=' . $targetUmpireId : ''));
        exit;
    } catch (RuntimeException $e) {
        error_log('[availability-management.php] availability mutation error: ' . $e->getMessage());
        if ($isAjaxBatch) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Availability window not found or could not be changed.']);
            exit;
        }
        $_SESSION['flash_error'] = 'Availability window not found or could not be changed.';
        header('Location: availability-management.php' . ($targetUmpireId > 0 ? '?umpire_user_id=' . $targetUmpireId : ''));
        exit;
    } catch (Throwable $e) {
        error_log('[availability-management.php] availability mutation error: ' . $e->getMessage());
        if ($isAjaxBatch) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
            exit;
        }
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

$calendarEvents = [];
foreach ($windows as $window) {
    $startsAt = (string) ($window['starts_at'] ?? '');
    $endsAt = (string) ($window['ends_at'] ?? '');
    $isAllDayWindow = isAllDayAvailabilityWindow($startsAt, $endsAt);
    $calendarEvents[] = [
        'start' => $isAllDayWindow ? substr($startsAt, 0, 10) : $startsAt,
        'end' => $isAllDayWindow ? substr($endsAt, 0, 10) : $endsAt,
        'allDay' => $isAllDayWindow,
        'title' => $isAllDayWindow
            ? 'Available (all day)'
            : 'Available ' . formatCalendarTime($startsAt) . '-' . formatCalendarTime($endsAt),
        'backgroundColor' => $isAllDayWindow ? '#198754' : '#0d6efd',
        'borderColor' => $isAllDayWindow ? '#198754' : '#0d6efd',
        'extendedProps' => [
            'availabilityId' => (int) ($window['availability_id'] ?? 0)
        ]
    ];
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .availability-calendar-shell {
            min-width: 0;
        }
        #availabilityCalendarEl {
            min-height: 520px;
        }
        #availabilityCalendarEl .fc-toolbar {
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        #availabilityCalendarEl .fc-button,
        .availability-save-controls .btn {
            min-height: 44px;
        }
        #availabilityCalendarEl .fc-daygrid-day {
            cursor: pointer;
        }
        #availabilityCalendarEl .fc-day-selected {
            background-color: rgba(13, 110, 253, 0.15) !important;
            box-shadow: inset 0 0 0 2px #0d6efd;
        }
        @media (max-width: 575.98px) {
            #availabilityCalendarEl {
                min-height: 460px;
            }
            #availabilityCalendarEl .fc-toolbar-title {
                font-size: 1.1rem;
            }
        }
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

        <div id="availabilityBatchAlert" class="alert alert-dismissible fade show d-none" role="alert">
            <span id="availabilityBatchAlertMessage"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Select Available Dates</strong></div>
            <div class="card-body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8 availability-calendar-shell">
                        <div id="availabilityCalendarEl"></div>
                    </div>
                    <div class="col-lg-4">
                        <form id="availabilityBatchForm" class="availability-save-controls">
                            <input type="hidden" name="action" value="batch_create">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="umpire_user_id" value="<?= (int) $targetUmpireId ?>">

                            <div class="mb-3">
                                <div class="text-muted small mb-1">Selected Dates</div>
                                <div id="availabilitySelectionSummary" class="fw-semibold">No dates selected</div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="availabilityAllDayToggle" checked>
                                <label class="form-check-label" for="availabilityAllDayToggle">All day</label>
                            </div>

                            <div id="availabilityTimeControls" class="row g-2 mb-3 d-none">
                                <div class="col-sm-6 col-lg-12 col-xl-6">
                                    <label for="availabilityStartTime" class="form-label">Start</label>
                                    <input type="time" class="form-control" id="availabilityStartTime" name="start_time" value="09:00">
                                </div>
                                <div class="col-sm-6 col-lg-12 col-xl-6">
                                    <label for="availabilityEndTime" class="form-label">End</label>
                                    <input type="time" class="form-control" id="availabilityEndTime" name="end_time" value="17:00">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="availabilityBatchNotes" class="form-label">Note</label>
                                <textarea class="form-control" id="availabilityBatchNotes" name="notes" rows="2" maxlength="255"></textarea>
                            </div>

                            <button type="submit" id="availabilityBatchSaveBtn" class="btn btn-primary w-100" disabled>
                                <i class="fas fa-save me-1"></i><span id="availabilityBatchSaveLabel">Save Dates</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Add Single Window</strong></div>
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

        <div class="card" id="availabilityWindowsCard">
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
                                    <?php
                                    $windowId = (int) $window['availability_id'];
                                    $editIsAllDay = isAllDayAvailabilityWindow((string)($window['starts_at'] ?? ''), (string)($window['ends_at'] ?? ''));
                                    ?>
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
                                                <form method="post" action="availability-management.php" class="availability-edit-form">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="umpire_user_id" value="<?= (int) $targetUmpireId ?>">
                                                        <input type="hidden" name="availability_id" value="<?= $windowId ?>">
                                                        <div class="form-check form-switch mb-3">
                                                            <input class="form-check-input availability-edit-all-day-toggle" type="checkbox" role="switch"
                                                                id="editAllDay<?= $windowId ?>" name="is_all_day" value="1" <?= $editIsAllDay ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="editAllDay<?= $windowId ?>">All day</label>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="editStart<?= $windowId ?>" class="form-label">Start</label>
                                                            <input type="datetime-local" id="editStart<?= $windowId ?>" name="starts_at"
                                                                class="form-control" required value="<?= h(formatForDatetimeLocal($window['starts_at'] ?? '')) ?>">
                                                        </div>
                                                        <div class="mb-3 availability-edit-end-wrapper">
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
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script>
    var availabilityEvents = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var selectedDates = new Set();
    var availabilityCalendar = null;

    function formatCalendarDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function showAvailabilityAlert(type, message) {
        const alert = document.getElementById('availabilityBatchAlert');
        const messageEl = document.getElementById('availabilityBatchAlertMessage');
        if (!alert || !messageEl) return;
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
        messageEl.textContent = message;
    }

    function updateSelectionSummary() {
        const summary = document.getElementById('availabilitySelectionSummary');
        const button = document.getElementById('availabilityBatchSaveBtn');
        const label = document.getElementById('availabilityBatchSaveLabel');
        const allDayToggle = document.getElementById('availabilityAllDayToggle');
        const startInput = document.getElementById('availabilityStartTime');
        const endInput = document.getElementById('availabilityEndTime');
        if (!summary || !button || !label || !allDayToggle || !startInput || !endInput) return;

        const count = selectedDates.size;
        if (count === 0) {
            summary.textContent = 'No dates selected';
            label.textContent = 'Save Dates';
            button.disabled = true;
            return;
        }

        const mode = allDayToggle.checked ? 'all day' : startInput.value + '-' + endInput.value;
        summary.textContent = Array.from(selectedDates).sort().join(', ') + ' (' + mode + ')';
        label.textContent = 'Save ' + count + (count === 1 ? ' Date' : ' Dates');
        button.disabled = false;
    }

    function refreshAvailabilityContent() {
        return fetch(window.location.href, { credentials: 'same-origin' })
            .then(function(response) { return response.text(); })
            .then(function(html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nextWindowsCard = doc.getElementById('availabilityWindowsCard');
                const windowsCard = document.getElementById('availabilityWindowsCard');

                if (nextWindowsCard && windowsCard) {
                    windowsCard.innerHTML = nextWindowsCard.innerHTML;
                }
                initializeAvailabilityEditForms();

                try {
                    const eventMatch = html.match(/var availabilityEvents = (.*?);[\s\S]*?var selectedDates/);
                    if (eventMatch && availabilityCalendar) {
                        availabilityEvents = JSON.parse(eventMatch[1]);
                        availabilityCalendar.removeAllEvents();
                        availabilityCalendar.addEventSource(availabilityEvents);
                    }
                } catch (_) {
                    // The table refresh still gives the assignor the current saved windows.
                }
            })
            .catch(function() {
                // The save succeeded; avoid replacing the success message with a refresh-only failure.
            });
    }

    function initializeAvailabilityEditForms() {
        document.querySelectorAll('.availability-edit-form').forEach(function(editForm) {
            if (editForm.dataset.allDayBound === '1') return;

            const toggle = editForm.querySelector('.availability-edit-all-day-toggle');
            const endWrapper = editForm.querySelector('.availability-edit-end-wrapper');
            const endInput = editForm.querySelector('input[name="ends_at"]');
            if (!toggle || !endWrapper || !endInput) return;

            function syncEditAllDay() {
                endWrapper.classList.toggle('d-none', toggle.checked);
                endInput.disabled = toggle.checked;
                endInput.required = !toggle.checked;
            }

            toggle.addEventListener('change', syncEditAllDay);
            editForm.dataset.allDayBound = '1';
            syncEditAllDay();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const calEl = document.getElementById('availabilityCalendarEl');
        const allDayToggle = document.getElementById('availabilityAllDayToggle');
        const timeControls = document.getElementById('availabilityTimeControls');
        const form = document.getElementById('availabilityBatchForm');
        const batchAlert = document.getElementById('availabilityBatchAlert');

        if (batchAlert) {
            batchAlert.addEventListener('close.bs.alert', function(event) {
                event.preventDefault();
                batchAlert.classList.add('d-none');
                batchAlert.classList.remove('show');
            });
        }

        if (calEl && window.FullCalendar) {
            availabilityCalendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                selectable: false,
                events: availabilityEvents,
                headerToolbar: {
                    start: 'prev,next today',
                    center: 'title',
                    end: ''
                },
                dateClick: function(info) {
                    const date = info.dateStr;
                    if (selectedDates.has(date)) {
                        selectedDates.delete(date);
                        info.dayEl.classList.remove('fc-day-selected');
                    } else {
                        selectedDates.add(date);
                        info.dayEl.classList.add('fc-day-selected');
                    }
                    updateSelectionSummary();
                },
                dayCellDidMount: function(info) {
                    if (selectedDates.has(formatCalendarDate(info.date))) {
                        info.el.classList.add('fc-day-selected');
                    }
                },
                eventClick: function(info) {
                    const id = info.event.extendedProps.availabilityId;
                    const modal = document.getElementById('editAvailability' + id);
                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                },
                eventDisplay: 'block'
            });
            availabilityCalendar.render();
        }

        if (allDayToggle && timeControls) {
            allDayToggle.addEventListener('change', function() {
                timeControls.classList.toggle('d-none', allDayToggle.checked);
                updateSelectionSummary();
            });
        }

        const startInput = document.getElementById('availabilityStartTime');
        const endInput = document.getElementById('availabilityEndTime');
        if (startInput) startInput.addEventListener('change', updateSelectionSummary);
        if (endInput) endInput.addEventListener('change', updateSelectionSummary);

        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                if (selectedDates.size === 0) {
                    showAvailabilityAlert('warning', 'Select at least one date.');
                    return;
                }

                const formData = new FormData(form);
                formData.set('is_all_day', allDayToggle && allDayToggle.checked ? '1' : '0');
                Array.from(selectedDates).sort().forEach(function(date) {
                    formData.append('dates[]', date);
                });

                fetch('availability-management.php?umpire_user_id=<?= (int) $targetUmpireId ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                    .then(function(response) {
                        return response.json().then(function(data) {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Unable to save availability.');
                            }
                            return data;
                        });
                    })
                    .then(function(data) {
                        let message = data.created.length + ' availability window' + (data.created.length === 1 ? '' : 's') + ' saved.';
                        if (data.skipped && data.skipped.length > 0) {
                            message += ' Skipped existing dates: ' + data.skipped.join(', ') + '.';
                        }
                        if (data.errors && data.errors.length > 0) {
                            message += ' Rejected invalid dates: ' + data.errors.join(', ') + '.';
                        }
                        showAvailabilityAlert((data.skipped && data.skipped.length > 0) || (data.errors && data.errors.length > 0) ? 'warning' : 'success', message);
                        selectedDates.clear();
                        document.querySelectorAll('#availabilityCalendarEl .fc-day-selected').forEach(function(cell) {
                            cell.classList.remove('fc-day-selected');
                        });
                        updateSelectionSummary();
                        return refreshAvailabilityContent();
                    })
                    .catch(function(error) {
                        showAvailabilityAlert('danger', error.message);
                    });
            });
        }

        updateSelectionSummary();
        initializeAvailabilityEditForms();
    });
</script>
</body>
</html>
