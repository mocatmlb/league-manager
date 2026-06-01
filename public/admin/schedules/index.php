<?php
/**
 * District 8 Travel League - Schedule Management
 */

// Robust EnvLoader include: locate includes/env-loader.php regardless of layout
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

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
                                switch ($action) {
            case 'edit_request':
                try {
                    $requestId = (int)$_POST['request_id'];
                    $existingRequest = $db->fetchOne("SELECT request_type FROM schedule_change_requests WHERE request_id = ?", [$requestId]);
                    $isPostponement = $existingRequest && ($existingRequest['request_type'] ?? '') === 'Postponement';
                    
                    $updateData = ['reason' => sanitize($_POST['reason']), 'modified_date' => date('Y-m-d H:i:s')];
                    if (!$isPostponement) {
                        $updateData['requested_date'] = sanitize($_POST['requested_date']);
                        $updateData['requested_time'] = sanitize($_POST['requested_time']);
                        $updateData['requested_location'] = sanitize($_POST['requested_location']);
                    }
                    
                    $db->update('schedule_change_requests', $updateData, 'request_id = :request_id', ['request_id' => $requestId]);
                    
                    Logger::info("Schedule change request updated", [
                        'request_id' => $requestId,
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    
                    $message = 'Schedule change request updated successfully!';
                } catch (Exception $e) {
                    Logger::error("Schedule request update failed", [
                        'request_id' => $requestId ?? 'unknown',
                        'error' => $e->getMessage(),
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $error = 'Error updating schedule request: ' . $e->getMessage();
                }
                break;
                
            case 'approve_change':
                try {
                    $requestId = (int)$_POST['request_id'];

                    // Get the change request details
                    $request = $db->fetchOne("SELECT * FROM schedule_change_requests WHERE request_id = ?", [$requestId]);

                    if ($request) {
                        $db->beginTransaction();

                        if ($request['request_type'] === 'Postponement') {
                            // Postponement approval — mark game Postponed, no date/schedule change
                            $db->update('games', [
                                'game_status'   => 'Postponed',
                                'modified_date' => date('Y-m-d H:i:s'),
                            ], "game_id = ? AND game_status NOT IN ('Completed', 'Cancelled')", [$request['game_id']]);

                            $db->update('schedule_history', ['is_current' => 0],
                                'game_id = :gid AND is_current = 1', ['gid' => $request['game_id']]);

                            $maxVersion  = $db->fetchOne("SELECT MAX(version_number) as max_ver FROM schedule_history WHERE game_id = ?", [$request['game_id']]);
                            $nextVersion = ($maxVersion['max_ver'] ?? 0) + 1;

                            $db->insert('schedule_history', [
                                'game_id'           => $request['game_id'],
                                'version_number'    => $nextVersion,
                                'schedule_type'     => 'Changed',
                                'game_date'         => $request['original_date'],
                                'game_time'         => $request['original_time'],
                                'location'          => $request['original_location'],
                                'change_request_id' => $requestId,
                                'created_by_type'   => 'Admin',
                                'created_by_id'     => $currentUser['id'],
                                'is_current'        => 1,
                                'notes'             => 'Postponement approved via request #' . $requestId .
                                                       (($_POST['admin_notes'] ?? '') ? '. Admin notes: ' . sanitize($_POST['admin_notes']) : ''),
                            ]);

                            $db->update('schedule_change_requests', [
                                'request_status' => 'Approved',
                                'reviewed_by'    => $currentUser['id'],
                                'reviewed_at'    => date('Y-m-d H:i:s'),
                                'review_notes'   => sanitize($_POST['admin_notes'] ?? ''),
                            ], 'request_id = :rid', ['rid' => $requestId]);

                            $db->commit();

                            logActivity('postponement_approved', "Postponement request #{$requestId} approved", $currentUser['id']);
                            // Story 18-2: sendNotification('onSchedulePostponed') fires here
                            $message = 'Postponement approved. Game is now marked Postponed.';

                        } else {
                            // Reschedule approval — existing logic unchanged
                            $db->update('schedule_history', [
                                'is_current' => 0
                            ], 'game_id = :game_id AND is_current = 1', ['game_id' => $request['game_id']]);

                            $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_ver FROM schedule_history WHERE game_id = ?", [$request['game_id']]);
                            $nextVersion = ($maxVersion['max_ver'] ?? 0) + 1;

                            $db->insert('schedule_history', [
                                'game_id' => $request['game_id'],
                                'version_number' => $nextVersion,
                                'schedule_type' => 'Changed',
                                'game_date' => $request['requested_date'],
                                'game_time' => $request['requested_time'],
                                'location' => $request['requested_location'],
                                'change_request_id' => $requestId,
                                'created_by_type' => 'Admin',
                                'created_by_id' => $currentUser['id'],
                                'is_current' => 1,
                                'notes' => 'Schedule changed via request #' . $requestId .
                                          (($_POST['admin_notes'] ?? '') ? '. Admin notes: ' . sanitize($_POST['admin_notes']) : '')
                            ]);

                            $db->update('schedules', [
                                'game_date' => $request['requested_date'],
                                'game_time' => $request['requested_time'],
                                'location' => $request['requested_location'],
                                'modified_date' => date('Y-m-d H:i:s')
                            ], 'game_id = :game_id', ['game_id' => $request['game_id']]);

                            $db->update('schedule_change_requests', [
                                'request_status' => 'Approved',
                                'reviewed_by' => $currentUser['id'],
                                'reviewed_at' => date('Y-m-d H:i:s'),
                                'review_notes' => sanitize($_POST['admin_notes'] ?? '')
                            ], 'request_id = :request_id', ['request_id' => $requestId]);

                            $db->update('games', [
                                'game_status' => 'Scheduled',
                                'modified_date' => date('Y-m-d H:i:s')
                            ], "game_id = ? AND game_status NOT IN ('Completed', 'Cancelled')", [$request['game_id']]);

                            $db->commit();

                            logActivity('schedule_change_approved', "Schedule change request #{$requestId} approved - Version {$nextVersion} created", $currentUser['id']);

                            sendNotification('onScheduleChangeApprove', $request['game_id'], $requestId);

                            $message = 'Schedule change approved successfully! New schedule version created.';
                        }
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error approving change: ' . $e->getMessage();
                }
                break;
                
            case 'deny_change':
                try {
                    $requestId = (int)$_POST['request_id'];

                    // Load and validate current request state to avoid duplicate/incorrect deny notifications
                    $request = $db->fetchOne("SELECT game_id, request_status FROM schedule_change_requests WHERE request_id = ?", [$requestId]);
                    if (!$request) {
                        throw new Exception('Schedule change request not found.');
                    }
                    if (($request['request_status'] ?? '') !== 'Pending') {
                        throw new Exception('Only pending schedule change requests can be denied.');
                    }

                    $updateStmt = $db->update('schedule_change_requests', [
                        'request_status' => 'Denied',
                        'reviewed_by' => $currentUser['id'],
                        'reviewed_at' => date('Y-m-d H:i:s'),
                        'review_notes' => sanitize($_POST['admin_notes'] ?? '')
                    ], 'request_id = :request_id AND request_status = :pending_status', [
                        'request_id' => $requestId,
                        'pending_status' => 'Pending'
                    ]);
                    if ($updateStmt->rowCount() === 0) {
                        throw new Exception('Schedule change request is no longer pending.');
                    }

                    $pendingCount = $db->fetchOne("
                        SELECT COUNT(*) as count 
                        FROM schedule_change_requests 
                        WHERE game_id = ? AND request_status = 'Pending' AND request_id != ?
                    ", [$request['game_id'], $requestId]);
                    
                    // If no other pending requests, update game status back to Scheduled
                    if ($pendingCount && $pendingCount['count'] == 0) {
                        $gameStatus = $db->fetchOne("SELECT game_status FROM games WHERE game_id = ?", [$request['game_id']]);
                        if ($gameStatus && $gameStatus['game_status'] === 'Pending Change') {
                            $db->update('games', [
                                'game_status' => 'Scheduled',
                                'modified_date' => date('Y-m-d H:i:s')
                            ], "game_id = ? AND game_status NOT IN ('Completed', 'Cancelled')", [$request['game_id']]);
                        }
                    }

                    sendNotification('onScheduleChangeDeny', $request['game_id'], $requestId);

                    logActivity('schedule_change_denied', "Schedule change request #{$requestId} denied", $currentUser['id']);
                    $message = 'Schedule change denied.';
                } catch (Exception $e) {
                    $error = 'Error denying change: ' . $e->getMessage();
                }
                break;

            case 'admin_direct_change':
                try {
                    $gameId = (int) ($_POST['game_id'] ?? 0);
                    $newDate = sanitize($_POST['new_date'] ?? '');
                    $newTime = sanitize($_POST['new_time'] ?? '');
                    $newLocation = sanitize($_POST['new_location'] ?? '');
                    $changeReason = sanitize($_POST['change_reason'] ?? '');
                    $gameNotes = trim($_POST['game_notes'] ?? '') ?: null;

                    if ($gameId <= 0 || empty($newDate) || empty($newTime) || empty($newLocation)) {
                        throw new Exception('All fields are required.');
                    }

                    $db->beginTransaction();

                    $originalSchedule = $db->fetchOne("SELECT game_date, game_time, location FROM schedules WHERE game_id = ?", [$gameId]);

                    $db->update('schedule_history', [
                        'is_current' => 0
                    ], 'game_id = :game_id AND is_current = 1', ['game_id' => $gameId]);

                    $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_ver FROM schedule_history WHERE game_id = ?", [$gameId]);
                    $nextVersion = ($maxVersion['max_ver'] ?? 0) + 1;

                    $db->insert('schedule_history', [
                        'game_id' => $gameId,
                        'version_number' => $nextVersion,
                        'schedule_type' => 'Changed',
                        'game_date' => $newDate,
                        'game_time' => $newTime,
                        'location' => $newLocation,
                        'created_by_type' => 'Admin',
                        'created_by_id' => $currentUser['id'],
                        'is_current' => 1,
                        'notes' => 'Admin direct change' . ($changeReason ? '. Reason: ' . $changeReason : ''),
                        'user_notes' => $gameNotes,
                    ]);

                    $newRequestId = $db->insert('schedule_change_requests', [
                        'game_id'            => $gameId,
                        'requested_by'       => $currentUser['username'] ?? 'Admin',
                        'request_type'       => 'Reschedule',
                        'original_date'      => $originalSchedule['game_date'] ?? null,
                        'original_time'      => $originalSchedule['game_time'] ?? null,
                        'original_location'  => $originalSchedule['location'] ?? null,
                        'requested_date'     => $newDate,
                        'requested_time'     => $newTime,
                        'requested_location' => $newLocation,
                        'reason'             => $changeReason ?: null,
                        'request_status'     => 'Approved',
                        'reviewed_by'        => $currentUser['id'],
                        'reviewed_at'        => date('Y-m-d H:i:s'),
                    ]);

                    $db->update('schedules', [
                        'game_date' => $newDate,
                        'game_time' => $newTime,
                        'location' => $newLocation,
                        'modified_date' => date('Y-m-d H:i:s')
                    ], 'game_id = :game_id', ['game_id' => $gameId]);

                    $gameStatus = $db->fetchOne("SELECT game_status FROM games WHERE game_id = ?", [$gameId]);
                    if ($gameStatus && $gameStatus['game_status'] === 'Pending Change') {
                        $db->update('games', [
                            'game_status' => 'Scheduled',
                            'modified_date' => date('Y-m-d H:i:s')
                        ], 'game_id = ?', [$gameId]);
                    }

                    $db->commit();

                    logActivity('schedule_direct_change', "Admin direct schedule change for game #$gameId - Version $nextVersion created", $currentUser['id']);

                    sendNotification('onScheduleChangeApprove', $gameId, $newRequestId);

                    $message = 'Schedule changed successfully! New version created.';
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error processing schedule change: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all games for admin direct change
$allGames = $db->fetchAll("
    SELECT g.game_id, g.game_number,
           ht.team_name as home_team_name,
           at.team_name as away_team_name,
           s.game_date, s.game_time, s.location
    FROM games g
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    LEFT JOIN schedules s ON g.game_id = s.game_id
    WHERE g.game_status NOT IN ('Cancelled', 'Completed')
    ORDER BY s.game_date ASC, s.game_time ASC, s.location ASC
");

// Get locations for admin direct change
$locations = $db->fetchAll("SELECT location_name FROM locations WHERE active_status = 'Active' ORDER BY location_name");

// Get pending schedule change requests
$pendingRequests = $db->fetchAll("
    SELECT scr.*, g.game_number, s.game_date, s.game_time, s.location,
           ht.team_name as home_team_name,
           at.team_name as away_team_name,
           (SELECT COUNT(*) FROM schedule_history sh WHERE sh.game_id = g.game_id AND sh.user_notes IS NOT NULL AND sh.user_notes != '') as has_notes
    FROM schedule_change_requests scr
    JOIN games g ON scr.game_id = g.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    LEFT JOIN schedules s ON g.game_id = s.game_id
    WHERE scr.request_status = 'Pending'
    ORDER BY scr.created_date DESC
");

// Get all schedule change requests (for history)
$allRequests = $db->fetchAll("
    SELECT scr.*, g.game_number, s.game_date, s.game_time, s.location,
           ht.team_name as home_team_name,
           at.team_name as away_team_name,
           COALESCE(au.username, CONCAT(u.first_name, ' ', u.last_name)) as reviewed_by_username,
           (SELECT COUNT(*) FROM schedule_history sh WHERE sh.game_id = g.game_id AND sh.user_notes IS NOT NULL AND sh.user_notes != '') as has_notes
    FROM schedule_change_requests scr
    JOIN games g ON scr.game_id = g.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    LEFT JOIN schedules s ON g.game_id = s.game_id
    LEFT JOIN admin_users au ON scr.reviewed_by = au.id
    LEFT JOIN users u ON scr.reviewed_by = u.id AND au.id IS NULL
    ORDER BY scr.created_date DESC
    LIMIT 50
");

$pageTitle = "Schedule Management - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/schedules -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/schedules -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="mb-0">Schedule Management</h1>
                    <button class="btn btn-primary" onclick="showDirectChangeModal()">
                        <i class="fas fa-calendar-alt"></i> Process Schedule Change
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Pending Requests -->
                <?php if (!empty($pendingRequests)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h3><i class="fas fa-clock"></i> Pending Schedule Change Requests (<?php echo count($pendingRequests); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($pendingRequests as $request): ?>
                        <?php $isPostponement = ($request['request_type'] ?? '') === 'Postponement'; ?>
                        <div class="card mb-3 <?php echo $isPostponement ? 'border-warning' : ''; ?>">
                            <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>Game #<?php echo sanitize($request['game_number']); ?>:
                                        <?php echo sanitize($request['away_team_name']); ?> @ <?php echo sanitize($request['home_team_name']); ?>
                                        <span class="badge <?php echo $isPostponement ? 'bg-warning text-dark' : 'bg-secondary'; ?> ms-2">
                                            <?php echo sanitize($request['request_type'] ?? 'Reschedule'); ?>
                                        </span>
                                        <?php if (!empty($request['has_notes'])): ?>
                                            <i class="fas fa-sticky-note text-warning ms-1" title="This game has notes — view in Games"></i>
                                        <?php endif; ?>
                                    </h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Current:</strong><br>
                                            Date: <?php echo formatDate($request['original_date']); ?><br>
                                            Time: <?php echo formatTime($request['original_time']); ?><br>
                                            Location: <?php echo sanitize($request['original_location']); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Requested:</strong><br>
                                            <?php if (!$isPostponement): ?>
                                            Date: <?php echo formatDate($request['requested_date']); ?><br>
                                            Time: <?php echo formatTime($request['requested_time']); ?><br>
                                            Location: <?php echo sanitize($request['requested_location']); ?>
                                            <?php else: ?>
                                            <em class="text-muted">Postponement — no new date requested</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($request['reason']): ?>
                                    <div class="mt-2">
                                        <strong>Reason:</strong> <?php echo sanitize($request['reason']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <small class="text-muted">
                                        Requested on <?php echo formatDate($request['created_date'], 'M j, Y g:i A'); ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($isPostponement): ?>
                                    <p class="text-warning-emphasis small mb-2">
                                        <i class="fas fa-clock"></i> Approving will mark this game as <strong>Postponed</strong> — no date or schedule change will be made.
                                    </p>
                                    <?php endif; ?>
                                    <button class="btn btn-success btn-sm mb-2" onclick="approveRequest(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm mb-2" onclick="denyRequest(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-times"></i> Deny
                                    </button>
                                </div>
                            </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No pending schedule change requests.
                </div>
                <?php endif; ?>

                <!-- Request History -->
                <div class="card">
                    <div class="card-header">
                        <h3>Request History</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                        <table id="requestsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Game #</th>
                                    <th>Teams</th>
                                    <th>Current Schedule</th>
                                    <th>Requested Change</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRequests as $request): ?>
                                <tr>
                                    <td><strong>#<?php echo $request['request_id'] ?? 'N/A'; ?></strong></td>
                                    <td>
                                        <?php echo sanitize($request['game_number']); ?>
                                        <?php if (!empty($request['has_notes'])): ?>
                                            <i class="fas fa-sticky-note text-warning ms-1" title="This game has notes — view in Games"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize($request['away_team_name']) . ' @ ' . sanitize($request['home_team_name']); ?></td>
                                    <td data-order="<?php echo htmlspecialchars($request['original_date'] ?? '9999-12-31', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php if ($request['original_date'] && $request['original_time']): ?>
                                            <strong><?php echo date('n/j/Y', strtotime($request['original_date'])); ?> @ <?php echo date('g:i A', strtotime($request['original_time'])); ?></strong><br>
                                            <small class="text-muted"><?php echo sanitize($request['original_location']); ?></small>
                                        <?php else: ?>
                                            <em class="text-muted">Not scheduled</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['requested_date'] && $request['requested_time']): ?>
                                            <strong><?php echo date('n/j/Y', strtotime($request['requested_date'])); ?> @ <?php echo date('g:i A', strtotime($request['requested_time'])); ?></strong><br>
                                            <small class="text-muted"><?php echo sanitize($request['requested_location']); ?></small>
                                        <?php else: ?>
                                            <em class="text-muted">No change requested</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $request['request_status'] === 'Approved' ? 'success' : 
                                                ($request['request_status'] === 'Denied' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $request['request_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['request_status'] === 'Pending'): ?>
                                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editRequest(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-success me-1" onclick="approveRequest(<?php echo $request['request_id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="denyRequest(<?php echo $request['request_id']; ?>)">
                                                <i class="fas fa-times"></i> Deny
                                            </button>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                <?php echo $request['reviewed_by_username'] ?? 'System'; ?><br>
                                                <?php echo $request['reviewed_at'] ? date('n/j/Y g:i A', strtotime($request['reviewed_at'])) : 'N/A'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div><!-- /.table-responsive -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Request Modal -->
    <div class="modal fade" id="editRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Schedule Change Request</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_request">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="request_id" id="editRequestId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Game Information</h6>
                                <p><strong>Game #:</strong> <span id="editGameNumber"></span></p>
                                <p><strong>Teams:</strong> <span id="editTeams"></span></p>
                                <p><strong>Current Schedule:</strong> <span id="editCurrentSchedule"></span></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Request Details</h6>
                                <p><strong>Requested by:</strong> <span id="editRequestedBy"></span></p>
                                <p><strong>Request Date:</strong> <span id="editRequestDate"></span></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row" id="editRequestDateFields">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Requested Date *</label>
                                    <input type="date" name="requested_date" id="editRequestedDate" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Requested Time *</label>
                                    <input type="time" name="requested_time" id="editRequestedTime" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Requested Location *</label>
                                    <input type="text" name="requested_location" id="editRequestedLocation" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Change *</label>
                            <textarea name="reason" id="editReason" class="form-control" rows="3" required placeholder="Explain why this schedule change is needed..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Approve Schedule Change</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_change">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="request_id" id="approveRequestId">
                        
                        <p>Are you sure you want to approve this request? For reschedule requests the game schedule will be updated; for postponement requests the game will be marked Postponed.</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes (Optional)</label>
                            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add any notes about this approval..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Change</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Direct Schedule Change Modal -->
    <div class="modal fade" id="directChangeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Process Schedule Change</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="admin_direct_change">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label class="form-label">Select Game *</label>
                            <select name="game_id" id="directChangeGameId" class="form-select" required>
                                <option value="">— choose a game —</option>
                                <?php foreach ($allGames as $g): ?>
                                <option value="<?php echo (int) $g['game_id']; ?>"
                                    data-date="<?php echo htmlspecialchars($g['game_date'] ?? ''); ?>"
                                    data-time="<?php echo htmlspecialchars($g['game_time'] ?? ''); ?>"
                                    data-location="<?php echo htmlspecialchars($g['location'] ?? ''); ?>">
                                    Game #<?php echo htmlspecialchars($g['game_number']); ?> &mdash;
                                    <?php echo htmlspecialchars(strtoupper($g['away_team_name'])); ?> @
                                    <?php echo htmlspecialchars(strtoupper($g['home_team_name'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="card bg-light mb-3" id="directChangeCurrentInfo" style="display:none">
                            <div class="card-body">
                                <h6 class="card-title">Current Schedule</h6>
                                <div class="row">
                                    <div class="col-md-4"><strong>Date:</strong> <span id="directCurrentDate"></span></div>
                                    <div class="col-md-4"><strong>Time:</strong> <span id="directCurrentTime"></span></div>
                                    <div class="col-md-4"><strong>Location:</strong> <span id="directCurrentLocation"></span></div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">New Date *</label>
                                <input type="date" name="new_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Time *</label>
                                <input type="time" name="new_time" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Location *</label>
                                <select name="new_location" class="form-select" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location_name']); ?>">
                                        <?php echo htmlspecialchars($loc['location_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason for Change</label>
                            <textarea name="change_reason" class="form-control" rows="3" placeholder="Optional reason for this schedule change..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes <small class="text-muted">(admin-visible only, optional)</small></label>
                            <textarea name="game_notes" class="form-control" rows="3" placeholder="Enter any ancillary notes about this game..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Apply Change
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deny Modal -->
    <div class="modal fade" id="denyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Deny Schedule Change</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deny_change">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="request_id" id="denyRequestId">
                        
                        <p>Are you sure you want to deny this schedule change request?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Denial</label>
                            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Please provide a reason for denying this request..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Deny Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#requestsTable').DataTable({
                order: [[0, 'desc']],
                columnDefs: [{ type: 'num', targets: 0 }],
                pageLength: 25
            });
        });
        
        function approveRequest(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            var approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
            approveModal.show();
        }
        
        function denyRequest(requestId) {
            document.getElementById('denyRequestId').value = requestId;
            var denyModal = new bootstrap.Modal(document.getElementById('denyModal'));
            denyModal.show();
        }
        
        function editRequest(request) {
            // Set hidden fields
            document.getElementById('editRequestId').value = request.request_id;
            
            // Set game information
            document.getElementById('editGameNumber').textContent = request.game_number;
            document.getElementById('editTeams').textContent = request.away_team_name + ' @ ' + request.home_team_name;
            
            // Format current schedule
            let currentSchedule = 'Not scheduled';
            if (request.game_date && request.game_time) {
                const gameDate = new Date(request.game_date + 'T' + request.game_time);
                currentSchedule = gameDate.toLocaleDateString('en-US', {month: 'numeric', day: 'numeric', year: 'numeric'}) + 
                                 ' @ ' + gameDate.toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true});
                if (request.location) {
                    currentSchedule += ' - ' + request.location;
                }
            }
            document.getElementById('editCurrentSchedule').textContent = currentSchedule;
            
            // Set request details
            document.getElementById('editRequestedBy').textContent = request.requested_by || 'Unknown';
            document.getElementById('editRequestDate').textContent = new Date(request.created_date).toLocaleDateString('en-US', {
                month: 'numeric', day: 'numeric', year: 'numeric', 
                hour: 'numeric', minute: '2-digit', hour12: true
            });
            
            // Hide date fields for postponement requests (no date change involved)
            var isPostponement = (request.request_type || '') === 'Postponement';
            var dateFields = document.getElementById('editRequestDateFields');
            if (dateFields) {
                dateFields.style.display = isPostponement ? 'none' : '';
            }
            
            // Set form fields
            document.getElementById('editRequestedDate').value = request.requested_date;
            document.getElementById('editRequestedTime').value = request.requested_time;
            document.getElementById('editRequestedLocation').value = request.requested_location || '';
            document.getElementById('editReason').value = request.reason || '';
            
            // Show modal
            var editModal = new bootstrap.Modal(document.getElementById('editRequestModal'));
            editModal.show();
        }

        function showDirectChangeModal() {
            var modal = new bootstrap.Modal(document.getElementById('directChangeModal'));
            modal.show();
        }

        document.addEventListener('change', function(e) {
            if (e.target.id === 'directChangeGameId') {
                var opt = e.target.options[e.target.selectedIndex];
                var panel = document.getElementById('directChangeCurrentInfo');
                if (opt && opt.value) {
                    document.getElementById('directCurrentDate').textContent = opt.dataset.date || '';
                    document.getElementById('directCurrentTime').textContent = opt.dataset.time || '';
                    document.getElementById('directCurrentLocation').textContent = opt.dataset.location || '';
                    panel.style.display = '';
                } else {
                    panel.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
