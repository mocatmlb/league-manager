<?php
/**
 * District 8 Travel League - Admin Sidebar Navigation
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<!-- Sidebar navigation -->
<div class="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_dir == 'admin' && $current_page == 'index') ? 'active' : ''; ?>" 
               href="/admin/index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'games' ? 'active' : ''; ?>" 
               href="/admin/games/index.php">
                <i class="fas fa-baseball-ball"></i> Games
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'schedules' ? 'active' : ''; ?>" 
               href="/admin/schedules/index.php">
                <i class="fas fa-calendar-alt"></i> Schedules
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'teams' ? 'active' : ''; ?>" 
               href="/admin/teams/index.php">
                <i class="fas fa-users"></i> Teams
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'seasons' ? 'active' : ''; ?>" 
               href="/admin/seasons/index.php">
                <i class="fas fa-calendar-check"></i> Seasons
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'divisions' ? 'active' : ''; ?>" 
               href="/admin/divisions/index.php">
                <i class="fas fa-layer-group"></i> Divisions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'locations' ? 'active' : ''; ?>" 
               href="/admin/locations/index.php">
                <i class="fas fa-map-marker-alt"></i> Locations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'settings' ? 'active' : ''; ?>" 
               href="/admin/settings/index.php">
                <i class="fas fa-cogs"></i> Settings
            </a>
        </li>
        <?php if (Auth::isAdmin()): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_dir == 'logs' ? 'active' : ''; ?>" 
               href="/admin/logs/index.php">
                <i class="fas fa-clipboard-list"></i> Logs
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
