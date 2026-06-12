<?php
/**
 * District 8 Travel League - Coach Profile Page
 *
 * Any authenticated user can update their name, contact info, and password.
 */

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
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $smsOptIn  = !empty($_POST['sms_opt_in']);

        if (empty($_POST['accept_terms'])) {
            // ToS must be affirmatively re-accepted on every save — block before any DB write.
            $error = 'You must accept the Terms of Service and Privacy Policy.';
        } else {
            try {
                // Contact info first — if it fails (e.g. duplicate email), name stays unchanged.
                $service->updateContactInfo($userId, $email, $phone, $smsOptIn, true);
                $service->updateName($userId, $nameData);

                $_SESSION['flash_success'] = 'Profile updated.';
                header('Location: profile.php');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage() ?: 'Profile update failed — please try again.';
            }
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
    'SELECT username, first_name, last_name, preferred_name, email, phone, sms_opt_in FROM users WHERE id = :id',
    ['id' => $userId]
);

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
if (Auth::isAdmin()) {
    include EnvLoader::getPath('includes/nav.php');
} else {
    $coachNavWebRoot = '../../';
    include EnvLoader::getPath('includes/coaches_nav.php');
    unset($coachNavWebRoot);
}
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

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control form-control-lg" id="username"
                                   value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                   disabled>
                        </div>

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

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email"
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control form-control-lg" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="(###) ###-####" required>
                            </div>
                        </div>

                        <?php if ($teamName !== ''): ?>
                        <div class="mb-3">
                            <label for="team_name" class="form-label">Team Name (managed by admin)</label>
                            <input type="text" class="form-control-plaintext" id="team_name" readonly
                                   value="<?php echo strtoupper($teamName); ?>">
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sms_opt_in" name="sms_opt_in"
                                       value="1" <?php echo !empty($user['sms_opt_in']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_opt_in">
                                    By checking, you consent to receive SMS messages from District 8 Travel League. Message frequency may vary. Message and data rates may apply, reply HELP for help or STOP to opt-out.
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms"
                                       value="1" required>
                                <label class="form-check-label" for="accept_terms">
                                    I agree to the <a href="../terms.php" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="../privacy-policy.php" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                                </label>
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
<script src="../../assets/js/coaches-registration.js"></script>
<script>
(function () {
    function fmt(raw) {
        var digits = raw.replace(/\D/g, '').substring(0, 10);
        if (!digits) return '';
        if (digits.length <= 3) return '(' + digits + ')';
        if (digits.length <= 6) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
        return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var ph = document.getElementById('phone');
        if (!ph) return;

        // Strip to raw digits while the user is actively typing
        ph.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });

        // Apply (###) ###-#### format when focus leaves the field
        ph.addEventListener('blur', function () {
            this.value = fmt(this.value);
        });

        // Strip back to raw digits when the user re-enters the field for editing
        ph.addEventListener('focus', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });

        // Format any pre-filled value on page load
        if (ph.value) ph.value = fmt(ph.value);
    });
})();
</script>
</body>
</html>
