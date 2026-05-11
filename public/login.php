<?php
/**
 * District 8 Travel League - Unified Login Page
 *
 * Single entry point for coaches and admins. Tries coach auth first
 * (AuthService::authenticate against `users` table), then admin auth
 * (Auth::authenticateAdmin against `admin_users` table) on failure.
 */

try {
    $bootstrapPath = file_exists(__DIR__ . '/includes/coach_bootstrap.php')
        ? __DIR__ . '/includes/coach_bootstrap.php'
        : __DIR__ . '/../includes/coach_bootstrap.php';
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

require_once EnvLoader::getPath('includes/AuthService.php');
require_once EnvLoader::getPath('includes/ActivityLogger.php');

/**
 * Allow only same-site coach paths in the intended_url redirect after login.
 * Mirrors coach_login_safe_redirect_target() from coaches/login.php exactly.
 */
function unified_login_safe_redirect_target(?string $raw): string
{
    $t = trim((string) $raw);
    if ($t === '' || strpbrk($t, "\r\n") !== false) {
        return 'coaches/dashboard.php';
    }
    if (str_contains($t, '..') || str_contains($t, '://') || str_starts_with($t, '//')) {
        return 'coaches/dashboard.php';
    }
    // Relative coach PHP page
    if (preg_match('/^coaches\/[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_.~=&%-]*)?$/', $t)) {
        return $t;
    }
    // Absolute path on this host: coaches area only
    if ($t[0] === '/' && preg_match('#^/(?:public/)?coaches/[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_.~=&%-]*)?$#', $t)) {
        return $t;
    }

    return 'coaches/dashboard.php';
}

$error   = '';
$success = '';
$identifier = trim((string) ($_POST['identifier'] ?? ''));
$ipAddress  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$cssPath = 'assets/css/style.css';

$captchaSiteKey = defined('RECAPTCHA_SITE_KEY')
    ? (string) RECAPTCHA_SITE_KEY
    : (defined('RECAPTCHA_SITE') ? (string) RECAPTCHA_SITE : '');

// Flash messages
if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = 'You have been successfully logged out.';
}

// Already-authenticated redirect — check admin FIRST (isCoach() is true for admins too)
if (Auth::isAdmin()) {
    $adminUrl = EnvLoader::isProduction() ? '/admin/index.php' : '/public/admin/index.php';
    header('Location: ' . $adminUrl);
    exit;
}
if (Auth::isCoach()) {
    $coachUrl = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';
    header('Location: ' . $coachUrl);
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        if ($captchaSiteKey !== '' && !AuthService::verifyRecaptcha($_POST['g-recaptcha-response'] ?? null)) {
            $error = 'Please complete the CAPTCHA';
        } else {
            $password   = (string) ($_POST['password'] ?? '');
            $rememberMe = !empty($_POST['remember_me']);

            try {
                $coachLoggedIn = AuthService::authenticate($identifier, $password, $ipAddress, $rememberMe);

                if ($coachLoggedIn) {
                    // Regenerate session ID on successful login to prevent fixation
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }

                    // Honor intended_url set by auth guard (AC2)
                    $raw = isset($_SESSION['intended_url']) ? (string) $_SESSION['intended_url'] : '';
                    unset($_SESSION['intended_url']);
                    $redirect = unified_login_safe_redirect_target($raw !== '' ? $raw : null);
                    header('Location: ' . $redirect);
                    exit;
                }

                // Coach auth returned false — try admin auth (AC3)
                if (Auth::authenticateAdmin($identifier, $password)) {
                    // Regenerate session ID on successful login to prevent fixation
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }

                    ActivityLogger::log('admin_login', ['username' => $identifier, 'ip' => $ipAddress]);
                    $adminUrl = EnvLoader::isProduction() ? '/admin/index.php' : '/public/admin/index.php';
                    header('Location: ' . $adminUrl);
                    exit;
                }

                // Both failed (AC4)
                $error = 'Invalid email/username or password';

            } catch (RuntimeException $e) {
                // AC5: status error (unverified, disabled) — do NOT try admin auth
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('[login] Throwable during authenticate: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $error = 'Unable to sign in right now. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In — District 8 Travel League';
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
            <a class="navbar-brand" href="index.php"><?php echo sanitize(APP_NAME); ?></a>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="card-header login-header">
                    <h2>Sign In</h2>
                    <p class="mb-0">District 8 Travel League</p>
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
                            <label for="identifier" class="form-label">Email or Username</label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="identifier"
                                   name="identifier"
                                   value="<?php echo sanitize($identifier); ?>"
                                   required
                                   autocomplete="username"
                                   aria-describedby="identifierHelp">
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

                        <?php if ($captchaSiteKey !== ''): ?>
                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="<?php echo sanitize($captchaSiteKey); ?>"></div>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Sign In
                            </button>
                        </div>

                        <div class="mt-3">
                            <a href="coaches/forgot-password.php">Forgot Password?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                    <p><small>Version <?php echo APP_VERSION; ?></small></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
