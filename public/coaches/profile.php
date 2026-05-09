<?php
/**
 * District 8 Travel League - Coach Profile Page
 *
 * Any authenticated coach (coach or team_owner role) can update their
 * name, phone numbers, and password.
 */

require_once __DIR__ . '/../../includes/env-loader.php';
require_once EnvLoader::getPath('includes/coach_bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/RegistrationService.php');
require_once EnvLoader::getPath('includes/ProfileService.php');

PermissionGuard::requireRole('user', '/coaches/login.php');

$db      = Database::getInstance();
$userId  = (int) ($_SESSION['coach_user_id'] ?? 0);
$service = new ProfileService($db);
$error   = '';

// ---------------------------------------------------------------------------
// POST handler — PRG pattern
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
            header('Location: profile.php');
            exit;
        }

        $nameData = [
            'first_name'     => trim($_POST['first_name'] ?? ''),
            'last_name'      => trim($_POST['last_name'] ?? ''),
            'preferred_name' => trim($_POST['preferred_name'] ?? ''),
        ];
        $primaryPhone   = trim($_POST['primary_phone'] ?? '');
        $primaryType    = $_POST['primary_type'] ?? '';
        $secondaryPhone = trim($_POST['secondary_phone'] ?? '');
        $secondaryType  = $_POST['secondary_type'] ?? '';

        try {
            $service->updateName($userId, $nameData);

            if ($primaryPhone !== '') {
                $service->updatePhone($userId, $primaryPhone, $primaryType, 'primary');
            }

            if ($secondaryPhone !== '') {
                $service->updatePhone($userId, $secondaryPhone, $secondaryType, 'secondary');
            } elseif ($secondaryPhone === '' && isset($_POST['secondary_phone'])) {
                $service->removeSecondaryPhone($userId);
            }

            $_SESSION['flash_success'] = 'Profile updated.';
            header('Location: profile.php');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage() ?: 'Profile update failed — please try again.';
        }
    } elseif ($action === 'change_password') {
        if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
            header('Location: profile.php');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirm         = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $service->changePassword($userId, $currentPassword, $newPassword);
                $_SESSION['flash_success'] = 'Password changed.';
                header('Location: profile.php');
                exit;
            } catch (IncorrectCurrentPasswordException $e) {
                $error = 'Current password is incorrect.';
            } catch (WeakPasswordException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Password change failed — please try again.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// GET — read flash, load data
// ---------------------------------------------------------------------------
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$user = $db->fetchOne(
    'SELECT first_name, last_name, preferred_name, email FROM users WHERE id = :id',
    ['id' => $userId]
);

$phones = $db->fetchAll(
    "SELECT phone, type, role FROM user_phones WHERE user_id = :user_id ORDER BY FIELD(role,'primary','secondary')",
    ['user_id' => $userId]
);
$primaryPhone = '';
$primaryType  = '';
$secondaryPhone = '';
$secondaryType  = '';
foreach ($phones as $p) {
    if ($p['role'] === 'primary') {
        $primaryPhone = $p['phone'];
        $primaryType  = $p['type'];
    } elseif ($p['role'] === 'secondary') {
        $secondaryPhone = $p['phone'];
        $secondaryType  = $p['type'];
    }
}

$teamRow = $db->fetchOne(
    'SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1',
    ['id' => $userId]
);

$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$teamName  = htmlspecialchars((string) ($teamRow['team_name'] ?? ''));

$pageTitle = 'My Profile — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<?php
$coachNavWebRoot = '../../';
include __DIR__ . '/../../includes/coaches_nav.php';
unset($coachNavWebRoot);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">

            <h1 class="mb-4"><i class="fas fa-user-edit me-2"></i>My Profile</h1>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flashSuccess); ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flashError); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Profile Information Card -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Profile Information</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo htmlspecialchars(Auth::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control form-control-lg" id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control form-control-lg" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="preferred_name" class="form-label">Preferred Name</label>
                                <input type="text" class="form-control form-control-lg" id="preferred_name" name="preferred_name"
                                       value="<?php echo htmlspecialchars($user['preferred_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control-plaintext" id="email" readonly
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            <small class="text-muted">(contact admin to change email)</small>
                        </div>

                        <?php if ($teamName !== ''): ?>
                        <div class="mb-3">
                            <label for="team_name" class="form-label">Team Name (managed by admin)</label>
                            <input type="text" class="form-control-plaintext" id="team_name" readonly
                                   value="<?php echo $teamName; ?>">
                        </div>
                        <?php endif; ?>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="primary_phone" class="form-label">Primary Phone</label>
                                <div class="row">
                                    <div class="col-7">
                                        <input type="tel" class="form-control" id="primary_phone" name="primary_phone"
                                               value="<?php echo htmlspecialchars($primaryPhone); ?>">
                                    </div>
                                    <div class="col-5">
                                        <select class="form-select" name="primary_type" id="primary_type">
                                            <option value="">Type</option>
                                            <option value="Home" <?php echo $primaryType === 'Home' ? 'selected' : ''; ?>>Home</option>
                                            <option value="Work" <?php echo $primaryType === 'Work' ? 'selected' : ''; ?>>Work</option>
                                            <option value="Cell" <?php echo $primaryType === 'Cell' ? 'selected' : ''; ?>>Cell</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="secondary_phone" class="form-label">Secondary Phone</label>
                                <div class="row">
                                    <div class="col-7">
                                        <input type="tel" class="form-control" id="secondary_phone" name="secondary_phone"
                                               value="<?php echo htmlspecialchars($secondaryPhone); ?>">
                                    </div>
                                    <div class="col-5">
                                        <select class="form-select" name="secondary_type" id="secondary_type">
                                            <option value="">Type</option>
                                            <option value="Home" <?php echo $secondaryType === 'Home' ? 'selected' : ''; ?>>Home</option>
                                            <option value="Work" <?php echo $secondaryType === 'Work' ? 'selected' : ''; ?>>Work</option>
                                            <option value="Cell" <?php echo $secondaryType === 'Cell' ? 'selected' : ''; ?>>Cell</option>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted">Clear to remove secondary phone</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">Save Profile</button>
                    </form>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Change Password</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo htmlspecialchars(Auth::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control form-control-lg" id="current_password"
                                   name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control form-control-lg" id="new_password"
                                   name="new_password" required autocomplete="new-password">
                            <small class="text-muted">At least 8 characters, with 1 uppercase, 1 number, and 1 special character</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control form-control-lg" id="confirm_password"
                                   name="confirm_password" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-warning btn-lg">Change Password</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-4">
    <div class="container text-center text-muted">
        <small>&copy; <?php echo date('Y'); ?> District 8 Travel League</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
