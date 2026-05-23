<?php
/**
 * District 8 Travel League — Admin Create User Account
 *
 * Story 13.2: Admin can create a fully active user account directly.
 */

$__dir   = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    if (file_exists($__dir . '/includes/env-loader.php')) {
        require_once $__dir . '/includes/env-loader.php';
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
unset($__dir, $__found, $__i);

@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
Auth::requireAdmin();

$adminUserId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminUserId < 1) {
    $_SESSION['flash_error'] = 'Your admin session is invalid. Please sign in again.';
    header('Location: ../login.php');
    exit;
}

if (!class_exists('UserManagementService')) {
    require_once EnvLoader::getPath('includes/UserManagementService.php');
}

$error    = '';
$formErrors = [];
$formData = [
    'first_name'    => '',
    'last_name'     => '',
    'email'         => '',
    'username'      => '',
    'phone'         => '',
    'preferred_name' => '',
    'role'          => 'user',
    'password_mode' => 'generate',
    'password'      => '',
    'send_welcome'  => '',
];

// --------------------------------------------------------------------------
// POST handling
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Collect form data for re-render on failure
        $formData = [
            'first_name'    => trim($_POST['first_name']    ?? ''),
            'last_name'     => trim($_POST['last_name']     ?? ''),
            'email'         => trim($_POST['email']         ?? ''),
            'username'      => trim($_POST['username']      ?? ''),
            'phone'         => trim($_POST['phone']         ?? ''),
            'preferred_name' => trim($_POST['preferred_name'] ?? ''),
            'role'          => trim($_POST['role']          ?? 'user'),
            'password_mode' => trim($_POST['password_mode'] ?? 'generate'),
            'password'      => '',  // never re-populate password field
            'send_welcome'  => $_POST['send_welcome'] ?? '',
        ];

        $service = new UserManagementService();

        try {
            $result = $service->createAccount([
                'first_name'    => $formData['first_name'],
                'last_name'     => $formData['last_name'],
                'email'         => $formData['email'],
                'username'      => $formData['username'],
                'phone'         => $formData['phone'],
                'preferred_name' => $formData['preferred_name'],
                'role'          => $formData['role'],
                'password_mode' => $formData['password_mode'],
                'password'      => trim($_POST['password'] ?? ''),
            ], $adminUserId);

            $newUserId    = $result['user_id'];
            $tempPassword = $result['temp_password'];
            $username     = $formData['username'];

            // Optional welcome email
            if (($formData['send_welcome'] ?? '') === '1') {
                try {
                    if (!class_exists('EmailService')) {
                        require_once EnvLoader::getPath('includes/EmailService.php');
                    }
                    $emailSvc = new EmailService();
                    $body = "Your account has been created.\n\nUsername: {$username}\n"
                          . ($tempPassword
                              ? "Temporary password: {$tempPassword}\n\nYou will be prompted to change your password on first login."
                              : "Please log in with the password that was set for your account.");
                    $emailSent = $emailSvc->sendTestEmail(
                        $formData['email'],
                        'Your District 8 Travel League account has been created',
                        $body
                    );
                    if ($emailSent !== true) {
                        Logger::error('[createAccount] Welcome email send returned false', [
                            'user_id' => $newUserId,
                            'email'   => $formData['email'],
                        ]);
                    }
                } catch (Throwable $e) {
                    Logger::error('[createAccount] Welcome email failed', [
                        'error'   => $e->getMessage(),
                        'user_id' => $newUserId,
                    ]);
                }
            }

            $flashMsg = "User account created. Username: {$username}";
            if ($tempPassword) {
                $flashMsg .= ", temporary password: {$tempPassword}";
            }
            $_SESSION['flash_message'] = $flashMsg;
            header('Location: detail.php?id=' . $newUserId);
            exit;

        } catch (DuplicateUsernameException $e) {
            $formErrors['username'] = 'That username is already taken. Please choose a different username.';
        } catch (DuplicateEmailException $e) {
            $formErrors['email'] = 'That email address is already registered. Please use a different email.';
        } catch (InvalidPasswordException $e) {
            $formErrors['password'] = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Username')) {
                $formErrors['username'] = $message;
            } elseif (str_contains(strtolower($message), 'email')) {
                $formErrors['email'] = $message;
            } elseif (str_contains($message, 'Password')) {
                $formErrors['password'] = $message;
            } else {
                $error = $message;
            }
        } catch (Throwable $e) {
            Logger::error('[createAccount] Unexpected error', ['error' => $e->getMessage()]);
            $error = 'An unexpected error occurred. Please try again or contact support.';
        }
    }
}

$pageTitle = 'Create User — ' . APP_NAME;
$fieldClass = static function (string $field) use ($formErrors): string {
    return isset($formErrors[$field]) ? ' is-invalid' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php
    $__nav = file_exists(__DIR__ . '/../../includes/nav.php')
        ? __DIR__ . '/../../includes/nav.php'
        : __DIR__ . '/../../../includes/nav.php';
    include $__nav;
    unset($__nav);
    ?>

    <div class="container mt-4">
        <div class="mb-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to User List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo sanitize($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus me-1"></i> Create User Account</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="create.php">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?php echo sanitize($formData['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?php echo sanitize($formData['last_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control<?php echo $fieldClass('email'); ?>"
                                   value="<?php echo sanitize($formData['email']); ?>" required>
                            <?php if (isset($formErrors['email'])): ?>
                                <div class="invalid-feedback d-block"><?php echo sanitize($formErrors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control<?php echo $fieldClass('username'); ?>"
                                   value="<?php echo sanitize($formData['username']); ?>" required>
                            <?php if (isset($formErrors['username'])): ?>
                                <div class="invalid-feedback d-block"><?php echo sanitize($formErrors['username']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo sanitize($formData['phone']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preferred Name <span class="text-muted">(optional)</span></label>
                            <input type="text" name="preferred_name" class="form-control"
                                   value="<?php echo sanitize($formData['preferred_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="user"          <?php echo $formData['role'] === 'user'          ? 'selected' : ''; ?>>User</option>
                                <option value="team_owner"    <?php echo $formData['role'] === 'team_owner'    ? 'selected' : ''; ?>>Team Owner</option>
                                <option value="administrator" <?php echo $formData['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="password_mode" id="modeGenerate"
                                   value="generate" <?php echo $formData['password_mode'] !== 'manual' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="modeGenerate">
                                Auto-generate a temporary password
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="password_mode" id="modeManual"
                                   value="manual" <?php echo $formData['password_mode'] === 'manual' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="modeManual">
                                Set a password
                            </label>
                        </div>
                    </div>

                    <div id="manualPasswordField" style="display:<?php echo $formData['password_mode'] === 'manual' ? 'block' : 'none'; ?>">
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="passwordField" class="form-control<?php echo $fieldClass('password'); ?>"
                                   autocomplete="new-password">
                            <?php if (isset($formErrors['password'])): ?>
                                <div class="invalid-feedback d-block"><?php echo sanitize($formErrors['password']); ?></div>
                            <?php endif; ?>
                            <div class="form-text">Min 8 characters, uppercase letter, number, and special character.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_welcome" id="sendWelcome"
                                   value="1" <?php echo $formData['send_welcome'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="sendWelcome">
                                Send welcome email with login details
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Create Account
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('input[name="password_mode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('manualPasswordField').style.display =
                    this.value === 'manual' ? 'block' : 'none';
                if (this.value !== 'manual') {
                    document.getElementById('passwordField').value = '';
                }
            });
        });
    </script>
</body>
</html>
