<?php
/**
 * District 8 Travel League — Admin: Start Impersonation
 *
 * POST-only handler. Validates CSRF, calls ImpersonationService::startImpersonation(),
 * then redirects to the coach dashboard. Story 13.1
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

$coachDashPath = EnvLoader::isProduction() ? '/coaches/dashboard.php' : '/public/coaches/dashboard.php';

// AC9: if already impersonating, do nothing and return to coach dashboard.
if (!empty($_SESSION['impersonating'])) {
    Logger::warn('Impersonation start blocked at handler: already impersonating', [
        'admin_id' => $_SESSION['impersonator_admin_id'] ?? 0,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);
    header('Location: ' . $coachDashPath);
    exit;
}

// Must be called by a logged-in admin when not already impersonating.
Auth::requireAdmin();

// Reject non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF check
if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid form submission. Please try again.';
    $userId = (int) ($_POST['user_id'] ?? 0);
    header('Location: detail.php' . ($userId > 0 ? '?id=' . $userId : ''));
    exit;
}

$targetUserId = (int) ($_POST['user_id'] ?? 0);
if ($targetUserId < 1) {
    $_SESSION['flash_error'] = 'Invalid user.';
    header('Location: index.php');
    exit;
}

if (!class_exists('ImpersonationService')) {
    require_once EnvLoader::getPath('includes/ImpersonationService.php');
}

try {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    ImpersonationService::startImpersonation($targetUserId, $ip);
    header('Location: ' . $coachDashPath);
    exit;
} catch (InvalidArgumentException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: detail.php?id=' . $targetUserId);
    exit;
} catch (RuntimeException $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: ' . $coachDashPath);
    exit;
} catch (Throwable $e) {
    Logger::error('Impersonation start failed', ['error' => $e->getMessage(), 'target' => $targetUserId]);
    $_SESSION['flash_error'] = 'Could not start impersonation. Please try again.';
    header('Location: detail.php?id=' . $targetUserId);
    exit;
}
