<?php
/**
 * District 8 Travel League - Umpire Logout
 */
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../includes/env-loader.php')
    ? __DIR__ . '/../includes/env-loader.php'
    : __DIR__ . '/../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$token = $_POST['csrf_token'] ?? '';
if (!Auth::verifyCSRFToken($token)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

Auth::logout();

header('Location: ../login.php?message=logged_out');
exit;
