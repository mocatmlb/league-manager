<?php
/**
 * District 8 Travel League - User Registration
 * 
 * Invitation-based user registration for the new user accounts system
 */

define('D8TL_APP', true);
require_once '../includes/bootstrap.php';
require_once '../includes/AuthService.php';
require_once '../includes/InvitationManager.php';
require_once '../includes/UserAccountEmailService.php';

$authService = new AuthService();
$invitationManager = new InvitationManager();
$userAccountManager = $authService->getUserAccountManager();
$emailService = new UserAccountEmailService();

$error = '';
$success = '';
$invitation = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid registration link. Please use the link provided in your invitation email.';
} else {
    $token = $_GET['token'];
    
    // Validate invitation
    $validationResult = $invitationManager->validateInvitation($token);
    
    if (!$validationResult['valid']) {
        $error = $validationResult['error'];
    } else {
        $invitation = $validationResult['invitation'];
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    try {
        // Validate CSRF token
        $authService->validateForm();
        
        // Get form data
        $formData = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? '')
        ];
        
        // Basic validation
        $errors = [];
        
        if (empty($formData['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($formData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        if (empty($formData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($formData['password'])) {
            $errors[] = 'Password is required';
        } elseif ($formData['password'] !== $formData['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($formData['first_name'])) {
            $errors[] = 'First name is required';
        }
        
        if (empty($formData['last_name'])) {
            $errors[] = 'Last name is required';
        }
        
        if (empty($formData['phone'])) {
            $errors[] = 'Phone number is required';
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Create user account
            $userData = [
                'username' => $formData['username'],
                'email' => $formData['email'],
                'password' => $formData['password'],
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'phone' => $formData['phone'],
                'role_id' => $invitation['role_id'],
                'status' => 'active' // Direct activation for invited users
            ];
            
            $userId = $userAccountManager->createUser($userData);
            
            if ($userId) {
                // Mark invitation as completed
                $invitationManager->completeInvitation($token, $userId);
                
                // Get complete user data for email
                $user = $userAccountManager->getUserById($userId);
                
                // Send registration completion email
                $emailService->sendRegistrationComplete($user);
                
                $success = 'Your account has been created successfully! You can now log in with your username and password.';
                
                // Clear form data on success
                $formData = [];
                
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        Logger::error("Registration error", ['error' => $e->getMessage(), 'token' => $token]);
    }
}

// Pre-populate email from invitation
if ($invitation && empty($formData['email'])) {
    $formData['email'] = $invitation['email'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - District 8 Travel League</title>
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
            max-width: 500px;
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
                <h1 class="h3">Create Your Account</h1>
                <p class="text-muted">District 8 Travel League</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            <?php elseif ($invitation): ?>
                <div class="alert alert-info">
                    <strong>Welcome!</strong> You've been invited to create an account as a <strong><?= htmlspecialchars($invitation['role_name']) ?></strong>.
                </div>
                
                <form method="POST" action="" id="registrationForm">
                    <?= $authService->csrfTokenField() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" 
                               placeholder="(555) 123-4567" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= htmlspecialchars($formData['username'] ?? '') ?>" 
                               pattern="[a-zA-Z0-9_]+" 
                               title="Username can only contain letters, numbers, and underscores" required>
                        <div class="form-text">3-50 characters, letters, numbers, and underscores only</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div id="passwordStrength" class="password-strength"></div>
                        <div class="form-text">
                            Password must be at least 8 characters and contain uppercase, lowercase, and numbers
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted">
                    Already have an account? <a href="login.php">Sign in</a>
                </p>
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
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            this.value = value;
        });
    </script>
</body>
</html>
