<?php
/**
 * District 8 Travel League - User Profile Management
 * 
 * Self-service profile management for users in the new accounts system
 */

define('D8TL_APP', true);
require_once '../includes/bootstrap.php';
require_once '../includes/AuthService.php';

$authService = new AuthService();

// Require authentication
$authService->requireAuth();

// Only works with new user accounts system
if (!$authService->isNewAuth()) {
    header('Location: index.php');
    exit;
}

$userAccountManager = $authService->getUserAccountManager();
$currentUser = $authService->getCurrentUser();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'profile';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authService->validateForm();
        
        switch ($action) {
            case 'update_profile':
                $updateData = [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? '')
                ];
                
                // Basic validation
                $errors = [];
                if (empty($updateData['first_name'])) $errors[] = 'First name is required';
                if (empty($updateData['last_name'])) $errors[] = 'Last name is required';
                if (empty($updateData['email'])) $errors[] = 'Email is required';
                if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
                if (empty($updateData['phone'])) $errors[] = 'Phone is required';
                
                if (!empty($errors)) {
                    $error = implode('<br>', $errors);
                } else {
                    $userAccountManager->updateUser($currentUser['id'], $updateData);
                    
                    // Update session data
                    $updatedUser = $userAccountManager->getUserById($currentUser['id']);
                    $_SESSION['user_data'] = $updatedUser;
                    
                    $success = 'Profile updated successfully!';
                    $currentUser = $updatedUser; // Update for display
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                // Validate current password
                if (empty($currentPassword)) {
                    $error = 'Current password is required';
                } elseif (!password_verify($currentPassword, $currentUser['password_hash'] ?? '')) {
                    // Get fresh user data to check password
                    $freshUser = $userAccountManager->getUserById($currentUser['id']);
                    if (!password_verify($currentPassword, $freshUser['password_hash'])) {
                        $error = 'Current password is incorrect';
                    }
                }
                
                if (empty($error)) {
                    if (empty($newPassword)) {
                        $error = 'New password is required';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'New passwords do not match';
                    } else {
                        $userAccountManager->changePassword($currentUser['id'], $newPassword);
                        $success = 'Password changed successfully!';
                    }
                }
                break;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get fresh user data
$user = $userAccountManager->getUserById($currentUser['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - District 8 Travel League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto;
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
    <?php include '../includes/nav.php'; ?>
    
    <div class="container profile-container">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Account Menu</h5>
                        <div class="list-group list-group-flush">
                            <a href="?action=profile" class="list-group-item list-group-item-action <?= $action === 'profile' ? 'active' : '' ?>">
                                Profile Information
                            </a>
                            <a href="?action=password" class="list-group-item list-group-item-action <?= $action === 'password' ? 'active' : '' ?>">
                                Change Password
                            </a>
                            <a href="?action=activity" class="list-group-item list-group-item-action <?= $action === 'activity' ? 'active' : '' ?>">
                                Account Activity
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title">Account Info</h6>
                        <p class="card-text">
                            <strong>Username:</strong> <?= htmlspecialchars($user['username']) ?><br>
                            <strong>Role:</strong> <?= htmlspecialchars($user['role_name']) ?><br>
                            <strong>Status:</strong> 
                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span><br>
                            <strong>Member Since:</strong> <?= date('M Y', strtotime($user['created_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if ($action === 'profile'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?action=update_profile">
                                <?= $authService->csrfTokenField() ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" 
                                           value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'password'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?action=change_password">
                                <?= $authService->csrfTokenField() ?>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
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
                                
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'activity'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Account Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Login Information</h6>
                                    <p>
                                        <strong>Last Login:</strong> 
                                        <?= $user['last_login_at'] ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never' ?><br>
                                        <strong>Last IP:</strong> <?= htmlspecialchars($user['last_login_ip'] ?? 'Unknown') ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Account Information</h6>
                                    <p>
                                        <strong>Account Created:</strong> <?= date('M j, Y', strtotime($user['created_at'])) ?><br>
                                        <strong>Last Updated:</strong> <?= date('M j, Y', strtotime($user['updated_at'])) ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Note:</strong> Detailed activity logs are available to administrators for security purposes.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('new_password')?.addEventListener('input', function() {
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
            const password = document.getElementById('new_password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';
            const matchDiv = document.getElementById('passwordMatch');
            
            if (!matchDiv) return;
            
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
        
        document.getElementById('new_password')?.addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password')?.addEventListener('input', checkPasswordMatch);
        
        // Phone number formatting
        document.getElementById('phone')?.addEventListener('input', function() {
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
