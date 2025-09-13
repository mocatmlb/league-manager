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
                    
                    // Update the schedule change request
                    $updateData = [
                        'requested_date' => sanitize($_POST['requested_date']),
                        'requested_time' => sanitize($_POST['requested_time']),
                        'requested_location' => sanitize($_POST['requested_location']),
                        'reason' => sanitize($_POST['reason']),
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
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
                        
                        // Mark current schedule version as no longer current
                        $db->update('schedule_history', [
                            'is_current' => 0
                        ], 'game_id = :game_id AND is_current = 1', ['game_id' => $request['game_id']]);
                        
                        // Get the next version number
                        $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_ver FROM schedule_history WHERE game_id = ?", [$request['game_id']]);
                        $nextVersion = ($maxVersion['max_ver'] ?? 0) + 1;
                        
                        // Create new schedule history entry
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
                        
                        // Update the main schedules table for backward compatibility
                        $db->update('schedules', [
                            'game_date' => $request['requested_date'],
                            'game_time' => $request['requested_time'],
                            'location' => $request['requested_location'],
                            'modified_date' => date('Y-m-d H:i:s')
                        ], 'game_id = :game_id', ['game_id' => $request['game_id']]);
                        
                        // Update request status
                        $db->update('schedule_change_requests', [
                            'request_status' => 'Approved',
                            'reviewed_by' => $currentUser['id'],
                            'reviewed_at' => date('Y-m-d H:i:s'),
                            'review_notes' => sanitize($_POST['admin_notes'] ?? '')
                        ], 'request_id = :request_id', ['request_id' => $requestId]);
                        
                        // Update game status to Scheduled (if not Cancelled or Completed)
                        $gameStatus = $db->fetchOne("SELECT game_status FROM games WHERE game_id = ?", [$request['game_id']]);
                        if ($gameStatus && $gameStatus['game_status'] === 'Pending Change') {
                            $db->update('games', [
                                'game_status' => 'Scheduled',
                                'modified_date' => date('Y-m-d H:i:s')
                            ], 'game_id = ?', [$request['game_id']]);
                        }
                        
                        $db->commit();
                        
                        logActivity('schedule_change_approved', "Schedule change request #{$requestId} approved - Version {$nextVersion} created", $currentUser['id']);
                        
                        // Send email notification for schedule change approval
                        sendNotification('onScheduleChangeApprove', $request['game_id'], $requestId);
                        
                        $message = 'Schedule change approved successfully! New schedule version created.';
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error approving change: ' . $e->getMessage();
                }
                break;
                
            case 'deny_change':
                try {
                    $requestId = (int)$_POST['request_id'];
                    
                    $db->update('schedule_change_requests', [
                        'request_status' => 'Denied',
                        'reviewed_by' => $currentUser['id'],
                        'reviewed_at' => date('Y-m-d H:i:s'),
                        'review_notes' => sanitize($_POST['admin_notes'] ?? '')
                    ], 'request_id = :request_id', ['request_id' => $requestId]);
                    
                    // Get the game ID from the request
                    $request = $db->fetchOne("SELECT game_id FROM schedule_change_requests WHERE request_id = ?", [$requestId]);
                    
                    // Check if there are any other pending requests for this game
                    if ($request) {
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
                                ], 'game_id = ?', [$request['game_id']]);
                            }
                        }
                    }
                    
                    logActivity('schedule_change_denied', "Schedule change request #{$requestId} denied", $currentUser['id']);
                    $message = 'Schedule change denied.';
                } catch (Exception $e) {
                    $error = 'Error denying change: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get pending schedule change requests
$pendingRequests = $db->fetchAll("
    SELECT scr.*, g.game_number, s.schedule_id, s.game_date, s.game_time, s.location,
           ht.team_name as home_team_name,
           at.team_name as away_team_name
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
    SELECT scr.*, g.game_number, s.schedule_id, s.game_date, s.game_time, s.location,
           ht.team_name as home_team_name,
           at.team_name as away_team_name,
           au.username as reviewed_by_username
    FROM schedule_change_requests scr
    JOIN games g ON scr.game_id = g.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    LEFT JOIN schedules s ON g.game_id = s.game_id
    LEFT JOIN admin_users au ON scr.reviewed_by = au.id
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
                <h1>Schedule Management</h1>

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
                        <div class="border rounded p-3 mb-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>Game #<?php echo sanitize($request['game_number']); ?>: 
                                        <?php echo sanitize($request['away_team_name']); ?> @ <?php echo sanitize($request['home_team_name']); ?>
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
                                            Date: <?php echo formatDate($request['requested_date']); ?><br>
                                            Time: <?php echo formatTime($request['requested_time']); ?><br>
                                            Location: <?php echo sanitize($request['requested_location']); ?>
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
                                    <button class="btn btn-success btn-sm mb-2" onclick="approveRequest(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm mb-2" onclick="denyRequest(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-times"></i> Deny
                                    </button>
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
                        <table id="requestsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Schedule ID</th>
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
                                    <td><strong>#<?php echo $request['schedule_id'] ?? 'N/A'; ?></strong></td>
                                    <td><?php echo sanitize($request['game_number']); ?></td>
                                    <td><?php echo sanitize($request['away_team_name']) . ' @ ' . sanitize($request['home_team_name']); ?></td>
                                    <td>
                                        <?php if ($request['game_date'] && $request['game_time']): ?>
                                            <strong><?php echo date('n/j/Y', strtotime($request['game_date'])); ?> @ <?php echo date('g:i A', strtotime($request['game_time'])); ?></strong><br>
                                            <small class="text-muted"><?php echo sanitize($request['location']); ?></small>
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
                        
                        <div class="row">
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
                        
                        <p>Are you sure you want to approve this schedule change? The game schedule will be updated immediately.</p>
                        
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
                order: [[2, 'desc']],
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
            
            // Set form fields
            document.getElementById('editRequestedDate').value = request.requested_date;
            document.getElementById('editRequestedTime').value = request.requested_time;
            document.getElementById('editRequestedLocation').value = request.requested_location || '';
            document.getElementById('editReason').value = request.reason || '';
            
            // Show modal
            var editModal = new bootstrap.Modal(document.getElementById('editRequestModal'));
            editModal.show();
        }
    </script>
</body>
</html>
