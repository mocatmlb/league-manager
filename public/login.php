<?php
/**
 * District 8 Travel League - Unified Login
 * 
 * Login page that works with both legacy and new user accounts systems
 */

define('D8TL_APP', true);
require_once '../includes/bootstrap.php';
require_once '../includes/AuthService.php';

$authService = new AuthService();

// Redirect if already logged in
if ($authService->isAuthenticated()) {
    if ($authService->isAdmin()) {
        header('Location: admin/');
    } elseif ($authService->isCoach()) {
        header('Location: coaches/');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

// Check for success message (e.g., from registration)
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Account created successfully! You can now log in with your credentials.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        $authService->validateForm();
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        // Basic validation
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Attempt authentication
            $user = $authService->authenticate($username, $password);
            
            if ($user) {
                // Set remember-me cookie if requested (only for new system)
                if ($remember && $authService->isNewAuth()) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    // Store token in database
                    $db = Database::getInstance();
                    $db->insert('remember_tokens', [
                        'user_id' => $user['id'],
                        'token' => $token,
                        'expires_at' => date('Y-m-d H:i:s', $expires)
                    ]);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expires, '/', '', 
                             isset($_SERVER['HTTPS']), true);
                }
                
                // Redirect based on role/type
                if ($authService->isAdmin()) {
                    header('Location: admin/');
                } elseif ($authService->isCoach()) {
                    header('Location: coaches/');
                } else {
                    header('Location: index.php');
                }
                exit;
                
            } else {
                $error = 'Invalid username or password.';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        Logger::error("Login error", ['error' => $e->getMessage()]);
    }
}

// Check for remember-me token
if (empty($error) && isset($_COOKIE['remember_token']) && !$authService->isAuthenticated()) {
    try {
        $token = $_COOKIE['remember_token'];
        $db = Database::getInstance();
        
        $tokenData = $db->fetchOne(
            "SELECT rt.*, u.* FROM remember_tokens rt 
             JOIN users u ON rt.user_id = u.id 
             WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active'",
            [$token]
        );
        
        if ($tokenData) {
            // Auto-login user
            $user = [
                'id' => $tokenData['user_id'],
                'username' => $tokenData['username'],
                'email' => $tokenData['email'],
                'first_name' => $tokenData['first_name'],
                'last_name' => $tokenData['last_name'],
                'role_id' => $tokenData['role_id'],
                'auth_method' => 'new_system'
            ];
            
            // Get role name
            $role = $db->fetchOne("SELECT name FROM roles WHERE id = ?", [$user['role_id']]);
            $user['role_name'] = $role ? $role['name'] : 'user';
            
            // Create session
            $_SESSION['auth_method'] = 'new_system';
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['user_data'] = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['expires'] = time() + SESSION_TIMEOUT;
            
            // Redirect
            if ($authService->isAdmin()) {
                header('Location: admin/');
            } elseif ($authService->isCoach()) {
                header('Location: coaches/');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            // Invalid or expired token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch (Exception $e) {
        Logger::error("Remember token error", ['error' => $e->getMessage()]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - District 8 Travel League</title>
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
        .system-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h1 class="h3">Sign In</h1>
                <p class="text-muted">District 8 Travel League</p>
            </div>
            
            <div class="system-info">
                <strong>Login Options:</strong><br>
                • <strong>Individual Account:</strong> Use your personal username/email and password<br>
                • <strong>Coach Access:</strong> Use "coach" as username with the coaches password<br>
                • <strong>Admin Access:</strong> Use your admin username and password
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= $authService->csrfTokenField() ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">
                        Remember me for 30 days
                    </label>
                    <div class="form-text">Only available for individual accounts</div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-muted">
                    <a href="forgot-password.php">Forgot your password?</a>
                </p>
                <p class="text-muted">
                    Need an account? Contact an administrator for an invitation.
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
