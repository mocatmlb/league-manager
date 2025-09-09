<?php
/**
 * District 8 Travel League - Admin Login Page
 */

// Handle both development and production paths
$bootstrapPath = file_exists(__DIR__ . '/../includes/admin_bootstrap.php') 
    ? __DIR__ . '/../includes/admin_bootstrap.php'  // Production: includes is one level up
    : __DIR__ . '/../../includes/admin_bootstrap.php';  // Development: includes is two levels up
require_once $bootstrapPath;

$error = '';
$success = '';

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = 'You have been successfully logged out.';
}

// Check if already logged in as admin
if (Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    // Verify CSRF token
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (Auth::authenticateAdmin($username, $password)) {
            logActivity('admin_login', "Admin user '{$username}' logged in successfully");
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
            logActivity('admin_login_failed', "Failed admin login attempt for username '{$username}'");
        }
    }
}

$pageTitle = "Admin Login - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo APP_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../standings.php">Standings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../coaches/login.php">Coaches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="login.php">Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="card-header login-header">
                    <h2>Administrator Login</h2>
                    <p class="mb-0">Access the administrative console</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo sanitize($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo sanitize($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   placeholder="Enter username"
                                   autocomplete="username"
                                   value="<?php echo sanitize($_POST['username'] ?? ''); ?>">
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

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Login to Admin Console
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <h6>Admin Console Features</h6>
                        <ul class="list-unstyled text-start">
                            <li>🎯 Dashboard with key metrics</li>
                            <li>⚾ Complete game and schedule management</li>
                            <li>👥 Team and program administration</li>
                            <li>📧 Email notification configuration</li>
                            <li>📊 System settings and reports</li>
                        </ul>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <strong>Default Credentials:</strong><br>
                                Username: <code>admin</code><br>
                                Password: <code>admin</code><br>
                                <em>Please change these after first login!</em>
                            </small>
                        </div>
                    </div>
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
    
    <script>
        // Auto-focus username field if empty, otherwise password field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (usernameField.value.trim() === '') {
                usernameField.focus();
            } else {
                passwordField.focus();
            }
        });
    </script>
</body>
</html>
