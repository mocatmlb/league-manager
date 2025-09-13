<?php
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

define('D8TL_APP', true);

// Use include_once instead of require_once and validate expected symbols
$included = @include_once EnvLoader::getPath('includes/admin_bootstrap.php');
if ($included === false || !class_exists('Auth')) {
    $adminBootstrapPath = EnvLoader::getPath('includes/admin_bootstrap.php');
    $bootstrapPath = EnvLoader::getPath('includes/bootstrap.php');
    $checks = [
        'admin_bootstrap_path' => $adminBootstrapPath,
        'admin_bootstrap_exists' => file_exists($adminBootstrapPath),
        'bootstrap_path' => $bootstrapPath,
        'bootstrap_exists' => file_exists($bootstrapPath),
        'includes_dir_readable' => is_dir(EnvLoader::getBasePath() . '/includes') && is_readable(EnvLoader::getBasePath() . '/includes'),
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
        'is_production_guess' => EnvLoader::isProduction(),
    ];

    error_log('D8TL ERROR: admin_bootstrap include failed or Auth class missing. Checks: ' . json_encode($checks));

    // Render a simple diagnostic page to the browser (temporary for debugging)
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Configuration error</title>';
    echo '<style>body{font-family:Segoe UI,Arial,Helvetica,sans-serif;padding:20px}pre{background:#f8f9fa;padding:10px;border:1px solid #ddd}</style>';
    echo '</head><body>';
    echo '<h1>Configuration/Include Error</h1>';
    echo '<p>The admin bootstrap could not be loaded or did not initialize correctly. See diagnostic data below.</p>';
    echo '<pre>' . htmlspecialchars(print_r($checks, true)) . '</pre>';
    echo '<p>Check server error logs for lines starting with "D8TL DEBUG" or "D8TL ERROR".</p>';
    echo '</body></html>';
    exit;
}

// Ensure user is logged in as admin
if (!Auth::isAdmin()) {
    header('Location: login.php');
    exit();
}

$currentUser = Auth::getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = trim(filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Basic validation
        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid username and email address.';
        }

        // Validate current password if trying to change password
        if (!$error && !empty($newPassword)) {
            if (empty($currentPassword)) {
                $error = 'Current password is required to set a new password';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters long';
            } else {
                // Verify current password against stored hash
                try {
                    $db = Database::getInstance();
                    $row = $db->fetchOne('SELECT password FROM admin_users WHERE id = ?', [$currentUser['id']]);
                    if (!$row || !password_verify($currentPassword, $row['password'])) {
                        $error = 'Current password is incorrect';
                    }
                } catch (Exception $e) {
                    error_log('Profile password verify failed: ' . $e->getMessage());
                    $error = 'An error occurred while verifying your password.';
                }
            }
        }

        if (!$error) {
            try {
                $db = isset($db) ? $db : Database::getInstance();
                $updateData = [
                    'username' => $username,
                    'email' => $email,
                ];
                if (!empty($newPassword)) {
                    $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateData['password_changed_at'] = date('Y-m-d H:i:s');
                }

                $db->update('admin_users', $updateData, 'id = :id', ['id' => $currentUser['id']]);
                $message = 'Profile updated successfully';

                // Update session/display data
                $_SESSION['admin_username'] = $username;
                $_SESSION['user']['username'] = $username; // for header display compatibility
                $_SESSION['user']['email'] = $email;

                // Refresh current user array for this request
                $currentUser['username'] = $username;
            } catch (Exception $e) {
                error_log('Error updating profile: ' . $e->getMessage());
                $error = 'Error updating profile. Please try again later.';
            }
        }
    }
}

$pageTitle = 'My Profile';
include EnvLoader::getPath('includes/admin_header.php');
?>

<!-- Main content area with proper spacing -->
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title h5 mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <hr>
                        <h4 class="h5 mb-3"><i class="fas fa-key me-2"></i>Change Password</h4>
                        <p class="text-muted small">Leave password fields empty to keep your current password</p>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                            </div>
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                            <a href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include EnvLoader::getPath('includes/admin_footer.php'); ?>
