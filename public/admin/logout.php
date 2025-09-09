<?php
/**
 * District 8 Travel League - Admin Logout
 */

// Handle both development and production paths
$bootstrapPath = file_exists(__DIR__ . '/../includes/admin_bootstrap.php') 
    ? __DIR__ . '/../includes/admin_bootstrap.php'  // Production: includes is one level up
    : __DIR__ . '/../../includes/admin_bootstrap.php';  // Development: includes is two levels up
require_once $bootstrapPath;

// Log the logout activity
if (Auth::isAdmin()) {
    $currentUser = Auth::getCurrentUser();
    logActivity('admin_logout', "Admin user '{$currentUser['username']}' logged out");
}

// Logout the user
Auth::logout();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit;
