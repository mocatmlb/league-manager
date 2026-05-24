<?php
/**
 * District 8 Travel League — Admin User Detail
 *
 * Story 4.3: user summary card + team assignment/removal.
 * Story 8.3: full CRUD edit form, role selector, disable/enable, reset password, delete.
 */

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

// Resolve user from query string
$userId = (int) ($_GET['id'] ?? 0);
if ($userId === 0) {
    header('Location: index.php');
    exit;
}

$user = $db->fetchOne(
    'SELECT u.id, u.first_name, u.last_name, u.preferred_name, u.email, u.username, u.status,
            u.created_at, r.name AS role_name, u.role_id, u.phone AS legacy_phone
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.id = :id LIMIT 1',
    ['id' => $userId]
);
if ($user === false) {
    header('Location: index.php');
    exit;
}

// Primary phone from user_phones table (migration 015)
$primaryPhone = $db->fetchOne(
    "SELECT phone, type FROM user_phones WHERE user_id = :uid AND role = 'primary' LIMIT 1",
    ['uid' => $userId]
);

// Current team assignment
$currentTeam = $db->fetchOne(
    'SELECT t.team_id, t.team_name, t.league_name
     FROM team_owners to2
     INNER JOIN teams t ON t.team_id = to2.team_id
     WHERE to2.user_id = :uid LIMIT 1',
    ['uid' => $userId]
);

// Flash messages
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

        // ------------------------------------------------------------------
        // Profile edit (Story 8.3 Task 1)
        // ------------------------------------------------------------------
        if ($action === 'update_profile') {
            $updateData = [
                'first_name'     => trim($_POST['first_name']     ?? ''),
                'last_name'      => trim($_POST['last_name']      ?? ''),
                'preferred_name' => trim($_POST['preferred_name'] ?? '') ?: null,
                'email'          => trim($_POST['email']          ?? ''),
                'username'       => trim($_POST['username']       ?? ''),
            ];

            $phoneVal  = trim($_POST['phone']      ?? '');
            $phoneType = $_POST['phone_type']      ?? 'Cell';
            if (!in_array($phoneType, ['Home', 'Work', 'Cell'], true)) {
                $phoneType = 'Cell';
            }

            try {
                $service->update($userId, $updateData);

                // Upsert primary phone in user_phones table
                if ($phoneVal !== '') {
                    $existingPhone = $db->fetchOne(
                        "SELECT id FROM user_phones WHERE user_id = :uid AND role = 'primary' LIMIT 1",
                        ['uid' => $userId]
                    );
                    if ($existingPhone !== false) {
                        $db->query(
                            "UPDATE user_phones SET phone = :phone, type = :type, updated_at = NOW()
                             WHERE user_id = :uid AND role = 'primary'",
                            ['phone' => $phoneVal, 'type' => $phoneType, 'uid' => $userId]
                        );
                    } else {
                        $db->query(
                            "INSERT INTO user_phones (user_id, phone, type, role, created_at, updated_at)
                             VALUES (:uid, :phone, :type, 'primary', NOW(), NOW())",
                            ['uid' => $userId, 'phone' => $phoneVal, 'type' => $phoneType]
                        );
                    }
                }

                $_SESSION['flash_message'] = 'Profile updated successfully.';
                header('Location: detail.php?id=' . $userId);
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                Logger::error('User detail profile update failed', ['error' => $e->getMessage()]);
                $error = 'Profile could not be updated. Please try again or contact support.';
            }

        // ------------------------------------------------------------------
        // Role change (Story 8.3 Task 2)
        // ------------------------------------------------------------------
        } elseif ($action === 'change_role') {
            $newRole = trim($_POST['new_role'] ?? '');
            try {
                $service->setRole($userId, $newRole, $adminUserId);
                $_SESSION['flash_message'] = 'Role updated successfully.';
                header('Location: detail.php?id=' . $userId);
                exit;
            } catch (InvalidArgumentException $e) {
                $error = 'Invalid role selected.';
            } catch (Throwable $e) {
                Logger::error('User detail role change failed', ['error' => $e->getMessage()]);
                $error = 'Role could not be changed. Please try again or contact support.';
            }

        // ------------------------------------------------------------------
        // Disable / Enable account (Story 8.3 Task 3)
        // ------------------------------------------------------------------
        } elseif ($action === 'disable_account') {
            try {
                $service->disable($userId, $adminUserId);
                $_SESSION['flash_message'] = 'Account disabled.';
                header('Location: detail.php?id=' . $userId);
                exit;
            } catch (Throwable $e) {
                Logger::error('User detail disable failed', ['error' => $e->getMessage()]);
                $error = 'Account could not be disabled. Please try again.';
            }

        } elseif ($action === 'enable_account') {
            try {
                $service->enable($userId, $adminUserId);
                $_SESSION['flash_message'] = 'Account enabled.';
                header('Location: detail.php?id=' . $userId);
                exit;
            } catch (Throwable $e) {
                Logger::error('User detail enable failed', ['error' => $e->getMessage()]);
                $error = 'Account could not be enabled. Please try again.';
            }

        // ------------------------------------------------------------------
        // Reset password (Story 8.3 Task 4)
        // ------------------------------------------------------------------
        } elseif ($action === 'reset_password') {
            try {
                $temp = $service->resetPassword($userId, $adminUserId);
                $_SESSION['temp_password'] = $temp;
                // Patch 4: redirect to dedicated confirmation page (one-time display, AC5)
                header('Location: password-reset-success.php?id=' . $userId);
                exit;
            } catch (Throwable $e) {
                Logger::error('User detail password reset failed', ['error' => $e->getMessage()]);
                $error = 'Password could not be reset. Please try again.';
            }

        // ------------------------------------------------------------------
        // Delete account (Story 8.3 Task 5)
        // ------------------------------------------------------------------
        } elseif ($action === 'delete_confirm') {
            // First step: set session flag, re-render with confirmation
            $_SESSION['confirm_delete_user'] = $userId;
            header('Location: detail.php?id=' . $userId);
            exit;

        } elseif ($action === 'delete_execute') {
            if ((int) ($_SESSION['confirm_delete_user'] ?? 0) !== $userId) {
                $error = 'Delete confirmation mismatch. Please try again.';
            } else {
                unset($_SESSION['confirm_delete_user']);
                try {
                    $service->delete($userId, $adminUserId);
                    $_SESSION['flash_message'] = 'Account deleted.';
                    header('Location: index.php');
                    exit;
                } catch (Throwable $e) {
                    Logger::error('User detail delete failed', ['error' => $e->getMessage()]);
                    $error = 'Account could not be deleted. Please try again.';
                }
            }

        // ------------------------------------------------------------------
        // Force-verify / Resend verification email
        // ------------------------------------------------------------------
        } elseif ($action === 'force_verify') {
            if (($user['status'] ?? '') !== 'unverified') {
                $error = 'Action not applicable.';
            } else {
                try {
                    $service->forceVerify($userId, $adminUserId);
                    if (!class_exists('RegistrationService')) {
                        require_once EnvLoader::getPath('includes/RegistrationService.php');
                    }
                    (new RegistrationService())->notifyAdminOfVerification($userId, (string) $user['email']);
                    $_SESSION['flash_message'] = 'Account verified.';
                    header('Location: detail.php?id=' . $userId);
                    exit;
                } catch (Throwable $e) {
                    Logger::error('User detail force verify failed', ['error' => $e->getMessage()]);
                    $error = 'Account could not be verified. Please try again.';
                }
            }

        } elseif ($action === 'resend_verification') {
            if (($user['status'] ?? '') !== 'unverified') {
                $error = 'Action not applicable.';
            } else {
                try {
                    if (!class_exists('RegistrationService')) {
                        require_once EnvLoader::getPath('includes/RegistrationService.php');
                    }
                    (new RegistrationService())->resendVerification((string) $user['email']);
                    $_SESSION['flash_message'] = 'Verification email sent.';
                    header('Location: detail.php?id=' . $userId);
                    exit;
                } catch (Throwable $e) {
                    Logger::error('User detail resend verification failed', ['error' => $e->getMessage()]);
                    $error = 'Failed to send verification email — check mail settings.';
                }
            }

        // ------------------------------------------------------------------
        // Team assignment (Story 4.3 — preserved)
        // ------------------------------------------------------------------
        } elseif ($action === 'assign_team') {
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

        // Re-fetch after a failed POST so the view is consistent
        $user = $db->fetchOne(
            'SELECT u.id, u.first_name, u.last_name, u.preferred_name, u.email, u.username, u.status,
                    u.created_at, r.name AS role_name, u.role_id, u.phone AS legacy_phone
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1',
            ['id' => $userId]
        );
        $primaryPhone = $db->fetchOne(
            "SELECT phone, type FROM user_phones WHERE user_id = :uid AND role = 'primary' LIMIT 1",
            ['uid' => $userId]
        );
        $currentTeam = $db->fetchOne(
            'SELECT t.team_id, t.team_name, t.league_name
             FROM team_owners to2
             INNER JOIN teams t ON t.team_id = to2.team_id
             WHERE to2.user_id = :uid LIMIT 1',
            ['uid' => $userId]
        );
    }
}

// Active-season teams without an owner for the assign dropdown
$activeTeams = $db->fetchAll(
    "SELECT t.team_id, t.team_name, t.league_name, s.season_name, s.season_year
     FROM teams t
     INNER JOIN seasons s ON s.season_id = t.season_id
     WHERE t.status = 'active'
       AND s.season_status = 'Active'
       AND t.team_id NOT IN (SELECT team_id FROM team_owners)
     ORDER BY t.team_name"
);

// Delete confirmation pending?
$deleteConfirmPending = ((int) ($_SESSION['confirm_delete_user'] ?? 0)) === $userId;

// Admins live in admin_users; users live in users — different tables, incomparable IDs.
// An admin account can never appear in the users table, so $isSelf is always false.
$isSelf = false;

// Patch 1: generate token once so all forms on this page share the same value
$csrfToken = Auth::generateCSRFToken();

$roleName   = $user['role_name'] ?? 'user';
$userStatus = $user['status']    ?? 'unverified';

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
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to User List
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


        <!-- User Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-1"></i>
                    User: <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?>
                    <?php if (!empty($user['preferred_name'])): ?>
                        <small class="text-muted">(goes by <?php echo sanitize($user['preferred_name']); ?>)</small>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['email']); ?></dd>

                    <dt class="col-sm-3">Username</dt>
                    <dd class="col-sm-9"><?php echo sanitize($user['username']); ?></dd>

                    <dt class="col-sm-3">Role</dt>
                    <dd class="col-sm-9"><?php echo sanitize($roleName); ?></dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        <?php
                        $badgeClass = match($userStatus) {
                            'active'     => 'bg-success',
                            'disabled'   => 'bg-danger',
                            'unverified' => 'bg-warning text-dark',
                            default      => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>">
                            <?php echo ucfirst($userStatus); ?>
                            <?php if ($userStatus === 'disabled'): ?> (Account Disabled)<?php endif; ?>
                        </span>
                    </dd>

                    <?php
                    $displayPhone = $primaryPhone['phone'] ?? $user['legacy_phone'] ?? '';
                    $displayType  = $primaryPhone['type']  ?? '';
                    if ($displayPhone !== ''):
                    ?>
                        <dt class="col-sm-3">Primary Phone</dt>
                        <dd class="col-sm-9">
                            <?php echo sanitize($displayPhone); ?>
                            <?php if ($displayType !== ''): ?>
                                <small class="text-muted">(<?php echo sanitize($displayType); ?>)</small>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <dt class="col-sm-3">Registered</dt>
                    <dd class="col-sm-9">
                        <?php echo !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—'; ?>
                    </dd>

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

        <!-- Edit Profile Card (Story 8.3 Task 1) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-1"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?php echo sanitize($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?php echo sanitize($user['last_name']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preferred Name <small class="text-muted">(optional)</small></label>
                            <input type="text" name="preferred_name" class="form-control"
                                   value="<?php echo sanitize($user['preferred_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo sanitize($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?php echo sanitize($user['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Primary Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo sanitize($primaryPhone['phone'] ?? $user['legacy_phone'] ?? ''); ?>"
                                   placeholder="e.g. 555-555-5555">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Type</label>
                            <select name="phone_type" class="form-select">
                                <?php foreach (['Cell', 'Home', 'Work'] as $ptype): ?>
                                    <option value="<?php echo $ptype; ?>"
                                        <?php echo ($primaryPhone['type'] ?? 'Cell') === $ptype ? 'selected' : ''; ?>>
                                        <?php echo $ptype; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Role Management Card (Story 8.3 Task 2) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-tag me-1"></i>Role Management</h5>
            </div>
            <div class="card-body">
                <?php if ($isSelf): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        You are viewing your own account. Role changes are disabled for self-service safety.
                    </div>
                <?php endif; ?>
                <form method="POST" <?php echo $isSelf ? 'class="opacity-50 pe-none"' : ''; ?>>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="change_role">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <label class="form-label mb-1">Current Role: <strong><?php echo sanitize($roleName); ?></strong></label>
                            <select name="new_role" class="form-select form-select-sm" style="min-width:180px;" <?php echo $isSelf ? 'disabled' : ''; ?>>
                                <option value="user"          <?php echo $roleName === 'user'          ? 'selected' : ''; ?>>User</option>
                                <option value="team_owner"    <?php echo $roleName === 'team_owner'    ? 'selected' : ''; ?>>Team Owner</option>
                                <option value="administrator" <?php echo $roleName === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-secondary btn-sm" <?php echo $isSelf ? 'disabled' : ''; ?>>
                                <i class="fas fa-exchange-alt"></i> Change Role
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Status Card (Story 8.3 Tasks 3 & 4) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-toggle-on me-1"></i>Account Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">

                    <?php if ($userStatus === 'active' || $userStatus === 'unverified'): ?>
                        <!-- Disable -->
                        <?php if ($isSelf): ?>
                            <button class="btn btn-warning btn-sm" disabled title="Cannot disable your own account">
                                <i class="fas fa-ban"></i> Disable Account
                            </button>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Disable this account? The coach will be logged out immediately.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="disable_account">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-ban"></i> Disable Account
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Enable -->
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="enable_account">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check-circle"></i> Enable Account
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Reset Password -->
                    <form method="POST" onsubmit="return confirm('Reset this coach\'s password? A temporary password will be generated and shown once.');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>

                    <?php if ($userStatus === 'unverified'): ?>
                        <!-- Force Verify -->
                        <form method="POST" onsubmit="return confirm('Mark this account as verified? This bypasses the email verification step.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="force_verify">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check-double"></i> Force Verify
                            </button>
                        </form>

                        <!-- Resend Verification Email -->
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="resend_verification">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope"></i> Resend Verification Email
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Delete Account Card (Story 8.3 Task 5) -->
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-trash-alt me-1"></i>Danger Zone</h5>
            </div>
            <div class="card-body">
                <?php if ($isSelf): ?>
                    <p class="text-muted mb-0">
                        <i class="fas fa-lock"></i> You cannot delete your own account.
                    </p>
                <?php elseif ($deleteConfirmPending): ?>
                    <!-- Step 2: Execute delete -->
                    <div class="alert alert-danger mb-3">
                        <strong><i class="fas fa-exclamation-triangle"></i> Are you sure?</strong><br>
                        This will permanently delete
                        <strong><?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></strong>'s
                        account and all team assignments. This cannot be undone.
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="delete_execute">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Yes, Delete Account
                            </button>
                        </form>
                        <a href="detail.php?id=<?php echo $userId; ?>" class="btn btn-outline-secondary"
                           onclick="<?php echo "document.cookie=''; "; ?>">
                            Cancel
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Step 1: Request confirmation -->
                    <p class="mb-2 text-muted">
                        Permanently delete this account and all associated team assignments.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="delete_confirm">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash-alt"></i> Delete Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Team Assignment Card (Story 4.3 — preserved) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-1"></i>Team Assignment</h5>
            </div>
            <div class="card-body">
                <?php if ($currentTeam !== false): ?>
                    <p class="mb-3">
                        <strong>Assigned Team:</strong>
                        <?php echo sanitize($currentTeam['team_name']); ?>
                        <span class="text-muted">(<?php echo sanitize($currentTeam['league_name']); ?>)</span>
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action"    value="remove_team">
                        <input type="hidden" name="team_id"   value="<?php echo (int) $currentTeam['team_id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Remove this team assignment? If this is the coach\'s only team their role will revert to user.')">
                            <i class="fas fa-user-minus"></i> Remove Assignment
                        </button>
                    </form>

                <?php else: ?>
                    <p class="text-muted mb-3">No team currently assigned.</p>
                    <?php if (empty($activeTeams)): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active-season teams are available to assign.
                        </div>
                    <?php else: ?>
                        <form method="POST" class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
