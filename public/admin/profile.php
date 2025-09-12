<?php
// Define the root path based on the environment
$rootPath = dirname(dirname(dirname(__FILE__)));
require_once $rootPath . '/includes/admin_bootstrap.php';

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
include $rootPath . '/includes/admin_header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Profile</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>

                        <hr>
                        <h4>Change Password</h4>
                        <p class="text-muted">Leave password fields empty to keep your current password</p>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                   minlength="8">
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <a href="../index.php" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $rootPath . '/includes/admin_footer.php'; ?>
