<?php
/**
 * District 8 Travel League - Forgot Password
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

$email = '';
$error = '';
$confirmation = (string) ($_SESSION['forgot_confirmation'] ?? '');
unset($_SESSION['forgot_confirmation']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // Lightweight throttle: cap per-IP forgot-password requests at 5
        // per 15 minutes (recorded in login_attempts under a synthetic
        // identifier so we share lazy-purge with the login flow).
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
                ['id' => 'forgot_password', 'ip' => $resolvedIp]
            );
            $recent = (int) ($row['count'] ?? 0);
        } catch (Throwable $e) {
            $recent = 0;
        }

        if ($recent >= 5) {
            // Same generic confirmation regardless — no enumeration of
            // throttle state vs. account state.
            $_SESSION['forgot_confirmation'] = 'If an account exists for that email, a reset link has been sent.';
            header('Location: forgot-password.php');
            exit;
        }

        try {
            // Record attempt BEFORE the call so legitimate-but-spammy users
            // see the throttle quickly.
            try {
                $db = Database::getInstance();
                $db->query(
                    'INSERT INTO login_attempts (identifier, ip_address, attempted_at)
                     VALUES (:id, :ip, NOW())',
                    ['id' => 'forgot_password', 'ip' => $resolvedIp]
                );
            } catch (Throwable $e) {
                // Non-fatal; throttle bookkeeping failure should not block users.
            }

            $service->requestPasswordReset($email);
        } catch (Throwable $e) {
            // AC5: never expose error states differently for known vs unknown
            // emails. Log operationally and show the same confirmation.
            if (class_exists('Logger')) {
                Logger::error('forgot-password handler error (suppressed)', ['error' => $e->getMessage()]);
            }
        }

        $_SESSION['forgot_confirmation'] = 'If an account exists for that email, a reset link has been sent.';
        header('Location: forgot-password.php');
        exit;
    }
}

$title = 'Forgot Password — District 8 Travel League';
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
        <div class="card-header"><h1 class="h4 mb-0">Forgot Password</h1></div>
        <div class="card-body">
            <?php if ($confirmation !== ''): ?>
                <div class="alert alert-info" role="alert"><?php echo sanitize($confirmation); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input id="email" name="email" type="email" class="form-control form-control-lg" value="<?php echo sanitize($email); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
                <a class="btn btn-link" href="login.php">Back to Login</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
