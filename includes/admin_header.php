<?php
if (!defined('D8TL_APP')) {
    exit('Direct script access not permitted');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin Dashboard'); ?> - League Manager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo EnvLoader::getAssetPath('css/admin.css'); ?>" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/">League Manager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAdmin">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/seasons/">Seasons</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/divisions/">Divisions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/teams/">Teams</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/schedules/">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/documents/">Documents</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/settings/">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo EnvLoader::getBaseUrl(); ?>/admin/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid mt-5 pt-3">
