<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.php';

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
                case 'add_division':
                    $data = [
                        'season_id' => (int)$_POST['season_id'],
                        'division_name' => sanitize($_POST['division_name']),
                        'division_code' => sanitize($_POST['division_code']) ?: null,
                        'max_teams' => $_POST['max_teams'] ? (int)$_POST['max_teams'] : null
                    ];
                    
                    $db->insert('divisions', $data);
                    $message = 'Division added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update_division':
                    $divisionId = (int)$_POST['division_id'];
                    $data = [
                        'season_id' => (int)$_POST['season_id'],
                        'division_name' => sanitize($_POST['division_name']),
                        'division_code' => sanitize($_POST['division_code']) ?: null,
                        'max_teams' => $_POST['max_teams'] ? (int)$_POST['max_teams'] : null
                    ];
                    
                    $db->update('divisions', $data, 'division_id = :division_id', ['division_id' => $divisionId]);
                    $message = 'Division updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_division':
                    $divisionId = (int)$_POST['division_id'];
                    $db->delete('divisions', 'division_id = ?', [$divisionId]);
                    $message = 'Division deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all divisions with season and program information
$divisions = $db->fetchAll("
    SELECT d.*, s.season_name, s.season_year, p.program_name, p.sport_type,
           COUNT(DISTINCT t.team_id) as team_count
    FROM divisions d
    LEFT JOIN seasons s ON d.season_id = s.season_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN teams t ON d.division_id = t.division_id
    GROUP BY d.division_id
    ORDER BY s.season_year DESC, s.season_name ASC, d.division_name ASC
");

// Get all active seasons for the dropdown
$seasons = $db->fetchAll("
    SELECT s.season_id, s.season_name, s.season_year, p.program_name, p.sport_type
    FROM seasons s
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.season_status IN ('Planning', 'Registration', 'Active')
    ORDER BY s.season_year DESC, s.season_name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divisions Management - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../../includes/nav.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Divisions Management</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
                        <i class="fas fa-plus"></i> Add New Division
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="divisionsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Division</th>
                                        <th>Season</th>
                                        <th>Program</th>
                                        <th>Division Code</th>
                                        <th>Max Teams</th>
                                        <th>Current Teams</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($divisions as $division): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($division['division_name']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($division['season_name']); ?> <?php echo $division['season_year']; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($division['program_name']); ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($division['sport_type']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($division['division_code']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($division['division_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($division['max_teams']): ?>
                                                    <?php echo $division['max_teams']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No limit</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $division['team_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $division['team_count']; ?> teams
                                                </span>
                                                <?php if ($division['max_teams'] && $division['team_count'] >= $division['max_teams']): ?>
                                                    <small class="text-warning d-block">Full</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editDivision(<?php echo htmlspecialchars(json_encode($division)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteDivision(<?php echo $division['division_id']; ?>, '<?php echo htmlspecialchars($division['division_name']); ?>')">
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
    </div>

    <!-- Add Division Modal -->
    <div class="modal fade" id="addDivisionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_division">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Season *</label>
                            <select name="season_id" class="form-select" required>
                                <option value="">Select Season</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo $season['season_id']; ?>">
                                        <?php echo htmlspecialchars($season['season_name']); ?> <?php echo $season['season_year']; ?>
                                        (<?php echo htmlspecialchars($season['program_name']); ?> - <?php echo htmlspecialchars($season['sport_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Division Name *</label>
                                    <input type="text" name="division_name" class="form-control" required 
                                           placeholder="e.g., Major League, Minor League, 12U Division A">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Division Code</label>
                                    <input type="text" name="division_code" class="form-control" maxlength="10" 
                                           placeholder="e.g., MAJ, MIN, 12A">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Maximum Teams</label>
                            <input type="number" name="max_teams" class="form-control" min="1" max="50" 
                                   placeholder="Leave blank for no limit">
                            <div class="form-text">Optional: Set a maximum number of teams for this division</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Division</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Division Modal -->
    <div class="modal fade" id="editDivisionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_division">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="division_id" id="editDivisionId">
                        
                        <div class="mb-3">
                            <label class="form-label">Season *</label>
                            <select name="season_id" id="editSeasonId" class="form-select" required>
                                <option value="">Select Season</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo $season['season_id']; ?>">
                                        <?php echo htmlspecialchars($season['season_name']); ?> <?php echo $season['season_year']; ?>
                                        (<?php echo htmlspecialchars($season['program_name']); ?> - <?php echo htmlspecialchars($season['sport_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Division Name *</label>
                                    <input type="text" name="division_name" id="editDivisionName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Division Code</label>
                                    <input type="text" name="division_code" id="editDivisionCode" class="form-control" maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Maximum Teams</label>
                            <input type="number" name="max_teams" id="editMaxTeams" class="form-control" min="1" max="50">
                            <div class="form-text">Optional: Set a maximum number of teams for this division</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Division</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_division">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="division_id" id="deleteDivisionId">
                        <p>Are you sure you want to delete the division "<span id="deleteDivisionName"></span>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This will also affect all teams currently assigned to this division.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Division</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#divisionsTable').DataTable({
                order: [[1, 'desc'], [0, 'asc']],
                pageLength: 25
            });
        });
        
        function editDivision(division) {
            document.getElementById('editDivisionId').value = division.division_id;
            document.getElementById('editSeasonId').value = division.season_id;
            document.getElementById('editDivisionName').value = division.division_name;
            document.getElementById('editDivisionCode').value = division.division_code || '';
            document.getElementById('editMaxTeams').value = division.max_teams || '';
            
            var editModal = new bootstrap.Modal(document.getElementById('editDivisionModal'));
            editModal.show();
        }
        
        function deleteDivision(divisionId, divisionName) {
            document.getElementById('deleteDivisionId').value = divisionId;
            document.getElementById('deleteDivisionName').textContent = divisionName;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteDivisionModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
