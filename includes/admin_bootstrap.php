<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Admin Bootstrap
 * 
 * Common bootstrap file for admin pages to reduce code duplication
 */

// Load environment loader (we're in includes directory)
require_once __DIR__ . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

// Generate CSRF token for admin pages
$csrfToken = Auth::generateCSRFToken();

// Get current user info
$currentUser = Auth::getCurrentUser();
?>
