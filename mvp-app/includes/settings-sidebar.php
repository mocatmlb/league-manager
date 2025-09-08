<?php
/**
 * Settings Sidebar Navigation Component
 */

// Get current page/section
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentSection = $_GET['section'] ?? 'general';

// Helper function to determine if a nav item should be active
function isSettingsActive($section) {
    global $currentSection;
    return $currentSection === $section ? 'active' : '';
}
?>

<div class="settings-sidebar">
    <div class="list-group">
        <!-- General Settings -->
        <a href="?section=general" 
           class="list-group-item list-group-item-action <?php echo isSettingsActive('general'); ?>">
            <i class="fas fa-cog"></i> General Settings
        </a>

        <!-- League Contacts -->
        <a href="?section=contacts" 
           class="list-group-item list-group-item-action <?php echo isSettingsActive('contacts'); ?>">
            <i class="fas fa-address-book"></i> League Contacts
        </a>

        <!-- Email Settings -->
        <div class="list-group-item list-group-item-action <?php echo strpos($currentSection, 'email') === 0 ? 'active' : ''; ?>"
             data-bs-toggle="collapse" href="#emailSubmenu" role="button">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-envelope"></i> Email Settings</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="collapse <?php echo strpos($currentSection, 'email') === 0 ? 'show' : ''; ?>" id="emailSubmenu">
            <a href="?section=email-setup" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('email-setup'); ?>">
                <i class="fas fa-server"></i> Email Setup
            </a>
            <a href="?section=email-templates" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('email-templates'); ?>">
                <i class="fas fa-file-alt"></i> Email Templates
            </a>
        </div>

        <!-- User Management -->
        <div class="list-group-item list-group-item-action <?php echo strpos($currentSection, 'users') === 0 ? 'active' : ''; ?>"
             data-bs-toggle="collapse" href="#userSubmenu" role="button">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users"></i> User Management</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="collapse <?php echo strpos($currentSection, 'users') === 0 ? 'show' : ''; ?>" id="userSubmenu">
            <a href="?section=users-admin" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('users-admin'); ?>">
                <i class="fas fa-user-shield"></i> Admin Users
            </a>
            <a href="?section=users-coach" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('users-coach'); ?>">
                <i class="fas fa-chalkboard-teacher"></i> Coach Access
            </a>
        </div>

        <!-- System Settings -->
        <div class="list-group-item list-group-item-action <?php echo strpos($currentSection, 'system') === 0 ? 'active' : ''; ?>"
             data-bs-toggle="collapse" href="#systemSubmenu" role="button">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-wrench"></i> System Settings</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="collapse <?php echo strpos($currentSection, 'system') === 0 ? 'show' : ''; ?>" id="systemSubmenu">
            <a href="?section=system-timezone" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('system-timezone'); ?>">
                <i class="fas fa-clock"></i> Timezone
            </a>
            <a href="?section=system-backup" 
               class="list-group-item list-group-item-action sub-item <?php echo isSettingsActive('system-backup'); ?>">
                <i class="fas fa-database"></i> Backup & Restore
            </a>
        </div>
    </div>
</div>

<style>
.settings-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    height: 100%;
    padding: 1rem 0;
}

.settings-sidebar .list-group {
    border-radius: 0;
}

.settings-sidebar .list-group-item {
    border-left: 0;
    border-right: 0;
    border-radius: 0;
    padding: 0.75rem 1rem;
    cursor: pointer;
}

.settings-sidebar .list-group-item:first-child {
    border-top: 0;
}

.settings-sidebar .list-group-item:last-child {
    border-bottom: 0;
}

.settings-sidebar .list-group-item i {
    width: 20px;
    text-align: center;
    margin-right: 8px;
}

.settings-sidebar .list-group-item.active {
    background-color: #007bff;
    border-color: #007bff;
}

.settings-sidebar .sub-item {
    padding-left: 2.5rem;
    background-color: #fff;
}

.settings-sidebar .sub-item.active {
    background-color: #e9ecef;
    color: #007bff;
    border-color: #dee2e6;
}

.settings-sidebar .fa-chevron-down {
    transition: transform 0.2s;
}

.settings-sidebar .collapse.show + .fa-chevron-down {
    transform: rotate(180deg);
}
</style>
