<?php
/**
 * District 8 Travel League - Public Schedule Page
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Initialize filter helpers
FilterHelpers::init();

// Get filter values from URL
$filters = FilterHelpers::getFilterValues();

// Validate filters
$filterValidation = FilterHelpers::validateFilters($filters);
if (!$filterValidation['valid']) {
    // Invalid filters provided, redirect to base schedule page
    header('Location: schedule.php');
    exit;
}

// Get filter options for dropdowns
$programs = FilterHelpers::getActivePrograms();
$seasons = FilterHelpers::getSeasons($filters['program_id']);
$divisions = FilterHelpers::getDivisions($filters['season_id']);

// Build SQL conditions based on filters
$filterSql = FilterHelpers::buildFilterConditions($filters);

// Get all games with schedule information
$sql = "SELECT g.game_number, g.game_status, g.home_score, g.away_score,
               s.game_date, s.game_time, s.location,
               ht.team_name as home_team, ht.league_name as home_league,
               at.team_name as away_team, at.league_name as away_league,
               d.division_name, p.program_name, se.season_name
        FROM games g
        JOIN schedules s ON g.game_id = s.game_id
        JOIN teams ht ON g.home_team_id = ht.team_id
        JOIN teams at ON g.away_team_id = at.team_id
        JOIN divisions d ON g.division_id = d.division_id
        JOIN seasons se ON g.season_id = se.season_id
        JOIN programs p ON se.program_id = p.program_id
        WHERE ht.active_status = 'Active' AND at.active_status = 'Active'
        " . $filterSql['conditions'] . "
        ORDER BY s.game_date DESC, s.game_time DESC";

$games = $db->fetchAll($sql, $filterSql['params']);

$pageTitle = "Schedule - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/../assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>Game Schedule</h1>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="tableViewBtn">
                            Table View
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="calendarViewBtn">
                            Calendar View
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="filterForm" method="get" class="row g-3">
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

        <!-- Table View -->
        <div id="tableView">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($games)): ?>
                        <div class="alert alert-info">
                            <h4>No Games Found</h4>
                            <p>No games match your current filter criteria. Try adjusting your filters or check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="scheduleTable" class="table table-striped table-hover schedule-table">
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
                                        <th>Program</th>
                                        <th>Division</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($games as $game): ?>
                                    <tr>
                                        <td><?php echo sanitize($game['game_number']); ?></td>
                                        <td data-sort="<?php echo $game['game_date']; ?>">
                                            <?php echo formatDate($game['game_date']); ?>
                                        </td>
                                        <td><?php echo formatTime($game['game_time']); ?></td>
                                        <td>
                                            <?php echo sanitize($game['away_team'] ?: $game['away_league']); ?>
                                        </td>
                                        <td>
                                            <?php echo sanitize($game['home_team'] ?: $game['home_league']); ?>
                                        </td>
                                        <td><?php echo sanitize($game['location']); ?></td>
                                        <td>
                                            <?php if ($game['game_status'] === 'Completed' && $game['away_score'] !== null): ?>
                                                <?php echo $game['away_score']; ?> - <?php echo $game['home_score']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($game['game_status']) {
                                                case 'Active':
                                                    $statusClass = 'status-active';
                                                    break;
                                                case 'Completed':
                                                    $statusClass = 'status-completed';
                                                    break;
                                                case 'Cancelled':
                                                    $statusClass = 'status-cancelled';
                                                    break;
                                                case 'Scheduled':
                                                    $statusClass = 'status-scheduled';
                                                    break;
                                                case 'Created':
                                                    $statusClass = 'status-created';
                                                    break;
                                                case 'Pending Change':
                                                    $statusClass = 'status-pending-change';
                                                    break;
                                                case 'Postponed':
                                                    $statusClass = 'status-postponed';
                                                    break;
                                                default:
                                                    $statusClass = 'status-default';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo sanitize($game['game_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($game['program_name']); ?></td>
                                        <td><?php echo sanitize($game['division_name']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Calendar View (placeholder for now) -->
        <div id="calendarView" style="display: none;">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info">
                        <h4>Calendar View</h4>
                        <p>Calendar view will be implemented in a future update. For now, please use the table view above.</p>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#scheduleTable').DataTable({
                order: [[1, 'desc']], // Sort by date descending
                pageLength: 25,
                responsive: true,
                language: {
                    search: "Search games:",
                    lengthMenu: "Show _MENU_ games per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ games",
                    emptyTable: "No games found"
                }
            });

            // View toggle functionality
            $('#tableViewBtn').click(function() {
                $('#tableView').show();
                $('#calendarView').hide();
                $(this).addClass('active');
                $('#calendarViewBtn').removeClass('active');
            });

            $('#calendarViewBtn').click(function() {
                $('#tableView').hide();
                $('#calendarView').show();
                $(this).addClass('active');
                $('#tableViewBtn').removeClass('active');
            });

            // Filter change handlers
            $('#program').change(function() {
                const programId = $(this).val();
                updateSeasonDropdown(programId);
                $('#filterForm').submit();
            });

            $('#season').change(function() {
                const seasonId = $(this).val();
                updateDivisionDropdown(seasonId);
                $('#filterForm').submit();
            });

            $('#division').change(function() {
                $('#filterForm').submit();
            });

            // Function to update season dropdown based on program selection
            function updateSeasonDropdown(programId) {
                const $seasonSelect = $('#season');
                $seasonSelect.prop('disabled', !programId);
                $seasonSelect.val('');
                $('#division').prop('disabled', true).val('');
            }

            // Function to update division dropdown based on season selection
            function updateDivisionDropdown(seasonId) {
                const $divisionSelect = $('#division');
                $divisionSelect.prop('disabled', !seasonId);
                $divisionSelect.val('');
            }
        });
    </script>
</body>
</html>