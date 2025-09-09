<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Coach Bootstrap
 * 
 * Common bootstrap file for coach pages to reduce code duplication
 */

// Load environment loader (we're in includes directory)
require_once __DIR__ . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Require coach authentication
Auth::requireCoach();

// Generate CSRF token for coach pages
$csrfToken = Auth::generateCSRFToken();

// Get current user info
$currentUser = Auth::getCurrentUser();
?>
