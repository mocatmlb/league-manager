<?php
/**
 * District 8 Travel League - Forgot Password
 * 
 * Password reset request page for new user accounts system
 */

define('D8TL_APP', true);
require_once '../includes/bootstrap.php';
require_once '../includes/AuthService.php';
require_once '../includes/UserAccountEmailService.php';

$authService = new AuthService();
$userAccountManager = $authService->getUserAccountManager();
$emailService = new UserAccountEmailService();

$error = '';
$success = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        $authService->validateForm();
        
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Find user by email
            $user = $userAccountManager->getUserByUsernameOrEmail($email);
            
            if ($user && $user['status'] === 'active') {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Update user with reset token
                $db = Database::getInstance();
                $db->update(
                    'users',
                    [
                        'password_reset_token' => $resetToken,
                        'password_reset_expiry' => $resetExpiry
                    ],
                    'id = :user_id',
                    ['user_id' => $user['id']]
                );
                
                // Send reset email
                $emailSent = $emailService->sendPasswordReset($user, $resetToken);
                
                if ($emailSent) {
                    Logger::info("Password reset requested", [
                        'user_id' => $user['id'],
                        'email' => $email
                    ]);
                }
            }
            
            // Always show success message for security (don't reveal if email exists)
            $success = 'If an account with that email address exists, you will receive a password reset link shortly.';
        }
        
    } catch (Exception $e) {
        $error = 'An error occurred. Please try again.';
        Logger::error("Password reset error", ['error' => $e->getMessage()]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .auth-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h1 class="h3">Reset Password</h1>
                <p class="text-muted">District 8 Travel League</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>Note:</strong> Password reset is only available for individual user accounts. 
                    If you use the shared coach login, please contact an administrator.
                </div>
                
                <form method="POST" action="">
                    <?= $authService->csrfTokenField() ?>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="Enter your email address" required autofocus>
                        <div class="form-text">
                            Enter the email address associated with your account
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted">
                    Remember your password? <a href="login.php">Sign in</a>
                </p>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-secondary">
                    ← Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
