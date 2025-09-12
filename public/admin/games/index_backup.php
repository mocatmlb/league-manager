<?php
/**
 * District 8 Travel League - Games Management
 */

require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.php';

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
                    $db->commit();
                    
                    logActivity('game_created', "Game #{$gameData['game_number']} created", $currentUser['id']);
                    $message = 'Game created successfully!';
                } catch (Exception $e) {
                    $db->rollback();
                    Logger::error("Game creation failed", [
                        'error' => $e->getMessage(),
                        'home_team_id' => $homeTeamId ?? 'unknown',
                        'away_team_id' => $awayTeamId ?? 'unknown',
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $error = 'Error creating game: ' . $e->getMessage();
                }
                break;
                
            case 'update_score':
                try {
                    $gameId = (int)$_POST['game_id'];
                    $homeScore = (int)$_POST['home_score'];
                    $awayScore = (int)$_POST['away_score'];
                    
                    $db->update('games', [
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'game_status' => 'Completed',
                        'modified_date' => date('Y-m-d H:i:s')
                    ], 'game_id = :game_id', ['game_id' => $gameId]);
                    
                    logActivity('score_updated', "Score updated for game ID {$gameId}: {$homeScore}-{$awayScore}", $currentUser['id']);
                    $message = 'Score updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating score: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get games with team names and schedule info
$games = $db->fetchAll("
    SELECT g.*, sch.game_date, sch.game_time, sch.location,
           ht.team_name as home_team_name, 
           at.team_name as away_team_name,
           s.season_name,
           d.division_name
    FROM games g
    LEFT JOIN schedules sch ON g.game_id = sch.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    JOIN seasons s ON g.season_id = s.season_id
    LEFT JOIN divisions d ON g.division_id = d.division_id
    ORDER BY sch.game_date DESC, sch.game_time DESC
");

// Get data for dropdowns
$seasons = $db->fetchAll("SELECT * FROM seasons WHERE season_status = 'Active' ORDER BY season_name");
$divisions = $db->fetchAll("SELECT * FROM divisions ORDER BY division_name");
$teams = $db->fetchAll("SELECT * FROM teams WHERE active_status = 'Active' ORDER BY team_name");
$locations = $db->fetchAll("SELECT DISTINCT location_name FROM locations ORDER BY location_name");

$pageTitle = "Games Management - " . APP_NAME;
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
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/games -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/games -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Games Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                        <i class="fas fa-plus"></i> Add New Game
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

                <!-- Games Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="gamesTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Game #</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Away Team</th>
                                    <th>Home Team</th>
                                    <th>Location</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($games as $game): ?>
                                <tr>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo formatTime($game['game_time']); ?></td>
                                    <td><?php echo sanitize($game['away_team_name']); ?></td>
                                    <td><?php echo sanitize($game['home_team_name']); ?></td>
                                    <td><?php echo sanitize($game['location']); ?></td>
                                    <td>
                                        <?php if ($game['game_status'] === 'Completed'): ?>
                                            <?php echo $game['away_score'] . ' - ' . $game['home_score']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not played</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $game['game_status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                            <?php echo $game['game_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($game['game_status'] !== 'Completed'): ?>
                                            <button class="btn btn-sm btn-success" onclick="updateScore(<?php echo $game['game_id']; ?>, '<?php echo sanitize($game['away_team_name']); ?>', '<?php echo sanitize($game['home_team_name']); ?>')">
                                                <i class="fas fa-edit"></i> Score
                                            </button>
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
                                    <select name="division_id" class="form-select" required>
                                        <option value="">Select Division</option>
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
                                    <label class="form-label">Location</label>
                                    <select name="location" class="form-select" required>
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo sanitize($location['location_name']); ?>">
                                                <?php echo sanitize($location['location_name']); ?>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Game</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/timezone.js"></script>
    
    <?php outputTimezoneJS(); ?>
    
    <script>
        $(document).ready(function() {
            $('#gamesTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
        });
        
        function updateScore(gameId, awayTeam, homeTeam) {
            document.getElementById('scoreGameId').value = gameId;
            document.getElementById('awayTeamLabel').textContent = awayTeam + ' Score';
            document.getElementById('homeTeamLabel').textContent = homeTeam + ' Score';
            
            var scoreModal = new bootstrap.Modal(document.getElementById('scoreModal'));
            scoreModal.show();
        }
    </script>
</body>
</html>
