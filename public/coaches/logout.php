<?php
/**
 * District 8 Travel League - Coaches Logout
 */

require_once __DIR__ . '/../../includes/coach_bootstrap.php';

// Log the logout activity
if (Auth::isCoach()) {
    logActivity('coach_logout', 'Coach logged out');
}

// Logout the user
Auth::logout();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit;
