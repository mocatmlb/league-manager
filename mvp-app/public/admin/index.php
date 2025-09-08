<?php
/**
 * District 8 Travel League - Admin Dashboard
 */

require_once '../../includes/bootstrap.php';

// Require admin authentication
Auth::requireAdmin();

// Generate CSRF token for this page
$csrfToken = Auth::generateCSRFToken();

$currentUser = Auth::getCurrentUser();

// Get dashboard metrics
$totalGames = $db->fetchOne("SELECT COUNT(*) as count FROM games")['count'] ?? 0;
$completedGames = $db->fetchOne("SELECT COUNT(*) as count FROM games WHERE game_status = 'Completed'")['count'] ?? 0;
$pendingChanges = $db->fetchOne("SELECT COUNT(*) as count FROM schedule_change_requests WHERE request_status = 'Pending'")['count'] ?? 0;
$activeTeams = $db->fetchOne("SELECT COUNT(*) as count FROM teams WHERE active_status = 'Active'")['count'] ?? 0;

// Get current season info
$currentSeason = $db->fetchOne("SELECT s.*, p.program_name FROM seasons s JOIN programs p ON s.program_id = p.program_id WHERE s.season_status = 'Active' LIMIT 1");

// Get recent activity
$recentActivity = $db->fetchAll("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");

$pageTitle = "Admin Dashboard - " . APP_NAME;
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
    <div class="container-fluid mt-4">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Admin Dashboard</h1>
                        <p class="text-muted mb-0">Welcome back, <?php echo sanitize($currentUser['username']); ?>!</p>
                    </div>
                    <div>
                        <?php if ($currentSeason): ?>
                            <span class="badge bg-success fs-6">
                                Active Season: <?php echo sanitize($currentSeason['season_name']); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning fs-6">No Active Season</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-baseball-ball fa-2x mb-2"></i>
                        <div class="metric-number"><?php echo $totalGames; ?></div>
                        <div>Total Games</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <div class="metric-number"><?php echo $completedGames; ?></div>
                        <div>Completed Games</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2 text-warning"></i>
                        <div class="metric-number text-warning"><?php echo $pendingChanges; ?></div>
                        <div>Pending Changes</div>
                        <?php if ($pendingChanges > 0): ?>
                            <a href="schedules/" class="btn btn-sm btn-warning mt-2" 
                               onclick="return confirm('Are you sure you want to review pending changes?')">Review</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card metric-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <div class="metric-number"><?php echo $activeTeams; ?></div>
                        <div>Active Teams</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="games/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-baseball-ball"></i><br>
                                    Manage Games
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="schedules/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-calendar"></i><br>
                                    Manage Schedule
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="teams/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-users"></i><br>
                                    Manage Teams
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="programs/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-trophy"></i><br>
                                    Manage Programs
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <a href="seasons/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-calendar-alt"></i><br>
                                    Manage Seasons
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="divisions/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-layer-group"></i><br>
                                    Manage Divisions
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="locations/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-map-marker-alt"></i><br>
                                    Manage Locations
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="settings/" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-cog"></i><br>
                                    Settings
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <a href="logs/" class="btn btn-outline-info w-100 mb-2">
                                    <i class="fas fa-file-alt"></i><br>
                                    Application Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted">No recent activity to display.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo sanitize($activity['action']); ?></h6>
                                        <small><?php echo formatDate($activity['created_at'], 'M j, g:i A'); ?></small>
                                    </div>
                                    <?php if ($activity['details']): ?>
                                        <p class="mb-1 text-muted"><?php echo sanitize($activity['details']); ?></p>
                                    <?php endif; ?>
                                    <small>User: <?php echo sanitize($activity['user_type']); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>System Status</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-database text-success"></i>
                                Database: <span class="text-success">Connected</span>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-envelope text-info"></i>
                                Email: <span class="text-info">Configured</span>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-shield-alt text-success"></i>
                                Security: <span class="text-success">Active</span>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-server text-success"></i>
                                Server: <span class="text-success">Running</span>
                            </li>
                        </ul>
                        
                        <hr>
                        
                        <h6>Quick Stats</h6>
                        <ul class="list-unstyled">
                            <li>PHP Version: <?php echo PHP_VERSION; ?></li>
                            <li>App Version: <?php echo APP_VERSION; ?></li>
                            <li>Last Login: <?php echo formatDate(date('Y-m-d H:i:s'), 'M j, g:i A'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <p><small>Admin Console - Version <?php echo APP_VERSION; ?></small></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
