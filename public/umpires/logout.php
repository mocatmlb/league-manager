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

Auth::logout();

header('Location: ../login.php?message=logged_out');
exit;
