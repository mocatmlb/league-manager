<?php
define('D8TL_APP', true);

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
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error: env-loader not found']);
    exit;
}
unset($__dir, $__found, $__i, $__candidate);

require_once EnvLoader::getPath('includes/bootstrap.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to use the chat.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$sessionId = trim($input['session_id'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required.']);
    exit;
}

if (empty($sessionId)) {
    $sessionId = session_id() . '-' . bin2hex(random_bytes(8));
}

require_once EnvLoader::getPath('includes/ChatService.php');

$chat = new ChatService();

$currentUser = Auth::getCurrentUser();

$teamName = null;
if (Auth::isCoach()) {
    $db = Database::getInstance();
    $team = $db->fetchOne(
        "SELECT t.team_name FROM teams t
         JOIN team_owners o ON t.team_id = o.team_id
         WHERE o.user_id = ?",
        [$currentUser['id'] ?? 0]
    );
    $teamName = $team['team_name'] ?? null;

    $teamOfficial = $db->fetchOne(
        "SELECT t.team_name FROM teams t
         JOIN team_officials o ON t.team_id = o.team_id
         WHERE o.user_id = ?",
        [$currentUser['id'] ?? 0]
    );
    if (!$teamName && $teamOfficial) {
        $teamName = $teamOfficial['team_name'];
    }
}

$result = $chat->answer(
    $message,
    $sessionId,
    $currentUser['id'] ?? null,
    $currentUser['type'] ?? 'user',
    $currentUser['username'] ?? null,
    $teamName
);

$result['session_id'] = $sessionId;

echo json_encode($result);
