<?php
/**
 * Test-only session creator for Playwright E2E tests.
 *
 * Creates a PHP admin session without requiring UI login.
 * Only active when TEST_AUTH_SECRET env var is set (never in production).
 * Never accessible in production: EnvLoader::isProduction() check + secret gate.
 *
 * Usage: GET /test-auth.php?role=admin&secret=<TEST_AUTH_SECRET>
 */

$__dir = __DIR__;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) { require_once $__candidate; break; }
    $__dir = dirname($__dir);
}

// Hard block in production
if (class_exists('EnvLoader') && EnvLoader::isProduction()) {
    http_response_code(404);
    exit;
}

$expectedSecret = getenv('TEST_AUTH_SECRET');
$providedSecret = $_GET['secret'] ?? '';

if ($expectedSecret === '' || $expectedSecret === false || $providedSecret !== $expectedSecret) {
    http_response_code(403);
    exit('Forbidden');
}

$role = $_GET['role'] ?? 'admin';

require_once EnvLoader::getPath('includes/bootstrap.php');

$db = Database::getInstance();

if ($role === 'admin') {
    $admin = $db->fetchOne("SELECT id, username FROM admin_users WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    if (!$admin) {
        http_response_code(500);
        exit('No active admin user found in test DB');
    }

    session_start();
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['user_type']      = 'admin';
    $_SESSION['role']           = 'administrator';
    $_SESSION['expires']        = time() + 7200;

    header('Location: /admin/index.php');
    exit;
}

http_response_code(400);
exit('Unknown role');
