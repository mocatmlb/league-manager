<?php
try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'
        : __DIR__ . '/../../includes/coach_bootstrap.php';
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

require_once EnvLoader::getPath('includes/ProfileService.php');

if (empty($_SESSION['force_password_change'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$coachUserId = (int) ($_SESSION['coach_user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($newPassword === '') {
            $error = 'New password is required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $profileService = new ProfileService();
                $profileService->forceSetPassword($coachUserId, $newPassword);

                $db = Database::getInstance();
                $db->query(
                    "UPDATE users SET force_password_change = 0, updated_at = NOW() WHERE id = :id",
                    ['id' => $coachUserId]
                );
                unset($_SESSION['force_password_change']);

                $_SESSION['flash_success'] = 'Password changed successfully.';
                header('Location: dashboard.php');
                exit;
            } catch (WeakPasswordException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('[force-change-password] Error: ' . $e->getMessage());
                $error = 'Unable to change password. Please try again.';
            }
        }
    }
}

$cssPath = file_exists(__DIR__ . '/../assets/css/style.css')
    ? '../assets/css/style.css'
    : '../../assets/css/style.css';
$pageTitle = 'Change Your Password — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($cssPath); ?>" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Change Your Password</h3>
                        <p class="mb-0 text-muted">You must set a new password before continuing.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
                                <div class="form-text">At least 8 characters with uppercase, number, and special character.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
