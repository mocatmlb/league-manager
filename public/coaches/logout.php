<?php
/**
 * District 8 Travel League - Coaches Logout
 */

// Handle both development and production paths
try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php') 
        ? __DIR__ . '/../includes/coach_bootstrap.php'  // Production: includes is one level up
        : __DIR__ . '/../../includes/coach_bootstrap.php';  // Development: includes is two levels up
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Activity logging for coach logouts is performed inside AuthService::logout()
// so the contract that "services own ActivityLogger calls" is preserved.
Auth::logout();

header('Location: login.php?message=logged_out');
exit;
