<?php
/**
 * District 8 Travel League - Comprehensive Games Management
 */

require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.php';

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Handle AJAX requests for schedule history
if (isset($_GET['action']) && $_GET['action'] === 'get_change_history' && isset($_GET['game_id'])) {
    $gameId = (int)$_GET['game_id'];
    
    // Get complete schedule history from the new schedule_history table
    $scheduleHistory = $db->fetchAll("
        SELECT 
            sh.history_id,
            sh.version_number,
            sh.schedule_type,
            sh.game_date,
            sh.game_time,
            sh.location,
            sh.is_current,
            sh.created_at,
            sh.notes,
            sh.change_request_id,
            scr.request_type,
            scr.requested_by,
            scr.reason,
            scr.request_status,
            scr.reviewed_at,
            scr.review_notes,
            au.username as reviewed_by_username
        FROM schedule_history sh
        LEFT JOIN schedule_change_requests scr ON sh.change_request_id = scr.request_id
        LEFT JOIN admin_users au ON scr.reviewed_by = au.id
        WHERE sh.game_id = ?
        ORDER BY sh.version_number ASC
    ", [$gameId]);
    
    // Add timezone information to each history entry
    foreach ($scheduleHistory as &$history) {
        $history['game_date_tz'] = formatDateForJS($history['game_date']);
        $history['game_time_tz'] = formatDateForJS($history['game_date'] . ' ' . $history['game_time']);
        if ($history['created_at']) {
            $history['created_at_tz'] = formatDateForJS($history['created_at']);
        }
        if ($history['reviewed_at']) {
            $history['reviewed_at_tz'] = formatDateForJS($history['reviewed_at']);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($scheduleHistory);
    exit;
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_game':
                try {
                    $homeTeamId = (int)$_POST['home_team_id'];
                    $awayTeamId = (int)$_POST['away_team_id'];
                    
                    // Validate that both teams are active
                    $homeTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$homeTeamId]);
                    $awayTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$awayTeamId]);
                    
                    if (!$homeTeam || $homeTeam['active_status'] !== 'Active') {
                        throw new Exception('Home team is not active and cannot be assigned to games.');
                    }
                    
                    if (!$awayTeam || $awayTeam['active_status'] !== 'Active') {
                        throw new Exception('Away team is not active and cannot be assigned to games.');
                    }
                    
                    Logger::debug("Creating game with active teams", [
                        'home_team' => $homeTeam['team_name'],
                        'away_team' => $awayTeam['team_name'],
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    
                    $db->beginTransaction();
                    
                    // Create game record
                    $gameData = [
                        'game_number' => sanitize($_POST['game_number']),
                        'season_id' => (int)$_POST['season_id'],
                        'division_id' => (int)$_POST['division_id'],
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                        'game_status' => 'Active',
                        'created_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $gameId = $db->insert('games', $gameData);
                    
                    // Create schedule record
                    $scheduleData = [
                        'game_id' => $gameId,
                        'game_date' => sanitize($_POST['game_date']),
                        'game_time' => sanitize($_POST['game_time']),
                        'location' => sanitize($_POST['location']),
                        'created_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->insert('schedules', $scheduleData);
                    
                    // Create initial schedule history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => 1,
                        'schedule_type' => 'Original',
                        'game_date' => sanitize($_POST['game_date']),
                        'game_time' => sanitize($_POST['game_time']),
                        'location' => sanitize($_POST['location']),
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Initial game schedule'
                    ];
                    
                    $db->insert('schedule_history', $historyData);
                    
                    $db->commit();
                    
                    logActivity('game_created', "Game {$gameData['game_number']} created: {$awayTeam['team_name']} vs {$homeTeam['team_name']}");
                    $message = 'Game created successfully!';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error creating game: ' . $e->getMessage();
                }
                break;
                
            case 'update_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $homeTeamId = (int)$_POST['home_team_id'];
                    $awayTeamId = (int)$_POST['away_team_id'];
                    
                    // Validate that both teams are active
                    $homeTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$homeTeamId]);
                    $awayTeam = $db->fetchOne("SELECT team_name, active_status FROM teams WHERE team_id = ?", [$awayTeamId]);
                    
                    if (!$homeTeam || $homeTeam['active_status'] !== 'Active') {
                        throw new Exception('Home team is not active and cannot be assigned to games.');
                    }
                    
                    if (!$awayTeam || $awayTeam['active_status'] !== 'Active') {
                        throw new Exception('Away team is not active and cannot be assigned to games.');
                    }
                    
                    $db->beginTransaction();
                    
                    // Get current schedule for comparison
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Update game record
                    $gameData = [
                        'season_id' => (int)$_POST['season_id'],
                        'division_id' => (int)$_POST['division_id'],
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                        'game_status' => sanitize($_POST['game_status']),
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    // Check if schedule changed
                    $newDate = sanitize($_POST['game_date']);
                    $newTime = sanitize($_POST['game_time']);
                    $newLocation = sanitize($_POST['location']);
                    
                    $scheduleChanged = ($currentSchedule['game_date'] !== $newDate || 
                                     $currentSchedule['game_time'] !== $newTime || 
                                     $currentSchedule['location'] !== $newLocation);
                    
                    if ($scheduleChanged) {
                        // Update schedule record
                        $scheduleData = [
                            'game_date' => $newDate,
                            'game_time' => $newTime,
                            'location' => $newLocation,
                            'modified_date' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->update('schedules', $scheduleData, 'game_id = ?', [$gameId]);
                        
                        // Mark current history as not current
                        $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);
                        
                        // Get next version number
                        $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                        $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;
                        
                        // Create new schedule history entry
                        $historyData = [
                            'game_id' => $gameId,
                            'version_number' => $nextVersion,
                            'schedule_type' => 'Changed',
                            'game_date' => $newDate,
                            'game_time' => $newTime,
                            'location' => $newLocation,
                            'is_current' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'notes' => 'Schedule updated via admin'
                        ];
                        
                        $db->insert('schedule_history', $historyData);
                    }
                    
                    $db->commit();
                    
                    logActivity('game_updated', "Game updated: {$awayTeam['team_name']} vs {$homeTeam['team_name']}");
                    $message = 'Game updated successfully!';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error updating game: ' . $e->getMessage();
                }
                break;
                
            case 'update_score':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $awayScore = (int)$_POST['away_score'];
                    $homeScore = (int)$_POST['home_score'];
                    
                    $gameData = [
                        'away_score' => $awayScore,
                        'home_score' => $homeScore,
                        'game_status' => 'Completed',
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    logActivity('score_updated', "Score updated for game ID $gameId: Away $awayScore, Home $homeScore");
                    $message = 'Score updated successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error updating score: ' . $e->getMessage();
                }
                break;
                
            case 'cancel_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $reason = sanitize($_POST['cancel_reason']);
                    
                    $db->beginTransaction();
                    
                    // Update game status
                    $gameData = [
                        'game_status' => 'Cancelled',
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    // Mark current history as not current
                    $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);
                    
                    // Get next version number
                    $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                    $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;
                    
                    // Get current schedule for the cancelled entry
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Create cancellation history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => $nextVersion,
                        'schedule_type' => 'Changed',
                        'game_date' => $currentSchedule['game_date'],
                        'game_time' => $currentSchedule['game_time'],
                        'location' => $currentSchedule['location'],
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Game cancelled: ' . $reason
                    ];
                    
                    $db->insert('schedule_history', $historyData);
                    
                    $db->commit();
                    
                    logActivity('game_cancelled', "Game ID $gameId cancelled: $reason");
                    $message = 'Game cancelled successfully!';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error cancelling game: ' . $e->getMessage();
                }
                break;
                
            case 'postpone_game':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $reason = sanitize($_POST['postpone_reason']);
                    
                    $db->beginTransaction();
                    
                    // Update game status
                    $gameData = [
                        'game_status' => 'Postponed',
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('games', $gameData, 'game_id = ?', [$gameId]);
                    
                    // Mark current history as not current
                    $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = ?", [$gameId]);
                    
                    // Get next version number
                    $maxVersion = $db->fetchOne("SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?", [$gameId]);
                    $nextVersion = ($maxVersion['max_version'] ?? 0) + 1;
                    
                    // Get current schedule for the postponed entry
                    $currentSchedule = $db->fetchOne("SELECT * FROM schedules WHERE game_id = ?", [$gameId]);
                    
                    // Create postponement history entry
                    $historyData = [
                        'game_id' => $gameId,
                        'version_number' => $nextVersion,
                        'schedule_type' => 'Changed',
                        'game_date' => $currentSchedule['game_date'],
                        'game_time' => $currentSchedule['game_time'],
                        'location' => $currentSchedule['location'],
                        'is_current' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'notes' => 'Game postponed: ' . $reason
                    ];
                    
                    $db->insert('schedule_history', $historyData);
                    
                    $db->commit();
                    
                    logActivity('game_postponed', "Game ID $gameId postponed: $reason");
                    $message = 'Game postponed successfully!';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Error postponing game: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get games with team names, schedule info, and change counts
$games = $db->fetchAll("
    SELECT g.*, sch.game_date, sch.game_time, sch.location,
           ht.team_name as home_team_name, 
           at.team_name as away_team_name,
           s.season_name,
           d.division_name,
           (SELECT COUNT(*) FROM schedule_change_requests scr WHERE scr.game_id = g.game_id) as change_count
    FROM games g
    JOIN schedules sch ON g.game_id = sch.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    JOIN seasons s ON g.season_id = s.season_id
    LEFT JOIN divisions d ON g.division_id = d.division_id
    ORDER BY sch.game_date DESC, sch.game_time DESC
");

// Get data for dropdowns
$seasons = $db->fetchAll("SELECT * FROM seasons ORDER BY season_year DESC");
$divisions = $db->fetchAll("SELECT * FROM divisions ORDER BY division_name");
$teams = $db->fetchAll("SELECT * FROM teams WHERE active_status = 'Active' ORDER BY team_name");
$locations = $db->fetchAll("SELECT DISTINCT location FROM schedules WHERE location IS NOT NULL AND location != '' ORDER BY location");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games Management - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .game-row {
            cursor: pointer;
        }
        .game-row:hover {
            background-color: #f8f9fa;
        }
        .schedule-history {
            background-color: #f8f9fa;
            border-left: 3px solid #007bff;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .change-count {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../../../includes/admin_nav.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2">
                <?php include '../../../includes/admin_sidebar.php'; ?>
            </div>
            <div class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Games Management</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                        Add New Game
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo sanitize($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo sanitize($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped" id="gamesTable">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Game #</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Away Team</th>
                                <th>Home Team</th>
                                <th>Location</th>
                                <th>Score</th>
                                <th>Changes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr class="game-row" data-game-id="<?php echo $game['game_id']; ?>" onclick="toggleGameDetails(<?php echo $game['game_id']; ?>)">
                                    <td>
                                        <?php
                                        $statusClass = match($game['game_status']) {
                                            'Active' => 'bg-success',
                                            'Completed' => 'bg-primary',
                                            'Cancelled' => 'bg-danger',
                                            'Postponed' => 'bg-warning text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                            <?php echo sanitize($game['game_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo formatTime($game['game_time']); ?></td>
                                    <td><?php echo sanitize($game['away_team_name']); ?></td>
                                    <td><?php echo sanitize($game['home_team_name']); ?></td>
                                    <td><?php echo sanitize($game['location']); ?></td>
                                    <td>
                                        <?php if ($game['game_status'] === 'Completed'): ?>
                                            <?php echo $game['away_score']; ?> - <?php echo $game['home_score']; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="event.stopPropagation(); updateScore(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['away_team_name']); ?>', '<?php echo addslashes($game['home_team_name']); ?>')">
                                                Enter Score
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($game['change_count'] > 0): ?>
                                            <span class="change-count"><?php echo $game['change_count']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="event.stopPropagation(); editGame(<?php echo htmlspecialchars(json_encode($game)); ?>)">
                                                Edit
                                            </button>
                                            <?php if ($game['game_status'] === 'Active'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="event.stopPropagation(); postponeGame(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>')">
                                                    Postpone
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="event.stopPropagation(); cancelGame(<?php echo $game['game_id']; ?>, '<?php echo addslashes($game['game_number']); ?>')">
                                                    Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="details-<?php echo $game['game_id']; ?>" class="schedule-history" style="display: none;">
                                    <td colspan="10">
                                        <div class="p-3">
                                            <h6>Schedule History & Change Details</h6>
                                            <div id="history-content-<?php echo $game['game_id']; ?>">
                                                <div class="text-center">
                                                    <div class="spinner-border spinner-border-sm" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    Loading schedule history...
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Number</label>
                                    <input type="text" name="game_number" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season</label>
                                    <select name="season_id" class="form-select" required>
                                        <option value="">Select Season</option>
                                        <?php foreach ($seasons as $season): ?>
                                            <option value="<?php echo $season['season_id']; ?>">
                                                <?php echo sanitize($season['season_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Division</label>
                                    <select name="division_id" class="form-select">
                                        <option value="">Select Division (Optional)</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['division_id']; ?>">
                                                <?php echo sanitize($division['division_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Away Team</label>
                                    <select name="away_team_id" class="form-select" required>
                                        <option value="">Select Away Team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['team_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Home Team</label>
                                    <select name="home_team_id" class="form-select" required>
                                        <option value="">Select Home Team</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['team_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Date</label>
                                    <input type="date" name="game_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Time</label>
                                    <input type="time" name="game_time" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" list="locationsList" required>
                            <datalist id="locationsList">
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo sanitize($location['location']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Game Modal -->
    <div class="modal fade" id="editGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="editGameId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Number</label>
                                    <input type="text" id="editGameNumber" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season</label>
                                    <select name="season_id" id="editSeasonId" class="form-select" required>
                                        <?php foreach ($seasons as $season): ?>
                                            <option value="<?php echo $season['season_id']; ?>">
                                                <?php echo sanitize($season['season_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Division</label>
                                    <select name="division_id" id="editDivisionId" class="form-select">
                                        <option value="">Select Division (Optional)</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['division_id']; ?>">
                                                <?php echo sanitize($division['division_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Status</label>
                                    <select name="game_status" id="editGameStatus" class="form-select" required>
                                        <option value="Active">Active</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                        <option value="Postponed">Postponed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Away Team</label>
                                    <select name="away_team_id" id="editAwayTeamId" class="form-select" required>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['team_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Home Team</label>
                                    <select name="home_team_id" id="editHomeTeamId" class="form-select" required>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['team_id']; ?>">
                                                <?php echo sanitize($team['team_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Date</label>
                                    <input type="date" name="game_date" id="editGameDate" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Game Time</label>
                                    <input type="time" name="game_time" id="editGameTime" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="editLocation" class="form-control" list="locationsList" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Score Update Modal -->
    <div class="modal fade" id="scoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Game Score</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_score">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="scoreGameId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" id="awayTeamLabel">Away Team Score</label>
                                    <input type="number" name="away_score" id="awayScore" class="form-control" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" id="homeTeamLabel">Home Team Score</label>
                                    <input type="number" name="home_score" id="homeScore" class="form-control" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Score</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Game Modal -->
    <div class="modal fade" id="cancelGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="cancelGameId">
                        
                        <p>Are you sure you want to cancel game <strong id="cancelGameNumber"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Cancellation</label>
                            <textarea name="cancel_reason" class="form-control" rows="3" required 
                                      placeholder="Enter reason for cancelling this game..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Cancel Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Postpone Game Modal -->
    <div class="modal fade" id="postponeGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Postpone Game</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="postpone_game">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="game_id" id="postponeGameId">
                        
                        <p>Are you sure you want to postpone game <strong id="postponeGameNumber"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Postponement</label>
                            <textarea name="postpone_reason" class="form-control" rows="3" required 
                                      placeholder="Enter reason for postponing this game..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Postpone Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/timezone.js"></script>
    
    <?php outputTimezoneJS(); ?>
    
    <script>
        $(document).ready(function() {
            $('#gamesTable').DataTable({
                order: [[2, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [9] } // Actions column
                ]
            });
        });
        
        function toggleGameDetails(gameId) {
            const detailsRow = document.getElementById(`details-${gameId}`);
            const isVisible = detailsRow.style.display !== 'none';
            
            if (isVisible) {
                detailsRow.style.display = 'none';
            } else {
                detailsRow.style.display = 'table-row';
                loadScheduleHistory(gameId);
            }
        }
        
        function loadScheduleHistory(gameId) {
            const contentDiv = document.getElementById(`history-content-${gameId}`);
            
            fetch(`?action=get_change_history&game_id=${gameId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        contentDiv.innerHTML = '<p class="text-muted">No schedule history found.</p>';
                        return;
                    }
                    
                    let html = '<div class="table-responsive"><table class="table table-sm">';
                    html += '<thead><tr><th>Version</th><th>Type</th><th>Date & Time</th><th>Location</th><th>Change Details</th></tr></thead><tbody>';
                    
                    const originalSchedule = data.find(h => h.schedule_type === 'Original');
                    const currentSchedule = data.find(h => h.is_current == 1);
                    
                    data.forEach(history => {
                        const typeClass = history.schedule_type === 'Original' ? 'success' : 'info';
                        const currentBadge = history.is_current == 1 ? ' <span class="badge bg-primary">Current</span>' : '';
                        
                        html += `<tr>`;
                        html += `<td><strong>v${history.version_number}</strong>${currentBadge}</td>`;
                        
                        const typeHtml = `<span class="badge bg-${typeClass}">${history.schedule_type}</span>`;
                        html += `<td>${typeHtml}</td>`;
                        
                        // Date & Time
                        html += `<td><strong>${formatDateTZ(history.game_date)}</strong><br>`;
                        html += `<small>${formatTimeTZ(history.game_time)}</small></td>`;
                        
                        // Location
                        html += `<td>${history.location || 'TBD'}</td>`;
                        
                        // Change Details
                        let changeDetails = '';
                        if (history.notes) {
                            changeDetails += `<strong>Notes:</strong> ${history.notes}<br>`;
                        }
                        if (history.change_request_id) {
                            if (history.requested_by) {
                                changeDetails += `<strong>Requested by:</strong> ${history.requested_by}<br>`;
                            }
                            if (history.reason) {
                                changeDetails += `<strong>Reason:</strong> ${history.reason}<br>`;
                            }
                            if (history.request_status) {
                                const statusClass = history.request_status === 'Approved' ? 'success' : 
                                                  history.request_status === 'Denied' ? 'danger' : 'warning';
                                changeDetails += `<strong>Status:</strong> <span class="badge bg-${statusClass}">${history.request_status}</span><br>`;
                            }
                            if (history.reviewed_at && history.reviewed_by_username) {
                                changeDetails += `<strong>Reviewed by:</strong> ${history.reviewed_by_username}<br>`;
                                changeDetails += `<strong>On:</strong> ${formatDateTZ(history.reviewed_at)}`;
                            }
                        }
                        html += `<td><small>${changeDetails}</small></td>`;
                        html += `</tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    
                    // Add summary section
                    if (originalSchedule && currentSchedule && originalSchedule.history_id !== currentSchedule.history_id) {
                        html += `<div class="mt-3 p-3 bg-light rounded">`;
                        html += `<h6>Schedule Summary</h6>`;
                        html += `<div class="row">`;
                        html += `<div class="col-md-4">`;
                        html += `<strong>Original:</strong><br>`;
                        html += `${formatDateTZ(originalSchedule.game_date)} at ${formatTimeTZ(originalSchedule.game_time)}<br>`;
                        html += `<small class="text-muted">${originalSchedule.location}</small>`;
                        html += `</div>`;
                        html += `<div class="col-md-4">`;
                        html += `<strong>Current:</strong><br>`;
                        html += `${formatDateTZ(currentSchedule.game_date)} at ${formatTimeTZ(currentSchedule.game_time)}<br>`;
                        html += `<small class="text-muted">${currentSchedule.location}</small>`;
                        html += `</div>`;
                        html += `<div class="col-md-4">`;
                        html += `<strong>Total Changes:</strong> ${data.length - 1}`;
                        html += `</div>`;
                        html += `</div>`;
                        html += `</div>`;
                    }
                    
                    contentDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading schedule history:', error);
                    contentDiv.innerHTML = '<p class="text-danger">Error loading schedule history.</p>';
                });
        }
        
        function editGame(game) {
            document.getElementById('editGameId').value = game.game_id;
            document.getElementById('editGameNumber').value = game.game_number;
            document.getElementById('editSeasonId').value = game.season_id;
            document.getElementById('editDivisionId').value = game.division_id || '';
            document.getElementById('editGameStatus').value = game.game_status;
            document.getElementById('editAwayTeamId').value = game.away_team_id;
            document.getElementById('editHomeTeamId').value = game.home_team_id;
            document.getElementById('editGameDate').value = game.game_date;
            document.getElementById('editGameTime').value = game.game_time;
            document.getElementById('editLocation').value = game.location;
            
            var editModal = new bootstrap.Modal(document.getElementById('editGameModal'));
            editModal.show();
        }
        
        function updateScore(gameId, awayTeam, homeTeam) {
            document.getElementById('scoreGameId').value = gameId;
            document.getElementById('awayTeamLabel').textContent = awayTeam + ' Score';
            document.getElementById('homeTeamLabel').textContent = homeTeam + ' Score';
            
            var scoreModal = new bootstrap.Modal(document.getElementById('scoreModal'));
            scoreModal.show();
        }
        
        function cancelGame(gameId, gameNumber) {
            document.getElementById('cancelGameId').value = gameId;
            document.getElementById('cancelGameNumber').textContent = gameNumber;
            
            var cancelModal = new bootstrap.Modal(document.getElementById('cancelGameModal'));
            cancelModal.show();
        }
        
        function postponeGame(gameId, gameNumber) {
            document.getElementById('postponeGameId').value = gameId;
            document.getElementById('postponeGameNumber').textContent = gameNumber;
            
            var postponeModal = new bootstrap.Modal(document.getElementById('postponeGameModal'));
            postponeModal.show();
        }
    </script>
</body>
</html>

