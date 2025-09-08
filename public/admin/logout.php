<?php
/**
 * District 8 Travel League - Admin Logout
 */

require_once '../../includes/bootstrap.php';

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
