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
    if ($success) {
        echo json_encode(['success' => true, 'data' => $payload]);
        exit;
    }
    if (is_array($payload)) {
        echo json_encode(array_merge(['success' => false], $payload));
        exit;
    }
    echo json_encode(['success' => false, 'error' => $payload]);
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
$overrideReason = isset($_POST['override_reason']) ? trim((string) $_POST['override_reason']) : null;

if (!$gameId || $gameId < 1 || $slotIndex === false || $slotIndex === null || !$umpireUserId || $umpireUserId < 1) {
    d8tl_umpire_json(false, 'Valid game_id, slot_index, and umpire_user_id are required.', 400);
}

$actorIsAdmin = ($_SESSION['role'] ?? '') === 'administrator';
$assignedByUserId = isset($_SESSION['coach_user_id']) ? (int) $_SESSION['coach_user_id'] : null;
$actorAdminId = isset($_SESSION['admin_id']) && !isset($_SESSION['coach_user_id']) ? (int) $_SESSION['admin_id'] : null;

if (($assignedByUserId === null || $assignedByUserId < 1) && ($actorAdminId === null || $actorAdminId < 1)) {
    d8tl_umpire_json(false, 'Authenticated user not found.', 400);
}

try {
    $svc = new UmpireAssignmentService();
    d8tl_umpire_json(true, $svc->saveSlot(
        (int) $gameId,
        (int) $slotIndex,
        (int) $umpireUserId,
        $assignedByUserId,
        $actorIsAdmin,
        $overrideReason,
        $actorAdminId
    ));
} catch (\InvalidArgumentException $e) {
    d8tl_umpire_json(false, $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    if ($e->getCode() === 409) {
        $payload = ['error' => $e->getMessage()];
        if (method_exists($e, 'getPayload')) {
            $payload = array_merge($payload, $e->getPayload());
        }
        d8tl_umpire_json(false, $payload, 409);
    } else {
        error_log('[umpires/ajax/save-slot.php] ' . $e->getMessage());
        d8tl_umpire_json(false, 'An unexpected error occurred.', 500);
    }
} catch (\Throwable $e) {
    error_log('[umpires/ajax/save-slot.php] ' . $e->getMessage());
    d8tl_umpire_json(false, 'An unexpected error occurred.', 500);
}
