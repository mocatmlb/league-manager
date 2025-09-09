<?php
/**
 * District 8 Travel League - Coaches Login Page
 */

// Handle both development and production paths
$bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php') 
    ? __DIR__ . '/../includes/coach_bootstrap.php'  // Production: includes is one level up
    : __DIR__ . '/../../includes/coach_bootstrap.php';  // Development: includes is two levels up
require_once $bootstrapPath;

$error = '';
$success = '';

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = 'You have been successfully logged out.';
}

// Check if already logged in as coach
if (Auth::isCoach()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if ($_POST && isset($_POST['password'])) {
    // Verify CSRF token
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'];
        
        if (Auth::authenticateCoach($password)) {
            logActivity('coach_login', 'Coach logged in successfully');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid password. Please try again.';
            logActivity('coach_login_failed', 'Failed coach login attempt');
        }
    }
}

$pageTitle = "Coaches Login - " . APP_NAME;
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
                        <a class="nav-link active" href="login.php">Coaches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/login.php">Admin</a>
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
                    <h2>Coaches Login</h2>
                    <p class="mb-0">Enter the coaches password to access team management tools</p>
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
                            <label for="password" class="form-label">Coaches Password</label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   placeholder="Enter coaches password"
                                   autocomplete="current-password">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Login
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <h6>What can coaches do?</h6>
                        <ul class="list-unstyled text-start">
                            <li>✅ Submit schedule change requests</li>
                            <li>✅ Input game scores</li>
                            <li>✅ Access team contact information</li>
                        </ul>
                        
                        <small class="text-muted">
                            Password is shared among all coaches. Contact the league administrator if you need the password.
                        </small>
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
        // Auto-focus password field
        document.getElementById('password').focus();
        
        // Show/hide password toggle (optional enhancement)
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
        }
    </script>
</body>
</html>
