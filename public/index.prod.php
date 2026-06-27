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

function buildHomeMapsUrl(array $game): string {
    $parts = [];
    if (!empty($game['address'])) {
        $parts[] = $game['address'];
        if (!empty($game['city']))    $parts[] = $game['city'];
        if (!empty($game['state']))   $parts[] = $game['state'];
        if (!empty($game['zip_code'])) $parts[] = $game['zip_code'];
    } else {
        $name = $game['loc_name'] ?? $game['location'] ?? '';
        if (!empty($name)) $parts[] = $name;
        if (!empty($game['city']))    $parts[] = $game['city'];
        if (!empty($game['state']))   $parts[] = $game['state'];
    }
    if (empty($parts)) return '';
    return 'https://maps.google.com/?q=' . urlencode(implode(', ', $parts));
}

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
        .home-games-cards { max-height: 420px; overflow-y: auto; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="jumbotron p-4">
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
                    <div class="card-body p-2 home-games-cards">
                        <?php if (empty($todaysGames)): ?>
                            <p class="text-muted mb-0 p-2">No games scheduled for today.</p>
                        <?php else: ?>
                        <?php foreach ($todaysGames as $game):
                            $away     = sanitize(strtoupper($game['away_team'] ?: $game['away_league']));
                            $home     = sanitize(strtoupper($game['home_team'] ?: $game['home_league']));
                            $locText  = htmlspecialchars($game['loc_name'] ?: ($game['location'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $mapsUrl  = buildHomeMapsUrl($game);
                            $locLine  = $mapsUrl && $locText
                                ? '<a href="' . $mapsUrl . '" target="_blank" rel="noopener noreferrer">' . $locText . '</a>'
                                : $locText;
                        ?>
                        <div class="mobile-game-card">
                            <div class="team-row">
                                <span><?php echo $away; ?></span>
                                <span class="score-or-vs">&mdash;</span>
                            </div>
                            <div class="team-row mt-1">
                                <span><?php echo $home; ?></span>
                                <span class="score-or-vs">vs</span>
                            </div>
                            <div class="game-meta mt-2">
                                <?php echo htmlspecialchars(formatTime($game['game_time']), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($locText): ?> &middot; <?php echo $locLine; ?><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Games (Next 7 Days) -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="mb-0" style="font-size:1.1rem">Next 7 Days</h3></div>
                    <div class="card-body p-2 home-games-cards">
                        <?php if (empty($upcomingGames)): ?>
                            <p class="text-muted mb-0 p-2">No upcoming games in the next 7 days.</p>
                        <?php else:
                            $upcomingByDate = [];
                            foreach ($upcomingGames as $g) {
                                $upcomingByDate[$g['game_date']][] = $g;
                            }
                        ?>
                        <?php foreach ($upcomingByDate as $gameDate => $dateGames): ?>
                            <div class="mobile-date-label"><?php echo htmlspecialchars(date('l, F j', strtotime($gameDate)), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php foreach ($dateGames as $game):
                                $away    = sanitize(strtoupper($game['away_team'] ?: $game['away_league']));
                                $home    = sanitize(strtoupper($game['home_team'] ?: $game['home_league']));
                                $locText = htmlspecialchars($game['loc_name'] ?: ($game['location'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $mapsUrl = buildHomeMapsUrl($game);
                                $locLine = $mapsUrl && $locText
                                    ? '<a href="' . $mapsUrl . '" target="_blank" rel="noopener noreferrer">' . $locText . '</a>'
                                    : $locText;
                            ?>
                            <div class="mobile-game-card">
                                <div class="team-row">
                                    <span><?php echo $away; ?></span>
                                    <span class="score-or-vs">&mdash;</span>
                                </div>
                                <div class="team-row mt-1">
                                    <span><?php echo $home; ?></span>
                                    <span class="score-or-vs">vs</span>
                                </div>
                                <div class="game-meta mt-2">
                                    <?php echo htmlspecialchars(formatTime($game['game_time']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($locText): ?> &middot; <?php echo $locLine; ?><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
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
