<?php
// Profile management is handled by the unified user accounts profile page.
$__dir = __DIR__;
for ($__i = 0; $__i < 6; $__i++) {
    $__candidate = $__dir . '/includes/env-loader.php';
    if (file_exists($__candidate)) { require_once $__candidate; break; }
    $__dir = dirname($__dir);
}
header('Location: ' . EnvLoader::getBaseUrl() . '/coaches/profile.php', true, 301);
exit;
