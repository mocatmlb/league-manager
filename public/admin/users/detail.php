<?php
/**
 * District 8 Travel League — Admin User Detail
 *
 * Story 4.3: user summary card + team assignment/removal.
 * Story 8.3 will ADD a full CRUD edit form here — do not over-build now.
 */

// Robust EnvLoader bootstrap (matches teams/index.php pattern)
$__dir   = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    if (file_exists($__dir . '/includes/env-loader.php')) {
        require_once $__dir . '/includes/env-loader.php';
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
unset($__dir, $__found, $__i);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
Auth::requireAdmin();

$db          = Database::getInstance();
$adminUserId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminUserId < 1) {
    $_SESSION['flash_error'] = 'Your admin session is invalid. Please sign in again.';
    header('Location: ../login.php');
    exit;
}

// Resolve user from query string — redirect away on missing/invalid ID
$userId = (int) ($_GET['id'] ?? 0);
if ($userId === 0) {
    header('Location: ../index.php');
    exit;
}

$user = $db->fetchOne(
    'SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.status,
            r.name AS role_name
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.id = :id LIMIT 1',
    ['id' => $userId]
);
if ($user === false) {
    header('Location: ../index.php');
    exit;
}

// Current team assignment (first row only — 1:1 enforced at app layer)
$currentTeam = $db->fetchOne(
    'SELECT t.team_id, t.team_name, t.league_name
     FROM team_owners to2
     INNER JOIN teams t ON t.team_id = to2.team_id
     WHERE to2.user_id = :uid LIMIT 1',
    ['uid' => $userId]
);

// Flash messages from PRG redirects
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$error   = '';
$message = '';

// --------------------------------------------------------------------------
// POST handling
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if (!class_exists('UserManagementService')) {
            require_once EnvLoader::getPath('includes/UserManagementService.php');
        }
        $service = new UserManagementService();

        if ($action === 'assign_team') {
            $teamId = (int) ($_POST['team_id'] ?? 0);
            if ($teamId === 0) {
                $error = 'Please select a team to assign.';
            } else {
                $teamAllowed = $db->fetchOne(
                    "SELECT t.team_id FROM teams t
                     INNER JOIN seasons s ON s.season_id = t.season_id
                     WHERE t.team_id = :tid AND t.status = 'active' AND s.season_status = 'Active'
                     LIMIT 1",
                    ['tid' => $teamId]
                );
                if ($teamAllowed === false) {
                    $error = 'Invalid or unavailable team selected.';
                } else {
                    try {
                        $service->assignTeam($userId, $teamId, $adminUserId);
                        $_SESSION['flash_message'] = 'Team assigned successfully.';
                        header('Location: detail.php?id=' . $userId);
                        exit;
                    } catch (TeamAlreadyClaimedException $e) {
                        $error = 'This coach already has a team assigned. Multiple team assignments are not supported in this version.';
                    } catch (Throwable $e) {
                        Logger::error('User detail team assignment failed', ['error' => $e->getMessage()]);
                        $error = 'Assignment could not be completed. Please try again or contact support.';
                    }
                }
            }
        } elseif ($action === 'remove_team') {
            $teamId = (int) ($_POST['team_id'] ?? 0);
            if ($teamId === 0) {
                $error = 'Invalid team ID.';
            } else {
                try {
                    $service->removeTeam($userId, $teamId, $adminUserId);
                    $_SESSION['flash_message'] = 'Team assignment removed.';
                    header('Location: detail.php?id=' . $userId);
                    exit;
                } catch (Throwable $e) {
                    Logger::error('User detail team removal failed', ['error' => $e->getMessage()]);
                    $error = 'Removal could not be completed. Please try again or contact support.';
                }
            }
        }

        // Re-fetch current team after a failed POST so the view is consistent
        $currentTeam = $db->fetchOne(
            'SELECT t.team_id, t.team_name, t.league_name
             FROM team_owners to2
             INNER JOIN teams t ON t.team_id = to2.team_id
             WHERE to2.user_id = :uid LIMIT 1',
            ['uid' => $userId]
        );
    }
}

// Active-season teams for the assign dropdown (AC3)
$activeTeams = $db->fetchAll(
    "SELECT t.team_id, t.team_name, t.league_name, s.season_name, s.season_year
     FROM teams t
     INNER JOIN seasons s ON s.season_id = t.season_id
     WHERE t.status = 'active' AND s.season_status = 'Active'
     ORDER BY t.team_name"
);

$pageTitle = 'User Detail — ' . sanitize($user['first_name'] . ' ' . $user['last_name']) . ' — ' . APP_NAME;
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
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $__nav;
    unset($__nav);
    ?>

    <div class="container mt-4">
        <div class="mb-3">
            <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
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

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo sanitize($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- User Summary Card (AC3) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-1"></i>
                    User: <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['email']); ?></dd>

                    <dt class="col-sm-3">Username</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['username']); ?></dd>

                    <dt class="col-sm-3">Role</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['role_name'] ?? '—'); ?></dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['status'] ?? '—'); ?></dd>

                    <dt class="col-sm-3">Current Team</dt>
                    <dd class="col-sm-9">
                        <?php if ($currentTeam !== false): ?>
                            <strong><?php echo sanitize($currentTeam['team_name']); ?></strong>
                            <span class="text-muted">(<?php echo sanitize($currentTeam['league_name']); ?>)</span>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Team Assignment Card (AC3 / AC4 / AC5) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-1"></i>Team Assignment</h5>
            </div>
            <div class="card-body">
                <?php if ($currentTeam !== false): ?>
                    <!-- Remove existing assignment (AC4) -->
                    <p class="mb-3">
                        <strong>Assigned Team:</strong>
                        <?php echo sanitize($currentTeam['team_name']); ?>
                        <span class="text-muted">(<?php echo sanitize($currentTeam['league_name']); ?>)</span>
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action"    value="remove_team">
                        <input type="hidden" name="team_id"   value="<?php echo (int) $currentTeam['team_id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Remove this team assignment? If this is the coach\'s only team their role will revert to user.')">
                            <i class="fas fa-user-minus"></i> Remove Assignment
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Assign a team (AC3) -->
                    <p class="text-muted mb-3">No team currently assigned.</p>
                    <?php if (empty($activeTeams)): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active-season teams are available to assign.
                        </div>
                    <?php else: ?>
                        <form method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="action"     value="assign_team">
                            <select name="team_id" class="form-select w-auto" required>
                                <option value="">— Select Team —</option>
                                <?php foreach ($activeTeams as $team): ?>
                                    <option value="<?php echo (int) $team['team_id']; ?>">
                                        <?php echo sanitize($team['team_name'] . ' (' . $team['season_name'] . ' ' . $team['season_year'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Assign to Team
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Story 8.3: full CRUD edit form goes here -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
