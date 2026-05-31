<?php
/**
 * District 8 Travel League - Reschedule Request (Team-Scoped)
 *
 * Requires team_owner role. Uses RescheduleService for all enforcement.
 */

$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) {
        require_once $__candidate;
        $__found = true;
        break;
    }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
        $__found = true;
    }
}
if (!$__found) {
    error_log('D8TL ERROR: Unable to locate includes/env-loader.php from ' . __FILE__);
    http_response_code(500);
    exit('Configuration error: env-loader not found');
}
unset($__dir, $__found, $__i, $__candidate);

require_once EnvLoader::getPath('includes/coach_bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/ActivityLogger.php');
require_once EnvLoader::getPath('includes/TeamScope.php');
require_once EnvLoader::getPath('includes/RescheduleService.php');
require_once EnvLoader::getPath('includes/TeamRegistrationService.php');

PermissionGuard::requireRole('team_owner', '/coaches/login.php');

$db     = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
$service = new RescheduleService($db);

// Coach contact info for pre-population (read-only display, UX-DR8)
$coachContact = $db->fetchOne(
    'SELECT first_name, last_name, phone, email FROM users WHERE id = :id',
    ['id' => $userId]
);

// ---------------------------------------------------------------------------
// POST handler — PRG pattern
// ---------------------------------------------------------------------------
$error        = '';
$postValues   = [];
$dupCandidates = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid form submission. Please try again.';
        } else {
            $postValues = [
                'game_id'            => $_POST['game_id']            ?? '',
                'requested_date'     => $_POST['requested_date']     ?? '',
                'requested_time'     => $_POST['requested_time']     ?? '',
                'requested_location' => $_POST['requested_location'] ?? '',
                'location_name_new'  => trim($_POST['location_name_new'] ?? ''),
                'reason'             => $_POST['reason']             ?? '',
                'game_notes'         => $_POST['game_notes']         ?? '',
            ];

            // Resolve requested_location: handle "not-listed" inline entry
            $resolvedLocation = $postValues['requested_location'];
            if ($resolvedLocation === 'not-listed') {
                if ($postValues['location_name_new'] === '') {
                    $error = 'Please enter a location name.';
                } else {
                    // Duplicate check against existing locations (skip if already confirmed)
                    if (($_POST['dup_confirmed'] ?? '') === '') {
                        $svc = new TeamRegistrationService($db);
                        $dupCandidates = $svc->findDuplicateCandidates([
                            'name'    => $postValues['location_name_new'],
                            'address' => '',
                        ]);
                    }
                    if (empty($dupCandidates)) {
                        $resolvedLocation = $postValues['location_name_new'];
                    }
                }
            }

            if (empty($error) && empty($dupCandidates)) {
                $requestData = [
                    'requested_date'     => $postValues['requested_date'],
                    'requested_time'     => $postValues['requested_time'],
                    'requested_location' => $resolvedLocation,
                    'reason'             => $postValues['reason'],
                ];
                try {
                    $gameId = (int) ($postValues['game_id'] ?? 0);
                    if ($gameId <= 0) {
                        $error = 'Please select a game to reschedule.';
                    } else {
                        $service->submit($userId, $gameId, $requestData);
                        $coachNote = trim($postValues['game_notes'] ?? '');
                        if ($coachNote !== '') {
                            try {
                                $db->update('schedule_history', ['user_notes' => $coachNote], 'game_id = ? AND is_current = 1', [$gameId]);
                            } catch (Throwable $noteEx) {
                                error_log('[schedule-change] Failed to save game note: ' . $noteEx->getMessage());
                            }
                        }
                        $_SESSION['flash_success'] =
                            'Request submitted. You will receive an email when your request is reviewed.';
                        header('Location: schedule-change.php');
                        exit;
                    }
                } catch (TeamScopeViolationException $e) {
                    http_response_code(403);
                    $error = 'Not authorized — you are not permitted to reschedule this game.';
                    include EnvLoader::getPath('includes/coaches_nav.php');
                    echo '<div class="container mt-4"><div class="alert alert-danger">' . htmlspecialchars($error) . '</div></div>';
                    include EnvLoader::getPath('includes/footer.php');
                    exit;
                } catch (SubmissionWindowException $e) {
                    $error = $e->getMessage();
                } catch (Throwable $e) {
                    $error = 'Request not submitted — please check your connection and try again.';
                }
            }
        }
    } elseif ($action === 'cancel') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid form submission. Please try again.';
        } else {
            try {
                $service->cancel((int) ($_POST['request_id'] ?? 0), $userId);
                $_SESSION['flash_success'] = 'Reschedule request cancelled.';
                header('Location: schedule-change.php');
                exit;
            } catch (RequestNotCancellableException | Throwable $e) {
                $error = 'Unable to cancel request. It may have already been reviewed.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Page data
// ---------------------------------------------------------------------------
$message = '';
if (isset($_SESSION['flash_success'])) {
    $message = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$eligibleGames  = $service->getEligibleGames($userId);
$coachRequests  = $service->getCoachRequests($userId);
$locations      = $db->fetchAll('SELECT location_name, city, state FROM locations WHERE active_status = \'Active\' ORDER BY location_name');
$minNewGameHours = (int) getSetting('reschedule_min_new_game_hours', '0');

$pageTitle = 'Schedule Change Request — ' . (defined('APP_NAME') ? APP_NAME : 'District 8 Travel League');

// Preserved POST game_id for auto-reveal on error re-render
$preservedGameId = !empty($postValues['game_id']) ? (int) $postValues['game_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <?php include EnvLoader::getPath('includes/coaches_nav.php'); ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Schedule Change Request</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($dupCandidates)): ?>
                <div class="alert alert-warning" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Similar Location Already Exists</h5>
                    <p class="mb-2">
                        "<strong><?php echo htmlspecialchars($postValues['location_name_new'], ENT_QUOTES, 'UTF-8'); ?></strong>"
                        looks similar to an existing location. Did you mean to select one of these from the list?
                    </p>
                    <ul class="mb-3">
                        <?php foreach ($dupCandidates as $c): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($c['location_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if (!empty($c['city'])): ?>
                                &mdash; <?php echo htmlspecialchars($c['city'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($c['state'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action"            value="submit">
                        <input type="hidden" name="csrf_token"        value="<?php echo htmlspecialchars(Auth::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="dup_confirmed"     value="yes">
                        <input type="hidden" name="game_id"           value="<?php echo htmlspecialchars($postValues['game_id'],            ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="requested_date"    value="<?php echo htmlspecialchars($postValues['requested_date'],     ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="requested_time"    value="<?php echo htmlspecialchars($postValues['requested_time'],     ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="requested_location" value="not-listed">
                        <input type="hidden" name="location_name_new" value="<?php echo htmlspecialchars($postValues['location_name_new'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="reason"            value="<?php echo htmlspecialchars($postValues['reason'],            ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-check"></i> It's Different — Submit Anyway
                        </button>
                    </form>
                    <span class="ms-2 text-muted small">or scroll down to pick the correct location from the list.</span>
                </div>
                <?php endif; ?>

                <!-- =========================================================
                     SUBMIT REQUEST SECTION
                     ========================================================= -->

                <?php if (empty($eligibleGames)): ?>
                    <!-- AC2: Empty state (UX-DR16) -->
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        No games are available to reschedule — scored and cancelled games are not eligible.
                    </div>

                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-calendar-alt"></i> Request a Reschedule</h3>
                    </div>
                    <div class="card-body">

                        <?php if ($minNewGameHours > 0): ?>
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-clock"></i>
                            The new game date and time you request must be at least
                            <strong><?php echo $minNewGameHours; ?> hour<?php echo $minNewGameHours !== 1 ? 's' : ''; ?></strong>
                            from the time you submit this form.
                        </div>
                        <?php endif; ?>

                        <!-- Game selection dropdown (AC3) -->
                        <div class="mb-4">
                            <label for="game-select" class="form-label fw-bold">Select a game *</label>
                            <select id="game-select" class="form-select form-select-lg">
                                <option value="">— choose a game —</option>
                                <?php foreach ($eligibleGames as $g): ?>
                                <option value="<?php echo (int) $g['game_id']; ?>"
                                        data-date="<?php echo htmlspecialchars($g['game_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-time="<?php echo htmlspecialchars($g['game_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-location="<?php echo htmlspecialchars($g['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php if ($preservedGameId === (int) $g['game_id']): ?>selected<?php endif; ?>>
                                    Game #<?php echo htmlspecialchars((string) ($g['game_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    &mdash; <?php echo htmlspecialchars(formatDate($g['game_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    &mdash; <?php echo htmlspecialchars(strtoupper($g['away_team_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    @ <?php echo htmlspecialchars(strtoupper($g['home_team_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Game Detail Reveal Panel (AC3, UX-DR5) -->
                        <div class="game-detail-panel card bg-light mb-4" aria-live="polite" style="display:none">
                            <div class="card-body">
                                <h6 class="card-title">Current Schedule</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Date:</strong>
                                        <span id="detail-date"></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Time:</strong>
                                        <span id="detail-time"></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Location:</strong>
                                        <span id="detail-location"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Request form (hidden until game selected, UX-DR5) -->
                        <form id="reschedule-form" method="POST" style="display:none">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars(Auth::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="game_id" id="form-game-id" value="">

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">New Date *</label>
                                    <input type="date" id="requested-date" name="requested_date" class="form-control" required
                                           value="<?php echo htmlspecialchars($postValues['requested_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if ($minNewGameHours > 0): ?>
                                    <div class="form-text">Must be at least <?php echo $minNewGameHours; ?> hour(s) from now.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">New Time *</label>
                                    <input type="time" id="requested-time" name="requested_time" class="form-control" required
                                           value="<?php echo htmlspecialchars($postValues['requested_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">New Location *</label>
                                    <select name="requested_location" id="requestedLocationSelect" class="form-select" required>
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo htmlspecialchars($loc['location_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php if (($postValues['requested_location'] ?? '') === $loc['location_name']): ?>selected<?php endif; ?>>
                                            <?php echo htmlspecialchars($loc['location_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="not-listed"
                                            <?php if (($postValues['requested_location'] ?? '') === 'not-listed'): ?>selected<?php endif; ?>>
                                            (Not Listed)
                                        </option>
                                    </select>
                                    <div id="notListedFields" style="display:none; margin-top: 8px;">
                                        <input type="text" name="location_name_new" id="locationNameNew"
                                               class="form-control form-control-sm"
                                               placeholder="Enter location name"
                                               value="<?php echo htmlspecialchars($postValues['location_name_new'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Reason for Change *</label>
                                <textarea name="reason" class="form-control" rows="4" required
                                          placeholder="Please provide a detailed reason for the schedule change request..."><?php echo htmlspecialchars($postValues['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Game Notes <small class="text-muted fw-normal">(admin-visible only, optional)</small></label>
                                <textarea name="game_notes" class="form-control" rows="3"
                                          placeholder="Any additional context about this game for the admin..."><?php echo htmlspecialchars($postValues['game_notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <!-- Contact info (read-only, pre-populated, UX-DR8) -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        Contact Information
                                        <a href="profile.php" class="small ms-2">Update in your profile →</a>
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Name:</strong>
                                            <?php echo htmlspecialchars(
                                                trim(($coachContact['first_name'] ?? '') . ' ' . ($coachContact['last_name'] ?? '')),
                                                ENT_QUOTES, 'UTF-8'
                                            ); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Phone:</strong>
                                            <?php echo htmlspecialchars($coachContact['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Email:</strong>
                                            <?php echo htmlspecialchars($coachContact['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Request Reschedule
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
                <?php endif; ?>

                <!-- =========================================================
                     PENDING REQUESTS TABLE (AC6)
                     ========================================================= -->

                <?php if (!empty($coachRequests)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Your Reschedule Requests</h5>
                    </div>
                    <div class="card-body">
                        <table id="coachRequestsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Req ID</th>
                                    <th>Game ID</th>
                                    <th>Game #</th>
                                    <th>Original Date</th>
                                    <th>Requested Date</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($coachRequests as $req): ?>
                            <tr>
                                <td><?php echo (int) $req['request_id']; ?></td>
                                <td><?php echo (int) $req['game_id']; ?></td>
                                <td><?php echo htmlspecialchars((string) ($req['game_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(formatDate($req['original_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(formatDate($req['requested_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($req['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($req['request_status'] === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($req['request_status'] === 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Denied</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['request_status'] === 'Pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token"
                                               value="<?php echo htmlspecialchars(Auth::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="request_id"
                                               value="<?php echo (int) $req['request_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Cancel this request?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?>
                <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : '', ENT_QUOTES, 'UTF-8'); ?>.
                All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        if ($('#coachRequestsTable').length) {
            $('#coachRequestsTable').DataTable({
                order: [[0, 'desc']],
                columnDefs: [
                    { type: 'num', targets: 0 },
                    { orderable: false, searchable: false, targets: 7 }
                ],
                pageLength: 25,
                language: { search: 'Filter requests:' }
            });
        }
    });
    </script>
    <script>
    // Reveal game detail panel and request form on game selection (AC3, UX-DR5)
    function onGameSelected(select) {
        var opt    = select.options[select.selectedIndex];
        var panel  = document.querySelector('.game-detail-panel');
        var form   = document.getElementById('reschedule-form');
        var gameId = document.getElementById('form-game-id');

        if (!opt || !opt.value) {
            if (panel) panel.style.display = 'none';
            if (form)  form.style.display  = 'none';
            return;
        }

        document.getElementById('detail-date').textContent     = opt.dataset.date     || '';
        document.getElementById('detail-time').textContent     = opt.dataset.time     || '';
        document.getElementById('detail-location').textContent = opt.dataset.location || '';

        if (gameId) gameId.value = opt.value;
        if (panel)  panel.style.display = '';
        if (form)   form.style.display  = '';
    }

    document.getElementById('game-select').addEventListener('change', function () {
        onGameSelected(this);
    });

    // Not Listed toggle
    function toggleNotListed() {
        var sel    = document.getElementById('requestedLocationSelect');
        var fields = document.getElementById('notListedFields');
        var input  = document.getElementById('locationNameNew');
        if (!sel || !fields) return;
        if (sel.value === 'not-listed') {
            fields.style.display = 'block';
            if (input) input.required = true;
        } else {
            fields.style.display = 'none';
            if (input) { input.required = false; input.value = ''; }
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('requestedLocationSelect');
        if (sel) {
            sel.addEventListener('change', toggleNotListed);
            toggleNotListed(); // show inline field if re-rendering after dup warning
        }
    });

    <?php if ($preservedGameId): ?>
    // Auto-trigger reveal for preserved game_id after error re-render (UX-DR18)
    (function () {
        var sel = document.getElementById('game-select');
        if (sel) {
            sel.value = <?php echo (int) $preservedGameId; ?>;
            onGameSelected(sel);
        }
    })();
    <?php endif; ?>

    <?php if ($minNewGameHours > 0): ?>
    // Enforce minimum lead time on the requested new date/time inputs.
    (function () {
        var minHours = <?php echo (int) $minNewGameHours; ?>;
        var dateInput = document.getElementById('requested-date');
        var timeInput = document.getElementById('requested-time');
        if (!dateInput || !timeInput) return;

        function getEarliest() {
            var d = new Date();
            d.setTime(d.getTime() + minHours * 3600 * 1000);
            return d;
        }

        function pad(n) { return n < 10 ? '0' + n : '' + n; }

        function toDateStr(d) {
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function toTimeStr(d) {
            return pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function enforceMin() {
            var earliest = getEarliest();
            dateInput.min = toDateStr(earliest);

            var selectedDate = dateInput.value;
            var earliestDate = toDateStr(earliest);
            if (selectedDate === earliestDate) {
                timeInput.min = toTimeStr(earliest);
            } else {
                timeInput.min = '';
            }
        }

        dateInput.addEventListener('change', enforceMin);
        timeInput.addEventListener('change', function () {
            // Re-validate: if chosen datetime is before earliest, clear time
            var earliest = getEarliest();
            var selectedDate = dateInput.value;
            var earliestDate = toDateStr(earliest);
            if (selectedDate === earliestDate && timeInput.value < toTimeStr(earliest)) {
                timeInput.value = '';
            }
        });

        enforceMin();

        var form = document.getElementById('reschedule-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var selDate = dateInput.value;
                var selTime = timeInput.value || '00:00';
                if (selDate && selTime) {
                    var chosen = new Date(selDate + 'T' + selTime);
                    if (chosen < getEarliest()) {
                        e.preventDefault();
                        alert('The requested new game date/time must be at least ' + minHours + ' hour(s) from now.');
                    }
                }
            });
        }
    })();
    <?php endif; ?>
    </script>
</body>
</html>
