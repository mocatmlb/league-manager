<?php
/**
 * District 8 Travel League - Coaches Schedule Change Request
 */

// Handle both development and production paths
try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php') 
        ? __DIR__ . '/../includes/coach_bootstrap.php'  // Production: includes is one level up
        : __DIR__ . '/../../includes/coach_bootstrap.php';  // Development: includes is two levels up
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Require coach authentication
Auth::requireCoach();

$db = Database::getInstance();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        try {
            $gameId = (int)$_POST['game_id'];
            
            // Get current game and schedule details
            $gameSchedule = $db->fetchOne("
                SELECT g.*, sch.game_date, sch.game_time, sch.location 
                FROM games g 
                LEFT JOIN schedules sch ON g.game_id = sch.game_id 
                WHERE g.game_id = ?", [$gameId]);
            
            if ($gameSchedule) {
                $requestData = [
                    'game_id' => $gameId,
                    'original_date' => $gameSchedule['game_date'],
                    'original_time' => $gameSchedule['game_time'],
                    'original_location' => $gameSchedule['location'],
                    'requested_date' => sanitize($_POST['new_date']),
                    'requested_time' => sanitize($_POST['new_time']),
                    'requested_location' => sanitize($_POST['new_location']),
                    'reason' => sanitize($_POST['reason']),
                    'requested_by' => sanitize($_POST['contact_name']) . ' (' . sanitize($_POST['contact_phone']) . ', ' . sanitize($_POST['contact_email']) . ')',
                    'request_status' => 'Pending',
                    'created_date' => date('Y-m-d H:i:s')
                ];
                
                $requestId = $db->insert('schedule_change_requests', $requestData);
                
                // Update game status to Pending Change
                $db->update('games', [
                    'game_status' => 'Pending Change',
                    'modified_date' => date('Y-m-d H:i:s')
                ], 'game_id = ?', [$gameId]);
                
                logActivity('schedule_change_requested', "Schedule change request submitted for game #{$gameSchedule['game_number']}", null);
                
                // Send email notification for schedule change request
                sendNotification('onScheduleChangeRequest', $gameId, $requestId);
                
                $message = 'Schedule change request submitted successfully! You will receive an email notification when it is reviewed.';
            } else {
                $error = 'Game not found.';
            }
        } catch (Exception $e) {
            $error = 'Error submitting request: ' . $e->getMessage();
        }
    }
}

// Get active games for the current season
$games = $db->fetchAll("
    SELECT g.*, sch.game_date, sch.game_time, sch.location,
           ht.team_name as home_team_name,
           at.team_name as away_team_name,
           s.season_name
    FROM games g
    LEFT JOIN schedules sch ON g.game_id = sch.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    JOIN seasons s ON g.season_id = s.season_id
    WHERE g.game_status = 'Active' 
    AND sch.game_date >= CURDATE()
    AND s.season_status = 'Active'
    AND ht.active_status = 'Active'
    AND at.active_status = 'Active'
    ORDER BY sch.game_date, sch.game_time
");

// Get locations for dropdown
$locations = $db->fetchAll("SELECT DISTINCT location_name FROM locations ORDER BY location_name");

$pageTitle = "Schedule Change Request - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php include '../../includes/nav.php'; ?>

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

                <!-- Information Card -->
                <div class="card mb-4">
                    <div class="card-body bg-light">
                        <h5><i class="fas fa-info-circle text-info"></i> Important Information</h5>
                        <ul class="mb-0">
                            <li>Schedule change requests require administrative approval</li>
                            <li>Changes will <strong>NOT</strong> take effect until approved</li>
                            <li>You will receive email notification when your request is reviewed</li>
                            <li>Submit requests as early as possible to allow time for processing</li>
                            <li>All fields marked with * are required</li>
                        </ul>
                    </div>
                </div>

                <!-- Request Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Submit Schedule Change Request</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            
                            <!-- Game Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Game *</label>
                                <select name="game_id" class="form-select" required onchange="updateGameDetails()">
                                    <option value="">Choose the game you want to change...</option>
                                    <?php foreach ($games as $game): ?>
                                        <option value="<?php echo $game['game_id']; ?>" 
                                                data-date="<?php echo $game['game_date']; ?>"
                                                data-time="<?php echo $game['game_time']; ?>"
                                                data-location="<?php echo sanitize($game['location']); ?>">
                                            Game #<?php echo sanitize($game['game_number']); ?> - 
                                            <?php echo formatDate($game['game_date']); ?> at <?php echo formatTime($game['game_time']); ?> - 
                                            <?php echo sanitize($game['away_team_name']); ?> @ <?php echo sanitize($game['home_team_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Current Game Details (will be populated by JavaScript) -->
                            <div id="currentGameDetails" class="mb-4" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5>Current Game Details</h5>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Date:</strong> <span id="currentDate"></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Time:</strong> <span id="currentTime"></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Location:</strong> <span id="currentLocation"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- New Game Details -->
                            <div class="row mb-4">
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
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo sanitize($location['location_name']); ?>">
                                                <?php echo sanitize($location['location_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div class="mb-4">
                                <label class="form-label">Reason for Change *</label>
                                <textarea name="reason" class="form-control" rows="4" required 
                                          placeholder="Please provide a detailed reason for the schedule change request..."></textarea>
                            </div>

                            <!-- Contact Information -->
                            <h5>Contact Information</h5>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Contact Name *</label>
                                    <input type="text" name="contact_name" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Phone *</label>
                                    <input type="tel" name="contact_phone" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact Email *</label>
                                    <input type="email" name="contact_email" class="form-control" required>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <p><small>Version <?php echo APP_VERSION; ?></small></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateGameDetails() {
            const gameSelect = document.querySelector('select[name="game_id"]');
            const selectedOption = gameSelect.options[gameSelect.selectedIndex];
            
            if (selectedOption.value) {
                const date = selectedOption.getAttribute('data-date');
                const time = selectedOption.getAttribute('data-time');
                const location = selectedOption.getAttribute('data-location');
                
                document.getElementById('currentDate').textContent = new Date(date).toLocaleDateString();
                document.getElementById('currentTime').textContent = new Date('1970-01-01T' + time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                document.getElementById('currentLocation').textContent = location;
                
                document.getElementById('currentGameDetails').style.display = 'block';
            } else {
                document.getElementById('currentGameDetails').style.display = 'none';
            }
        }
    </script>
</body>
</html>
