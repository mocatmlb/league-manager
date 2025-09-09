<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Public Standings Page
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

// Validate filters
$filterValidation = FilterHelpers::validateFilters($filters);
if (!$filterValidation['valid']) {
    // Invalid filters provided, redirect to base standings page
    header('Location: standings.php');
    exit;
}

// Get filter options for dropdowns
$programs = FilterHelpers::getActivePrograms();
$seasons = FilterHelpers::getSeasons($filters['program_id']);
$divisions = FilterHelpers::getDivisions($filters['season_id']);

// Build custom filter conditions for standings (no games table)
$filterConditions = [];
$filterParams = [];

if (!empty($filters['program_id'])) {
    $filterConditions[] = "p.program_id = ?";
    $filterParams[] = $filters['program_id'];
}

if (!empty($filters['season_id'])) {
    $filterConditions[] = "s.season_id = ?";
    $filterParams[] = $filters['season_id'];
}

if (!empty($filters['division_id'])) {
    $filterConditions[] = "d.division_id = ?";
    $filterParams[] = $filters['division_id'];
}

// Get all divisions with their standings
$divisionsQuery = "SELECT d.division_id, d.division_name, d.season_id,
                          s.season_name, s.season_year, p.program_name, p.program_id
                   FROM divisions d
                   JOIN seasons s ON d.season_id = s.season_id
                   JOIN programs p ON s.program_id = p.program_id
                   WHERE s.season_status IN ('Active', 'Planning', 'Registration')";

// Add filter conditions
if (!empty($filterConditions)) {
    $divisionsQuery .= ' AND ' . implode(' AND ', $filterConditions);
}

$divisionsQuery .= " ORDER BY p.program_name, s.season_year DESC, s.season_name, d.division_name";

$divisions = $db->fetchAll($divisionsQuery, $filterParams);

$pageTitle = "Standings - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>League Standings</h1>
                <p class="lead">Current standings for all active divisions</p>
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

        <?php if (empty($divisions)): ?>
            <div class="alert alert-info">
                <h4>No Divisions Found</h4>
                <p>No divisions match your current filter criteria. Try adjusting your filters or check back during the season.</p>
            </div>
        <?php else: ?>
            <?php 
            $currentProgram = null;
            $currentSeason = null;
            foreach ($divisions as $division): 
                // Add program header if it's a new program
                if ($currentProgram !== $division['program_name']):
                    if ($currentProgram !== null) echo '</div>'; // Close previous program div
                    $currentProgram = $division['program_name'];
                    $currentSeason = null; // Reset season tracking
            ?>
                <div class="program-section mb-4">
                    <h2 class="program-header"><?php echo sanitize($division['program_name']); ?></h2>
            <?php 
                endif;
                
                // Add season header if it's a new season
                if ($currentSeason !== $division['season_id']):
                    $currentSeason = $division['season_id'];
            ?>
                    <h3 class="season-header mt-3 mb-3">
                        <?php echo sanitize($division['season_name'] . ' ' . $division['season_year']); ?>
                    </h3>
            <?php 
                endif;
                
                $standings = getDivisionStandings($division['division_id']);
            ?>
                <div class="division-section mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><?php echo sanitize($division['division_name']); ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($standings)): ?>
                                <p class="text-muted">No teams or games recorded for this division yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped standings-table">
                                        <thead>
                                            <tr>
                                                <th>Place</th>
                                                <th>Team</th>
                                                <th>Won</th>
                                                <th>Lost</th>
                                                <th>Tied</th>
                                                <th>Games Back</th>
                                                <th>RS</th>
                                                <th>RA</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $place = 1;
                                            foreach ($standings as $team): 
                                                $placeClass = '';
                                                if ($place === 1) {
                                                    $placeClass = 'place-1';
                                                } elseif ($place === 2) {
                                                    $placeClass = 'place-2';
                                                }
                                            ?>
                                            <tr class="<?php echo $placeClass; ?>">
                                                <td>
                                                    <strong><?php echo $place; ?></strong>
                                                    <?php if ($place === 1): ?>
                                                        <small class="text-warning">👑</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo sanitize($team['team_name'] ?: $team['league_name']); ?></strong>
                                                    <?php if ($team['team_name'] && $team['league_name']): ?>
                                                        <br><small class="text-muted"><?php echo sanitize($team['league_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo $team['wins']; ?></strong></td>
                                                <td><?php echo $team['losses']; ?></td>
                                                <td><?php echo $team['ties']; ?></td>
                                                <td>
                                                    <?php if ($team['games_back'] == 0): ?>
                                                        <span class="text-muted">-</span>
                                                    <?php else: ?>
                                                        <?php echo number_format($team['games_back'], 1); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $team['runs_scored']; ?></td>
                                                <td><?php echo $team['runs_against']; ?></td>
                                            </tr>
                                            <?php 
                                            $place++;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div><!-- Close last program div -->
        <?php endif; ?>

        <!-- Additional Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5>How Standings Work</h5>
                        <ul class="mb-0">
                            <li><strong>Wins/Losses/Ties:</strong> Based on completed games only</li>
                            <li><strong>Games Back:</strong> Calculated as (Leader Wins - Team Wins + Team Losses - Leader Losses) ÷ 2</li>
                            <li><strong>Tie Breaking:</strong> Teams with identical records are ordered by runs scored</li>
                            <li><strong>Updates:</strong> Standings are updated immediately when scores are entered</li>
                        </ul>
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
    
    <script>
        $(document).ready(function() {
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