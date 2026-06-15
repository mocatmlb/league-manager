<?php
/**
 * District 8 Travel League - Umpire Assignor Role Test Page
 * Verifies umpire_assignor role gating is working. Replace with real queue in Story 23.1.
 */
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../includes/env-loader.php'
    : __DIR__ . '/../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');

PermissionGuard::requireRole('umpire_assignor', '/login.php');

$currentUser = Auth::getCurrentUser();
$name = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
$role = htmlspecialchars($currentUser['role_name'] ?? $_SESSION['role'] ?? 'unknown');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Assignment — D8TL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card mx-auto" style="max-width:520px;">
        <div class="card-body text-center p-5">
            <h2 class="mb-1">Umpire Assignment</h2>
            <p class="text-muted mb-4">Role gate: <code>umpire_assignor</code> ✅</p>
            <p class="fs-5 mb-1">Welcome, <strong><?= $name ?: 'Assignor' ?></strong></p>
            <p class="text-muted small mb-4">Logged in as: <code><?= $role ?></code></p>
            <p class="text-muted small mb-4">
                This placeholder page confirms your <code>umpire_assignor</code> role gate is working correctly.
                The real assignment board (Unassigned Queue) ships in Story 23.1.
            </p>
            <a href="../logout.php" class="btn btn-outline-secondary">Log Out</a>
        </div>
    </div>
</div>
</body>
</html>
