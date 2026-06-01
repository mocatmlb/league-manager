<?php
/**
 * District 8 Travel League - Public Home Page (Production)
 * 
 * This is the production version with corrected paths for cPanel deployment
 */

require_once __DIR__ . '/includes/bootstrap.php';

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .home-games-table th,
        .home-games-table td { padding: 0.3rem 0.4rem; vertical-align: middle; }
        .home-games-table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .03em; color: #888; border-bottom: 1px solid #dee2e6; }
        .matchup { line-height: 1.2; }
        .matchup .away { font-weight: 600; font-size: 0.8rem; }
        .matchup .vs   { font-size: 0.72rem; color: #6c757d; }
        .matchup .home { font-weight: 600; font-size: 0.8rem; }
        .col-gnum { white-space: nowrap; font-size: 0.72rem; color: #888; width: 58px; }
        .col-time { white-space: nowrap; font-size: 0.8rem; width: 38px; }
        .col-date { white-space: nowrap; font-size: 0.8rem; width: 36px; }
        .col-loc  { font-size: 0.75rem; color: #555; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="jumbotron bg-light p-4 rounded">
                    <h1 class="display-4">Welcome to <?php echo APP_NAME; ?></h1>
                    <p class="lead"><?php echo htmlspecialchars(getSetting('league_tagline', 'Your source for schedules, standings, and league information.'), ENT_COMPAT, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Today's Games -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="mb-0" style="font-size:1.1rem">Today's Games</h3></div>
                    <div class="card-body p-2">
                        <?php if (empty($todaysGames)): ?>
                            <p class="text-muted mb-0 p-2">No games scheduled for today.</p>
                        <?php else: ?>
                        <table class="table table-sm mb-0 home-games-table">
                            <thead>
                                <tr>
                                    <th class="col-gnum">Game #</th>
                                    <th class="col-time">Time</th>
                                    <th>Matchup</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($todaysGames as $game): ?>
                            <tr>
                                <td class="col-gnum"><?php echo sanitize($game['game_number']); ?></td>
                                <td class="col-time"><?php echo formatTime($game['game_time']); ?></td>
                                <td class="matchup">
                                    <span class="away"><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></span><br>
                                    <span class="vs">@&nbsp;</span><span class="home"><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></span>
                                </td>
                                <td class="col-loc"><?php echo sanitize($game['location']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Games (Next 7 Days) -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="mb-0" style="font-size:1.1rem">Next 7 Days</h3></div>
                    <div class="card-body p-2">
                        <?php if (empty($upcomingGames)): ?>
                            <p class="text-muted mb-0 p-2">No upcoming games in the next 7 days.</p>
                        <?php else: ?>
                        <table class="table table-sm mb-0 home-games-table">
                            <thead>
                                <tr>
                                    <th class="col-gnum">Game #</th>
                                    <th class="col-date">Date</th>
                                    <th class="col-time">Time</th>
                                    <th>Matchup</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($upcomingGames as $game): ?>
                            <tr>
                                <td class="col-gnum"><?php echo sanitize($game['game_number']); ?></td>
                                <td class="col-date"><?php echo formatDate($game['game_date'], 'n/j'); ?></td>
                                <td class="col-time"><?php echo formatTime($game['game_time']); ?></td>
                                <td class="matchup">
                                    <span class="away"><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></span><br>
                                    <span class="vs">@&nbsp;</span><span class="home"><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></span>
                                </td>
                                <td class="col-loc"><?php echo sanitize($game['location']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
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
                                        <a href="download-document.php?id=<?php echo (int) $doc['document_id']; ?>" 
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
                    <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                    <p class="mb-0 small">
                        <a href="about.php" class="text-muted me-2">About</a>
                        <a href="privacy-policy.php" class="text-muted me-2">Privacy Policy</a>
                        <a href="terms.php" class="text-muted">Terms &amp; Conditions</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
