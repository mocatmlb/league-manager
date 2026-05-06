<?php
/**
 * District 8 Travel League - Email Verification
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
$service = new RegistrationService();

$token = trim((string) ($_GET['token'] ?? ''));
$expiredToken = false;
$message = '';
$error = '';
$mode = 'check-email';

// Resend handler — accepts user-supplied email rather than a leaked user_id
// from a prior page render. The service silently no-ops for unknown or
// non-unverified emails (no enumeration), so we always show the same
// confirmation message regardless of whether the address is real.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter the email address you registered with.';
        } else {
            try {
                $service->resendVerification($email);
            } catch (Throwable $e) {
                // Suppress: no enumeration via differential errors.
                if (class_exists('Logger')) {
                    Logger::error('resendVerification failed (suppressed)', ['email' => $email, 'error' => $e->getMessage()]);
                }
            }
            $mode = 'check-email';
            $message = 'If that email matches an unverified account, a new verification link has been sent.';
        }
    }
}

if ($token !== '' && $error === '') {
    try {
        $service->verifyEmail($token);
        $mode = 'verified';
        $message = 'Email verified — your account is active.';
    } catch (ExpiredTokenException $e) {
        $mode = 'expired';
        $expiredToken = true;
        $error = 'Link expired.';
    } catch (Throwable $e) {
        $mode = 'expired';
        $error = 'Verification link is invalid or already used.';
    }
} elseif (isset($_SESSION['flash_success'])) {
    $message = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$title = 'Verify Email — District 8 Travel League';
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
        <div class="card-body">
            <?php if ($mode === 'verified'): ?>
                <h1 class="h4">Email Verified</h1>
                <div class="alert alert-success" role="alert"><?php echo sanitize($message); ?></div>
                <a class="btn btn-primary btn-lg" href="login.php">Continue to Login</a>
            <?php elseif ($mode === 'expired'): ?>
                <h1 class="h4">Verification Link Problem</h1>
                <div class="alert alert-warning" role="alert"><?php echo sanitize($error); ?></div>
                <?php if ($expiredToken): ?>
                    <p class="mb-3">If you still need account access, enter your registered email address to request another verification email.</p>
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                        <input type="hidden" name="action" value="resend">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email Address</label>
                            <input class="form-control form-control-lg" id="email" name="email" type="email" required aria-describedby="email_help">
                            <div id="email_help" class="form-text">We will send a new verification link if your account exists and is still unverified.</div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">Resend Verification</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <h1 class="h4">Check Your Email</h1>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success" role="alert"><?php echo sanitize($message); ?></div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        We sent a verification link to your email address. Please open it to activate your account.
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <a class="btn btn-outline-primary btn-lg" href="login.php">Back to Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
