<?php
/**
 * District 8 Travel League - Umpire Role Test Page
 * Verifies umpire role gating is working. Replace with real portal in Story 24.1.
 */
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');

PermissionGuard::requireRole('umpire', '/login.php');

$currentUser = Auth::getCurrentUser();
$name = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Portal — D8TL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card mx-auto" style="max-width:480px;">
        <div class="card-body text-center p-5">
            <h2 class="mb-1">Umpire Portal</h2>
            <p class="text-muted mb-4">Role gate: <code>umpire</code> ✅</p>
            <p class="fs-5 mb-4">Welcome, <strong><?= $name ?: 'Umpire' ?></strong></p>
            <p class="text-muted small mb-4">
                This placeholder page confirms your <code>umpire</code> role is working correctly.
                The real portal (My Assignments) ships in Story 24.1.
            </p>
            <a href="logout.php" class="btn btn-outline-secondary">Log Out</a>
        </div>
    </div>
</div>
</body>
</html>
