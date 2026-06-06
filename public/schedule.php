<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Public Schedule Page
 */

// Detect environment and set include path
$includePath = file_exists(__DIR__ . '/includes/env-loader.php')
    ? __DIR__ . '/includes'  // Production: includes is in web root
    : __DIR__ . '/../includes';  // Development: includes is one level up

// Load environment loader
require_once $includePath . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Initialize filter helpers
FilterHelpers::init();

// Get filter values from URL
$filters = FilterHelpers::getFilterValues();
$activeTab = $filters['tab'];

// Validate filters
$filterValidation = FilterHelpers::validateFilters($filters);
if (!$filterValidation['valid']) {
    header('Location: schedule.php');
    exit;
}

// Get filter options for dropdowns
$programs = FilterHelpers::getActivePrograms();
$seasons = FilterHelpers::getSeasons($filters['program_id']);
$divisions = FilterHelpers::getDivisions($filters['season_id']);

// Build SQL conditions based on filters
$filterSql = FilterHelpers::buildFilterConditions($filters);

$today = date('Y-m-d');

// Shared SELECT columns and JOIN clause for all four queries
$selectCols = "g.game_number, g.game_status, g.home_score, g.away_score,
               s.game_date, s.game_time, s.location,
               loc.location_name as loc_name, loc.address, loc.city, loc.state, loc.zip_code,
               ht.team_name as home_team, ht.league_name as home_league,
               at.team_name as away_team, at.league_name as away_league,
               d.division_name, p.program_name, p.program_code, se.season_name, se.season_year";

$joinBase = "FROM games g
        JOIN schedules s ON g.game_id = s.game_id
        LEFT JOIN locations loc ON s.location_id = loc.location_id
        JOIN teams ht ON g.home_team_id = ht.team_id
        JOIN teams at ON g.away_team_id = at.team_id
        JOIN divisions d ON g.division_id = d.division_id
        JOIN seasons se ON g.season_id = se.season_id
        JOIN programs p ON se.program_id = p.program_id
        WHERE ht.active_status = 'Active' AND at.active_status = 'Active'
        " . $filterSql['conditions'];

// Four tab queries
$gamesUpcoming = $db->fetchAll(
    "SELECT $selectCols $joinBase
     AND s.game_date >= ?
     AND g.game_status NOT IN ('Completed', 'Cancelled', 'Postponed')
     ORDER BY s.game_date ASC, s.game_time ASC",
    array_merge($filterSql['params'], [$today])
);

$gamesCompleted = $db->fetchAll(
    "SELECT $selectCols $joinBase
     AND g.game_status IN ('Completed', 'Cancelled')
     ORDER BY s.game_date ASC, s.game_time ASC",
    $filterSql['params']
);

$gamesAwaiting = $db->fetchAll(
    "SELECT $selectCols $joinBase
     AND s.game_date < ?
     AND g.game_status NOT IN ('Completed', 'Cancelled', 'Postponed')
     AND g.home_score IS NULL
     ORDER BY s.game_date ASC, s.game_time ASC",
    array_merge($filterSql['params'], [$today])
);

$gamesPostponed = $db->fetchAll(
    "SELECT $selectCols $joinBase
     AND g.game_status = 'Postponed'
     ORDER BY g.game_number ASC",
    $filterSql['params']
);

$tabCounts = [
    'upcoming'  => count($gamesUpcoming),
    'completed' => count($gamesCompleted),
    'awaiting'  => count($gamesAwaiting),
    'postponed' => count($gamesPostponed),
];

// Days Late for awaiting games
foreach ($gamesAwaiting as &$g) {
    $g['days_late'] = (int)floor((strtotime($today) - strtotime($g['game_date'])) / 86400);
}
unset($g);

// Mobile: pre-group all four tabs by date
$mobileByTab = [
    'upcoming'  => [],
    'completed' => [],
    'awaiting'  => [],
    'postponed' => [],
];
foreach (['upcoming' => $gamesUpcoming, 'completed' => $gamesCompleted,
           'awaiting' => $gamesAwaiting, 'postponed' => $gamesPostponed] as $tabKey => $tabGames) {
    foreach ($tabGames as $game) {
        $mobileByTab[$tabKey][$game['game_date']][] = $game;
    }
}

// Mobile chips built from ALL games across all tabs
$mobileChipPrograms = [];
$mobileChipSeasons = [];
$mobileChipDivisions = [];
foreach (['upcoming' => $gamesUpcoming, 'completed' => $gamesCompleted,
           'awaiting' => $gamesAwaiting, 'postponed' => $gamesPostponed] as $tabKey => $tabGames) {
    foreach ($tabGames as $game) {
        $progName = trim((string) ($game['program_name'] ?? ''));
        if ($progName !== '' && !in_array($progName, $mobileChipPrograms, true)) {
            $mobileChipPrograms[] = $progName;
        }
        $seasonLabel = trim((string) (($game['season_name'] ?? '') . ' ' . ($game['season_year'] ?? '')));
        if ($seasonLabel !== '' && !in_array($seasonLabel, $mobileChipSeasons, true)) {
            $mobileChipSeasons[] = $seasonLabel;
        }
        $divName = $game['division_name'] ?? '';
        if ($divName !== '' && !in_array($divName, $mobileChipDivisions, true)) {
            $mobileChipDivisions[] = $divName;
        }
    }
}

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

// Returns [cssClass, displayLabel] for a game status badge
function getStatusInfo($status) {
    switch ($status) {
        case 'Active':
        case 'Created':
        case 'Scheduled':
            return ['status-scheduled', 'Scheduled'];
        case 'Completed':
            return ['status-completed', 'Completed'];
        case 'Cancelled':
            return ['status-cancelled', 'Cancelled'];
        case 'Pending Change':
            return ['status-pending-change', 'Pending Change'];
        case 'Postponed':
            return ['status-postponed', 'Postponed'];
        default:
            return ['status-default', htmlspecialchars($status, ENT_QUOTES, 'UTF-8')];
    }
}

$pageTitle = "Schedule - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php
    // Include navigation - handle both development and production paths
    $navPath = file_exists(__DIR__ . '/includes/nav.php')
        ? __DIR__ . '/includes/nav.php'  // Production: includes is in same directory
        : dirname(__DIR__) . '/includes/nav.php';  // Development: includes is one level up
    include $navPath;
    ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>Game Schedule</h1>
                </div>
            </div>
        </div>

        <!-- Filters (desktop only) -->
        <div class="card mb-4 d-none d-lg-block">
            <div class="card-body">
                <form id="filterForm" method="get" class="row g-3">
                    <!-- Preserve active tab across filter changes -->
                    <input type="hidden" name="tab" id="hiddenTab" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Program Filter -->
                    <div class="col-md-4">
                        <label for="program" class="form-label">Program</label>
                        <select name="program" id="program" class="form-select">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['program_id']; ?>"
                                        <?php echo $filters['program_id'] == $program['program_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($program['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Season Filter -->
                    <div class="col-md-4">
                        <label for="season" class="form-label">Season</label>
                        <select name="season" id="season" class="form-select" <?php echo empty($filters['program_id']) ? 'disabled' : ''; ?>>
                            <option value="">All Seasons</option>
                            <?php foreach ($seasons as $season): ?>
                                <option value="<?php echo $season['season_id']; ?>"
                                        <?php echo $filters['season_id'] == $season['season_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($season['season_name'] . ' ' . $season['season_year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Division Filter -->
                    <div class="col-md-4">
                        <label for="division" class="form-label">Division</label>
                        <select name="division" id="division" class="form-select" <?php echo empty($filters['season_id']) ? 'disabled' : ''; ?>>
                            <option value="">All Divisions</option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?php echo $division['division_id']; ?>"
                                        <?php echo $filters['division_id'] == $division['division_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($division['division_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================
             MOBILE SCHEDULE (hidden on lg+)
             Tab chips + program/season/division chips + 4 panes
             ====================================================== -->
        <div id="mobileSchedule" class="d-lg-none">

            <!-- Mobile tab chips (tab switching) -->
            <div class="mobile-tab-chips mb-3 d-flex gap-2 overflow-auto pb-1">
                <?php
                $mobileTabDefs = [
                    ['key' => 'upcoming',  'label' => 'Upcoming', 'extra' => ''],
                    ['key' => 'completed', 'label' => 'Results',  'extra' => ''],
                    ['key' => 'awaiting',  'label' => 'Awaiting', 'extra' => 'tab-chip-warn'],
                    ['key' => 'postponed', 'label' => 'PPD',      'extra' => 'tab-chip-ppd'],
                ];
                foreach ($mobileTabDefs as $mtd):
                    $isActive = $activeTab === $mtd['key'];
                ?>
                <button class="chip-btn <?php echo $mtd['extra']; ?> <?php echo $isActive ? 'active' : ''; ?>"
                        data-tab-chip="<?php echo $mtd['key']; ?>">
                    <?php echo $mtd['label']; ?> <span class="ms-1"><?php echo $tabCounts[$mtd['key']]; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Program / Season / Division filter chips (filter within each pane) -->
            <?php if (!empty($mobileChipPrograms)): ?>
                <div class="small text-muted text-uppercase mb-1" style="font-size:0.7rem;letter-spacing:0.05em;">Program</div>
                <div class="mobile-filter-chips mb-2 d-flex gap-2 overflow-auto pb-1">
                    <button type="button" class="chip-btn active" data-dimension="program" data-filter="all">All</button>
                    <?php foreach ($mobileChipPrograms as $chipProg): ?>
                        <button type="button" class="chip-btn" data-dimension="program" data-filter="<?php echo htmlspecialchars($chipProg, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($chipProg, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($mobileChipSeasons)): ?>
                <div class="small text-muted text-uppercase mb-1" style="font-size:0.7rem;letter-spacing:0.05em;">Season</div>
                <div class="mobile-filter-chips mb-2 d-flex gap-2 overflow-auto pb-1">
                    <button type="button" class="chip-btn active" data-dimension="season" data-filter="all">All</button>
                    <?php foreach ($mobileChipSeasons as $chipSeason): ?>
                        <button type="button" class="chip-btn" data-dimension="season" data-filter="<?php echo htmlspecialchars($chipSeason, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($chipSeason, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($mobileChipDivisions)): ?>
                <div class="small text-muted text-uppercase mb-1" style="font-size:0.7rem;letter-spacing:0.05em;">Division</div>
                <div class="mobile-filter-chips mb-3 d-flex gap-2 overflow-auto pb-1">
                    <button type="button" class="chip-btn active" data-dimension="division" data-filter="all">All</button>
                    <?php foreach ($mobileChipDivisions as $chipDiv): ?>
                        <button type="button" class="chip-btn" data-dimension="division" data-filter="<?php echo htmlspecialchars($chipDiv, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($chipDiv, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            // Helper: render mobile cards for one tab's grouped-by-date data
            function renderMobilePane($tabKey, $gamesByDate, $today, $tabCounts) {
                $isActive = true; // visibility controlled by JS; all panes emitted to DOM
                $display = 'block'; // JS will set display:none on inactive panes on ready
                ?>
                <div id="mobilePane-<?php echo $tabKey; ?>" class="mobile-tab-pane">
                    <?php if (empty($gamesByDate)): ?>
                        <div class="alert alert-info">No games in this category. Try adjusting your filters.</div>
                    <?php else: ?>
                        <?php foreach ($gamesByDate as $gameDate => $dateGames): ?>
                            <div class="date-group">
                                <div class="mobile-date-label"><?php echo htmlspecialchars(date('l, F j, Y', strtotime($gameDate)), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php foreach ($dateGames as $mg):
                                    $mgProgram = htmlspecialchars(trim((string) ($mg['program_name'] ?? '')), ENT_QUOTES, 'UTF-8');
                                    $mgSeason  = htmlspecialchars(trim((string) (($mg['season_name'] ?? '') . ' ' . ($mg['season_year'] ?? ''))), ENT_QUOTES, 'UTF-8');
                                    $mgDiv     = htmlspecialchars($mg['division_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $mgAway    = strtoupper($mg['away_team'] ?: $mg['away_league']);
                                    $mgHome    = strtoupper($mg['home_team'] ?: $mg['home_league']);
                                    $mgLoc     = htmlspecialchars($mg['loc_name'] ?: ($mg['location'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $mgMaps    = buildMapsUrl($mg);
                                    $mgLocLink = $mgMaps ? '<a href="' . $mgMaps . '" target="_blank" rel="noopener noreferrer">' . $mgLoc . '</a>' : $mgLoc;
                                    $mgTime    = formatTime($mg['game_time']);
                                    [$mgStatusClass, $mgStatusLabel] = getStatusInfo($mg['game_status']);
                                    $isToday   = ($mg['game_date'] === $today);
                                ?>
                                <div class="mobile-game-card<?php echo $isToday ? ' game-row-today' : ''; ?>"
                                     data-program="<?php echo $mgProgram; ?>"
                                     data-season="<?php echo $mgSeason; ?>"
                                     data-division="<?php echo $mgDiv; ?>"
                                     data-status="<?php echo htmlspecialchars($mg['game_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="team-row">
                                        <span><?php echo htmlspecialchars($mgAway, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="score-or-vs">
                                            <?php if ($tabKey !== 'postponed'): ?>
                                                <?php if ($mg['game_status'] === 'Completed' && $mg['away_score'] !== null): ?>
                                                    <?php echo (int)$mg['away_score']; ?>
                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="team-row mt-1">
                                        <span><?php echo htmlspecialchars($mgHome, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="score-or-vs">
                                            <?php if ($tabKey !== 'postponed'): ?>
                                                <?php if ($mg['game_status'] === 'Completed' && $mg['home_score'] !== null): ?>
                                                    <?php echo (int)$mg['home_score']; ?>
                                                <?php else: ?>
                                                    vs
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="game-meta d-flex justify-content-between align-items-center mt-2">
                                        <span>
                                            <?php echo htmlspecialchars($mgTime, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($mgLoc): ?>· <?php echo $mgLocLink; ?><?php endif; ?>
                                            <?php if ($tabKey === 'awaiting' && isset($mg['days_late'])): ?>
                                                <span class="badge bg-warning text-dark ms-1"><?php echo $mg['days_late']; ?>d late</span>
                                            <?php endif; ?>
                                            <?php if ($tabKey === 'postponed'): ?>
                                                <span class="text-muted ms-1">Game #<?php echo sanitize($mg['game_number']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="badge <?php echo $mgStatusClass; ?>"><?php echo $mgStatusLabel; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php
            }

            renderMobilePane('upcoming',  $mobileByTab['upcoming'],  $today, $tabCounts);
            renderMobilePane('completed', $mobileByTab['completed'], $today, $tabCounts);
            renderMobilePane('awaiting',  $mobileByTab['awaiting'],  $today, $tabCounts);
            renderMobilePane('postponed', $mobileByTab['postponed'], $today, $tabCounts);
            ?>
        </div><!-- /#mobileSchedule -->

        <!-- ======================================================
             DESKTOP SCHEDULE (hidden on < lg)
             Tab strip + four DataTable panes
             ====================================================== -->
        <div id="desktopSchedule" class="d-none d-lg-block">

            <!-- Tab strip -->
            <ul class="nav nav-tabs mb-3" id="scheduleTabs">
                <?php
                $desktopTabDefs = [
                    ['key' => 'upcoming',  'label' => 'Upcoming',         'extra' => ''],
                    ['key' => 'completed', 'label' => 'Completed',         'extra' => ''],
                    ['key' => 'awaiting',  'label' => 'Awaiting Results',  'extra' => 'tab-warn'],
                    ['key' => 'postponed', 'label' => 'Postponed',         'extra' => 'tab-ppd'],
                ];
                foreach ($desktopTabDefs as $dtd):
                    $isActive = $activeTab === $dtd['key'];
                ?>
                <li class="nav-item">
                    <button class="nav-link <?php echo $dtd['extra']; ?> <?php echo $isActive ? 'active' : ''; ?>"
                            data-tab="<?php echo $dtd['key']; ?>">
                        <?php echo $dtd['label']; ?>
                        <span class="badge ms-1 <?php echo $isActive ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?php echo $tabCounts[$dtd['key']]; ?>
                        </span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- ── Upcoming pane ── -->
            <div id="pane-upcoming" class="tab-pane<?php echo $activeTab === 'upcoming' ? ' active' : ''; ?>">
                <?php if (empty($gamesUpcoming)): ?>
                    <div class="alert alert-info">No upcoming games found. Try adjusting your filters.</div>
                <?php else: ?>
                <div class="card"><div class="card-body">
                    <div class="table-responsive">
                        <table id="tableUpcoming" class="table table-striped table-hover schedule-table">
                            <thead>
                                <tr>
                                    <th>Game #</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Away Team</th>
                                    <th>Home Team</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gamesUpcoming as $game):
                                    $isToday = ($game['game_date'] === $today);
                                    [$statusClass, $statusLabel] = getStatusInfo($game['game_status']);
                                    $mapsUrl     = buildMapsUrl($game);
                                    $displayLoc  = $game['loc_name'] ?: $game['location'];
                                ?>
                                <tr<?php echo $isToday ? ' class="game-row-today"' : ''; ?>>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td data-sort="<?php echo $game['game_date']; ?>"><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo formatTime($game['game_time']); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></td>
                                    <td><?php
                                        if ($mapsUrl && !empty($displayLoc)):
                                            ?><a href="<?php echo $mapsUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($displayLoc); ?></a><?php
                                        elseif (!empty($displayLoc)):
                                            echo sanitize($displayLoc);
                                        else:
                                            echo '-';
                                        endif;
                                    ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
                <?php endif; ?>
            </div>

            <!-- ── Completed pane ── -->
            <div id="pane-completed" class="tab-pane<?php echo $activeTab === 'completed' ? ' active' : ''; ?>">
                <?php if (empty($gamesCompleted)): ?>
                    <div class="alert alert-info">No completed games found. Try adjusting your filters.</div>
                <?php else: ?>
                <div class="card"><div class="card-body">
                    <div class="table-responsive">
                        <table id="tableCompleted" class="table table-striped table-hover schedule-table">
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gamesCompleted as $game):
                                    [$statusClass, $statusLabel] = getStatusInfo($game['game_status']);
                                    $mapsUrl    = buildMapsUrl($game);
                                    $displayLoc = $game['loc_name'] ?: $game['location'];
                                ?>
                                <tr>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td data-sort="<?php echo $game['game_date']; ?>"><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo formatTime($game['game_time']); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></td>
                                    <td><?php
                                        if ($mapsUrl && !empty($displayLoc)):
                                            ?><a href="<?php echo $mapsUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($displayLoc); ?></a><?php
                                        elseif (!empty($displayLoc)):
                                            echo sanitize($displayLoc);
                                        else:
                                            echo '-';
                                        endif;
                                    ?></td>
                                    <td>
                                        <?php if ($game['game_status'] === 'Completed' && $game['away_score'] !== null): ?>
                                            <?php echo (int)$game['away_score']; ?> &ndash; <?php echo (int)$game['home_score']; ?>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
                <?php endif; ?>
            </div>

            <!-- ── Awaiting Results pane ── -->
            <div id="pane-awaiting" class="tab-pane<?php echo $activeTab === 'awaiting' ? ' active' : ''; ?>">
                <?php if (empty($gamesAwaiting)): ?>
                    <div class="alert alert-info">No games awaiting results. All past games have been scored.</div>
                <?php else: ?>
                <div class="card"><div class="card-body">
                    <div class="table-responsive">
                        <table id="tableAwaiting" class="table table-striped table-hover schedule-table">
                            <thead>
                                <tr>
                                    <th>Game #</th>
                                    <th>Date</th>
                                    <th>Away Team</th>
                                    <th>Home Team</th>
                                    <th>Location</th>
                                    <th>Days Late</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gamesAwaiting as $game):
                                    [$statusClass, $statusLabel] = getStatusInfo($game['game_status']);
                                    $mapsUrl    = buildMapsUrl($game);
                                    $displayLoc = $game['loc_name'] ?: $game['location'];
                                    $daysLate   = $game['days_late'];
                                    $daysLabel  = $daysLate . ' day' . ($daysLate === 1 ? '' : 's');
                                ?>
                                <tr>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td data-sort="<?php echo $game['game_date']; ?>"><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></td>
                                    <td><?php
                                        if ($mapsUrl && !empty($displayLoc)):
                                            ?><a href="<?php echo $mapsUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($displayLoc); ?></a><?php
                                        elseif (!empty($displayLoc)):
                                            echo sanitize($displayLoc);
                                        else:
                                            echo '-';
                                        endif;
                                    ?></td>
                                    <td data-sort="<?php echo $daysLate; ?>"><?php echo $daysLabel; ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
                <?php endif; ?>
            </div>

            <!-- ── Postponed pane ── -->
            <div id="pane-postponed" class="tab-pane<?php echo $activeTab === 'postponed' ? ' active' : ''; ?>">
                <?php if (empty($gamesPostponed)): ?>
                    <div class="alert alert-info">No postponed games this season. Try adjusting your filters.</div>
                <?php else: ?>
                <div class="card"><div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePostponed" class="table table-striped table-hover schedule-table">
                            <thead>
                                <tr>
                                    <th>Game #</th>
                                    <th>Orig. Date</th>
                                    <th>Away Team</th>
                                    <th>Home Team</th>
                                    <th>Division</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gamesPostponed as $game):
                                    [$statusClass, $statusLabel] = getStatusInfo($game['game_status']);
                                ?>
                                <tr>
                                    <td><?php echo sanitize($game['game_number']); ?></td>
                                    <td data-sort="<?php echo $game['game_date']; ?>"><?php echo formatDate($game['game_date']); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['away_team'] ?: $game['away_league'])); ?></td>
                                    <td><?php echo sanitize(strtoupper($game['home_team'] ?: $game['home_league'])); ?></td>
                                    <td><?php echo sanitize($game['division_name']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div></div>
                <?php endif; ?>
            </div>

        </div><!-- /#desktopSchedule -->

        <!-- Calendar View stub (hidden; preserved for future story) -->
        <!--
        <div id="calendarView">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info">
                        <h4>Calendar View</h4>
                        <p>Calendar view will be implemented in a future update.</p>
                    </div>
                </div>
            </div>
        </div>
        -->
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                    <p class="mb-1"><small>Version <?php echo APP_VERSION; ?></small></p>
                    <p class="mb-0 small">
                        <a href="privacy-policy.php" class="text-muted me-2">Privacy Policy</a>
                        <a href="terms.php" class="text-muted">Terms &amp; Conditions</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {

        // ── Desktop: initialize four DataTables ──────────────────────────
        var dtBase = { pageLength: 25, responsive: false };

        var dtUpcoming  = $('#tableUpcoming').length  ? $('#tableUpcoming').DataTable($.extend({}, dtBase, { order: [[1,'asc'],[2,'asc']] })) : null;
        var dtCompleted = $('#tableCompleted').length ? $('#tableCompleted').DataTable($.extend({}, dtBase, { order: [[1,'asc']] }))           : null;
        var dtAwaiting  = $('#tableAwaiting').length  ? $('#tableAwaiting').DataTable($.extend({}, dtBase, { order: [[1,'asc']] }))            : null;
        var dtPostponed = $('#tablePostponed').length ? $('#tablePostponed').DataTable($.extend({}, dtBase, { order: [[0,'asc']] }))           : null;

        var dtMap = { upcoming: dtUpcoming, completed: dtCompleted, awaiting: dtAwaiting, postponed: dtPostponed };

        // ── Desktop: tab switching ────────────────────────────────────────
        function switchTab(name) {
            // Update pane visibility
            document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
            var pane = document.getElementById('pane-' + name);
            if (pane) pane.classList.add('active');

            // Update tab button state and badge colours
            document.querySelectorAll('#scheduleTabs .nav-link').forEach(function(b) {
                b.classList.remove('active');
                var badge = b.querySelector('.badge');
                if (badge) badge.className = badge.className.replace('bg-primary', 'bg-secondary');
            });
            var activeBtn = document.querySelector('#scheduleTabs [data-tab="' + name + '"]');
            if (activeBtn) {
                activeBtn.classList.add('active');
                var activeBadge = activeBtn.querySelector('.badge');
                if (activeBadge) activeBadge.className = activeBadge.className.replace('bg-secondary', 'bg-primary');
            }

            // Fix DataTable column widths (they miscalculate when initialized hidden)
            if (dtMap[name] && typeof dtMap[name].columns === 'function') {
                dtMap[name].columns.adjust().draw(false);
            }

            // Persist tab in URL without page reload
            var url = new URL(window.location);
            url.searchParams.set('tab', name);
            history.replaceState(null, '', url);

            // Keep hidden form input in sync so filter submits preserve tab
            document.getElementById('hiddenTab').value = name;
        }

        document.querySelectorAll('#scheduleTabs .nav-link').forEach(function(btn) {
            btn.addEventListener('click', function() { switchTab(this.dataset.tab); });
        });

        // ── Desktop filter dropdowns ──────────────────────────────────────
        $('#program').change(function() {
            $('#season').prop('disabled', !$(this).val()).val('');
            $('#division').prop('disabled', true).val('');
            $('#filterForm').submit();
        });

        $('#season').change(function() {
            $('#division').prop('disabled', !$(this).val()).val('');
            $('#filterForm').submit();
        });

        $('#division').change(function() {
            $('#filterForm').submit();
        });

        // ── Mobile: tab chip switching ────────────────────────────────────
        var activeMobileTab = '<?php echo $activeTab; ?>';

        function switchMobileTab(name) {
            activeMobileTab = name;
            document.querySelectorAll('.mobile-tab-pane').forEach(function(p) { p.style.display = 'none'; });
            var pane = document.getElementById('mobilePane-' + name);
            if (pane) pane.style.display = 'block';

            document.querySelectorAll('.mobile-tab-chips .chip-btn').forEach(function(b) { b.classList.remove('active'); });
            var activeChip = document.querySelector('.mobile-tab-chips [data-tab-chip="' + name + '"]');
            if (activeChip) activeChip.classList.add('active');

            // Re-apply filters to the newly visible pane
            applyMobileFilters();
        }

        // Initialize: hide all mobile panes, show active
        document.querySelectorAll('.mobile-tab-pane').forEach(function(p) { p.style.display = 'none'; });
        var initPane = document.getElementById('mobilePane-' + activeMobileTab);
        if (initPane) initPane.style.display = 'block';

        document.querySelectorAll('.mobile-tab-chips .chip-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { switchMobileTab(this.dataset.tabChip); });
        });

        // ── Mobile: program/season/division chip filtering ────────────────
        var mobileFilters = { program: 'all', season: 'all', division: 'all' };

        function applyMobileFilters() {
            // Only filter cards in the currently visible pane
            var activePane = document.getElementById('mobilePane-' + activeMobileTab);
            if (!activePane) return;
            activePane.querySelectorAll('.mobile-game-card').forEach(function(card) {
                var show = true;
                if (mobileFilters.program !== 'all' && card.dataset.program !== mobileFilters.program) show = false;
                if (mobileFilters.season  !== 'all' && card.dataset.season  !== mobileFilters.season)  show = false;
                if (mobileFilters.division !== 'all' && card.dataset.division !== mobileFilters.division) show = false;
                card.classList.toggle('is-filtered-out', !show);
            });
            activePane.querySelectorAll('.date-group').forEach(function(group) {
                var visible = group.querySelectorAll('.mobile-game-card:not(.is-filtered-out)').length;
                group.classList.toggle('is-empty', visible === 0);
            });
        }

        document.querySelectorAll('#mobileSchedule .mobile-filter-chips .chip-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var dimension = this.dataset.dimension;
                var filter    = this.dataset.filter;
                if (!dimension) return;
                var row = this.closest('.mobile-filter-chips');
                if (row) row.querySelectorAll('.chip-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                mobileFilters[dimension] = filter;
                applyMobileFilters();
            });
        });

    });
    </script>
</body>
</html>
