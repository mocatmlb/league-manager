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
    $action = $_POST['action'] ?? '';
    $isAjaxBatch = $action === 'batch_create';

    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        if ($isAjaxBatch) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        } else {
            echo 'Invalid CSRF token.';
        }
        exit;
    }

    try {
        if ($action === 'batch_create') {
            $dates = $_POST['dates'] ?? [];
            if (!is_array($dates)) {
                $dates = [$dates];
            }

            $isAllDay = (string)($_POST['is_all_day'] ?? '1') === '1';
            $startTime = $isAllDay ? null : trim($_POST['start_time'] ?? '');
            $endTime = $isAllDay ? null : trim($_POST['end_time'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            $result = $service->createWindowsForDates($userId, $dates, $startTime, $endTime, $notes);
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
        } elseif ($action === 'update') {
            $availabilityId = (int) ($_POST['availability_id'] ?? 0);
            $startsAt = trim($_POST['starts_at'] ?? '');
            $endsAt = trim($_POST['ends_at'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $isAllDay = (string)($_POST['is_all_day'] ?? '0') === '1';

            if ($isAllDay) {
                [$startsAt, $endsAt] = (new UmpireAvailabilityService())->normalizeAllDayFromDate($startsAt);
            } else {
                // Normalization from datetime-local YYYY-MM-DDTHH:MM to MySQL YYYY-MM-DD HH:MM:SS
                $startsAt = str_replace('T', ' ', $startsAt);
                $endsAt = str_replace('T', ' ', $endsAt);
                if (strlen($startsAt) === 16) $startsAt .= ':00';
                if (strlen($endsAt) === 16) $endsAt .= ':00';
            }

            $service->updateWindow($availabilityId, $userId, $startsAt, $endsAt, $notes);
            $_SESSION['flash_success'] = 'Availability window updated.';
        } elseif ($action === 'delete') {
            $availabilityId = (int) ($_POST['availability_id'] ?? 0);
            $service->deleteWindow($availabilityId, $userId);
            $_SESSION['flash_success'] = 'Availability window removed.';
        } else {
            throw new InvalidArgumentException('Unsupported availability action.');
        }

        header('Location: /umpires/availability.php');
        exit;
    } catch (Throwable $e) {
        if ($isAjaxBatch) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $flashError = $e->getMessage();
    }
}

$windows = $service->listForUmpire($userId);
$csrfToken = Auth::generateCSRFToken();

function availabilityFormatDateTime(?string $datetime): string {
    $ts = $datetime !== null && trim($datetime) !== '' ? strtotime($datetime) : false;
    return $ts !== false ? date('m/d/Y g:i A', $ts) : 'TBD';
}

function availabilityFormatDateTimeLocal(?string $datetime): string {
    $ts = $datetime !== null && trim($datetime) !== '' ? strtotime($datetime) : false;
    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
}

function availabilityIsAllDayWindow(string $startsAt, string $endsAt): bool {
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startsAt);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endsAt);
    if (!$start || !$end) {
        return false;
    }

    $expectedEnd = $start->modify('+1 day')->setTime(0, 0, 0);
    return $start->format('H:i:s') === '00:00:00' && $end == $expectedEnd;
}

function availabilityFormatCalendarTime(string $datetime): string {
    $ts = strtotime($datetime);
    return $ts !== false ? date('g:i A', $ts) : '';
}

$calendarEvents = [];
foreach ($windows as $window) {
    $startsAt = (string)($window['starts_at'] ?? '');
    $endsAt = (string)($window['ends_at'] ?? '');
    $isAllDayWindow = availabilityIsAllDayWindow($startsAt, $endsAt);
    $calendarEvents[] = [
        'start' => $isAllDayWindow ? substr($startsAt, 0, 10) : $startsAt,
        'end' => $isAllDayWindow ? substr($endsAt, 0, 10) : $endsAt,
        'allDay' => $isAllDayWindow,
        'title' => $isAllDayWindow
            ? 'Available (all day)'
            : 'Available ' . availabilityFormatCalendarTime($startsAt) . '-' . availabilityFormatCalendarTime($endsAt),
        'backgroundColor' => $isAllDayWindow ? '#198754' : '#0d6efd',
        'borderColor' => $isAllDayWindow ? '#198754' : '#0d6efd',
        'extendedProps' => [
            'availabilityId' => (int)($window['availability_id'] ?? 0)
        ]
    ];
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .availability-card {
            transition: transform 0.2s;
        }
        .availability-card:hover {
            border-color: #0d6efd;
        }
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

                <div id="availabilityBatchAlert" class="alert alert-dismissible fade show d-none" role="alert">
                    <span id="availabilityBatchAlertMessage"></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-calendar-alt me-2"></i>Select Available Dates</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4 align-items-start">
                            <div class="col-lg-8 availability-calendar-shell">
                                <div id="availabilityCalendarEl"></div>
                            </div>
                            <div class="col-lg-4">
                                <form id="availabilityBatchForm" class="availability-save-controls">
                                    <input type="hidden" name="action" value="batch_create">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

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
                                        <label for="availabilityBatchNotes" class="form-label">Notes (Optional)</label>
                                        <textarea class="form-control" id="availabilityBatchNotes" name="notes" rows="2" placeholder="e.g. Available after work"></textarea>
                                    </div>

                                    <button type="submit" id="availabilityBatchSaveBtn" class="btn btn-primary w-100" disabled>
                                        <i class="fas fa-save me-1"></i><span id="availabilityBatchSaveLabel">Save Dates</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4" id="availabilityWindowsCard">
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
                                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Window" data-bs-toggle="modal" data-bs-target="#editWindowModal<?= (int)$w['availability_id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
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

    <div id="editWindowModals">
        <?php foreach ($windows as $w): ?>
            <?php
            $editWindowId = (int)$w['availability_id'];
            $editIsAllDay = availabilityIsAllDayWindow((string)($w['starts_at'] ?? ''), (string)($w['ends_at'] ?? ''));
            ?>
            <div class="modal fade text-start" id="editWindowModal<?= $editWindowId ?>" tabindex="-1" aria-labelledby="editWindowModalLabel<?= $editWindowId ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" class="needs-validation availability-edit-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="availability_id" value="<?= $editWindowId ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editWindowModalLabel<?= $editWindowId ?>">Edit Availability Window</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input availability-edit-all-day-toggle" type="checkbox" role="switch"
                                           id="edit_all_day_<?= $editWindowId ?>" name="is_all_day" value="1" <?= $editIsAllDay ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_all_day_<?= $editWindowId ?>">All day</label>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_starts_at_<?= $editWindowId ?>" class="form-label">Starts At</label>
                                    <input type="datetime-local" class="form-control" id="edit_starts_at_<?= $editWindowId ?>" name="starts_at" value="<?= htmlspecialchars(availabilityFormatDateTimeLocal($w['starts_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="mb-3 availability-edit-end-wrapper">
                                    <label for="edit_ends_at_<?= $editWindowId ?>" class="form-label">Ends At</label>
                                    <input type="datetime-local" class="form-control" id="edit_ends_at_<?= $editWindowId ?>" name="ends_at" value="<?= htmlspecialchars(availabilityFormatDateTimeLocal($w['ends_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_notes_<?= $editWindowId ?>" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="edit_notes_<?= $editWindowId ?>" name="notes" rows="2"><?= htmlspecialchars($w['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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
        <?php endforeach; ?>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Window</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
        var availabilityEvents = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var selectedDates = new Set();
        var availabilityCalendar = null;

        function showAvailabilityAlert(type, message) {
            const alert = document.getElementById('availabilityBatchAlert');
            if (!alert) return;
            alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
            document.getElementById('availabilityBatchAlertMessage').textContent = message;
        }

        function updateSelectionSummary() {
            const count = selectedDates.size;
            const summary = document.getElementById('availabilitySelectionSummary');
            const button = document.getElementById('availabilityBatchSaveBtn');
            const label = document.getElementById('availabilityBatchSaveLabel');
            const allDay = document.getElementById('availabilityAllDayToggle').checked;
            const startTime = document.getElementById('availabilityStartTime').value;
            const endTime = document.getElementById('availabilityEndTime').value;

            if (count === 0) {
                summary.textContent = 'No dates selected';
                label.textContent = 'Save Dates';
                button.disabled = true;
                return;
            }

            const sortedDates = Array.from(selectedDates).sort();
            const mode = allDay ? 'all day' : startTime + '-' + endTime;
            summary.textContent = sortedDates.join(', ') + ' (' + mode + ')';
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
                    const nextModals = doc.getElementById('editWindowModals');
                    const windowsCard = document.getElementById('availabilityWindowsCard');
                    const modals = document.getElementById('editWindowModals');

                    if (nextWindowsCard && windowsCard) {
                        windowsCard.innerHTML = nextWindowsCard.innerHTML;
                    }
                    if (nextModals && modals) {
                        modals.innerHTML = nextModals.innerHTML;
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
                        // calendar event re-parse failed; windows table was still refreshed
                    }
                })
                .catch(function() {
                    // refresh failed silently; the save already succeeded
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
                batchAlert.addEventListener('close.bs.alert', function(e) {
                    e.preventDefault();
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
                        if (selectedDates.has(info.dateStr)) {
                            info.el.classList.add('fc-day-selected');
                        }
                    },
                    eventClick: function(info) {
                        const id = info.event.extendedProps.availabilityId;
                        const modal = document.getElementById('editWindowModal' + id);
                        if (modal) {
                            bootstrap.Modal.getOrCreateInstance(modal).show();
                        }
                    },
                    eventDisplay: 'block'
                });
                availabilityCalendar.render();
            }

            allDayToggle.addEventListener('change', function() {
                timeControls.classList.toggle('d-none', allDayToggle.checked);
                updateSelectionSummary();
            });
            document.getElementById('availabilityStartTime').addEventListener('change', updateSelectionSummary);
            document.getElementById('availabilityEndTime').addEventListener('change', updateSelectionSummary);

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                if (selectedDates.size === 0) {
                    showAvailabilityAlert('warning', 'Select at least one date.');
                    return;
                }

                const formData = new FormData(form);
                formData.set('is_all_day', allDayToggle.checked ? '1' : '0');
                Array.from(selectedDates).sort().forEach(function(date) {
                    formData.append('dates[]', date);
                });

                fetch('/umpires/availability.php', {
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

            updateSelectionSummary();
            initializeAvailabilityEditForms();
        });

        // Simple client-side validation for start < end
        document.querySelectorAll('form.needs-validation').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const allDayToggle = form.querySelector('input[name="is_all_day"]');
                if (allDayToggle && allDayToggle.checked) {
                    return;
                }

                const startInput = form.querySelector('input[name="starts_at"]');
                const endInput = form.querySelector('input[name="ends_at"]');
                if (!startInput || !endInput || endInput.disabled) {
                    return;
                }
                const start = new Date(startInput.value);
                const end = new Date(endInput.value);
            
                if (start >= end) {
                    event.preventDefault();
                    alert('End time must be after start time.');
                }
            });
        });
    </script>
</body>
</html>
