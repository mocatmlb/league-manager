<?php
/**
 * District 8 Travel League - Public Home Page
 */

// Define application constant and prevent direct access to includes
define('D8TL_APP', true);

// Detect environment and set include path
$includePath = file_exists(__DIR__ . '/includes/env-loader.php') 
    ? __DIR__ . '/includes'  // Production: includes is in web root
    : __DIR__ . '/../includes';  // Development: includes is one level up

// Load environment loader
require_once $includePath . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Get today's games and upcoming games
$todaysGames = getTodaysGames();
$upcomingGames = getUpcomingGames(7);

// Get uploaded documents
$documents = $db->fetchAll("SELECT * FROM documents WHERE is_public = 1 ORDER BY upload_date DESC");

$pageTitle = "Home - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="jumbotron bg-light p-4 rounded">
                    <h1 class="display-4">Welcome to <?php echo APP_NAME; ?></h1>
                    <p class="lead">Your source for schedules, standings, and league information.</p>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Today's Games -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Today's Games</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaysGames)): ?>
                            <p class="text-muted">No games scheduled for today.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Away</th>
                                            <th>Home</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todaysGames as $game): ?>
                                        <tr>
                                            <td><?php echo formatTime($game['game_time']); ?></td>
                                            <td><?php echo sanitize($game['away_team']); ?></td>
                                            <td><?php echo sanitize($game['home_team']); ?></td>
                                            <td><?php echo sanitize($game['location']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Games (Next 7 Days) -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Upcoming Games (Next 7 Days)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingGames)): ?>
                            <p class="text-muted">No upcoming games in the next 7 days.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Away</th>
                                            <th>Home</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingGames as $game): ?>
                                        <tr>
                                            <td><?php echo formatDate($game['game_date'], 'M j'); ?></td>
                                            <td><?php echo formatTime($game['game_time']); ?></td>
                                            <td><?php echo sanitize($game['away_team']); ?></td>
                                            <td><?php echo sanitize($game['home_team']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <?php if (!empty($documents)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>League Documents</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($documents as $doc): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo sanitize($doc['title']); ?></h5>
                                        <p class="card-text text-muted"><?php echo sanitize($doc['description']); ?></p>
                                        <a href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../uploads/documents/<?php echo sanitize($doc['filename']); ?>" 
                                           class="btn btn-primary btn-sm" target="_blank">Download</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
</body>
</html>
