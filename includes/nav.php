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
$isAdmin          = Auth::isAdmin();
$isCoach          = Auth::isCoach();
$isLoggedIn       = Auth::isLoggedIn();
$isUmpireAssignor = ($isCoach && !$isAdmin) && (($_SESSION['role'] ?? '') === 'umpire_assignor');

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
<nav class="navbar navbar-expand-lg navbar-dark bg-primary" id="main-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $rootPath; ?>index.php">⚾ <?php echo APP_NAME; ?></a>
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
                        <!-- Admin Management Mega-Menu -->
                        <li class="nav-item dropdown mega-dropdown">
                            <a class="nav-link dropdown-toggle <?php echo in_array($currentDir, ['games', 'schedules', 'teams', 'programs', 'seasons', 'divisions', 'locations', 'league-list', 'users', 'logs', 'umpires', 'ai']) ? 'active' : ''; ?>"
                               href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cogs"></i> Manage
                            </a>
                            <div class="dropdown-menu mega-menu" aria-labelledby="adminDropdown">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="mega-section-header">Games &amp; Teams</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'games'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/games/">
                                            <i class="fas fa-baseball-ball"></i> Games
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'schedules'); ?>"
                                           href="<?php echo $rootPath; ?>admin/schedules/">
                                            <i class="fas fa-calendar-alt"></i> Schedules
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('special-dates', 'schedules'); ?>"
                                           href="<?php echo $rootPath; ?>admin/schedules/special-dates.php">
                                            <i class="fas fa-star"></i> Special Dates
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'teams'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/teams/">
                                            <i class="fas fa-users"></i> Teams
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'league-list'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/league-list/">
                                            <i class="fas fa-list-ul"></i> League List
                                        </a>

                                        <div class="mega-section-header mt-3">Umpires</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'umpires'); ?>"
                                           href="<?php echo $rootPath; ?>admin/umpires/index.php">
                                            <i class="fas fa-list-check"></i> Assignment Queue
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('board', 'umpires'); ?>"
                                           href="<?php echo $rootPath; ?>admin/umpires/board.php">
                                            <i class="fas fa-table-columns"></i> Assignment Board
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('roster', 'umpires'); ?>"
                                           href="<?php echo $rootPath; ?>admin/umpires/roster.php">
                                            <i class="fas fa-id-card"></i> Umpire Roster
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('import', 'umpires'); ?>"
                                           href="<?php echo $rootPath; ?>admin/umpires/import.php">
                                            <i class="fas fa-file-csv"></i> Import Umpires
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mega-section-header">League Setup</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'programs'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/programs/">
                                            <i class="fas fa-trophy"></i> Programs
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'seasons'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/seasons/">
                                            <i class="fas fa-calendar-check"></i> Seasons
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'divisions'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/divisions/">
                                            <i class="fas fa-layer-group"></i> Divisions
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'locations'); ?>" 
                                           href="<?php echo $rootPath; ?>admin/locations/">
                                            <i class="fas fa-map-marker-alt"></i> Locations
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mega-section-header">Users &amp; Access</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'users'); ?>"
                                           href="<?php echo $rootPath; ?>admin/users/">
                                            <i class="fas fa-user-cog"></i> Admin Users
                                        </a>
                                        <a class="dropdown-item <?php echo isActiveNav('invitations', 'users'); ?>"
                                           href="<?php echo $rootPath; ?>admin/users/invitations.php">
                                            <i class="fas fa-envelope-open-text"></i> Coach Invitations
                                        </a>

                                        <div class="mega-section-header mt-3">System</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'logs'); ?>"
                                           href="<?php echo $rootPath; ?>admin/logs/">
                                            <i class="fas fa-clipboard-list"></i> Activity Logs
                                        </a>
                                        <a class="dropdown-item <?php echo in_array($currentDir, ['ai']) ? 'active' : ''; ?>"
                                           href="<?php echo $rootPath; ?>admin/ai/">
                                            <i class="fas fa-robot"></i> AI Blue
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mega-section-header">Email</div>
                                        <a class="dropdown-item" 
                                           href="<?php echo $rootPath; ?>admin/settings/?section=email-setup">
                                            <i class="fas fa-envelope"></i> Email Setup
                                        </a>
                                        <a class="dropdown-item"
                                           href="<?php echo $rootPath; ?>admin/settings/?section=email-templates">
                                            <i class="fas fa-file-alt"></i> Email Templates
                                        </a>

                                        <div class="mega-section-header mt-3">Configuration</div>
                                        <a class="dropdown-item <?php echo isActiveNav('index', 'settings'); ?>"
                                           href="<?php echo $rootPath; ?>admin/settings/">
                                            <i class="fas fa-cog"></i> All Settings
                                        </a>
                                        <a class="dropdown-item"
                                           href="<?php echo $rootPath; ?>admin/settings/?section=schedule-changes">
                                            <i class="fas fa-calendar-alt"></i> Schedule Changes
                                        </a>
                                        <a class="dropdown-item"
                                           href="<?php echo $rootPath; ?>admin/settings/?section=system-backup">
                                            <i class="fas fa-database"></i> Backup &amp; Restore
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>

                    <?php if ($isCoach && !$isAdmin && !$isUmpireAssignor): ?>
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
                                    <a class="dropdown-item <?php echo isActiveNav('schedule', 'coaches'); ?>"
                                       href="<?php echo $rootPath; ?>coaches/schedule.php">
                                        <i class="fas fa-list-ul"></i> My Schedule
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('score-input', 'coaches'); ?>"
                                       href="<?php echo $rootPath; ?>coaches/score-input.php">
                                        <i class="fas fa-baseball-ball"></i> Score Input
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
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('rules', 'coaches'); ?>"
                                       href="<?php echo $rootPath; ?>coaches/rules.php">
                                        <i class="fas fa-book"></i> Rules
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($isUmpireAssignor): ?>
                        <!-- Umpire Assignor Links -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $currentDir === 'umpires' ? 'active' : ''; ?>"
                               href="#" id="umpireDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-id-card"></i> Umpire Tools
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'umpires'); ?>"
                                       href="<?php echo $rootPath; ?>admin/umpires/index.php">
                                        <i class="fas fa-list-check"></i> Assignment Queue
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('board', 'umpires'); ?>"
                                       href="<?php echo $rootPath; ?>admin/umpires/board.php">
                                        <i class="fas fa-table-columns"></i> Assignment Board
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('roster', 'umpires'); ?>"
                                       href="<?php echo $rootPath; ?>admin/umpires/roster.php">
                                        <i class="fas fa-users"></i> Umpire Roster
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('import', 'umpires'); ?>"
                                       href="<?php echo $rootPath; ?>admin/umpires/import.php">
                                        <i class="fas fa-file-csv"></i> Import Umpires
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
                                <a class="dropdown-item" href="<?php echo $rootPath; ?>coaches/profile.php">
                                    <i class="fas fa-user-circle"></i> Profile
                                </a>
                            </li>
                            <li>
                                <?php $logoutUrl = $isCoach && !$isAdmin
                                    ? $rootPath . 'coaches/logout.php'
                                    : $rootPath . 'admin/logout.php'; ?>
                                <a class="dropdown-item" href="<?php echo $logoutUrl; ?>">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $rootPath; ?>login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php
// AI Chatbot Widget (Blue) — only for logged-in users
$__blueWidget = __DIR__ . '/ai-chat-widget.php';
if (file_exists($__blueWidget)) {
    include $__blueWidget;
}
unset($__blueWidget);
?>
