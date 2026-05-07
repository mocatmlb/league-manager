<?php
/**
 * District 8 Travel League - Coaches Login Page
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

require_once EnvLoader::getPath('includes/AuthService.php');

/**
 * Only allow same-site coach paths in Location after login (mitigate open redirect).
 */
function coach_login_safe_redirect_target(?string $raw): string
{
    $t = trim((string) $raw);
    if ($t === '' || strpbrk($t, "\r\n") !== false) {
        return 'dashboard.php';
    }
    if (str_contains($t, '..') || str_contains($t, '://') || str_starts_with($t, '//')) {
        return 'dashboard.php';
    }
    // Relative coach PHP page (same directory as login.php)
    if (preg_match('/^[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_.~=&%-]*)?$/', $t)) {
        return $t;
    }
    // Absolute path on this host: coaches area only
    if ($t[0] === '/' && preg_match('#^/(?:public/)?coaches/[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_.~=&%-]*)?$#', $t)) {
        return $t;
    }

    return 'dashboard.php';
}

$error = '';
$success = '';
$identifier = trim((string) ($_POST['identifier'] ?? ''));
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$cssPath = file_exists(__DIR__ . '/../assets/css/style.css')
    ? '../assets/css/style.css'
    : '../../assets/css/style.css';
$jsPath = file_exists(__DIR__ . '/../assets/js/coaches-registration.js')
    ? '../assets/js/coaches-registration.js'
    : '../../assets/js/coaches-registration.js';

$captchaSiteKey = defined('RECAPTCHA_SITE_KEY')
    ? (string) RECAPTCHA_SITE_KEY
    : (defined('RECAPTCHA_SITE') ? (string) RECAPTCHA_SITE : '');

if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = 'You have been successfully logged out.';
}

if (Auth::isCoach()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $requiresCaptcha = AuthService::captchaRequired($ipAddress);
        if ($requiresCaptcha && !AuthService::verifyRecaptcha($_POST['g-recaptcha-response'] ?? null)) {
            $error = 'Please complete the CAPTCHA';
        } else {
            try {
                $loggedIn = AuthService::authenticate(
                    $identifier,
                    (string) ($_POST['password'] ?? ''),
                    $ipAddress,
                    !empty($_POST['remember_me'])
                );

                if ($loggedIn) {
                    // AC4 (Story 4.4): honour intended_url set by auth guard (sanitized)
                    $raw = isset($_SESSION['intended_url']) ? (string) $_SESSION['intended_url'] : '';
                    unset($_SESSION['intended_url']);
                    $redirect = coach_login_safe_redirect_target($raw !== '' ? $raw : null);
                    header('Location: ' . $redirect);
                    exit;
                }

                // Keep generic error except for explicit deprecated shared-credential message.
                if (strtolower($identifier) === 'coach') {
                    $error = 'Coach login has been updated — please use your individual account.';
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Unable to sign in right now. Please try again.';
            }
        }
    }
}

$failedAttempts = AuthService::failedAttemptsForIp($ipAddress);
$pageTitle = 'Coach Login — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo sanitize($cssPath); ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if ($captchaSiteKey !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo sanitize(APP_NAME); ?></a>
            <span class="badge bg-secondary">Team: Not assigned</span>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="card-header login-header">
                    <h2>Coaches Login</h2>
                    <p class="mb-0">Sign in with your username or email</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo sanitize($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i>
                            <?php echo sanitize($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="identifier" class="form-label">Username or Email</label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="identifier"
                                   name="identifier"
                                   value="<?php echo sanitize($identifier); ?>"
                                   required
                                   autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   placeholder="Enter password"
                                   autocomplete="current-password">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1">
                            <label class="form-check-label" for="remember_me">Remember me</label>
                        </div>
                        <div id="recaptcha-container" class="d-none mb-3" data-failed-attempts="<?php echo (int) $failedAttempts; ?>">
                            <?php if ($captchaSiteKey !== ''): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo sanitize($captchaSiteKey); ?>"></div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">CAPTCHA is unavailable in this environment.</div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Sign In
                            </button>
                        </div>
                        <div class="mt-3">
                            <a href="forgot-password.php">Forgot Password?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <p><small>Version <?php echo APP_VERSION; ?></small></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo sanitize($jsPath); ?>"></script>
</body>
</html>
