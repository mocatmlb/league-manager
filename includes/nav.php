<?php
/**
 * Unified Navigation Component
 * Shows/hides navigation items based on user permissions
 */

// Ensure we have the current user data
if (!isset($currentUser)) {
    $currentUser = Auth::getCurrentUser();
}

// Get user roles and permissions
$isAdmin = Auth::isAdmin();
$isCoach = Auth::isCoach();
$isLoggedIn = Auth::isLoggedIn();

// Determine the current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function to determine if a nav item should be active
function isActiveNav($page, $dir = '') {
    global $currentPage, $currentDir;
    if ($dir && $currentDir === $dir && $currentPage === $page) return 'active';
    return '';
}

// Helper function to get relative path prefix from current script to web root
function getPathToRoot() {
    // SCRIPT_NAME like '/index.php' or '/admin/games/index.php'
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName === '' || $scriptName === '/') {
        return './';
    }
    // Remove leading/trailing slashes and split
    $parts = explode('/', trim($scriptName, '/'));
    // Exclude the file name (last part)
    $depth = max(count($parts) - 1, 0);
    if ($depth <= 0) {
        return './';
    }
    return str_repeat('../', $depth);
}

$rootPath = getPathToRoot();
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $rootPath; ?>index.php"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Public Links - Always Visible -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isActiveNav('index'); ?>" href="<?php echo $rootPath; ?>index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActiveNav('schedule'); ?>" href="<?php echo $rootPath; ?>schedule.php">
                        <i class="fas fa-calendar"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActiveNav('standings'); ?>" href="<?php echo $rootPath; ?>standings.php">
                        <i class="fas fa-trophy"></i> Standings
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <?php if ($isAdmin): ?>
                        <!-- Admin Management Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo in_array($currentDir, ['games', 'schedules', 'teams', 'programs', 'seasons', 'divisions', 'locations']) ? 'active' : ''; ?>" 
                               href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cogs"></i> Management
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Game Management</h6></li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'games'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/games/">
                                        <i class="fas fa-gamepad"></i> Games
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'schedules'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/schedules/">
                                        <i class="fas fa-calendar-check"></i> Schedules
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'teams'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/teams/">
                                        <i class="fas fa-users"></i> Teams
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">League Setup</h6></li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'programs'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/programs/">
                                        <i class="fas fa-project-diagram"></i> Programs
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'seasons'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/seasons/">
                                        <i class="fas fa-calendar-alt"></i> Seasons
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'divisions'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/divisions/">
                                        <i class="fas fa-sitemap"></i> Divisions
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'locations'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/locations/">
                                        <i class="fas fa-map-marker-alt"></i> Locations
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Admin Settings -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActiveNav('index', 'settings'); ?>" 
                               href="<?php echo $rootPath; ?>admin/settings/">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isCoach): ?>
                        <!-- Coach Links -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $currentDir === 'coaches' ? 'active' : ''; ?>" 
                               href="#" id="coachDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-chalkboard-teacher"></i> Coach Tools
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('dashboard', 'coaches'); ?>" 
                                       href="<?php echo $rootPath; ?>coaches/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('score-input', 'coaches'); ?>" 
                                       href="<?php echo $rootPath; ?>coaches/score-input.php">
                                        <i class="fas fa-edit"></i> Score Input
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('schedule-change', 'coaches'); ?>" 
                                       href="<?php echo $rootPath; ?>coaches/schedule-change.php">
                                        <i class="fas fa-calendar-alt"></i> Schedule Changes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('contacts', 'coaches'); ?>" 
                                       href="<?php echo $rootPath; ?>coaches/contacts.php">
                                        <i class="fas fa-address-book"></i> Contacts
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- User Menu -->
            <ul class="navbar-nav">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo sanitize($currentUser['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $rootPath; ?>admin/">
                                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo $rootPath; ?>admin/profile.php">
                                    <i class="fas fa-user-circle"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $rootPath; ?>admin/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $rootPath; ?>coaches/login.php">
                            <i class="fas fa-chalkboard-teacher"></i> Coach Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $rootPath; ?>admin/login.php">
                            <i class="fas fa-user-shield"></i> Admin Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
