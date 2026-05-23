<?php
/**
 * District 8 Travel League - Locations Management
 */

// Robust EnvLoader include: locate includes/env-loader.php regardless of layout
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

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
require_once EnvLoader::getPath('includes/TeamRegistrationService.php');

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Handle form submissions
$message = '';
$error = '';
$dupCandidates = [];
$dupFormData   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_location':
                try {
                    $locationData = [
                        'location_name'   => sanitize($_POST['location_name']    ?? ''),
                        'address'         => sanitize($_POST['location_address'] ?? ''),
                        'city'            => sanitize($_POST['location_city']    ?? ''),
                        'state'           => sanitize($_POST['location_state']   ?? ''),
                        'zip_code'        => sanitize($_POST['location_zip']     ?? ''),
                        'gps_coordinates' => sanitize($_POST['gps_coordinates']  ?? ''),
                        'notes'           => sanitize($_POST['notes']            ?? ''),
                        'active_status'   => sanitize($_POST['active_status']    ?? 'Active'),
                        'created_date'    => date('Y-m-d H:i:s'),
                    ];

                    if (($_POST['dup_confirmed'] ?? '') === '') {
                        $svc = new TeamRegistrationService($db);
                        $candidates = $svc->findDuplicateCandidates([
                            'name'    => $locationData['location_name'],
                            'address' => $locationData['address'],
                        ]);
                        if (!empty($candidates)) {
                            $dupCandidates = $candidates;
                            // Use original POST field names so the "Add Anyway" form resubmits correctly
                            $dupFormData = [
                                'location_name'    => $locationData['location_name'],
                                'location_address' => $locationData['address'],
                                'location_city'    => $locationData['city'],
                                'location_state'   => $locationData['state'],
                                'location_zip'     => $locationData['zip_code'],
                                'gps_coordinates'  => $locationData['gps_coordinates'],
                                'notes'            => $locationData['notes'],
                                'active_status'    => $locationData['active_status'],
                            ];
                            break;
                        }
                    }

                    $locationId = $db->insert('locations', $locationData);
                    logActivity('location_created', "Location '{$locationData['location_name']}' created", $currentUser['id']);
                    $message = 'Location created successfully!';
                } catch (Exception $e) {
                    $error = 'Error creating location: ' . $e->getMessage();
                }
                break;
                
            case 'update_location':
                try {
                    $locationId = (int)$_POST['location_id'];
                    $locationData = [
                        'location_name'   => sanitize($_POST['location_name']    ?? ''),
                        'address'         => sanitize($_POST['location_address'] ?? ''),
                        'city'            => sanitize($_POST['location_city']    ?? ''),
                        'state'           => sanitize($_POST['location_state']   ?? ''),
                        'zip_code'        => sanitize($_POST['location_zip']     ?? ''),
                        'gps_coordinates' => sanitize($_POST['gps_coordinates']  ?? ''),
                        'notes'           => sanitize($_POST['notes']            ?? ''),
                        'active_status'   => sanitize($_POST['active_status']    ?? 'Active'),
                        'modified_date'   => date('Y-m-d H:i:s'),
                    ];

                    $db->update('locations', $locationData, 'location_id = :location_id', ['location_id' => $locationId]);
                    logActivity('location_updated', "Location ID {$locationId} updated", $currentUser['id']);
                    $message = 'Location updated successfully!';
                } catch (Exception $e) {
                    $error = 'Error updating location: ' . $e->getMessage();
                }
                break;

            case 'delete_location':
                try {
                    $locationId = (int)$_POST['location_id'];
                    $loc = $db->fetchOne("SELECT location_name FROM locations WHERE location_id = ?", [$locationId]);
                    if (!$loc) {
                        $error = 'Location not found.';
                        break;
                    }
                    $locationName = $loc['location_name'];
                    $usageCount = $db->fetchOne(
                        "SELECT COUNT(*) as cnt FROM schedules WHERE location_id = ? OR location = ?",
                        [$locationId, $locationName]
                    )['cnt'];
                    if ($usageCount > 0) {
                        $error = "This location cannot be deleted because it is assigned to {$usageCount} game(s). Set it to Inactive instead.";
                        break;
                    }
                    $db->query("DELETE FROM locations WHERE location_id = ?", [$locationId]);
                    logActivity('location_deleted', "Location '{$locationName}' (ID: {$locationId}) deleted", $currentUser['id']);
                    $message = "Location '{$locationName}' deleted.";
                } catch (Exception $e) {
                    $error = 'Error deleting location: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all locations with usage statistics
$locations = $db->fetchAll("
    SELECT l.*,
           COUNT(DISTINCT CASE WHEN s.location_id = l.location_id OR s.location = l.location_name THEN s.schedule_id END) as games_scheduled,
           COUNT(DISTINCT CASE WHEN (s.location_id = l.location_id OR s.location = l.location_name) AND s.game_date >= CURDATE() THEN s.schedule_id END) as upcoming_games
    FROM locations l
    LEFT JOIN schedules s ON s.location_id = l.location_id OR s.location = l.location_name
    GROUP BY l.location_id
    ORDER BY l.active_status DESC, l.location_name
");

$pageTitle = "Locations Management - " . APP_NAME;
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
        ? __DIR__ . '/../../includes/nav.php'      // Production: /admin/locations -> ../../includes
        : __DIR__ . '/../../../includes/nav.php';  // Development: /public/admin/locations -> ../../../includes
    include $__nav;
    unset($__nav);
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Locations Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                        <i class="fas fa-plus"></i> Add New Location
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

                <?php if (!empty($dupCandidates)): ?>
                    <div class="alert alert-warning">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Possible Duplicate Location</h5>
                        <p class="mb-2">
                            "<strong><?php echo sanitize($dupFormData['location_name']); ?></strong>"
                            is similar to the following existing location(s):
                        </p>
                        <ul class="mb-3">
                            <?php foreach ($dupCandidates as $c): ?>
                            <li>
                                <strong><?php echo sanitize($c['location_name']); ?></strong>
                                <?php if ($c['city']): ?>
                                    &mdash; <?php echo sanitize($c['city']); ?>, <?php echo sanitize($c['state']); ?>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action"        value="add_location">
                            <input type="hidden" name="csrf_token"    value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="dup_confirmed" value="yes">
                            <?php foreach ($dupFormData as $k => $v): ?>
                            <input type="hidden"
                                   name="<?php echo htmlspecialchars($k, ENT_QUOTES); ?>"
                                   value="<?php echo htmlspecialchars($v, ENT_QUOTES); ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fas fa-plus"></i> Add Anyway
                            </button>
                        </form>
                        <span class="ms-2 text-muted small">or dismiss this alert to cancel.</span>
                    </div>
                <?php endif; ?>

                <!-- Locations Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="locationsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Location Name</th>
                                    <th>Address</th>
                                    <th>GPS Coordinates</th>
                                    <th>Status</th>
                                    <th>Games Scheduled</th>
                                    <th>Upcoming Games</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $location): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($location['location_name']); ?></strong></td>
                                    <td>
                                        <?php if ($location['address']): ?>
                                            <?php echo sanitize($location['address']); ?><br>
                                            <?php echo sanitize($location['city']); ?>, <?php echo sanitize($location['state']); ?> <?php echo sanitize($location['zip_code']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No address provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($location['gps_coordinates']): ?>
                                            <code><?php echo sanitize($location['gps_coordinates']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $location['active_status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $location['active_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $location['games_scheduled']; ?> total</span>
                                    </td>
                                    <td>
                                        <?php if ($location['upcoming_games'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $location['upcoming_games']; ?> upcoming</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editLocation(<?php echo htmlspecialchars(json_encode($location)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if ($location['games_scheduled'] == 0): ?>
                                        <button class="btn btn-sm btn-outline-danger ms-1"
                                                onclick="confirmDeleteLocation(<?php echo (int)$location['location_id']; ?>, <?php echo htmlspecialchars(json_encode($location['location_name'])); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php endif; ?>
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

    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_location">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name *</label>
                            <input type="text" name="location_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="location_address" class="form-control" placeholder="Street address">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="location_city" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="location_state" class="form-control" maxlength="2" placeholder="NY">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" name="location_zip" class="form-control" maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Number of Fields</label>
                                    <input type="number" name="field_count" class="form-control" min="1" value="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="active_status" class="form-select">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Parking Information</label>
                            <textarea name="parking_info" class="form-control" rows="2" 
                                      placeholder="Parking availability and instructions..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Facility Notes</label>
                            <textarea name="facility_notes" class="form-control" rows="2" 
                                      placeholder="Additional facility information, amenities, restrictions..."></textarea>
                        </div>
                        
                        <h6>Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Name</label>
                                    <input type="text" name="contact_name" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" name="contact_phone" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div class="modal fade" id="editLocationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_location">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="location_id" id="editLocationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Location Name *</label>
                            <input type="text" name="location_name" id="editLocationName" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="location_address" id="editLocationAddress" class="form-control">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="location_city" id="editLocationCity" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="location_state" id="editLocationState" class="form-control" maxlength="2">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" name="location_zip" id="editLocationZip" class="form-control" maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Number of Fields</label>
                                    <input type="number" name="field_count" id="editFieldCount" class="form-control" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="active_status" id="editLocationStatus" class="form-select">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Parking Information</label>
                            <textarea name="parking_info" id="editParkingInfo" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Facility Notes</label>
                            <textarea name="facility_notes" id="editFacilityNotes" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <h6>Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Name</label>
                                    <input type="text" name="contact_name" id="editContactName" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" name="contact_phone" id="editContactPhone" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Location Confirmation Modal -->
    <div class="modal fade" id="deleteLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteLocationForm">
                    <input type="hidden" name="action" value="delete_location">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <input type="hidden" name="location_id" id="deleteLocationId">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Delete <strong id="deleteLocationName"></strong>? This cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
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
            $('#locationsTable').DataTable({
                order: [[4, 'desc'], [0, 'asc']],
                pageLength: 25
            });
        });
        
        function editLocation(location) {
            document.getElementById('editLocationId').value = location.location_id;
            document.getElementById('editLocationName').value = location.location_name;
            document.getElementById('editLocationAddress').value = location.location_address || '';
            document.getElementById('editLocationCity').value = location.location_city || '';
            document.getElementById('editLocationState').value = location.location_state || '';
            document.getElementById('editLocationZip').value = location.location_zip || '';
            document.getElementById('editFieldCount').value = location.field_count || 1;
            document.getElementById('editLocationStatus').value = location.active_status;
            document.getElementById('editParkingInfo').value = location.parking_info || '';
            document.getElementById('editFacilityNotes').value = location.facility_notes || '';
            document.getElementById('editContactName').value = location.contact_name || '';
            document.getElementById('editContactPhone').value = location.contact_phone || '';
            
            var editModal = new bootstrap.Modal(document.getElementById('editLocationModal'));
            editModal.show();
        }

        function confirmDeleteLocation(locationId, locationName) {
            document.getElementById('deleteLocationId').value = locationId;
            document.getElementById('deleteLocationName').textContent = locationName;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteLocationModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
