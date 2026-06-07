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

// Build calendar events array for FullCalendar
$calendarEvents = [];

$categoryMap = [
    'upcoming'  => ['color' => '#2563eb', 'textColor' => '#ffffff'],
    'completed' => ['color' => '#6c757d', 'textColor' => '#ffffff'],
    'awaiting'  => ['color' => '#f59e0b', 'textColor' => '#000000'],
    'postponed' => ['color' => '#6f42c1', 'textColor' => '#ffffff'],
];

foreach (['upcoming' => $gamesUpcoming, 'completed' => $gamesCompleted,
           'awaiting' => $gamesAwaiting, 'postponed' => $gamesPostponed] as $cat => $catGames) {
    foreach ($catGames as $g) {
        $away = htmlspecialchars(strtoupper($g['away_team'] ?: $g['away_league']), ENT_QUOTES, 'UTF-8');
        $home = htmlspecialchars(strtoupper($g['home_team'] ?: $g['home_league']), ENT_QUOTES, 'UTF-8');
        $timeStr = !empty($g['game_time']) ? date('g:i A', strtotime($g['game_time'])) : '';
        $locDisplay = $g['loc_name'] ?: ($g['location'] ?? '');
        [$statusClass, $statusLabel] = getStatusInfo($g['game_status']);
        $mapsUrl = buildMapsUrl($g);

        $calendarEvents[] = [
            'title'       => $away . ' @ ' . $home,
            'start'       => $g['game_date'] . (!empty($g['game_time']) ? 'T' . $g['game_time'] : ''),
            'color'       => $categoryMap[$cat]['color'],
            'textColor'   => $categoryMap[$cat]['textColor'],
            'extendedProps' => [
                'gameNumber'  => $g['game_number'],
                'gameDate'    => formatDate($g['game_date']),
                'gameTime'    => $timeStr,
                'away'        => $away,
                'home'        => $home,
                'location'    => htmlspecialchars($locDisplay, ENT_QUOTES, 'UTF-8'),
                'mapsUrl'     => $mapsUrl,
                'statusClass' => $statusClass,
                'statusLabel' => $statusLabel,
                'category'    => $cat,
            ],
        ];
    }
}

// Query special dates filtered by active season and global
$specialDateScopeId = (int)($filters['season_id'] ?? 0);
if ($specialDateScopeId > 0) {
    $specialDates = $db->fetchAll(
        "SELECT sd.id, sd.date, sd.label, sd.date_type, sd.display_color, sd.season_id,
                s.season_name, s.season_year
         FROM league_special_dates sd
         LEFT JOIN seasons s ON sd.season_id = s.season_id
         WHERE sd.season_id IS NULL
            OR sd.season_id = ?
         ORDER BY sd.date ASC",
        [$specialDateScopeId]
    );
} else {
    $specialDates = $db->fetchAll(
        "SELECT id, date, label, date_type, display_color, season_id,
                NULL AS season_name, NULL AS season_year
         FROM league_special_dates
         WHERE season_id IS NULL
         ORDER BY date ASC"
    );
}

$specialDateEvents = [];
foreach ($specialDates as $sd) {
    $specialDateEvents[] = [
        'title'      => $sd['label'],
        'start'      => $sd['date'],
        'allDay'     => true,
        'display'    => 'background',
        'color'      => $sd['display_color'],
        'classNames' => ['fc-special-date'],
        'extendedProps' => [
            'isSpecialDate' => true,
            'dateType'      => $sd['date_type'],
        ],
    ];
}

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

$mobileSpecialDatesByDate = [];
foreach ($specialDates as $sd) {
    $mobileSpecialDatesByDate[$sd['date']][] = $sd;
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
            function renderMobileSpecialDateCard($sd) {
                $typeIcons = [
                    'milestone' => 'fas fa-star',
                    'holiday'   => 'fas fa-flag',
                    'deadline'  => 'fas fa-exclamation',
                    'other'     => 'fas fa-calendar-day',
                ];
                $typeLabels = [
                    'milestone' => 'Milestone',
                    'holiday'   => 'Holiday',
                    'deadline'  => 'Deadline',
                    'other'     => 'Other',
                ];
                $icon  = $typeIcons[$sd['date_type']] ?? 'fas fa-calendar-day';
                $tl    = $typeLabels[$sd['date_type']] ?? 'Special Date';
                $scope = $sd['season_id']
                    ? htmlspecialchars(trim(($sd['season_name'] ?? '') . ' ' . ($sd['season_year'] ?? '')), ENT_QUOTES, 'UTF-8')
                    : 'All Seasons';
                $color = htmlspecialchars($sd['display_color'], ENT_QUOTES, 'UTF-8');
                // Append hex alpha 18 (~10% opacity) for the tinted background
                $bgColor = $color . '1a';
                ?>
                <div class="mobile-special-date-card" style="background:<?php echo $bgColor; ?>;border-color:<?php echo $color; ?>;">
                    <div class="sd-icon" style="background:<?php echo $color; ?>;">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                    <div class="sd-body">
                        <div class="sd-label"><?php echo htmlspecialchars($sd['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="sd-type"><?php echo $tl; ?> &bull; <?php echo $scope; ?></div>
                    </div>
                </div>
                <?php
            }

            function renderMobilePane($tabKey, $gamesByDate, $today, $tabCounts, $mobileSpecialDatesByDate) {
                // Standalone special-date placeholders only make sense in the upcoming tab
                $showStandalone = ($tabKey === 'upcoming');

                // Build the full set of dates to render
                if ($showStandalone) {
                    $allDates = array_unique(array_merge(
                        array_keys($gamesByDate),
                        array_keys($mobileSpecialDatesByDate)
                    ));
                    sort($allDates);
                } else {
                    $allDates = array_keys($gamesByDate);
                }

                $isActive = true; // visibility controlled by JS; all panes emitted to DOM
                $display = 'block'; // JS will set display:none on inactive panes on ready
                ?>
                <div id="mobilePane-<?php echo $tabKey; ?>" class="mobile-tab-pane">
                    <?php if (empty($allDates)): ?>
                        <div class="alert alert-info">No games in this category. Try adjusting your filters.</div>
                    <?php else: ?>
                        <?php foreach ($allDates as $gameDate):
                            $dateGames = $gamesByDate[$gameDate] ?? [];
                        ?>
                            <div class="date-group">
                                <div class="mobile-date-label"><?php echo htmlspecialchars(date('l, F j, Y', strtotime($gameDate)), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($mobileSpecialDatesByDate[$gameDate])): ?>
                                    <?php foreach ($mobileSpecialDatesByDate[$gameDate] as $sd): ?>
                                        <?php renderMobileSpecialDateCard($sd); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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

            renderMobilePane('upcoming',  $mobileByTab['upcoming'],  $today, $tabCounts, $mobileSpecialDatesByDate);
            renderMobilePane('completed', $mobileByTab['completed'], $today, $tabCounts, $mobileSpecialDatesByDate);
            renderMobilePane('awaiting',  $mobileByTab['awaiting'],  $today, $tabCounts, $mobileSpecialDatesByDate);
            renderMobilePane('postponed', $mobileByTab['postponed'], $today, $tabCounts, $mobileSpecialDatesByDate);
            ?>
        </div><!-- /#mobileSchedule -->

        <!-- ======================================================
             DESKTOP SCHEDULE (hidden on < lg)
             Tab strip + four DataTable panes
             ====================================================== -->
        <div id="desktopSchedule" class="d-none d-lg-block">

            <!-- View toggle — desktop only -->
            <div class="schedule-view-toggle d-none d-lg-flex mb-3">
                <button class="view-toggle-btn <?php echo $activeTab !== 'calendar' ? 'active' : ''; ?>" id="btnTableView" data-view="table">
                    <i class="fas fa-table me-1"></i> Table
                </button>
                <button class="view-toggle-btn <?php echo $activeTab === 'calendar' ? 'active' : ''; ?>" id="btnCalendarView" data-view="calendar">
                    <i class="fas fa-calendar-alt me-1"></i> Calendar
                </button>
            </div>

            <!-- Tab strip — underline style -->
            <div class="schedule-tab-strip" id="scheduleTabs"<?php echo $activeTab === 'calendar' ? ' style="display:none;"' : ''; ?>>
                <?php
                $desktopTabDefs = [
                    ['key' => 'upcoming',  'label' => 'Upcoming',        'extra' => ''],
                    ['key' => 'completed', 'label' => 'Completed',        'extra' => ''],
                    ['key' => 'awaiting',  'label' => 'Awaiting Results', 'extra' => 'tab-warn'],
                    ['key' => 'postponed', 'label' => 'Postponed',        'extra' => 'tab-ppd'],
                ];
                foreach ($desktopTabDefs as $dtd):
                    $isActive = $activeTab === $dtd['key'];
                ?>
                <button class="sch-tab <?php echo $dtd['extra']; ?> <?php echo $isActive ? 'active' : ''; ?>"
                        data-tab="<?php echo $dtd['key']; ?>">
                    <?php echo $dtd['label']; ?>
                    <span class="sch-tab-badge"><?php echo $tabCounts[$dtd['key']]; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

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

            <!-- Calendar view -->
            <div id="calendarView"<?php echo $activeTab !== 'calendar' ? ' style="display:none;"' : ''; ?>>
                <div class="cal-search-wrap mb-2">
                    <input type="text" id="calSearchInput" class="form-control form-control-sm"
                           placeholder="Search teams…" autocomplete="off">
                </div>
                <div id="calendarEl"></div>
                <p id="calendarEmptyNote" class="text-muted mt-2 small" style="display:none;">
                    No games match your current filters.
                </p>
                <!-- Calendar legend -->
                <div class="cal-legend mt-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="cal-legend-chip cal-legend-upcoming">&#9632; Upcoming</span>
                    <span class="cal-legend-chip cal-legend-completed">&#9632; Completed</span>
                    <span class="cal-legend-chip cal-legend-awaiting">&#9632; Awaiting Result</span>
                    <span class="cal-legend-chip cal-legend-postponed">&#9632; Postponed</span>
                    <?php if (!empty($specialDateEvents)): ?>
                    <span class="cal-legend-chip cal-legend-special">&#9733; Special Date</span>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /#desktopSchedule -->
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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

    <script>
    var calendarEvents = <?php echo json_encode(
        array_merge($calendarEvents, $specialDateEvents),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT
    ); ?>;

    $(document).ready(function() {

        // ── FullCalendar init ─────────────────────────────────────────────
        var calendar = null;
        var calendarInitialized = false;
        var currentPopover = null;
        var allCalendarEvents = calendarEvents;

        function initCalendar() {
            if (calendarInitialized) return;
            calendarInitialized = true;
            var calEl = document.getElementById('calendarEl');
            calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: {
                    start: 'prev,next today',
                    center: 'title',
                    end: ''
                },
                events: calendarEvents,
                eventClick: function(info) {
                    showEventPopover(info.el, info.event);
                },
                eventDisplay: 'block',
                datesSet: function() {
                    if (currentPopover) { currentPopover.dispose(); currentPopover = null; }
                },
            });
            calendar.render();

            if (calendarEvents.length === 0) {
                document.getElementById('calendarEmptyNote').style.display = 'block';
            }
        }

        // ── Event popover ─────────────────────────────────────────────────
        function showEventPopover(el, event) {
            if (event.extendedProps && event.extendedProps.isSpecialDate) return;
            if (currentPopover) { currentPopover.dispose(); currentPopover = null; }

            var p = event.extendedProps;
            var locHtml = p.mapsUrl
                ? '<a href="' + p.mapsUrl + '" target="_blank" rel="noopener noreferrer">' + p.location + '</a>'
                : (p.location || '—');

            var content = '<div style="font-size:0.8rem;min-width:180px;">'
                + '<div class="mb-1"><strong>#' + p.gameNumber + '</strong> &nbsp;'
                + '<span class="badge ' + p.statusClass + '" style="font-size:0.65rem;">' + p.statusLabel + '</span></div>'
                + '<div class="mb-1">' + p.gameDate + (p.gameTime ? ' &middot; ' + p.gameTime : '') + '</div>'
                + '<div class="mb-1"><strong>' + p.away + '</strong> @ <strong>' + p.home + '</strong></div>'
                + '<div class="text-muted">' + locHtml + '</div>'
                + '</div>';

            currentPopover = new bootstrap.Popover(el, {
                content: content,
                html: true,
                trigger: 'manual',
                placement: 'top',
                fallbackPlacements: ['bottom', 'auto'],
                container: 'body',
            });
            currentPopover.show();
        }

        document.addEventListener('click', function(e) {
            if (currentPopover && !e.target.closest('.fc-event') && !e.target.closest('.popover')) {
                currentPopover.dispose();
                currentPopover = null;
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && currentPopover) {
                currentPopover.dispose();
                currentPopover = null;
            }
        });

        // ── Team search ───────────────────────────────────────────────────
        document.getElementById('calSearchInput').addEventListener('input', function() {
            var term = this.value.trim().toLowerCase();
            var eventsToShow = term
                ? allCalendarEvents.filter(function(ev) {
                    if (ev.extendedProps && ev.extendedProps.isSpecialDate) return true;
                    return ev.title.toLowerCase().indexOf(term) !== -1;
                  })
                : allCalendarEvents;
            calendar.removeAllEvents();
            calendar.addEventSource(eventsToShow);
            document.getElementById('calendarEmptyNote').style.display =
                eventsToShow.length === 0 ? 'block' : 'none';
        });

        // ── View toggle ───────────────────────────────────────────────────
        var activeTableTab = '<?php echo $activeTab === 'calendar' ? 'upcoming' : $activeTab; ?>';

        function showTableView() {
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('scheduleTabs').style.display = '';
            switchTab(activeTableTab);
            document.getElementById('btnTableView').classList.add('active');
            document.getElementById('btnCalendarView').classList.remove('active');
            var url = new URL(window.location);
            url.searchParams.set('tab', activeTableTab);
            history.replaceState(null, '', url);
            document.getElementById('hiddenTab').value = activeTableTab;
            if (currentPopover) { currentPopover.dispose(); currentPopover = null; }
            document.getElementById('calSearchInput').value = '';
            if (calendar) {
                calendar.removeAllEvents();
                calendar.addEventSource(allCalendarEvents);
            }
        }

        function showCalendarView() {
            document.getElementById('scheduleTabs').style.display = 'none';
            document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
            document.getElementById('calendarView').style.display = 'block';
            initCalendar();
            document.getElementById('btnCalendarView').classList.add('active');
            document.getElementById('btnTableView').classList.remove('active');
            var url = new URL(window.location);
            url.searchParams.set('tab', 'calendar');
            history.replaceState(null, '', url);
            document.getElementById('hiddenTab').value = 'calendar';
            if (calendar) {
                calendar.updateSize();
                setTimeout(function() { calendar.updateSize(); }, 50);
            }
        }

        document.getElementById('btnTableView').addEventListener('click', showTableView);
        document.getElementById('btnCalendarView').addEventListener('click', showCalendarView);

        if ('<?php echo $activeTab; ?>' === 'calendar') {
            showCalendarView();
        }

        // ── Desktop: initialize four DataTables ──────────────────────────
        var dtBase = { pageLength: 25, responsive: false };

        var dtUpcoming  = $('#tableUpcoming').length  ? $('#tableUpcoming').DataTable($.extend({}, dtBase, { order: [[1,'asc'],[2,'asc']] })) : null;
        var dtCompleted = $('#tableCompleted').length ? $('#tableCompleted').DataTable($.extend({}, dtBase, { order: [[1,'asc']] }))           : null;
        var dtAwaiting  = $('#tableAwaiting').length  ? $('#tableAwaiting').DataTable($.extend({}, dtBase, { order: [[1,'asc']] }))            : null;
        var dtPostponed = $('#tablePostponed').length ? $('#tablePostponed').DataTable($.extend({}, dtBase, { order: [[0,'asc']] }))           : null;

        var dtMap = { upcoming: dtUpcoming, completed: dtCompleted, awaiting: dtAwaiting, postponed: dtPostponed };

        // ── Desktop: tab switching ────────────────────────────────────────
        function switchTab(name) {
            activeTableTab = name;
            // Update pane visibility
            document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
            var pane = document.getElementById('pane-' + name);
            if (pane) pane.classList.add('active');

            // Update tab button active state (CSS handles badge colour via parent class)
            document.querySelectorAll('#scheduleTabs .sch-tab').forEach(function(b) {
                b.classList.remove('active');
            });
            var activeBtn = document.querySelector('#scheduleTabs [data-tab="' + name + '"]');
            if (activeBtn) activeBtn.classList.add('active');

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

        document.querySelectorAll('#scheduleTabs .sch-tab').forEach(function(btn) {
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
