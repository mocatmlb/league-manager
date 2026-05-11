<?php
/**
 * District 8 Travel League - Coach Registration
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

$registrationEnabled = getSetting('open_registration', '0') === '1';
$service = new RegistrationService();
$globalError = '';
$formData = [
    'first_name' => '',
    'last_name' => '',
    'preferred_name' => '',
    'email' => '',
    'phone' => '',
    'phone_type' => 'mobile',
];
$fieldErrors = [];

$captchaSiteKey = defined('RECAPTCHA_SITE_KEY')
    ? (string) RECAPTCHA_SITE_KEY
    : (defined('RECAPTCHA_SITE') ? (string) RECAPTCHA_SITE : '');
$captchaSecretConfigured = (defined('RECAPTCHA_SECRET') && (string) RECAPTCHA_SECRET !== '')
    || (defined('RECAPTCHA_SECRET_KEY') && (string) RECAPTCHA_SECRET_KEY !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $key) {
        $formData[$key] = trim((string) ($_POST[$key] ?? $formData[$key]));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Abort early on CSRF failure
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $globalError = 'Invalid form submission. Please try again.';
    } else {
        if (!$registrationEnabled) {
            $globalError = 'Registration is currently closed.';
        }

        // CAPTCHA: fail-closed if the site/secret keys are not configured.
        // AR-8 fail-open is for "Google unreachable", not for missing config.
        if ($globalError === '') {
            if ($captchaSiteKey === '' || !$captchaSecretConfigured) {
                $globalError = 'Registration is temporarily unavailable. Please contact the league administrator.';
            } elseif (!AuthService::verifyRecaptcha($_POST['g-recaptcha-response'] ?? null)) {
                $fieldErrors['captcha'] = 'Please complete the CAPTCHA';
            }
        }

        if ($formData['first_name'] === '') $fieldErrors['first_name'] = 'First name is required.';
        if ($formData['last_name'] === '') $fieldErrors['last_name'] = 'Last name is required.';
        if ($formData['email'] === '' || !isValidEmail($formData['email'])) $fieldErrors['email'] = 'A valid email is required.';
        if ($formData['phone'] === '') $fieldErrors['phone'] = 'Phone is required.';
        if ($password === '') $fieldErrors['password'] = 'Password is required.';
        if ($confirmPassword === '' || $password !== $confirmPassword) $fieldErrors['confirm_password'] = 'Passwords must match.';

        if ($globalError === '' && empty($fieldErrors)) {
            try {
                $service->register([
                    'username' => $formData['email'],
                    'email' => $formData['email'],
                    'password' => $password,
                    'first_name' => $formData['first_name'],
                    'last_name' => $formData['last_name'],
                    'phone' => $formData['phone'],
                ]);

                $_SESSION['registered_email'] = $formData['email'];
                $_SESSION['flash_success'] = 'Registration complete. Please check your email to verify your account.'
                    . ((defined('EMAIL_DEV_LOG_ONLY') && EMAIL_DEV_LOG_ONLY === true)
                        ? ' (Local dev: outgoing mail is off — set EMAIL_DEV_LOG_ONLY to false and configure SMTP, or copy your verification token from the database.)'
                        : '');
                header('Location: verify-email.php');
                exit;
            } catch (DuplicateUsernameException $e) {
                $fieldErrors['email'] = 'An account with this email already exists.';
            } catch (DuplicateEmailException $e) {
                $fieldErrors['email'] = 'An account with this email already exists.';
            } catch (InvalidPasswordException $e) {
                $fieldErrors['password'] = $e->getMessage();
                $password = '';
                $confirmPassword = '';
            } catch (Throwable $e) {
                error_log('[register.php] RegistrationService::register() threw '
                    . get_class($e) . ': ' . $e->getMessage()
                    . ' in ' . $e->getFile() . ':' . $e->getLine());
                $globalError = 'Unable to complete registration right now. Please try again.'
                    . ' (' . get_class($e) . ': ' . $e->getMessage() . ')';
            }
        }
    }
}

$title = 'Coach Registration — District 8 Travel League';
$cssPath = file_exists(__DIR__ . '/../assets/css/style.css') ? '../assets/css/style.css' : '../../assets/css/style.css';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($title); ?></title>
    <meta name="robots" content="noindex">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo sanitize($cssPath); ?>" rel="stylesheet">
    <?php if ($captchaSiteKey !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php"><?php echo sanitize(APP_NAME); ?></a>
    </div>
</nav>

<div class="container py-4">
    <?php if (!$registrationEnabled): ?>
        <div class="alert alert-warning" role="alert">Registration is currently closed.</div>
    <?php else: ?>
        <?php if ($globalError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo sanitize($globalError); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h1 class="h4 mb-0">Create Your Account</h1></div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">First Name</label>
                            <input class="form-control form-control-lg" id="first_name" name="first_name" value="<?php echo sanitize($formData['first_name']); ?>" aria-describedby="first_name_error" required>
                            <div id="first_name_error" class="text-danger small"><?php echo sanitize($fieldErrors['first_name'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input class="form-control form-control-lg" id="last_name" name="last_name" value="<?php echo sanitize($formData['last_name']); ?>" aria-describedby="last_name_error" required>
                            <div id="last_name_error" class="text-danger small"><?php echo sanitize($fieldErrors['last_name'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="preferred_name">Preferred Name (Optional)</label>
                            <input class="form-control form-control-lg" id="preferred_name" name="preferred_name" value="<?php echo sanitize($formData['preferred_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control form-control-lg" id="email" name="email" type="email" value="<?php echo sanitize($formData['email']); ?>" aria-describedby="email_error" required>
                            <div id="email_error" class="text-danger small"><?php echo sanitize($fieldErrors['email'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control form-control-lg" id="phone" name="phone" value="<?php echo sanitize($formData['phone']); ?>" aria-describedby="phone_error" required>
                            <div id="phone_error" class="text-danger small"><?php echo sanitize($fieldErrors['phone'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone_type">Phone Type</label>
                            <select class="form-select form-select-lg" id="phone_type" name="phone_type">
                                <option value="mobile" <?php echo $formData['phone_type'] === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                                <option value="home" <?php echo $formData['phone_type'] === 'home' ? 'selected' : ''; ?>>Home</option>
                                <option value="work" <?php echo $formData['phone_type'] === 'work' ? 'selected' : ''; ?>>Work</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control form-control-lg" id="password" type="password" name="password" aria-describedby="password_error" required>
                            <div id="password_error" class="text-danger small"><?php echo sanitize($fieldErrors['password'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <input class="form-control form-control-lg" id="confirm_password" type="password" name="confirm_password" aria-describedby="confirm_password_error" required>
                            <div id="confirm_password_error" class="text-danger small"><?php echo sanitize($fieldErrors['confirm_password'] ?? ''); ?></div>
                        </div>
                        <div class="col-12">
                            <?php if ($captchaSiteKey !== '' && $captchaSecretConfigured): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo sanitize($captchaSiteKey); ?>"></div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">Registration is temporarily unavailable. Please contact the league administrator.</div>
                            <?php endif; ?>
                            <?php if (!empty($fieldErrors['captcha'])): ?>
                                <div class="text-danger small mt-1"><?php echo sanitize($fieldErrors['captcha']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-lg" type="submit">Create Account</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
