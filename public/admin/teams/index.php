<?php
/**
 * District 8 Travel League - Teams Management
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

// Require admin authentication
Auth::requireAdmin();

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Shared CSRF token for all forms (Blind Hunter Finding 2)
$csrfToken = Auth::generateCSRFToken();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'approve_registration':
                try {
                    $teamId      = (int) ($_POST['team_id']     ?? 0);
                    $divisionId  = (int) ($_POST['division_id'] ?? 0);
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }

                    if ($teamId === 0 || $divisionId === 0) {
                        $error = 'Please select a division before approving.';
                        break;
                    }

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    $regService = new TeamRegistrationService();
                    $regService->approve($teamId, $adminUserId, $divisionId);

                    $_SESSION['flash_message'] = 'Team registration approved successfully.';
                    header('Location: index.php');
                    exit;
                } catch (TeamAlreadyClaimedException $e) {
                    $error = 'This coach already has a team assigned. Multiple team assignments are not supported in this version.';
                } catch (RuntimeException $e) {
                    $error = 'Approval could not be completed: ' . $e->getMessage();
                } catch (Throwable $e) {
                    Logger::error('Team registration approval failed', ['error' => $e->getMessage()]);
                    $error = 'Approval could not be completed. Please try again or contact support.';
                }
                break;

            case 'reject_registration':
                try {
                    $teamId      = (int) ($_POST['team_id'] ?? 0);
                    $reason      = trim((string) ($_POST['reject_reason'] ?? ''));
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }
                    if ($teamId === 0) {
                        $error = 'Invalid registration.';
                        break;
                    }

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    (new TeamRegistrationService())->reject($teamId, $adminUserId, $reason);

                    $_SESSION['flash_message'] = 'Team registration rejected.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('Team registration rejection failed', ['error' => $e->getMessage()]);
                    $error = 'Rejection could not be completed: ' . $e->getMessage();
                }
                break;

            case 'admin_create_registration':
                try {
                    $adminUserId  = (int) ($_SESSION['admin_id'] ?? 0);
                    $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
                    $seasonId     = (int) ($_POST['season_id'] ?? 0);
                    $leagueName   = trim((string) ($_POST['league_name'] ?? ''));
                    $teamName     = trim((string) ($_POST['team_name'] ?? ''));

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }

                    $missing = [];
                    if ($targetUserId < 1) $missing[] = 'user';
                    if ($seasonId < 1)     $missing[] = 'season';
                    if ($leagueName === '') $missing[] = 'league';
                    if ($teamName === '')   $missing[] = 'team name';
                    if (!empty($missing)) {
                        $error = 'Please provide: ' . implode(', ', $missing) . '.';
                        break;
                    }

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    (new TeamRegistrationService())->adminCreate(
                        $targetUserId,
                        [
                            'season_id'   => $seasonId,
                            'league_name' => $leagueName,
                            'team_name'   => $teamName,
                        ],
                        $adminUserId
                    );

                    $_SESSION['flash_message'] = 'Team registration created.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('Admin team registration creation failed', ['error' => $e->getMessage()]);
                    $error = 'Could not create registration: ' . $e->getMessage();
                }
                break;

            case 'edit_registration':
                try {
                    $adminUserId  = (int) ($_SESSION['admin_id'] ?? 0);
                    $teamId       = (int) ($_POST['team_id'] ?? 0);
                    $teamName     = trim((string) ($_POST['team_name'] ?? ''));
                    $seasonId     = (int) ($_POST['season_id'] ?? 0);
                    $leagueName   = trim((string) ($_POST['league_name'] ?? ''));
                    $targetUserId = (int) ($_POST['submitted_by_user_id'] ?? 0);

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }
                    if ($teamId === 0 || $teamName === '' || $seasonId === 0
                        || $leagueName === '' || $targetUserId === 0) {
                        $error = 'All fields are required to update the registration.';
                        break;
                    }

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    (new TeamRegistrationService())->update(
                        $teamId,
                        [
                            'team_name'            => $teamName,
                            'season_id'            => $seasonId,
                            'league_name'          => $leagueName,
                            'submitted_by_user_id' => $targetUserId,
                        ],
                        $adminUserId
                    );

                    $_SESSION['flash_message'] = 'Team registration updated.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('Team registration update failed', ['error' => $e->getMessage()]);
                    $error = 'Update failed: ' . $e->getMessage();
                }
                break;

            case 'delete_registration':
                try {
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);
                    $teamId      = (int) ($_POST['team_id'] ?? 0);

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }
                    if ($teamId === 0) {
                        $error = 'Invalid registration.';
                        break;
                    }

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    (new TeamRegistrationService())->deleteRegistration($teamId, $adminUserId);

                    $_SESSION['flash_message'] = 'Team registration deleted.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('Team registration deletion failed', ['error' => $e->getMessage()]);
                    $error = 'Delete failed: ' . $e->getMessage();
                }
                break;

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
                    $teamId      = (int) ($_POST['team_id'] ?? 0);
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

                    if ($adminUserId < 1) {
                        $error = 'Your admin session is invalid. Please sign in again.';
                        break;
                    }
                    if ($teamId === 0) {
                        $error = 'Invalid team.';
                        break;
                    }

                    // Capture team info for logging before deletion
                    $teamInfo = $db->fetchOne(
                        "SELECT team_name, league_name FROM teams WHERE team_id = ?",
                        [$teamId]
                    );

                    if (!class_exists('TeamRegistrationService')) {
                        require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
                    }
                    (new TeamRegistrationService())->deleteRegistration($teamId, $adminUserId);

                    Logger::info("Team deleted successfully", [
                        'team_id'     => $teamId,
                        'team_name'   => $teamInfo['team_name']   ?? 'unknown',
                        'league_name' => $teamInfo['league_name'] ?? 'unknown',
                        'admin_user'  => $_SESSION['admin_username'] ?? 'unknown',
                    ]);

                    $_SESSION['flash_message'] = 'Team deleted successfully!';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    // Service throws "Cannot delete: team has game assignments." — surface that
                    Logger::warn("Team deletion failed", [
                        'team_id'    => $teamId ?? 'unknown',
                        'error'      => $e->getMessage(),
                        'admin_user' => $_SESSION['admin_username'] ?? 'unknown',
                    ]);
                    $error = 'Error deleting team: ' . $e->getMessage();
                }
                break;

            case 'assign_owner':
                try {
                    $userId     = (int) ($_POST['user_id'] ?? 0);
                    $teamId     = (int) ($_POST['team_id'] ?? 0);
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

                    if ($adminUserId < 1 || $userId < 1 || $teamId < 1) {
                        $error = 'Invalid request.';
                        break;
                    }

                    if (!class_exists('UserManagementService')) {
                        require_once EnvLoader::getPath('includes/UserManagementService.php');
                    }
                    (new UserManagementService())->assignTeam($userId, $teamId, $adminUserId);

                    $_SESSION['flash_message'] = 'Team owner assigned successfully.';
                    header('Location: index.php');
                    exit;
                } catch (TeamAlreadyClaimedException $e) {
                    $error = 'This user already has a team assigned.';
                } catch (Throwable $e) {
                    Logger::error('Team owner assignment failed', ['error' => $e->getMessage()]);
                    $error = 'Assignment could not be completed. Please try again or contact support.';
                }
                break;

            case 'remove_owner':
                try {
                    $teamId      = (int) ($_POST['team_id'] ?? 0);
                    $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

                    if ($adminUserId < 1 || $teamId < 1) {
                        $error = 'Invalid request.';
                        break;
                    }

                    // Look up current owner for this team
                    $owner = $db->fetchOne(
                        'SELECT user_id FROM team_owners WHERE team_id = :tid LIMIT 1',
                        ['tid' => $teamId]
                    );
                    if ($owner === false) {
                        $error = 'This team does not have an owner assigned.';
                        break;
                    }

                    if (!class_exists('UserManagementService')) {
                        require_once EnvLoader::getPath('includes/UserManagementService.php');
                    }
                    (new UserManagementService())->removeTeam(
                        (int) $owner['user_id'],
                        $teamId,
                        $adminUserId
                    );

                    $_SESSION['flash_message'] = 'Team owner removed successfully.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('Team owner removal failed', ['error' => $e->getMessage()]);
                    $error = 'Removal could not be completed. Please try again or contact support.';
                }
                break;
        }
    }
}

// Flash messages from PRG redirects
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// Pending team registrations (AC1)
if (!class_exists('TeamRegistrationService')) {
    require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
}
$regServiceForList = new TeamRegistrationService();
$pendingRegistrations  = $regServiceForList->getPendingRegistrations();
$rejectedRegistrations = $regServiceForList->getRejectedRegistrations();

// Active users for admin "Create Registration" + "Edit Registration" forms (Stories 11.6, 11.7)
$activeUsers = $db->fetchAll(
    "SELECT id, first_name, last_name, username
     FROM users WHERE status = 'active'
     ORDER BY last_name, first_name"
);

// All seasons (admin-create + edit are not restricted to Registration status — Story 11.6 AC, 11.7 AC1)
$adminFormSeasons = $db->fetchAll(
    "SELECT season_id, season_name, season_year, season_status
     FROM seasons
     ORDER BY season_year DESC, season_name"
);

// Active leagues for admin-create form (Story 11.6 Task 2)
if (!class_exists('LeagueListManager')) {
    require_once EnvLoader::getPath('includes/LeagueListManager.php');
}
$adminFormLeagues = LeagueListManager::getActiveList();

/** @var array<int, array<int, array<string, mixed>>> Divisions keyed by team season_id (code review: scoped picker) */
$approveDivisionsBySeason = [];

// Active users who are NOT already assigned as team owners (for assign dropdown)
$unassignedUsers = $db->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email
     FROM users u
     WHERE u.status = 'active'
       AND u.id NOT IN (SELECT user_id FROM team_owners)
     ORDER BY u.last_name, u.first_name"
);

// Get teams with division and season info, plus owner details from users
// via team_owners.  Falls back to teams.manager_* for legacy teams without
// an owner record.
$teams = $db->fetchAll("
    SELECT t.*, d.division_name, s.season_name,
           tow.user_id AS owner_user_id,
           u.first_name AS owner_first_name, u.last_name AS owner_last_name,
           u.email AS owner_email, u.phone AS owner_phone,
           CONCAT(COALESCE(u.first_name, t.manager_first_name), ' ', COALESCE(u.last_name, t.manager_last_name)) AS owner_name
    FROM teams t
    JOIN divisions d ON t.division_id = d.division_id
    JOIN seasons s ON t.season_id = s.season_id
    LEFT JOIN team_owners tow ON tow.team_id = t.team_id
    LEFT JOIN users u ON u.id = tow.user_id
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

                <?php if ($flashMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo sanitize($flashMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($flashError); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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

                <!-- Pending Team Registrations (AC1) -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-1"></i>
                            Pending Team Registrations (<?php echo count($pendingRegistrations); ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingRegistrations)): ?>
                            <p class="p-3 mb-0 text-muted">No pending registrations.</p>
                        <?php else: ?>
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Coach</th>
                                        <th>Team Name</th>
                                        <th>League</th>
                                        <th>Season / Program</th>
                                        <th>Submitted</th>
                                        <th style="min-width: 360px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pendingRegistrations as $reg):
                                    // season_name, season_year, program_name are JOINed in getPendingRegistrations() (Story 10.1 AC5)
                                    $seasonKey = (int) ($reg['season_id'] ?? 0);
                                    if (!isset($approveDivisionsBySeason[$seasonKey])) {
                                        $approveDivisionsBySeason[$seasonKey] = $seasonKey > 0
                                            ? $db->fetchAll(
                                                'SELECT d.division_id, d.division_name, s.season_name, s.season_year, p.program_name
                                                 FROM divisions d
                                                 INNER JOIN seasons s ON s.season_id = d.season_id
                                                 INNER JOIN programs p ON p.program_id = s.program_id
                                                 WHERE d.season_id = :sid
                                                 ORDER BY p.program_name, d.division_name',
                                                ['sid' => $seasonKey]
                                            )
                                            : [];
                                    }
                                    $rowDivisions = $approveDivisionsBySeason[$seasonKey];
                                ?>
                                    <tr>
                                        <td><?php echo sanitize($reg['manager_first_name'] . ' ' . $reg['manager_last_name']); ?></td>
                                        <td><strong><?php echo sanitize(strtoupper($reg['team_name'])); ?></strong></td>
                                        <td><?php echo sanitize($reg['league_name']); ?></td>
                                        <td>
                                            <?php if (!empty($reg['season_name'])): ?>
                                                <?php echo sanitize($reg['program_name'] . ' — ' . $reg['season_name'] . ' ' . $reg['season_year']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo sanitize($reg['created_date']); ?></td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <?php if (empty($rowDivisions)): ?>
                                                    <span class="text-muted small">No divisions for this season.</span>
                                                <?php else: ?>
                                                <form method="POST" class="d-flex align-items-center gap-1">
                                                    <input type="hidden" name="csrf_token"  value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action"      value="approve_registration">
                                                    <input type="hidden" name="team_id"     value="<?php echo (int) $reg['team_id']; ?>">
                                                    <select name="division_id" class="form-select form-select-sm" style="min-width:200px;" required>
                                                        <option value="">— Select Division —</option>
                                                        <?php foreach ($rowDivisions as $div): ?>
                                                            <option value="<?php echo (int) $div['division_id']; ?>">
                                                                <?php echo sanitize($div['program_name'] . ': ' . $div['division_name'] . ' (' . $div['season_year'] . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <?php endif; ?>

                                                <div class="btn-group btn-group-sm" role="group">
                                                    <!-- Reject (Story 11.4) -->
                                                    <button type="button" class="btn btn-outline-warning"
                                                            onclick="openRejectModal(<?php echo (int) $reg['team_id']; ?>, '<?php echo htmlspecialchars(addslashes($reg['team_name']), ENT_QUOTES); ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>

                                                    <!-- Edit (Story 11.7) -->
                                                    <button type="button" class="btn btn-outline-primary"
                                                            onclick='openEditRegistrationModal(<?php echo json_encode([
                                                                "team_id"              => (int) $reg["team_id"],
                                                                "team_name"            => $reg["team_name"],
                                                                "season_id"            => (int) $reg["season_id"],
                                                                "league_name"          => $reg["league_name"],
                                                                "submitted_by_user_id" => (int) $reg["submitted_by_user_id"],
                                                            ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>

                                                    <!-- Delete (Story 11.7) -->
                                                    <form method="POST" class="d-inline"
                                                          onsubmit="return confirm('Delete this registration? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="action"     value="delete_registration">
                                                        <input type="hidden" name="team_id"    value="<?php echo (int) $reg['team_id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rejected Team Registrations (Story 11.4 AC3) -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <button class="btn btn-link text-white text-decoration-none p-0 w-100 text-start"
                                type="button" data-bs-toggle="collapse" data-bs-target="#rejectedRegistrationsBody"
                                aria-expanded="false">
                            <i class="fas fa-ban me-1"></i>
                            Rejected Registrations (<?php echo count($rejectedRegistrations); ?>)
                        </button>
                    </div>
                    <div class="collapse" id="rejectedRegistrationsBody">
                        <div class="card-body p-0">
                            <?php if (empty($rejectedRegistrations)): ?>
                                <p class="p-3 mb-0 text-muted">No rejected registrations.</p>
                            <?php else: ?>
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Team Name</th>
                                            <th>Coach</th>
                                            <th>Season</th>
                                            <th>Date Rejected</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rejectedRegistrations as $rej): ?>
                                        <tr>
                                            <td><strong><?php echo sanitize(strtoupper($rej['team_name'])); ?></strong></td>
                                            <td>
                                                <?php
                                                  $coachName = trim(($rej['submitter_first_name'] ?? '') . ' ' . ($rej['submitter_last_name'] ?? ''));
                                                  echo sanitize($coachName !== '' ? $coachName : '—');
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($rej['season_name'])): ?>
                                                    <?php echo sanitize(($rej['program_name'] ?? '') . ' — ' . $rej['season_name'] . ' ' . $rej['season_year']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo sanitize($rej['modified_date'] ?? ''); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            onclick='openEditRegistrationModal(<?php echo json_encode([
                                                                "team_id"              => (int) $rej["team_id"],
                                                                "team_name"            => $rej["team_name"],
                                                                "season_id"            => (int) $rej["season_id"],
                                                                "league_name"          => $rej["league_name"],
                                                                "submitted_by_user_id" => (int) $rej["submitted_by_user_id"],
                                                            ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <form method="POST" class="d-inline"
                                                          onsubmit="return confirm('Delete this registration? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="action"     value="delete_registration">
                                                        <input type="hidden" name="team_id"    value="<?php echo (int) $rej['team_id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Admin Create Team Registration (Story 11.6) -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <button class="btn btn-link text-white text-decoration-none p-0 w-100 text-start"
                                type="button" data-bs-toggle="collapse" data-bs-target="#adminCreateRegistrationBody"
                                aria-expanded="false">
                            <i class="fas fa-user-plus me-1"></i>
                            Create Team Registration (on behalf of a user)
                        </button>
                    </div>
                    <div class="collapse" id="adminCreateRegistrationBody">
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action"     value="admin_create_registration">

                                <div class="col-md-6">
                                    <label class="form-label">User *</label>
                                    <select name="target_user_id" id="adminCreateUser" class="form-select" required>
                                        <option value="">— Select User —</option>
                                        <?php foreach ($activeUsers as $u): ?>
                                            <option value="<?php echo (int) $u['id']; ?>"
                                                    data-last-name="<?php echo htmlspecialchars($u['last_name'], ENT_QUOTES); ?>">
                                                <?php echo sanitize($u['last_name'] . ', ' . $u['first_name'] . ' (' . $u['username'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Season *</label>
                                    <select name="season_id" class="form-select" required>
                                        <option value="">— Select Season —</option>
                                        <?php foreach ($adminFormSeasons as $s): ?>
                                            <option value="<?php echo (int) $s['season_id']; ?>">
                                                <?php echo sanitize($s['season_name'] . ' ' . $s['season_year'] . ' (' . $s['season_status'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">League *</label>
                                    <select name="league_name" id="adminCreateLeague" class="form-select" required>
                                        <option value="">— Select League —</option>
                                        <?php foreach ($adminFormLeagues as $lg): ?>
                                            <option value="<?php echo htmlspecialchars($lg['display_name'], ENT_QUOTES); ?>">
                                                <?php echo sanitize($lg['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Team Name *</label>
                                    <input type="text" name="team_name" id="adminCreateTeamName"
                                           class="form-control" data-auto="1" required
                                           placeholder="Auto-suggested from league + user last name">
                                    <div class="form-text">Auto-populates as <code>{league}-{last_name}</code>; edit to override.</div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create Registration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

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
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><strong><?php echo sanitize(strtoupper($team['team_name'])); ?></strong></td>
                                    <td><?php echo sanitize($team['league_name']); ?></td>
                                    <td><?php echo sanitize($team['division_name']); ?></td>
                                    <td><?php echo sanitize($team['season_name']); ?></td>
                                    <td>
                                        <?php if ($team['owner_user_id']): ?>
                                            <div><strong><?php echo sanitize($team['owner_name']); ?></strong></div>
                                            <?php if ($team['owner_phone']): ?>
                                                <small class="text-muted"><i class="fas fa-phone"></i> <?php echo sanitize($team['owner_phone']); ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($team['owner_email']): ?>
                                                <small class="text-muted"><i class="fas fa-envelope"></i> <?php echo sanitize($team['owner_email']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
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
                                        <button class="btn btn-sm btn-outline-danger me-1" onclick="deleteTeam(<?php echo $team['team_id']; ?>, '<?php echo htmlspecialchars(strtoupper($team['team_name'])); ?>', '<?php echo htmlspecialchars($team['league_name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php if ($team['owner_user_id']): ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this team owner? The user\'s role will revert if they have no other teams.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action"   value="remove_owner">
                                                <input type="hidden" name="team_id" value="<?php echo (int) $team['team_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-user-minus"></i> Remove
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="openAssignOwner(<?php echo (int) $team['team_id']; ?>, '<?php echo sanitize(strtoupper($team['team_name'])); ?>')">
                                                <i class="fas fa-user-plus"></i> Assign
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
                                    <input type="tel" name="manager_phone" class="form-control phone-format" required>
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
                                Leave blank to auto-generate from league name.
                            </div>
                        </div>
                        
                        <!-- Owner / Manager Info (read-only) -->
                        <div class="mb-3" id="editOwnerInfo">
                            <label class="form-label">Owner</label>
                            <div id="editOwnerInfoBody" class="border rounded p-3 bg-light">
                                <div id="editOwnerDetails" style="display:none">
                                    <strong id="editOwnerName"></strong><br>
                                    <small class="text-muted"><i class="fas fa-envelope"></i> <span id="editOwnerEmail"></span></small><br>
                                    <small class="text-muted"><i class="fas fa-phone"></i> <span id="editOwnerPhone"></span></small>
                                </div>
                                <div id="editOwnerNone" class="text-muted">Not assigned</div>
                            </div>
                            <div class="form-text">
                                Owner info is managed via <a href="users/index.php">Manage Users</a>.
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

    <!-- Assign Team Owner Modal -->
    <div class="modal fade" id="assignOwnerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Team Owner</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action"   value="assign_owner">
                        <input type="hidden" name="team_id"  id="assignOwnerTeamId">

                        <p>Assign an owner to <strong id="assignOwnerTeamName"></strong>:</p>

                        <div class="mb-3">
                            <label class="form-label">User *</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">— Select User —</option>
                                <?php foreach ($unassignedUsers as $u): ?>
                                    <option value="<?php echo (int) $u['id']; ?>">
                                        <?php echo sanitize($u['last_name'] . ', ' . $u['first_name'] . ' (' . $u['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Only users who are not already assigned as a team owner are shown.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Assign Owner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Registration Modal (Story 11.4) -->
    <div class="modal fade" id="rejectRegistrationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Team Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action"     value="reject_registration">
                        <input type="hidden" name="team_id"    id="rejectTeamId">
                        <p>You are about to reject the registration for
                            <strong id="rejectTeamName"></strong>. The coach will be notified by email.</p>
                        <div class="mb-3">
                            <label class="form-label">Reason (optional)</label>
                            <textarea name="reject_reason" class="form-control" rows="3"
                                      placeholder="e.g., Roster incomplete; please reapply with full information."></textarea>
                            <div class="form-text">Included in the rejection email if provided.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-times"></i> Reject Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Registration Modal (Story 11.7) -->
    <div class="modal fade" id="editRegistrationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Team Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action"     value="edit_registration">
                        <input type="hidden" name="team_id"    id="editRegTeamId">

                        <div class="mb-3">
                            <label class="form-label">Team Name *</label>
                            <input type="text" name="team_name" id="editRegTeamName" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Season *</label>
                            <select name="season_id" id="editRegSeason" class="form-select" required>
                                <?php foreach ($adminFormSeasons as $s): ?>
                                    <option value="<?php echo (int) $s['season_id']; ?>">
                                        <?php echo sanitize($s['season_name'] . ' ' . $s['season_year'] . ' (' . $s['season_status'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">League *</label>
                            <select name="league_name" id="editRegLeague" class="form-select" required>
                                <?php foreach ($adminFormLeagues as $lg): ?>
                                    <option value="<?php echo htmlspecialchars($lg['display_name'], ENT_QUOTES); ?>">
                                        <?php echo sanitize($lg['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assigned User *</label>
                            <select name="submitted_by_user_id" id="editRegUser" class="form-select" required>
                                <?php foreach ($activeUsers as $u): ?>
                                    <option value="<?php echo (int) $u['id']; ?>">
                                        <?php echo sanitize($u['last_name'] . ', ' . $u['first_name'] . ' (' . $u['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Changing this updates who will be assigned as team owner upon approval.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
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
        function updateDefaultTeamName() {
            const leagueInput = document.getElementById('addLeagueName') || document.querySelector('input[name="league_name"]');
            const lastNameInput = document.getElementById('addManagerLastName');
            const teamNameInput = document.getElementById('addTeamName');
            if (!leagueInput || !lastNameInput || !teamNameInput) return;
            if (teamNameInput.value.trim() === '' && leagueInput.value && lastNameInput.value) {
                teamNameInput.value = leagueInput.value + '-' + lastNameInput.value;
            }
        }

        // Add event listeners for dynamic team name updates
        document.addEventListener('DOMContentLoaded', function() {
            const addLeagueInput = document.querySelector('input[name="league_name"]');
            if (addLeagueInput) {
                addLeagueInput.addEventListener('change', () => updateDefaultTeamName());
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

            // Populate owner info from the unified data source (users via team_owners)
            const ownerName   = document.getElementById('editOwnerName');
            const ownerEmail  = document.getElementById('editOwnerEmail');
            const ownerPhone  = document.getElementById('editOwnerPhone');
            const ownerDetail = document.getElementById('editOwnerDetails');
            const ownerNone   = document.getElementById('editOwnerNone');
            if (team.owner_user_id) {
                ownerName.textContent  = team.owner_first_name + ' ' + team.owner_last_name;
                ownerEmail.textContent = team.owner_email || '';
                ownerPhone.textContent = team.owner_phone || '';
                ownerDetail.style.display = 'block';
                ownerNone.style.display   = 'none';
            } else {
                ownerDetail.style.display = 'none';
                ownerNone.style.display   = 'block';
            }

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

        // ----- Assign/Remove owner -----
        function openAssignOwner(teamId, teamName) {
            document.getElementById('assignOwnerTeamId').value = teamId;
            document.getElementById('assignOwnerTeamName').textContent = teamName;
            new bootstrap.Modal(document.getElementById('assignOwnerModal')).show();
        }

        // ----- Story 11.4: Reject modal -----
        function openRejectModal(teamId, teamName) {
            document.getElementById('rejectTeamId').value = teamId;
            document.getElementById('rejectTeamName').textContent = teamName;
            new bootstrap.Modal(document.getElementById('rejectRegistrationModal')).show();
        }

        // ----- Story 11.7: Edit registration modal -----
        function openEditRegistrationModal(reg) {
            document.getElementById('editRegTeamId').value   = reg.team_id;
            document.getElementById('editRegTeamName').value = reg.team_name || '';
            document.getElementById('editRegSeason').value   = reg.season_id;
            document.getElementById('editRegLeague').value   = reg.league_name || '';
            document.getElementById('editRegUser').value     = reg.submitted_by_user_id;
            new bootstrap.Modal(document.getElementById('editRegistrationModal')).show();
        }

        // ----- Story 11.6: Auto-populate team name from league + user last name -----
        (function () {
            const userSel  = document.getElementById('adminCreateUser');
            const leagueSel = document.getElementById('adminCreateLeague');
            const nameInp  = document.getElementById('adminCreateTeamName');
            if (!userSel || !leagueSel || !nameInp) return;

            function recompute() {
                if (nameInp.dataset.auto !== '1') return;
                const lastName = userSel.options[userSel.selectedIndex]?.dataset?.lastName || '';
                const league   = leagueSel.value || '';
                if (league && lastName) {
                    nameInp.value = league + '-' + lastName;
                }
            }

            // Detect manual edits — once the admin types, stop auto-populating.
            nameInp.addEventListener('input', function () {
                nameInp.dataset.auto = '0';
            });

            userSel.addEventListener('change', recompute);
            leagueSel.addEventListener('change', recompute);
        })();

        // Phone auto-formatting: (###) ###-####
        (function () {
            function formatPhone(raw) {
                var d = raw.replace(/\D/g, '').substring(0, 10);
                if (d.length === 0) return '';
                if (d.length <= 3) return '(' + d;
                if (d.length <= 6) return '(' + d.substring(0, 3) + ') ' + d.substring(3);
                return '(' + d.substring(0, 3) + ') ' + d.substring(3, 6) + '-' + d.substring(6);
            }
            document.querySelectorAll('input.phone-format').forEach(function (inp) {
                inp.addEventListener('input', function () {
                    var pos = this.selectionStart;
                    var before = this.value.length;
                    this.value = formatPhone(this.value);
                    var delta = this.value.length - before;
                    this.setSelectionRange(pos + delta, pos + delta);
                });
                if (inp.value) inp.value = formatPhone(inp.value);
            });

        })();
    </script>
</body>
</html>
