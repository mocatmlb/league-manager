<?php
/**
 * District 8 Travel League - Coaches Score Input
 */

require_once __DIR__ . '/../../includes/coach_bootstrap.php';

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
            $homeScore = (int)$_POST['home_score'];
            $awayScore = (int)$_POST['away_score'];
            
            // Update game with scores
            $db->update('games', [
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'game_status' => 'Completed',
                'modified_date' => date('Y-m-d H:i:s')
            ], 'game_id = :game_id', ['game_id' => $gameId]);
            
            // Log the activity
            $game = $db->fetchOne("SELECT game_number FROM games WHERE game_id = ?", [$gameId]);
            logActivity('score_submitted', "Score submitted for game #{$game['game_number']}: {$awayScore}-{$homeScore}", null);
            
            // Send email notification for score update
            sendNotification('onGameScoreUpdate', $gameId);
            
            $message = 'Score submitted successfully! Standings have been updated automatically.';
        } catch (Exception $e) {
            $error = 'Error submitting score: ' . $e->getMessage();
        }
    }
}

// Get games that need scores (played but not completed)
$gamesNeedingScores = $db->fetchAll("
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
    AND sch.game_date <= CURDATE()
    AND s.season_status = 'Active'
    AND ht.active_status = 'Active'
    AND at.active_status = 'Active'
    ORDER BY sch.game_date DESC, sch.game_time DESC
");

// Get recently completed games for reference
$recentlyCompleted = $db->fetchAll("
    SELECT g.*, sch.game_date, sch.game_time,
           ht.team_name as home_team_name,
           at.team_name as away_team_name
    FROM games g
    LEFT JOIN schedules sch ON g.game_id = sch.game_id
    JOIN teams ht ON g.home_team_id = ht.team_id
    JOIN teams at ON g.away_team_id = at.team_id
    WHERE g.game_status = 'Completed'
    AND ht.active_status = 'Active'
    AND at.active_status = 'Active'
    ORDER BY g.modified_date DESC
    LIMIT 10
");

$pageTitle = "Score Input - " . APP_NAME;
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo APP_NAME; ?></a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="../index.php">Home</a>
                <a class="nav-link" href="../schedule.php">Schedule</a>
                <a class="nav-link" href="../standings.php">Standings</a>
                <a class="nav-link" href="dashboard.php">Coaches</a>
            </div>
            <div class="navbar-nav">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Score Input</h1>
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
                        <h5><i class="fas fa-info-circle text-success"></i> Score Submission Information</h5>
                        <ul class="mb-0">
                            <li><strong>Immediate Processing:</strong> Scores are recorded immediately upon submission</li>
                            <li><strong>Automatic Updates:</strong> Standings are updated automatically when scores are submitted</li>
                            <li><strong>No Approval Required:</strong> Unlike schedule changes, scores do not require admin approval</li>
                            <li><strong>Corrections:</strong> Contact the administrator if you need to correct a submitted score</li>
                            <li>Submit scores promptly after games are completed</li>
                        </ul>
                    </div>
                </div>

                <!-- Score Input Form -->
                <?php if (!empty($gamesNeedingScores)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-baseball-ball"></i> Submit Game Score</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            
                            <!-- Game Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Game *</label>
                                <select name="game_id" class="form-select" required onchange="updateGameInfo()">
                                    <option value="">Choose the game to submit score for...</option>
                                    <?php foreach ($gamesNeedingScores as $game): ?>
                                        <option value="<?php echo $game['game_id']; ?>"
                                                data-away-team="<?php echo sanitize($game['away_team_name']); ?>"
                                                data-home-team="<?php echo sanitize($game['home_team_name']); ?>">
                                            Game #<?php echo sanitize($game['game_number']); ?> - 
                                            <?php echo formatDate($game['game_date']); ?> - 
                                            <?php echo sanitize($game['away_team_name']); ?> @ <?php echo sanitize($game['home_team_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Score Input -->
                            <div id="scoreInputSection" style="display: none;">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label" id="awayTeamLabel">Away Team Score *</label>
                                        <input type="number" name="away_score" class="form-control form-control-lg text-center" 
                                               min="0" max="99" required style="font-size: 2rem;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" id="homeTeamLabel">Home Team Score *</label>
                                        <input type="number" name="home_score" class="form-control form-control-lg text-center" 
                                               min="0" max="99" required style="font-size: 2rem;">
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Submit Score
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No games currently need score input. All recent games have been completed or are scheduled for the future.
                </div>
                <?php endif; ?>

                <!-- Recently Completed Games -->
                <?php if (!empty($recentlyCompleted)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Recently Completed Games</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Game #</th>
                                        <th>Date</th>
                                        <th>Away Team</th>
                                        <th>Home Team</th>
                                        <th>Final Score</th>
                                        <th>Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentlyCompleted as $game): ?>
                                    <tr>
                                        <td><?php echo sanitize($game['game_number']); ?></td>
                                        <td><?php echo formatDate($game['game_date']); ?></td>
                                        <td><?php echo sanitize($game['away_team_name']); ?></td>
                                        <td><?php echo sanitize($game['home_team_name']); ?></td>
                                        <td class="text-center">
                                            <strong><?php echo $game['away_score'] . ' - ' . $game['home_score']; ?></strong>
                                        </td>
                                        <td><?php echo formatDate($game['modified_date'], 'M j, g:i A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateGameInfo() {
            const gameSelect = document.querySelector('select[name="game_id"]');
            const selectedOption = gameSelect.options[gameSelect.selectedIndex];
            const scoreSection = document.getElementById('scoreInputSection');
            
            if (selectedOption.value) {
                const awayTeam = selectedOption.getAttribute('data-away-team');
                const homeTeam = selectedOption.getAttribute('data-home-team');
                
                document.getElementById('awayTeamLabel').textContent = awayTeam + ' Score *';
                document.getElementById('homeTeamLabel').textContent = homeTeam + ' Score *';
                
                scoreSection.style.display = 'block';
                
                // Clear previous scores
                document.querySelector('input[name="away_score"]').value = '';
                document.querySelector('input[name="home_score"]').value = '';
            } else {
                scoreSection.style.display = 'none';
            }
        }
        
        // Auto-focus on score inputs when they become visible
        document.querySelector('select[name="game_id"]').addEventListener('change', function() {
            if (this.value) {
                setTimeout(() => {
                    document.querySelector('input[name="away_score"]').focus();
                }, 100);
            }
        });
    </script>
</body>
</html>
