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

<!-- Theme loader: runs sync before first paint, swaps CSS href from localStorage -->
<script>
(function () {
    var themes = {
        default:  'style.css',
        diamond:  'style-diamond.css',
        sundown:  'style-sundown.css',
        clean:    'style-clean.css'
    };
    var active = localStorage.getItem('d8theme') || 'default';
    var cssFile = themes[active] || themes.default;
    var links = document.querySelectorAll('link[rel="stylesheet"]');
    for (var i = 0; i < links.length; i++) {
        if (/assets\/css\/style/.test(links[i].href)) {
            links[i].href = '/assets/css/' + cssFile;
            break;
        }
    }
    document.documentElement.setAttribute('data-d8theme', active);
})();
</script>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary" id="main-navbar">
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
                            <a class="nav-link dropdown-toggle <?php echo in_array($currentDir, ['games', 'schedules', 'teams', 'programs', 'seasons', 'divisions', 'locations', 'league-list', 'users', 'logs']) ? 'active' : ''; ?>"
                               href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'league-list'); ?>" 
                                       href="<?php echo $rootPath; ?>admin/league-list/">
                                        <i class="fas fa-list-ul"></i> League List
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'users'); ?>"
                                       href="<?php echo $rootPath; ?>admin/users/">
                                        <i class="fas fa-user-cog"></i> User Management
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('invitations', 'users'); ?>"
                                       href="<?php echo $rootPath; ?>admin/users/invitations.php">
                                        <i class="fas fa-envelope-open-text"></i> Coach Invitations
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">System</h6></li>
                                <li>
                                    <a class="dropdown-item <?php echo isActiveNav('index', 'logs'); ?>"
                                       href="<?php echo $rootPath; ?>admin/logs/">
                                        <i class="fas fa-clipboard-list"></i> Activity Logs
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo in_array($currentDir, ['ai']) ? 'active' : ''; ?>"
                                       href="<?php echo $rootPath; ?>admin/ai/">
                                        <i class="fas fa-robot"></i> AI Blue
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

                    <?php if ($isCoach && !$isAdmin): ?>
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
                <?php endif; ?>
            </ul>

            <!-- Theme Picker -->
            <ul class="navbar-nav me-2">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="themePickerDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false" title="Switch theme">
                        <i class="fas fa-palette"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="themePickerDropdown" style="min-width:190px">
                        <li><h6 class="dropdown-header" style="font-size:0.7rem">UI THEME</h6></li>
                        <li>
                            <a class="dropdown-item d8-theme-pick" href="#" data-theme="default">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#007bff;margin-right:6px;vertical-align:middle"></span>
                                Original Blue
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d8-theme-pick" href="#" data-theme="diamond">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#0f2744;margin-right:6px;vertical-align:middle"></span>
                                Diamond &amp; Turf
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d8-theme-pick" href="#" data-theme="sundown">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#d97706;margin-right:6px;vertical-align:middle"></span>
                                Sundown Classic
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d8-theme-pick" href="#" data-theme="clean">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#4f46e5;margin-right:6px;vertical-align:middle"></span>
                                Clean Slate
                            </a>
                        </li>
                    </ul>
                </li>
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
                                <?php if ($isCoach && !$isAdmin): ?>
                                <a class="dropdown-item" href="<?php echo $rootPath; ?>coaches/profile.php">
                                    <i class="fas fa-user-circle"></i> Profile
                                </a>
                                <?php else: ?>
                                <a class="dropdown-item" href="<?php echo $rootPath; ?>admin/profile.php">
                                    <i class="fas fa-user-circle"></i> Profile
                                </a>
                                <?php endif; ?>
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

<script>
(function () {
    var themes = {
        default:  'style.css',
        diamond:  'style-diamond.css',
        sundown:  'style-sundown.css',
        clean:    'style-clean.css'
    };

    // Navbar variant: clean theme uses light nav, all others use dark
    var navDarkThemes  = ['default', 'diamond', 'sundown'];
    var navLightThemes = ['clean'];

    function applyNavbarVariant(theme) {
        var nav = document.getElementById('main-navbar');
        if (!nav) return;
        if (navLightThemes.indexOf(theme) !== -1) {
            nav.classList.remove('navbar-dark', 'bg-primary');
            nav.classList.add('navbar-light', 'bg-white');
        } else {
            nav.classList.remove('navbar-light', 'bg-white');
            nav.classList.add('navbar-dark', 'bg-primary');
        }
    }

    function applyTheme(theme) {
        var cssFile = themes[theme] || themes.default;
        var links = document.querySelectorAll('link[rel="stylesheet"]');
        for (var i = 0; i < links.length; i++) {
            if (/assets\/css\/style/.test(links[i].href)) {
                links[i].href = '/assets/css/' + cssFile;
                break;
            }
        }
        document.documentElement.setAttribute('data-d8theme', theme);
        applyNavbarVariant(theme);

        // Highlight active item
        document.querySelectorAll('.d8-theme-pick').forEach(function (el) {
            var icon = el.querySelector('.d8-check');
            if (el.dataset.theme === theme) {
                if (!icon) {
                    icon = document.createElement('i');
                    icon.className = 'fas fa-check d8-check ms-auto float-end';
                    icon.style.fontSize = '0.75rem';
                    el.appendChild(icon);
                }
            } else {
                if (icon) icon.remove();
            }
        });
    }

    // Apply active checkmark on load
    var current = localStorage.getItem('d8theme') || 'default';
    // Defer until DOM ready for the checkmark (nav is already in DOM here)
    applyTheme(current);

    // Wire up picker links
    document.querySelectorAll('.d8-theme-pick').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var theme = this.dataset.theme;
            localStorage.setItem('d8theme', theme);
            applyTheme(theme);
        });
    });
})();
</script>

<?php
// AI Chatbot Widget (Blue) — only for logged-in users
$__blueWidget = __DIR__ . '/ai-chat-widget.php';
if (file_exists($__blueWidget)) {
    include $__blueWidget;
}
unset($__blueWidget);
?>
