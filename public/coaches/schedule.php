<?php
/**
 * District 8 Travel League - Coach Team Schedule View
 *
 * Requires team_owner role. Displays full team schedule with sort/filter.
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
require_once EnvLoader::getPath('includes/TeamScope.php');
require_once EnvLoader::getPath('includes/CoachScheduleService.php');

PermissionGuard::requireRole('team_owner', '/coaches/login.php');

$db = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
$service = new CoachScheduleService($db);

$games = $service->getTeamSchedule($userId);

function buildMapsUrl($game) {
    $parts = [];
    $name = $game['loc_name'] ?? $game['location'] ?? '';
    if (!empty($name)) $parts[] = $name;
    if (!empty($game['address'])) $parts[] = $game['address'];
    if (!empty($game['city'])) $parts[] = $game['city'];
    if (!empty($game['state'])) $parts[] = $game['state'];
    if (!empty($game['zip_code'])) $parts[] = $game['zip_code'];
    if (empty($parts)) return '';
    return 'https://maps.google.com/?q=' . urlencode(implode(', ', $parts));
}

$user = $db->fetchOne('SELECT first_name, last_name FROM users WHERE id = :id', ['id' => $userId]);
$teamRow = $db->fetchOne('SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1', ['id' => $userId]);
$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$teamName = htmlspecialchars((string) ($teamRow['team_name'] ?? ''));

$pageTitle = 'Team Schedule — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include EnvLoader::getPath('includes/coaches_nav.php'); ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>Team Schedule</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>

<?php if (empty($games)): ?>
                <div class="alert alert-info">No games scheduled for your team yet. Check back after your team assignment is confirmed.</div>
<?php else:
    // Group games by date for mobile cards
    $gamesByDate = [];
    foreach ($games as $g) {
        $gamesByDate[$g['game_date'] ?? ''][] = $g;
    }
?>

                <!-- Mobile game cards (hidden on lg+) -->
                <div class="d-lg-none">
                    <?php foreach ($gamesByDate as $gameDate => $dateGames): ?>
                        <div class="mobile-date-label"><?php echo htmlspecialchars(date('l, F j, Y', strtotime($gameDate)), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php foreach ($dateGames as $mg):
                            $mgAway = strtoupper($mg['away_team_name'] ?? 'Away');
                            $mgHome = strtoupper($mg['home_team_name'] ?? 'Home');
                            $mgTime = htmlspecialchars(formatTime($mg['game_time'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $mgMaps = buildMapsUrl($mg);
                            $mgLoc  = htmlspecialchars($mg['loc_name'] ?: ($mg['location'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $mgLocLink = $mgMaps ? '<a href="' . $mgMaps . '" target="_blank" rel="noopener noreferrer">' . $mgLoc . '</a>' : $mgLoc;
                            $mgStatus = $mg['game_status'] ?? '';
                            $mgStatusClass = match($mgStatus) {
                                'Active'         => 'status-active',
                                'Completed'      => 'status-completed',
                                'Cancelled'      => 'status-cancelled',
                                'Scheduled'      => 'status-scheduled',
                                'Created'        => 'status-created',
                                'Pending Change' => 'status-pending-change',
                                'Postponed'      => 'status-postponed',
                                default          => 'status-default',
                            };
                        ?>
                        <div class="mobile-game-card">
                            <div class="team-row">
                                <span><?php echo htmlspecialchars($mgAway, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="score-or-vs">
                                    <?php if ($mgStatus === 'Completed' && ($mg['away_score'] ?? null) !== null): ?>
                                        <?php echo (int)$mg['away_score']; ?>
                                    <?php else: ?>&mdash;<?php endif; ?>
                                </span>
                            </div>
                            <div class="team-row mt-1">
                                <span><?php echo htmlspecialchars($mgHome, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="score-or-vs">
                                    <?php if ($mgStatus === 'Completed' && ($mg['home_score'] ?? null) !== null): ?>
                                        <?php echo (int)$mg['home_score']; ?>
                                    <?php else: ?>vs<?php endif; ?>
                                </span>
                            </div>
                            <div class="game-meta d-flex justify-content-between align-items-center mt-2">
                                <span><?php echo $mgTime . ($mgLoc ? ' · ' . $mgLocLink : ''); ?></span>
                                <?php if ($mgStatus): ?>
                                    <span class="badge <?php echo $mgStatusClass; ?>"><?php echo htmlspecialchars($mgStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop table (hidden on mobile) -->
                <button id="clearFilters" class="btn btn-outline-secondary btn-sm mb-3 d-none d-lg-inline-block">Clear Filters</button>
                <div class="table-responsive d-none d-lg-block">
                    <table id="scheduleTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th data-col="0" style="cursor:pointer" aria-sort="none">Game # <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="1" style="cursor:pointer" aria-sort="none">Date <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="2" style="cursor:pointer" aria-sort="none">Time <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="3" style="cursor:pointer" aria-sort="none">Away Team <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="4" style="cursor:pointer" aria-sort="none">Home Team <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="5" style="cursor:pointer" aria-sort="none">Location <span class="sort-indicator" aria-hidden="true"></span></th>
                                <th data-col="6" style="cursor:pointer" aria-sort="none">Score <span class="sort-indicator" aria-hidden="true"></span></th>
                            </tr>
                            <tr>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="0" placeholder="Filter..."></th>
                                <th>
                                    <input type="date" id="dateFrom" class="form-control form-control-sm mb-1" placeholder="From">
                                    <input type="date" id="dateTo" class="form-control form-control-sm" placeholder="To">
                                </th>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="2" placeholder="Filter..."></th>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="3" placeholder="Filter..."></th>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="4" placeholder="Filter..."></th>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="5" placeholder="Filter..."></th>
                                <th><input type="text" class="col-filter form-control form-control-sm" data-col="6" placeholder="Filter..."></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($games as $game): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($game['game_number'] ?? ''); ?></td>
                                <td data-date="<?php echo htmlspecialchars($game['game_date'] ?? ''); ?>"><?php echo htmlspecialchars(formatDate($game['game_date'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($game['game_time'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper($game['away_team_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper($game['home_team_name'] ?? '')); ?></td>
                                <td><?php
                                    $mapsUrl = buildMapsUrl($game);
                                    $displayText = $game['loc_name'] ?: ($game['location'] ?? '');
                                    if ($mapsUrl && !empty($displayText)): ?>
                                        <a href="<?php echo $mapsUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($displayText); ?></a>
                                    <?php elseif (!empty($displayText)): ?>
                                        <?php echo htmlspecialchars($displayText); ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td><?php
                                    if ($game['game_status'] === 'Completed' && $game['away_score'] !== null) {
                                        echo htmlspecialchars($game['away_score']) . ' – ' . htmlspecialchars($game['home_score']);
                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/coaches-schedule.js"></script>
</body>
</html>
