<?php
/**
 * District 8 Travel League — Admin: Stop Impersonation
 *
 * POST-only handler. Cannot use Auth::requireAdmin() because the session
 * looks like a coach session during impersonation. Guards directly on
 * the impersonation session keys. Story 13.1
 */

$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) {
        require_once $__candidate;
        $__found = true;
        break;
    }
    $__dir = dirname($__dir);
}
if (!$__found) {
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/env-loader.php';
        $__found = true;
    }
}
if (!$__found) {
    error_log('D8TL ERROR: Unable to locate includes/env-loader.php from ' . __FILE__);
    http_response_code(500);
    exit('Configuration error: env-loader not found');
}
unset($__dir, $__found, $__i, $__candidate);

@include_once EnvLoader::getPath('includes/bootstrap.php');

// Guard: must be an active impersonation session
if (empty($_SESSION['impersonating']) || empty($_SESSION['impersonator_admin_id'])) {
    $dashPath = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';
    header('Location: ' . $dashPath);
    exit;
}

// Reject non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $dashPath = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';
    header('Location: ' . $dashPath);
    exit;
}

// CSRF check
if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
    $dashPath = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';
    header('Location: ' . $dashPath);
    exit;
}

if (!class_exists('ImpersonationService')) {
    require_once EnvLoader::getPath('includes/ImpersonationService.php');
}

try {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $returnUrl = ImpersonationService::stopImpersonation($ip);
    header('Location: ' . $returnUrl);
    exit;
} catch (Throwable $e) {
    Logger::error('Impersonation stop failed', ['error' => $e->getMessage()]);
    $_SESSION['flash_error'] = 'Could not stop impersonation. Please try again.';
    $dashPath = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';
    header('Location: ' . $dashPath);
    exit;
}
