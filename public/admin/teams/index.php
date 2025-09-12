<?php
/**
 * District 8 Travel League - Teams Management
 */

// Environment-aware bootstrap include (production vs development)
$__bootstrap = file_exists(__DIR__ . '/../../includes/bootstrap.php')
    ? __DIR__ . '/../../includes/bootstrap.php'      // Production: /admin/teams -> ../../includes
    : __DIR__ . '/../../../includes/bootstrap.php';   // Development: /public/admin/teams -> ../../../includes
require_once $__bootstrap;
unset($__bootstrap);

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
            case 'add_team':
                try {
                    $leagueName = sanitize($_POST['league_name']);
                    $managerLastName = sanitize($_POST['manager_last_name']);
                    
                    // If team name is not provided, generate it from league name and manager last name
                    $teamName = sanitize($_POST['team_name']);
                    if (empty($teamName)) {
                        $teamName = $leagueName . '-' . $managerLastName;
                    }
                    
                    $teamData = [
                        'team_name' => $teamName,
                        'league_name' => $leagueName,
                        'division_id' => (int)$_POST['division_id'],
                        'season_id' => (int)$_POST['season_id'],
                        'manager_first_name' => sanitize($_POST['manager_first_name']),
                        'manager_last_name' => $managerLastName,
                        'manager_phone' => sanitize($_POST['manager_phone']),
                        'manager_email' => sanitize($_POST['manager_email']),
                        'active_status' => 'Active'
                    ];
                    
                    $teamId = $db->insert('teams', $teamData);
                    Logger::info("Team created successfully", [
                        'team_id' => $teamId,
                        'team_name' => $teamData['team_name'],
                        'league_name' => $teamData['league_name'],
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $message = 'Team created successfully!';
                } catch (Exception $e) {
                    Logger::error("Team creation failed", [
                        'error' => $e->getMessage(),
                        'team_data' => $teamData ?? [],
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $error = 'Error creating team: ' . $e->getMessage();
                }
                break;
                
            case 'update_team':
                try {
                    $teamId = (int)$_POST['team_id'];
                    
                    $leagueName = sanitize($_POST['league_name']);
                    $managerLastName = sanitize($_POST['manager_last_name']);
                    
                    // If team name is not provided, generate it from league name and manager last name
                    $teamName = sanitize($_POST['team_name']);
                    if (empty($teamName)) {
                        $teamName = $leagueName . '-' . $managerLastName;
                    }
                    
                    $teamData = [
                        'team_name' => $teamName,
                        'league_name' => $leagueName,
                        'division_id' => (int)$_POST['division_id'],
                        'manager_first_name' => sanitize($_POST['manager_first_name']),
                        'manager_last_name' => $managerLastName,
                        'manager_phone' => sanitize($_POST['manager_phone']),
                        'manager_email' => sanitize($_POST['manager_email']),
                        'active_status' => sanitize($_POST['active_status'])
                    ];
                    
                    $db->update('teams', $teamData, 'team_id = :team_id', ['team_id' => $teamId]);
                    Logger::info("Team updated successfully", [
                        'team_id' => $teamId,
                        'team_name' => $teamData['team_name'],
                        'league_name' => $teamData['league_name'],
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $message = 'Team updated successfully!';
                } catch (Exception $e) {
                    Logger::error("Team update failed", [
                        'team_id' => $teamId ?? 'unknown',
                        'error' => $e->getMessage(),
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $error = 'Error updating team: ' . $e->getMessage();
                }
                break;
                
            case 'delete_team':
                try {
                    $teamId = (int)$_POST['team_id'];
                    
                    // Check if team is assigned to any games
                    $gameCount = $db->fetchOne("
                        SELECT COUNT(*) as count 
                        FROM games 
                        WHERE home_team_id = ? OR away_team_id = ?
                    ", [$teamId, $teamId]);
                    
                    if ($gameCount['count'] > 0) {
                        // Get specific games for better error message
                        $assignedGames = $db->fetchAll("
                            SELECT g.game_id, g.game_number, s.game_date, s.game_time,
                                   ht.team_name as home_team, at.team_name as away_team
                            FROM games g
                            LEFT JOIN schedules s ON g.game_id = s.game_id
                            LEFT JOIN teams ht ON g.home_team_id = ht.team_id
                            LEFT JOIN teams at ON g.away_team_id = at.team_id
                            WHERE g.home_team_id = ? OR g.away_team_id = ?
                            ORDER BY s.game_date, s.game_time
                            LIMIT 5
                        ", [$teamId, $teamId]);
                        
                        $gamesList = [];
                        foreach ($assignedGames as $game) {
                            $gamesList[] = "Game #{$game['game_number']} ({$game['home_team']} vs {$game['away_team']})";
                        }
                        
                        $error = 'Cannot delete team: Team is assigned to ' . $gameCount['count'] . ' game(s). ';
                        if (count($assignedGames) > 0) {
                            $error .= 'Examples: ' . implode(', ', array_slice($gamesList, 0, 3));
                            if ($gameCount['count'] > 3) {
                                $error .= ' and ' . ($gameCount['count'] - 3) . ' more';
                            }
                            $error .= '. ';
                        }
                        $error .= 'Please remove the team from all games first.';
                        
                        Logger::warn("Team deletion blocked - has game assignments", [
                            'team_id' => $teamId,
                            'game_count' => $gameCount['count'],
                            'assigned_games' => $gamesList,
                            'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                        ]);
                    } else {
                        // Get team info for logging before deletion
                        $teamInfo = $db->fetchOne("SELECT team_name, league_name FROM teams WHERE team_id = ?", [$teamId]);
                        
                        // Safe to delete - no game assignments
                        $db->delete('teams', 'team_id = ?', [$teamId]);
                        
                        Logger::info("Team deleted successfully", [
                            'team_id' => $teamId,
                            'team_name' => $teamInfo['team_name'] ?? 'unknown',
                            'league_name' => $teamInfo['league_name'] ?? 'unknown',
                            'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                        ]);
                        
                        $message = 'Team deleted successfully!';
                    }
                } catch (Exception $e) {
                    Logger::error("Team deletion failed", [
                        'team_id' => $teamId ?? 'unknown',
                        'error' => $e->getMessage(),
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown'
                    ]);
                    $error = 'Error deleting team: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get teams with division and season info
$teams = $db->fetchAll("
    SELECT t.*, d.division_name, s.season_name,
           CONCAT(t.manager_first_name, ' ', t.manager_last_name) as manager_name
    FROM teams t
    JOIN divisions d ON t.division_id = d.division_id
    JOIN seasons s ON t.season_id = s.season_id
    ORDER BY s.season_name DESC, d.division_name, t.team_name
");

// Get data for dropdowns
$programs = $db->fetchAll("
    SELECT program_id, program_name, sport_type, active_status
    FROM programs 
    WHERE active_status = 'Active'
    ORDER BY program_name
");

// Get all seasons grouped by program for JavaScript
$allSeasons = $db->fetchAll("
    SELECT s.season_id, s.season_name, s.season_year, s.program_id, s.season_status
    FROM seasons s
    WHERE s.season_status IN ('Planning', 'Registration', 'Active')
    ORDER BY s.season_year DESC, s.season_name
");

// Get all divisions grouped by season for JavaScript  
$allDivisions = $db->fetchAll("
    SELECT d.division_id, d.division_name, d.division_code, d.season_id
    FROM divisions d
    ORDER BY d.division_name
");

$pageTitle = "Teams Management - " . APP_NAME;
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
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/teams -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/teams -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Teams Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                        <i class="fas fa-plus"></i> Add New Team
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

                <!-- Teams Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="teamsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Team Name</th>
                                    <th>League</th>
                                    <th>Division</th>
                                    <th>Season</th>
                                    <th>Manager</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($team['team_name']); ?></strong></td>
                                    <td><?php echo sanitize($team['league_name']); ?></td>
                                    <td><?php echo sanitize($team['division_name']); ?></td>
                                    <td><?php echo sanitize($team['season_name']); ?></td>
                                    <td>
                                        <?php if ($team['manager_name']): ?>
                                            <?php echo sanitize($team['manager_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($team['manager_phone'] || $team['manager_email']): ?>
                                            <?php if ($team['manager_phone']): ?>
                                                <i class="fas fa-phone"></i> <?php echo sanitize($team['manager_phone']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($team['manager_email']): ?>
                                                <i class="fas fa-envelope"></i> <?php echo sanitize($team['manager_email']); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No contact info</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $team['active_status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $team['active_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editTeam(<?php echo htmlspecialchars(json_encode($team)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTeam(<?php echo $team['team_id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>', '<?php echo htmlspecialchars($team['league_name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
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

    <!-- Add Team Modal -->
    <div class="modal fade" id="addTeamModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Team</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_team">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <!-- League Name (Required) -->
                        <div class="mb-3">
                            <label class="form-label">League Name *</label>
                            <input type="text" name="league_name" class="form-control" required>
                        </div>

                        <!-- Team Name (Optional) -->
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" name="team_name" class="form-control" id="addTeamName">
                            <div class="form-text">
                                Optional. If not provided, team will be identified as "League Name-Manager Last Name"
                            </div>
                        </div>
                        
                        <!-- Manager Contact Info (Required) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager First Name *</label>
                                    <input type="text" name="manager_first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Last Name *</label>
                                    <input type="text" name="manager_last_name" class="form-control" required 
                                           onchange="updateDefaultTeamName()">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Phone *</label>
                                    <input type="tel" name="manager_phone" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Email *</label>
                                    <input type="email" name="manager_email" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Program Assignment</h6>
                        
                        <!-- Program Selection (Always visible, required) -->
                        <div class="mb-3">
                            <label class="form-label">Program *</label>
                            <div class="d-flex">
                                <select name="program_id" id="programSelect" class="form-select" required>
                                    <option value="">Select Program</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['program_id']; ?>">
                                            <?php echo sanitize($program['program_name']); ?> 
                                            (<?php echo sanitize($program['sport_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="showCreateProgramModal()">
                                    <i class="fas fa-plus"></i> New
                                </button>
                            </div>
                            <div id="noProgramsError" class="alert alert-warning mt-2" style="display: none;">
                                No programs available. Please create a program first.
                            </div>
                        </div>
                        
                        <!-- Season Selection (Becomes available after program selection) -->
                        <div class="mb-3">
                            <label class="form-label">Season *</label>
                            <div class="d-flex">
                                <select name="season_id" id="seasonSelect" class="form-select" required disabled>
                                    <option value="">Select Program First</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="showCreateSeasonModal()" disabled id="newSeasonBtn">
                                    <i class="fas fa-plus"></i> New
                                </button>
                            </div>
                            <div id="noSeasonsError" class="alert alert-warning mt-2" style="display: none;">
                                No seasons available for this program. Please create a season first.
                            </div>
                        </div>
                        
                        <!-- Division Selection (Becomes available after season selection) -->
                        <div class="mb-3">
                            <label class="form-label">Division *</label>
                            <div class="d-flex">
                                <select name="division_id" id="divisionSelect" class="form-select" required disabled>
                                    <option value="">Select Season First</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="showCreateDivisionModal()" disabled id="newDivisionBtn">
                                    <i class="fas fa-plus"></i> New
                                </button>
                            </div>
                            <div id="noDivisionsError" class="alert alert-warning mt-2" style="display: none;">
                                No divisions available for this season. Please create a division first.
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div class="modal fade" id="editTeamModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Team</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_team">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="team_id" id="editTeamId">
                        
                        <!-- League Name (Required) -->
                        <div class="mb-3">
                            <label class="form-label">League Name *</label>
                            <input type="text" name="league_name" id="editLeagueName" class="form-control" required>
                        </div>

                        <!-- Team Name -->
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" name="team_name" id="editTeamName" class="form-control">
                            <div class="form-text">
                                Leave blank to use "League Name-Manager Last Name" format
                            </div>
                        </div>
                        
                        <!-- Manager Contact Info (Required) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager First Name *</label>
                                    <input type="text" name="manager_first_name" id="editManagerFirstName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Last Name *</label>
                                    <input type="text" name="manager_last_name" id="editManagerLastName" class="form-control" required
                                           onchange="updateDefaultTeamName('edit')">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Phone *</label>
                                    <input type="tel" name="manager_phone" id="editManagerPhone" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Manager Email *</label>
                                    <input type="email" name="manager_email" id="editManagerEmail" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Program Assignment</h6>
                        
                        <!-- Program Selection -->
                        <div class="mb-3">
                            <label class="form-label">Program *</label>
                            <select name="program_id" id="editProgramSelect" class="form-select" required>
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo $program['program_id']; ?>">
                                        <?php echo sanitize($program['program_name']); ?> 
                                        (<?php echo sanitize($program['sport_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Season Selection -->
                        <div class="mb-3">
                            <label class="form-label">Season *</label>
                            <select name="season_id" id="editSeasonSelect" class="form-select" required>
                                <option value="">Select Program First</option>
                            </select>
                        </div>
                        
                        <!-- Division Selection -->
                        <div class="mb-3">
                            <label class="form-label">Division *</label>
                            <select name="division_id" id="editDivisionSelect" class="form-select" required>
                                <option value="">Select Season First</option>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="active_status" id="editActiveStatus" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Team Confirmation Modal -->
    <div class="modal fade" id="deleteTeamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Team Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_team">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="team_id" id="deleteTeamId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone.
                        </div>
                        
                        <p>Are you sure you want to delete the following team?</p>
                        
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title" id="deleteTeamName"></h6>
                                <p class="card-text">
                                    <strong>League:</strong> <span id="deleteLeagueName"></span><br>
                                    <strong>Team ID:</strong> <span id="deleteTeamIdDisplay"></span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> Teams assigned to games cannot be deleted. 
                                You must remove the team from all games first.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Function to update team name based on league name and manager last name
        function updateDefaultTeamName(mode = 'add') {
            const prefix = mode === 'edit' ? 'edit' : '';
            const leagueInput = document.getElementById(prefix + 'LeagueName');
            const lastNameInput = document.getElementById(prefix + 'ManagerLastName');
            const teamNameInput = document.getElementById(prefix + 'TeamName');
            
            // Only update if team name is empty
            if (teamNameInput.value.trim() === '' && leagueInput.value && lastNameInput.value) {
                teamNameInput.value = leagueInput.value + '-' + lastNameInput.value;
            }
        }

        // Add event listeners for dynamic team name updates
        document.addEventListener('DOMContentLoaded', function() {
            // Add Team form
            const addLeagueInput = document.querySelector('input[name="league_name"]');
            if (addLeagueInput) {
                addLeagueInput.addEventListener('change', () => updateDefaultTeamName());
            }

            // Edit Team form
            const editLeagueInput = document.getElementById('editLeagueName');
            if (editLeagueInput) {
                editLeagueInput.addEventListener('change', () => updateDefaultTeamName('edit'));
            }
        });

        // Data for cascading dropdowns
        const seasonsData = <?php echo json_encode($allSeasons); ?>;
        const divisionsData = <?php echo json_encode($allDivisions); ?>;
        const programsCount = <?php echo count($programs); ?>;
        
        $(document).ready(function() {
            // Initialize DataTable
            $('#teamsTable').DataTable({
                order: [[3, 'desc'], [2, 'asc'], [0, 'asc']],
                pageLength: 25
            });
            
            // Initialize cascading dropdowns
            checkProgramsAvailable();
            setupCascadingDropdowns();
        });
        
        function checkProgramsAvailable() {
            if (programsCount === 0) {
                $('#noProgramsError').show();
                $('#programSelect').prop('disabled', true);
            }
        }
        
        function setupCascadingDropdowns() {
            // Add team modal dropdowns
            $('#programSelect').on('change', function() {
                const programId = $(this).val();
                updateSeasonDropdown(programId);
            });
            
            $('#seasonSelect').on('change', function() {
                const seasonId = $(this).val();
                updateDivisionDropdown(seasonId);
            });
            
            // Edit team modal dropdowns
            $('#editProgramSelect').on('change', function() {
                const programId = $(this).val();
                updateEditSeasonDropdown(programId);
            });
            
            $('#editSeasonSelect').on('change', function() {
                const seasonId = $(this).val();
                updateEditDivisionDropdown(seasonId);
            });
        }
        
        function updateSeasonDropdown(programId) {
            const $seasonSelect = $('#seasonSelect');
            const $newSeasonBtn = $('#newSeasonBtn');
            const $noSeasonsError = $('#noSeasonsError');
            
            // Clear existing options
            $seasonSelect.html('<option value="">Select Season</option>');
            
            if (!programId) {
                $seasonSelect.prop('disabled', true);
                $newSeasonBtn.prop('disabled', true);
                $noSeasonsError.hide();
                updateDivisionDropdown('');
                return;
            }
            
            // Filter seasons for selected program
            const programSeasons = seasonsData.filter(season => season.program_id == programId);
            
            if (programSeasons.length === 0) {
                $seasonSelect.prop('disabled', true);
                $newSeasonBtn.prop('disabled', false);
                $noSeasonsError.show();
                updateDivisionDropdown('');
                return;
            }
            
            // Add season options
            programSeasons.forEach(season => {
                $seasonSelect.append(`<option value="${season.season_id}">${season.season_name} ${season.season_year}</option>`);
            });
            
            $seasonSelect.prop('disabled', false);
            $newSeasonBtn.prop('disabled', false);
            $noSeasonsError.hide();
            
            // Auto-select if only one season
            if (programSeasons.length === 1) {
                $seasonSelect.val(programSeasons[0].season_id);
                updateDivisionDropdown(programSeasons[0].season_id);
            } else {
                updateDivisionDropdown('');
            }
        }
        
        function updateDivisionDropdown(seasonId) {
            const $divisionSelect = $('#divisionSelect');
            const $newDivisionBtn = $('#newDivisionBtn');
            const $noDivisionsError = $('#noDivisionsError');
            
            // Clear existing options
            $divisionSelect.html('<option value="">Select Division</option>');
            
            if (!seasonId) {
                $divisionSelect.prop('disabled', true);
                $newDivisionBtn.prop('disabled', true);
                $noDivisionsError.hide();
                return;
            }
            
            // Filter divisions for selected season
            const seasonDivisions = divisionsData.filter(division => division.season_id == seasonId);
            
            if (seasonDivisions.length === 0) {
                $divisionSelect.prop('disabled', true);
                $newDivisionBtn.prop('disabled', false);
                $noDivisionsError.show();
                return;
            }
            
            // Add division options
            seasonDivisions.forEach(division => {
                let optionText = division.division_name;
                if (division.division_code) {
                    optionText += ` (${division.division_code})`;
                }
                $divisionSelect.append(`<option value="${division.division_id}">${optionText}</option>`);
            });
            
            $divisionSelect.prop('disabled', false);
            $newDivisionBtn.prop('disabled', false);
            $noDivisionsError.hide();
            
            // Auto-select if only one division
            if (seasonDivisions.length === 1) {
                $divisionSelect.val(seasonDivisions[0].division_id);
            }
        }
        
        // Modal functions
        function showCreateProgramModal() {
            alert('Redirecting to Programs page to create a new program...');
            window.open('../programs/', '_blank');
        }
        
        function showCreateSeasonModal() {
            const programId = $('#programSelect').val();
            if (!programId) {
                alert('Please select a program first');
                return;
            }
            alert('Redirecting to Seasons page to create a new season...');
            window.open('../seasons/', '_blank');
        }
        
        function showCreateDivisionModal() {
            const seasonId = $('#seasonSelect').val();
            if (!seasonId) {
                alert('Please select a season first');
                return;
            }
            alert('Redirecting to Divisions page to create a new division...');
            window.open('../divisions/', '_blank');
        }
        
        // Edit modal cascading dropdown functions
        function updateEditSeasonDropdown(programId) {
            const $seasonSelect = $('#editSeasonSelect');
            
            // Clear existing options
            $seasonSelect.html('<option value="">Select Season</option>');
            
            if (!programId) {
                $seasonSelect.prop('disabled', true);
                updateEditDivisionDropdown('');
                return;
            }
            
            // Filter seasons for selected program
            const programSeasons = seasonsData.filter(season => season.program_id == programId);
            
            if (programSeasons.length === 0) {
                $seasonSelect.prop('disabled', true);
                updateEditDivisionDropdown('');
                return;
            }
            
            // Add season options
            programSeasons.forEach(season => {
                $seasonSelect.append(`<option value="${season.season_id}">${season.season_name} ${season.season_year}</option>`);
            });
            
            $seasonSelect.prop('disabled', false);
            
            // Don't auto-select in edit mode - let user choose
            updateEditDivisionDropdown('');
        }
        
        function updateEditDivisionDropdown(seasonId) {
            const $divisionSelect = $('#editDivisionSelect');
            
            // Clear existing options
            $divisionSelect.html('<option value="">Select Division</option>');
            
            if (!seasonId) {
                $divisionSelect.prop('disabled', true);
                return;
            }
            
            // Filter divisions for selected season
            const seasonDivisions = divisionsData.filter(division => division.season_id == seasonId);
            
            if (seasonDivisions.length === 0) {
                $divisionSelect.prop('disabled', true);
                return;
            }
            
            // Add division options
            seasonDivisions.forEach(division => {
                let optionText = division.division_name;
                if (division.division_code) {
                    optionText += ` (${division.division_code})`;
                }
                $divisionSelect.append(`<option value="${division.division_id}">${optionText}</option>`);
            });
            
            $divisionSelect.prop('disabled', false);
        }

        function editTeam(team) {
            // Set basic team info
            document.getElementById('editTeamId').value = team.team_id;
            document.getElementById('editTeamName').value = team.team_name;
            document.getElementById('editLeagueName').value = team.league_name;
            document.getElementById('editActiveStatus').value = team.active_status;
            document.getElementById('editManagerFirstName').value = team.manager_first_name || '';
            document.getElementById('editManagerLastName').value = team.manager_last_name || '';
            document.getElementById('editManagerPhone').value = team.manager_phone || '';
            document.getElementById('editManagerEmail').value = team.manager_email || '';
            
            // Find the team's current division, season, and program
            const currentDivision = divisionsData.find(d => d.division_id == team.division_id);
            if (currentDivision) {
                const currentSeason = seasonsData.find(s => s.season_id == currentDivision.season_id);
                if (currentSeason) {
                    // Set program first
                    $('#editProgramSelect').val(currentSeason.program_id);
                    
                    // Update season dropdown for this program
                    updateEditSeasonDropdown(currentSeason.program_id);
                    
                    // Set season after dropdown is populated
                    setTimeout(() => {
                        $('#editSeasonSelect').val(currentSeason.season_id);
                        
                        // Update division dropdown for this season
                        updateEditDivisionDropdown(currentSeason.season_id);
                        
                        // Set division after dropdown is populated
                        setTimeout(() => {
                            $('#editDivisionSelect').val(team.division_id);
                        }, 100);
                    }, 100);
                }
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editTeamModal'));
            editModal.show();
        }
        
        function deleteTeam(teamId, teamName, leagueName) {
            // Populate the delete modal with team information
            document.getElementById('deleteTeamId').value = teamId;
            document.getElementById('deleteTeamName').textContent = teamName;
            document.getElementById('deleteLeagueName').textContent = leagueName;
            document.getElementById('deleteTeamIdDisplay').textContent = teamId;
            
            // Show the delete confirmation modal
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteTeamModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
