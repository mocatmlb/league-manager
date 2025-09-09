<?php
/**
 * District 8 Travel League - Admin Bootstrap
 * 
 * Common bootstrap file for admin pages to reduce code duplication
 */

// Load environment loader
require_once __DIR__ . '/env-loader.php';

// Define application constant
define('D8TL_APP', true);

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

// Generate CSRF token for admin pages
$csrfToken = Auth::generateCSRFToken();

// Get current user info
$currentUser = Auth::getCurrentUser();
?>
