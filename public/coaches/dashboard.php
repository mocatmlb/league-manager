<?php
/**
 * District 8 Travel League - Coaches Dashboard
 */

// Handle both development and production paths
$bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php') 
    ? __DIR__ . '/../includes/coach_bootstrap.php'  // Production: includes is one level up
    : __DIR__ . '/../../includes/coach_bootstrap.php';  // Development: includes is two levels up
require_once $bootstrapPath;

// Require coach authentication
Auth::requireCoach();

$pageTitle = "Coaches Dashboard - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="jumbotron bg-light p-4 rounded">
                    <h1 class="display-5">Coaches Dashboard</h1>
                    <p class="lead">Welcome to the coaches area. Here you can manage your team's schedule and scores.</p>
                </div>
            </div>
        </div>

        <!-- Coach Tools -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                        <h5>Schedule Change Request</h5>
                        <p class="text-muted">Request changes to your game schedule</p>
                        <a href="schedule-change.php" class="btn btn-primary">Submit Request</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <i class="fas fa-baseball-ball fa-3x text-success mb-3"></i>
                        <h5>Score Input</h5>
                        <p class="text-muted">Submit game scores and results</p>
                        <a href="score-input.php" class="btn btn-success">Input Score</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <i class="fas fa-address-book fa-3x text-info mb-3"></i>
                        <h5>Contact Directory</h5>
                        <p class="text-muted">Access league contact information</p>
                        <a href="contacts.php" class="btn btn-info">View Contacts</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Important Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Schedule Changes</h5>
                                <ul>
                                    <li>All schedule change requests require administrative approval</li>
                                    <li>Changes will not take effect until approved</li>
                                    <li>You will receive email notification of approval/denial</li>
                                    <li>Submit requests as early as possible</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5>Score Submission</h5>
                                <ul>
                                    <li>Scores are recorded immediately upon submission</li>
                                    <li>Standings are updated automatically</li>
                                    <li>Contact admin if corrections are needed</li>
                                    <li>Submit scores promptly after games</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Info -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Session Information:</strong> Your session will expire in 1 hour. You'll need to log in again after that time.
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
                    <p><small>Coaches Area - Version <?php echo APP_VERSION; ?></small></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
