<?php
/**
 * District 8 Travel League - Team Registration Confirmation
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

Auth::requireCoach();

$pageTitle = 'Registration Submitted — District 8 Travel League';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <meta name="robots" content="noindex">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php"><?php echo defined('APP_NAME') ? sanitize(APP_NAME) : 'District 8 Travel League'; ?></a>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center mb-4">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h1 class="h3">Registration Submitted</h1>
            </div>

            <div class="alert alert-success" role="alert">
                Account created and team registration submitted. An administrator will review your registration and assign you to your team.
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title">What happens next?</h5>
                    <ul class="mb-0">
                        <li>An administrator will review your team registration.</li>
                        <li>Once approved, you will be assigned to your team and can access the coaches portal.</li>
                        <li>You will receive an email notification when your registration is approved.</li>
                    </ul>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="../index.php" class="btn btn-outline-primary">Return to League Home</a>
            </div>
        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? sanitize(APP_NAME) : 'District 8 Travel League'; ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
