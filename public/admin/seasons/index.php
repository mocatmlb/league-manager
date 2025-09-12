<?php
// Environment-aware bootstrap include (production vs development)
$__bootstrap = file_exists(__DIR__ . '/../../includes/bootstrap.php')
    ? __DIR__ . '/../../includes/bootstrap.php'      // Production: /admin/seasons -> ../../includes
    : __DIR__ . '/../../../includes/bootstrap.php';   // Development: /public/admin/seasons -> ../../../includes
require_once $__bootstrap;
unset($__bootstrap);

// Check admin authentication
if (!Auth::isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'add_season':
                    $data = [
                        'program_id' => (int)$_POST['program_id'],
                        'season_name' => sanitize($_POST['season_name']),
                        'season_year' => (int)$_POST['season_year'],
                        'start_date' => sanitize($_POST['start_date']) ?: null,
                        'end_date' => sanitize($_POST['end_date']) ?: null,
                        'registration_start' => sanitize($_POST['registration_start']) ?: null,
                        'registration_end' => sanitize($_POST['registration_end']) ?: null,
                        'season_status' => sanitize($_POST['season_status'])
                    ];
                    
                    $db->insert('seasons', $data);
                    $message = 'Season added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_season':
                    $seasonId = (int)$_POST['season_id'];
                    $data = [
                        'program_id' => (int)$_POST['program_id'],
                        'season_name' => sanitize($_POST['season_name']),
                        'season_year' => (int)$_POST['season_year'],
                        'start_date' => sanitize($_POST['start_date']) ?: null,
                        'end_date' => sanitize($_POST['end_date']) ?: null,
                        'registration_start' => sanitize($_POST['registration_start']) ?: null,
                        'registration_end' => sanitize($_POST['registration_end']) ?: null,
                        'season_status' => sanitize($_POST['season_status'])
                    ];
                    
                    $db->update('seasons', $data, 'season_id = :season_id', ['season_id' => $seasonId]);
                    $message = 'Season updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_season':
                    $seasonId = (int)$_POST['season_id'];
                    
                    // Check for dependent records
                    $dependencies = [];
                    
                    // Check for teams
                    $teamCount = $db->fetchOne("SELECT COUNT(*) as count FROM teams WHERE season_id = ?", [$seasonId]);
                    if ($teamCount['count'] > 0) {
                        $teams = $db->fetchAll("SELECT team_name FROM teams WHERE season_id = ? ORDER BY team_name", [$seasonId]);
                        $dependencies['teams'] = [
                            'count' => $teamCount['count'],
                            'items' => array_column($teams, 'team_name')
                        ];
                    }
                    
                    // Check for games
                    $gameCount = $db->fetchOne("SELECT COUNT(*) as count FROM games WHERE season_id = ?", [$seasonId]);
                    if ($gameCount['count'] > 0) {
                        $games = $db->fetchAll("SELECT game_number FROM games WHERE season_id = ? ORDER BY game_number", [$seasonId]);
                        $dependencies['games'] = [
                            'count' => $gameCount['count'],
                            'items' => array_column($games, 'game_number')
                        ];
                    }
                    
                    // Check for divisions
                    $divisionCount = $db->fetchOne("SELECT COUNT(*) as count FROM divisions WHERE season_id = ?", [$seasonId]);
                    if ($divisionCount['count'] > 0) {
                        $divisions = $db->fetchAll("SELECT division_name FROM divisions WHERE season_id = ? ORDER BY division_name", [$seasonId]);
                        $dependencies['divisions'] = [
                            'count' => $divisionCount['count'],
                            'items' => array_column($divisions, 'division_name')
                        ];
                    }
                    
                    if (!empty($dependencies)) {
                        // Cannot delete - has dependencies
                        $errorMessage = "Cannot delete season. The following items must be deleted first:\n\n";
                        
                        foreach ($dependencies as $type => $data) {
                            $errorMessage .= ucfirst($type) . " ({$data['count']}): " . implode(', ', array_slice($data['items'], 0, 10));
                            if (count($data['items']) > 10) {
                                $errorMessage .= " and " . (count($data['items']) - 10) . " more...";
                            }
                            $errorMessage .= "\n\n";
                        }
                        
                        throw new Exception($errorMessage);
                    }
                    
                    // Safe to delete
                    $db->delete('seasons', 'season_id = ?', [$seasonId]);
                    $message = 'Season deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all seasons with program information, grouped by year
$seasons = $db->fetchAll("
    SELECT s.*, p.program_name, p.sport_type,
           COUNT(DISTINCT t.team_id) as team_count,
           COUNT(DISTINCT g.game_id) as game_count
    FROM seasons s
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN teams t ON s.season_id = t.season_id
    LEFT JOIN games g ON s.season_id = g.season_id
    GROUP BY s.season_id
    ORDER BY s.season_year DESC, s.season_name ASC
");

// Group seasons by year
$seasonsByYear = [];
foreach ($seasons as $season) {
    $year = $season['season_year'];
    if (!isset($seasonsByYear[$year])) {
        $seasonsByYear[$year] = [];
    }
    $seasonsByYear[$year][] = $season;
}

// Sort years in descending order (most recent first)
krsort($seasonsByYear);

// Determine which years should be expanded by default
$currentYear = date('Y');
$expandedYears = [];
foreach ($seasonsByYear as $year => $yearSeasons) {
    // Expand if it's current year or has any active/registration seasons
    $hasActiveSeason = false;
    foreach ($yearSeasons as $season) {
        if (in_array($season['season_status'], ['Active', 'Registration'])) {
            $hasActiveSeason = true;
            break;
        }
    }
    $expandedYears[$year] = ($year == $currentYear || $hasActiveSeason);
}

// Get all programs for the dropdown
$programs = $db->fetchAll("SELECT program_id, program_name, sport_type FROM programs WHERE active_status = 'Active' ORDER BY program_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seasons Management - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .season-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: 1px solid #dee2e6;
        }
        .season-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            border-bottom: 2px solid rgba(255,255,255,0.2);
        }
        .year-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .collapse-icon {
            transition: transform 0.2s ease-in-out;
        }
        .btn-link:hover {
            color: rgba(255,255,255,0.8) !important;
        }
        .card-header .btn-link:focus {
            box-shadow: none;
        }
    </style>
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/seasons -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/seasons -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Seasons Management</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSeasonModal">
                        Add New Season
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Seasons grouped by year -->
                <?php foreach ($seasonsByYear as $year => $yearSeasons): 
                    $isExpanded = $expandedYears[$year];
                    $collapseId = "collapse-year-" . $year;
                ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <button class="btn btn-link text-white text-decoration-none p-0 w-100 text-start" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#<?php echo $collapseId; ?>" 
                                        aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>" 
                                        aria-controls="<?php echo $collapseId; ?>">
                                    <i class="fas fa-chevron-<?php echo $isExpanded ? 'down' : 'right'; ?> me-2 collapse-icon" id="icon-<?php echo $year; ?>"></i>
                                    <i class="fas fa-calendar-alt me-2"></i><?php echo $year; ?> Season
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($yearSeasons); ?> season<?php echo count($yearSeasons) !== 1 ? 's' : ''; ?></span>
                                </button>
                            </h4>
                        </div>
                        <div class="collapse <?php echo $isExpanded ? 'show' : ''; ?>" id="<?php echo $collapseId; ?>">
                            <div class="card-body">
                            <div class="row">
                                <?php foreach ($yearSeasons as $season): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 season-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($season['season_name']); ?></h5>
                                                    <span class="badge bg-<?php 
                                                        // Backwards compatible switch instead of match()
                                                        switch($season['season_status']) {
                                                            case 'Planning':
                                                                echo 'secondary';
                                                                break;
                                                            case 'Registration':
                                                                echo 'info';
                                                                break;
                                                            case 'Active':
                                                                echo 'success';
                                                                break;
                                                            case 'Completed':
                                                                echo 'primary';
                                                                break;
                                                            case 'Archived':
                                                                echo 'dark';
                                                                break;
                                                            default:
                                                                echo 'secondary';
                                                                break;
                                                        }
                                                    ?>">
                                                        <?php echo $season['season_status']; ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text">
                                                    <strong><?php echo htmlspecialchars($season['program_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($season['sport_type']); ?></small>
                                                </p>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Season Dates:</small><br>
                                                    <?php if ($season['start_date'] && $season['end_date']): ?>
                                                        <small><?php echo formatDate($season['start_date']); ?> - <?php echo formatDate($season['end_date']); ?></small>
                                                    <?php elseif ($season['start_date']): ?>
                                                        <small>From <?php echo formatDate($season['start_date']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Not set</small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Registration:</small><br>
                                                    <?php if ($season['registration_start'] && $season['registration_end']): ?>
                                                        <small><?php echo formatDate($season['registration_start']); ?> - <?php echo formatDate($season['registration_end']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Not set</small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="row text-center mb-3">
                                                    <div class="col-6">
                                                        <div class="border-end">
                                                            <h6 class="mb-0"><?php echo $season['team_count']; ?></h6>
                                                            <small class="text-muted">Teams</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <h6 class="mb-0"><?php echo $season['game_count']; ?></h6>
                                                        <small class="text-muted">Games</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <div class="btn-group w-100" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editSeason(<?php echo htmlspecialchars(json_encode($season)); ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteSeason(<?php echo $season['season_id']; ?>, '<?php echo htmlspecialchars($season['season_name']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($seasonsByYear)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No seasons found</h5>
                            <p class="text-muted">Get started by adding your first season.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSeasonModal">
                                <i class="fas fa-plus"></i> Add First Season
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Season Modal -->
    <div class="modal fade" id="addSeasonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Season</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_season">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Program *</label>
                                    <select name="program_id" class="form-select" required>
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $program): ?>
                                            <option value="<?php echo $program['program_id']; ?>">
                                                <?php echo htmlspecialchars($program['program_name']); ?> 
                                                (<?php echo htmlspecialchars($program['sport_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season Year *</label>
                                    <input type="number" name="season_year" class="form-control" required 
                                           min="2020" max="2030" value="<?php echo date('Y'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Season Name *</label>
                                    <input type="text" name="season_name" class="form-control" required 
                                           placeholder="e.g., Spring Season, Fall League">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="season_status" class="form-select">
                                        <option value="Planning">Planning</option>
                                        <option value="Registration">Registration</option>
                                        <option value="Active">Active</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season Start Date</label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season End Date</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Registration Start</label>
                                    <input type="date" name="registration_start" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Registration End</label>
                                    <input type="date" name="registration_end" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Season</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Season Modal -->
    <div class="modal fade" id="editSeasonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Season</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_season">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="season_id" id="editSeasonId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Program *</label>
                                    <select name="program_id" id="editProgramId" class="form-select" required>
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $program): ?>
                                            <option value="<?php echo $program['program_id']; ?>">
                                                <?php echo htmlspecialchars($program['program_name']); ?> 
                                                (<?php echo htmlspecialchars($program['sport_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season Year *</label>
                                    <input type="number" name="season_year" id="editSeasonYear" class="form-control" required 
                                           min="2020" max="2030">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Season Name *</label>
                                    <input type="text" name="season_name" id="editSeasonName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="season_status" id="editSeasonStatus" class="form-select">
                                        <option value="Planning">Planning</option>
                                        <option value="Registration">Registration</option>
                                        <option value="Active">Active</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season Start Date</label>
                                    <input type="date" name="start_date" id="editStartDate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Season End Date</label>
                                    <input type="date" name="end_date" id="editEndDate" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Registration Start</label>
                                    <input type="date" name="registration_start" id="editRegistrationStart" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Registration End</label>
                                    <input type="date" name="registration_end" id="editRegistrationEnd" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Season</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteSeasonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_season">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="season_id" id="deleteSeasonId">
                        <p>Are you sure you want to delete the season "<span id="deleteSeasonName"></span>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This will also delete all associated teams, games, and schedules.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Season</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle collapse icon rotation
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all collapse elements
            <?php foreach ($seasonsByYear as $year => $yearSeasons): ?>
                const collapse<?php echo $year; ?> = document.getElementById('collapse-year-<?php echo $year; ?>');
                const icon<?php echo $year; ?> = document.getElementById('icon-<?php echo $year; ?>');
                
                if (collapse<?php echo $year; ?> && icon<?php echo $year; ?>) {
                    collapse<?php echo $year; ?>.addEventListener('show.bs.collapse', function() {
                        icon<?php echo $year; ?>.className = 'fas fa-chevron-down me-2 collapse-icon';
                    });
                    
                    collapse<?php echo $year; ?>.addEventListener('hide.bs.collapse', function() {
                        icon<?php echo $year; ?>.className = 'fas fa-chevron-right me-2 collapse-icon';
                    });
                }
            <?php endforeach; ?>
        });
        
        function editSeason(season) {
            document.getElementById('editSeasonId').value = season.season_id;
            document.getElementById('editProgramId').value = season.program_id;
            document.getElementById('editSeasonName').value = season.season_name;
            document.getElementById('editSeasonYear').value = season.season_year;
            document.getElementById('editStartDate').value = season.start_date || '';
            document.getElementById('editEndDate').value = season.end_date || '';
            document.getElementById('editRegistrationStart').value = season.registration_start || '';
            document.getElementById('editRegistrationEnd').value = season.registration_end || '';
            document.getElementById('editSeasonStatus').value = season.season_status;
            
            var editModal = new bootstrap.Modal(document.getElementById('editSeasonModal'));
            editModal.show();
        }
        
        function deleteSeason(seasonId, seasonName) {
            document.getElementById('deleteSeasonId').value = seasonId;
            document.getElementById('deleteSeasonName').textContent = seasonName;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteSeasonModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
