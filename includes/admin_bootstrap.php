<?php
define('D8TL_APP', true);
/**
 * District 8 Travel League - Admin Bootstrap
 * 
 * Common bootstrap file for admin pages to reduce code duplication
 */

// Detect environment and set include path
$includePath = file_exists(__DIR__ . '/includes/env-loader.php') 
    ? __DIR__ . '/includes'  // Production: includes is in web root
    : __DIR__;  // Development: already in includes

// Load environment loader
require_once $includePath . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Require admin authentication
Auth::requireAdmin();

// Generate CSRF token for admin pages
$csrfToken = Auth::generateCSRFToken();

// Get current user info
$currentUser = Auth::getCurrentUser();
?>
