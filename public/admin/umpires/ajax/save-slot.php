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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    d8tl_umpire_json(false, 'Method not allowed.', 405);
}

if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    d8tl_umpire_json(false, 'Invalid CSRF token.', 403);
}

$gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
$slotIndex = filter_input(INPUT_POST, 'slot_index', FILTER_VALIDATE_INT);
$umpireUserId = filter_input(INPUT_POST, 'umpire_user_id', FILTER_VALIDATE_INT);

if (!$gameId || $gameId < 1 || $slotIndex === false || $slotIndex === null || !$umpireUserId || $umpireUserId < 1) {
    d8tl_umpire_json(false, 'Valid game_id, slot_index, and umpire_user_id are required.', 400);
}

$currentUser = Auth::getCurrentUser();
$actorUserId = (int) ($currentUser['id'] ?? 0);
if ($actorUserId < 1) {
    d8tl_umpire_json(false, 'Authenticated user not found.', 400);
}

try {
    $svc = new UmpireAssignmentService();
    d8tl_umpire_json(true, $svc->saveSlot((int) $gameId, (int) $slotIndex, (int) $umpireUserId, $actorUserId));
} catch (\InvalidArgumentException $e) {
    d8tl_umpire_json(false, $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    if ($e->getCode() === 409) {
        d8tl_umpire_json(false, $e->getMessage(), 409);
    } else {
        error_log('[umpires/ajax/save-slot.php] ' . $e->getMessage());
        d8tl_umpire_json(false, 'An unexpected error occurred.', 500);
    }
} catch (\Throwable $e) {
    error_log('[umpires/ajax/save-slot.php] ' . $e->getMessage());
    d8tl_umpire_json(false, 'An unexpected error occurred.', 500);
}
