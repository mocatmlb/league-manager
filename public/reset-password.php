<?php
/**
 * District 8 Travel League - Reset Password
 * 
 * Password reset form for new user accounts system
 */

define('D8TL_APP', true);
require_once '../includes/bootstrap.php';
require_once '../includes/AuthService.php';

$authService = new AuthService();
$userAccountManager = $authService->getUserAccountManager();

$error = '';
$success = '';
$validToken = false;
$user = null;

// Check if token is provided and valid
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid password reset link.';
} else {
    $token = $_GET['token'];
    
    // Validate reset token
    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE password_reset_token = ? AND password_reset_expiry > NOW() AND status = 'active'",
        [$token]
    );
    
    if (!$user) {
        $error = 'Invalid or expired password reset link. Please request a new password reset.';
    } else {
        $validToken = true;
    }
}

// Process password reset form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    try {
        // Validate CSRF token
        $authService->validateForm();
        
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            $error = 'Password is required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // Change password
            $success = $userAccountManager->changePassword($user['id'], $password);
            
            if ($success) {
                // Clear reset token
                $db->update(
                    'users',
                    [
                        'password_reset_token' => null,
                        'password_reset_expiry' => null
                    ],
                    'id = :user_id',
                    ['user_id' => $user['id']]
                );
                
                Logger::info("Password reset completed", [
                    'user_id' => $user['id'],
                    'username' => $user['username']
                ]);
                
                $success = 'Your password has been reset successfully! You can now log in with your new password.';
                $validToken = false; // Hide form
                
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        Logger::error("Password reset error", ['error' => $e->getMessage(), 'user_id' => $user['id'] ?? null]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - District 8 Travel League</title>
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
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h1 class="h3">Reset Your Password</h1>
                <p class="text-muted">District 8 Travel League</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                    <?php if (!$validToken): ?>
                        <div class="mt-3">
                            <a href="forgot-password.php" class="btn btn-primary">Request New Reset</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            <?php elseif ($validToken): ?>
                <div class="alert alert-info">
                    <strong>Hello <?= htmlspecialchars($user['first_name']) ?>!</strong> 
                    Please enter your new password below.
                </div>
                
                <form method="POST" action="">
                    <?= $authService->csrfTokenField() ?>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div id="passwordStrength" class="password-strength"></div>
                        <div class="form-text">
                            Password must be at least 8 characters and contain uppercase, lowercase, and numbers
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-outline-secondary">
                    ← Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score++;
            else feedback.push('at least 8 characters');
            
            if (/[A-Z]/.test(password)) score++;
            else feedback.push('uppercase letter');
            
            if (/[a-z]/.test(password)) score++;
            else feedback.push('lowercase letter');
            
            if (/[0-9]/.test(password)) score++;
            else feedback.push('number');
            
            if (score === 0) {
                strengthDiv.textContent = '';
                strengthDiv.className = 'password-strength';
            } else if (score < 3) {
                strengthDiv.textContent = 'Weak - Missing: ' + feedback.join(', ');
                strengthDiv.className = 'password-strength strength-weak';
            } else if (score === 3) {
                strengthDiv.textContent = 'Medium - Missing: ' + feedback.join(', ');
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                strengthDiv.textContent = 'Strong';
                strengthDiv.className = 'password-strength strength-strong';
            }
        });
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchDiv.textContent = '';
                matchDiv.className = 'form-text';
            } else if (password === confirmPassword) {
                matchDiv.textContent = 'Passwords match';
                matchDiv.className = 'form-text text-success';
            } else {
                matchDiv.textContent = 'Passwords do not match';
                matchDiv.className = 'form-text text-danger';
            }
        }
        
        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>
