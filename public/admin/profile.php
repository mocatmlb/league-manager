<?php
require_once __DIR__ . '/../includes/env-loader.php';
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

// Ensure user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = Auth::getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate current password if trying to change password
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $error = "Current password is required to set a new password";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match";
        } elseif (strlen($newPassword) < 8) {
            $error = "New password must be at least 8 characters long";
        } else {
            // Verify current password
            if (!Auth::verifyPassword($currentPassword, $currentUser['password'])) {
                $error = "Current password is incorrect";
            }
        }
    }

    if (empty($error)) {
        $updateData = [
            'username' => $username,
            'email' => $email
        ];

        // Only include password if it's being changed
        if (!empty($newPassword)) {
            $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ?" .
                (!empty($newPassword) ? ", password = ?" : "") .
                " WHERE id = ?");

            $params = [$username, $email];
            if (!empty($newPassword)) {
                $params[] = $updateData['password'];
            }
            $params[] = $currentUser['id'];

            $stmt->execute($params);
            $message = "Profile updated successfully";

            // Update session data
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['email'] = $email;
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

$pageTitle = "My Profile";
include EnvLoader::getPath('includes/admin_header.php');
?>

<!-- Main content area with proper spacing -->
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo EnvLoader::getBasePath(); ?>/admin/">Dashboard</a></li>
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
                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
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
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       minlength="8">
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
                            <a href="../index.php" class="btn btn-secondary">
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
