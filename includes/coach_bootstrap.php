<?php
/**
 * District 8 Travel League - Coach Bootstrap
 * 
 * Common bootstrap file for coach pages to reduce code duplication
 */

// Define application constant
define('D8TL_APP', true);

// Detect environment and set include path
$includePath = file_exists(__DIR__ . '/includes/env-loader.php') 
    ? __DIR__ . '/includes'  // Production: includes is in web root
    : __DIR__;  // Development: already in includes

// Load environment loader
require_once $includePath . '/env-loader.php';

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');

// Require coach authentication
Auth::requireCoach();

// Generate CSRF token for coach pages
$csrfToken = Auth::generateCSRFToken();

// Get current user info
$currentUser = Auth::getCurrentUser();
?>
