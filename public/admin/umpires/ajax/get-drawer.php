<?php
define('D8TL_APP', true);

$envLoader = file_exists(__DIR__ . '/../../../includes/env-loader.php')
    ? __DIR__ . '/../../../includes/env-loader.php'
    : __DIR__ . '/../../../../includes/env-loader.php';
require_once $envLoader;
require_once EnvLoader::getPath('includes/bootstrap.php');
require_once EnvLoader::getPath('includes/PermissionGuard.php');
require_once EnvLoader::getPath('includes/UmpireAssignmentService.php');

PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php');

header('Content-Type: application/json');

function d8tl_umpire_json(bool $success, array|string $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($success ? ['success' => true, 'data' => $payload] : ['success' => false, 'error' => $payload]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    d8tl_umpire_json(false, 'Method not allowed.', 405);
}

$gameId = filter_input(INPUT_GET, 'game_id', FILTER_VALIDATE_INT);
if (!$gameId || $gameId < 1) {
    d8tl_umpire_json(false, 'A valid game_id is required.', 400);
}

try {
    $svc = new UmpireAssignmentService();
    d8tl_umpire_json(true, $svc->getGameAssignmentDrawer((int) $gameId));
} catch (\InvalidArgumentException $e) {
    d8tl_umpire_json(false, $e->getMessage(), 400);
} catch (\Throwable $e) {
    error_log('[umpires/ajax/get-drawer.php] ' . $e->getMessage());
    d8tl_umpire_json(false, 'An unexpected error occurred.', 500);
}
