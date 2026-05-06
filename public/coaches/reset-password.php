<?php
/**
 * District 8 Travel League - Reset Password
 */

try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'
        : __DIR__ . '/../../includes/coach_bootstrap.php';
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

require_once EnvLoader::getPath('includes/RegistrationService.php');
require_once EnvLoader::getPath('includes/AuthService.php');
$service = new RegistrationService();

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$expired = false;

if ($token === '') {
    $expired = true;
    $error = 'This link has expired.';
}

// Token validity is determined entirely inside completePasswordReset(); the
// previous "pre-validate via direct DB query" pattern raced with the
// service's own consume-token UPDATE, allowing the form to be submitted
// twice. We still do a soft existence check on GET so an expired/already-
// used token displays the "expired" UI instead of an empty form, but we
// do NOT treat its result as authoritative.
if (!$expired && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = Database::getInstance();
    $tokenRow = $db->fetchOne(
        'SELECT id FROM users
         WHERE password_reset_token = :token
           AND password_reset_expiry >= NOW()
         LIMIT 1',
        ['token' => $token]
    );
    if ($tokenRow === false) {
        $expired = true;
        $error = 'This link has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$expired) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // Per-IP submission throttle (5 / 15 min) — same scheme as forgot-password.
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $resolvedIp = method_exists('AuthService', 'resolveClientIp')
            ? AuthService::resolveClientIp($ipAddress)
            : $ipAddress;
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne(
                'SELECT COUNT(*) AS count FROM login_attempts
                 WHERE identifier = :id AND ip_address = :ip
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
                ['id' => 'reset_password', 'ip' => $resolvedIp]
            );
            $recent = (int) ($row['count'] ?? 0);
        } catch (Throwable $e) {
            $recent = 0;
        }

        if ($recent >= 5) {
            $error = 'Too many attempts. Please try again later.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords must match.';
        } else {
            try {
                $db = Database::getInstance();
                try {
                    $db->query(
                        'INSERT INTO login_attempts (identifier, ip_address, attempted_at)
                         VALUES (:id, :ip, NOW())',
                        ['id' => 'reset_password', 'ip' => $resolvedIp]
                    );
                } catch (Throwable $e) {
                    // Non-fatal.
                }

                $service->completePasswordReset($token, $password);

                // Regenerate session id to invalidate any stale session bound
                // to this user (e.g., logged-in attacker with stolen credentials).
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                $_SESSION['flash_success'] = 'Password updated — please log in';
                header('Location: login.php');
                exit;
            } catch (WeakPasswordException $e) {
                $error = $e->getMessage();
            } catch (ExpiredTokenException $e) {
                $expired = true;
                $error = 'This link has expired.';
            } catch (Throwable $e) {
                $error = 'Unable to reset password right now. Please try again.';
            }
        }
    }
}

$title = 'Reset Password — District 8 Travel League';
$cssPath = file_exists(__DIR__ . '/../assets/css/style.css') ? '../assets/css/style.css' : '../../assets/css/style.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($title); ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo sanitize($cssPath); ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php"><?php echo sanitize(APP_NAME); ?></a>
    </div>
</nav>
<div class="container py-4">
    <div class="card">
        <div class="card-header"><h1 class="h4 mb-0">Reset Password</h1></div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-warning" role="alert"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <?php if ($expired): ?>
                <a class="btn btn-primary btn-lg" href="forgot-password.php">Request New Reset Link</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                    <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input id="password" name="password" type="password" class="form-control form-control-lg" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" class="form-control form-control-lg" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
