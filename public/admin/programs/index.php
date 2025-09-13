<?php
/**
 * District 8 Travel League - Programs Management
 */

// Environment-aware bootstrap include (production vs development)
require_once __DIR__ . '/../../../includes/env-loader.php';
@include_once EnvLoader::getPath('includes/admin_bootstrap.php');

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
            case 'add_program':
                try {
                    $programData = [
                        'program_name' => sanitize($_POST['program_name']),
                        'program_code' => sanitize($_POST['program_code']),
                        'sport_type' => sanitize($_POST['sport_type']),
                        'age_min' => (int)$_POST['age_min'],
                        'age_max' => (int)$_POST['age_max'],
                        'default_season_type' => sanitize($_POST['default_season_type']),
                        'game_format' => sanitize($_POST['game_format']),
                        'active_status' => sanitize($_POST['active_status']),
                        'created_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $programId = $db->insert('programs', $programData);
                    logActivity('program_created', "Program '{$programData['program_name']}' created", $currentUser['id']);
                    $message = 'Program created successfully!';
                } catch (Exception $e) {
                    $error = 'Error creating program: ' . $e->getMessage();
                }
                break;
                
            case 'update_program':
                try {
                    $programId = (int)$_POST['program_id'];
                    $programData = [
                        'program_name' => sanitize($_POST['program_name']),
                        'program_code' => sanitize($_POST['program_code']),
                        'sport_type' => sanitize($_POST['sport_type']),
                        'age_min' => (int)$_POST['age_min'],
                        'age_max' => (int)$_POST['age_max'],
                        'default_season_type' => sanitize($_POST['default_season_type']),
                        'game_format' => sanitize($_POST['game_format']),
                        'active_status' => sanitize($_POST['active_status']),
                        'modified_date' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->update('programs', $programData, 'program_id = :program_id', ['program_id' => $programId]);
                    logActivity('program_updated', "Program ID {$programId} updated", $currentUser['id']);
                    $message = 'Program updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating program: ' . $e->getMessage();
                }
                break;
                
            case 'delete_program':
                $programId = (int)$_POST['program_id'];
                
                try {
                    // Check for dependent records
                    $dependencies = [];
                    
                    // Check for seasons
                    $seasonCount = $db->fetchOne("SELECT COUNT(*) as count FROM seasons WHERE program_id = ?", [$programId]);
                    if ($seasonCount['count'] > 0) {
                        $seasons = $db->fetchAll("SELECT season_name, season_year FROM seasons WHERE program_id = ? ORDER BY season_year DESC, season_name", [$programId]);
                        $seasonNames = array_map(function($s) { return $s['season_name'] . ' (' . $s['season_year'] . ')'; }, $seasons);
                        $dependencies['seasons'] = [
                            'count' => $seasonCount['count'],
                            'items' => $seasonNames
                        ];
                    }
                    
                    // Check for teams (through seasons)
                    $teamCount = $db->fetchOne("
                        SELECT COUNT(DISTINCT t.team_id) as count 
                        FROM teams t 
                        JOIN seasons s ON t.season_id = s.season_id 
                        WHERE s.program_id = ?
                    ", [$programId]);
                    if ($teamCount['count'] > 0) {
                        $teams = $db->fetchAll("
                            SELECT DISTINCT t.team_name, s.season_name 
                            FROM teams t 
                            JOIN seasons s ON t.season_id = s.season_id 
                            WHERE s.program_id = ? 
                            ORDER BY s.season_name, t.team_name
                        ", [$programId]);
                        $teamNames = array_map(function($t) { return $t['team_name'] . ' (' . $t['season_name'] . ')'; }, $teams);
                        $dependencies['teams'] = [
                            'count' => $teamCount['count'],
                            'items' => $teamNames
                        ];
                    }
                    
                    // Check for games (through seasons)
                    $gameCount = $db->fetchOne("
                        SELECT COUNT(DISTINCT g.game_id) as count 
                        FROM games g 
                        JOIN seasons s ON g.season_id = s.season_id 
                        WHERE s.program_id = ?
                    ", [$programId]);
                    if ($gameCount['count'] > 0) {
                        $dependencies['games'] = [
                            'count' => $gameCount['count'],
                            'items' => ['Multiple games across seasons']
                        ];
                    }
                    
                    if (!empty($dependencies)) {
                        // Cannot delete - has dependencies
                        $errorMessage = "Cannot delete program. The following items must be deleted first:\n\n";
                        
                        foreach ($dependencies as $type => $data) {
                            $errorMessage .= ucfirst($type) . " ({$data['count']}): ";
                            if ($type === 'games') {
                                $errorMessage .= "Multiple games exist across seasons";
                            } else {
                                $errorMessage .= implode(', ', array_slice($data['items'], 0, 5));
                                if (count($data['items']) > 5) {
                                    $errorMessage .= " and " . (count($data['items']) - 5) . " more...";
                                }
                            }
                            $errorMessage .= "\n\n";
                        }
                        
                        throw new Exception($errorMessage);
                    }
                    
                    // Safe to delete
                    $db->delete('programs', 'program_id = ?', [$programId]);
                    logActivity('program_deleted', "Program ID {$programId} deleted", $currentUser['id']);
                    $message = 'Program deleted successfully!';
                    
                } catch (Exception $e) {
                    $error = 'Error deleting program: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all programs
$programs = $db->fetchAll("
    SELECT p.*,
           COUNT(s.season_id) as season_count,
           COUNT(CASE WHEN s.season_status = 'Active' THEN 1 END) as active_seasons
    FROM programs p
    LEFT JOIN seasons s ON p.program_id = s.program_id
    GROUP BY p.program_id
    ORDER BY p.active_status DESC, p.program_name
");

$pageTitle = "Programs Management - " . APP_NAME;
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
    // Include nav with environment-aware path
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/programs -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/programs -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Programs Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus"></i> Add New Program
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

                <!-- Programs Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="programsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Program Name</th>
                                    <th>Code</th>
                                    <th>Sport</th>
                                    <th>Age Range</th>
                                    <th>Season Type</th>
                                    <th>Status</th>
                                    <th>Seasons</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($program['program_name']); ?></strong></td>
                                    <td><code><?php echo sanitize($program['program_code']); ?></code></td>
                                    <td><?php echo sanitize($program['sport_type']); ?></td>
                                    <td>
                                        <?php if ($program['age_min'] && $program['age_max']): ?>
                                            <?php echo $program['age_min']; ?>-<?php echo $program['age_max']; ?> years
                                        <?php elseif ($program['age_min']): ?>
                                            <?php echo $program['age_min']; ?>+ years
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize($program['default_season_type']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $program['active_status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $program['active_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $program['season_count']; ?> total</span>
                                        <?php if ($program['active_seasons'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $program['active_seasons']; ?> active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editProgram(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(<?php echo $program['program_id']; ?>, '<?php echo htmlspecialchars($program['program_name']); ?>')">
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

    <!-- Add Program Modal -->
    <div class="modal fade" id="addProgramModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Program</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_program">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Program Name *</label>
                                    <input type="text" name="program_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Program Code *</label>
                                    <input type="text" name="program_code" class="form-control" required maxlength="10" placeholder="e.g., BB12U">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sport Type *</label>
                                    <input type="text" name="sport_type" class="form-control" required placeholder="e.g., Baseball, Softball">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Default Season Type</label>
                                    <select name="default_season_type" class="form-select">
                                        <option value="Spring">Spring</option>
                                        <option value="Summer">Summer</option>
                                        <option value="Fall">Fall</option>
                                        <option value="Year-round">Year-round</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Minimum Age</label>
                                    <input type="number" name="age_min" class="form-control" min="4" max="18" placeholder="e.g., 8">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Maximum Age</label>
                                    <input type="number" name="age_max" class="form-control" min="4" max="18" placeholder="e.g., 12">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="active_status" class="form-select">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                        <option value="Archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Game Format</label>
                            <textarea name="game_format" class="form-control" rows="3" 
                                      placeholder="Describe the game format, rules, or special considerations..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Program Modal -->
    <div class="modal fade" id="editProgramModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Program</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_program">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="program_id" id="editProgramId">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Program Name *</label>
                                    <input type="text" name="program_name" id="editProgramName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Program Code *</label>
                                    <input type="text" name="program_code" id="editProgramCode" class="form-control" required maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sport Type *</label>
                                    <input type="text" name="sport_type" id="editSportType" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Default Season Type</label>
                                    <select name="default_season_type" id="editDefaultSeasonType" class="form-select">
                                        <option value="Spring">Spring</option>
                                        <option value="Summer">Summer</option>
                                        <option value="Fall">Fall</option>
                                        <option value="Year-round">Year-round</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Minimum Age</label>
                                    <input type="number" name="age_min" id="editAgeMin" class="form-control" min="4" max="18">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Maximum Age</label>
                                    <input type="number" name="age_max" id="editAgeMax" class="form-control" min="4" max="18">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="active_status" id="editActiveStatus" class="form-select">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                        <option value="Archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Game Format</label>
                            <textarea name="game_format" id="editGameFormat" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Program Modal -->
    <div class="modal fade" id="deleteProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Program</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_program">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="program_id" id="deleteProgramId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone.
                        </div>
                        
                        <p>Are you sure you want to delete the program "<span id="deleteProgramName"></span>"?</p>
                        <p><small class="text-muted">This will only work if the program has no associated seasons, teams, or games.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Program</button>
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
        $(document).ready(function() {
            $('#programsTable').DataTable({
                order: [[2, 'desc'], [0, 'asc']],
                pageLength: 25
            });
        });
        
        function editProgram(program) {
            document.getElementById('editProgramId').value = program.program_id;
            document.getElementById('editProgramName').value = program.program_name;
            document.getElementById('editProgramCode').value = program.program_code || '';
            document.getElementById('editSportType').value = program.sport_type || '';
            document.getElementById('editAgeMin').value = program.age_min || '';
            document.getElementById('editAgeMax').value = program.age_max || '';
            document.getElementById('editDefaultSeasonType').value = program.default_season_type || 'Spring';
            document.getElementById('editGameFormat').value = program.game_format || '';
            document.getElementById('editActiveStatus').value = program.active_status || 'Active';
            
            var editModal = new bootstrap.Modal(document.getElementById('editProgramModal'));
            editModal.show();
        }
        
        function deleteProgram(programId, programName) {
            document.getElementById('deleteProgramId').value = programId;
            document.getElementById('deleteProgramName').textContent = programName;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteProgramModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
