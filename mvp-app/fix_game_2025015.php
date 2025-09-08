<?php
/**
 * District 8 Travel League - Fix Game 2025015
 * This script fixes game 2025015 which should be marked as cancelled
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix Game 2025015 ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Game details
$game_number = '2025015';

// Get game info
$game_query = "SELECT g.game_id, g.game_number, g.game_status FROM games g WHERE g.game_number = ?";
$game_stmt = $db->prepare($game_query);
$game_stmt->execute([$game_number]);
$game = $game_stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die("Game {$game_number} not found in the database\n");
}

echo "Processing game {$game_number} (ID: {$game['game_id']}, current status: {$game['game_status']})...\n";

// Check for existing change request
$request_query = "SELECT scr.request_id, scr.request_type, scr.request_status, scr.reason 
                  FROM schedule_change_requests scr 
                  WHERE scr.game_id = ? 
                  ORDER BY scr.request_id";
$request_stmt = $db->prepare($request_query);
$request_stmt->execute([$game['game_id']]);
$change_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);

// Update existing request to be a cancellation if it exists
if (!empty($change_requests)) {
    $request = $change_requests[0]; // Use the first request
    echo "  Found existing change request (ID: {$request['request_id']}, type: {$request['request_type']}, status: {$request['request_status']})\n";
    
    // Update request type to Cancel and status to Approved
    $update_request = $db->prepare("UPDATE schedule_change_requests SET request_type = 'Cancel', request_status = 'Approved', reviewed_at = NOW() WHERE request_id = ?");
    $update_request->execute([$request['request_id']]);
    echo "  ✓ Updated request type to Cancel and status to Approved\n";
    
    $request_id = $request['request_id'];
} else {
    // Create a new cancellation request
    echo "  No existing request found, creating a new cancellation request\n";
    
    // Get original schedule
    $original_query = "SELECT sh.game_date, sh.game_time, sh.location 
                      FROM schedule_history sh 
                      WHERE sh.game_id = ? AND sh.version_number = 1";
    $original_stmt = $db->prepare($original_query);
    $original_stmt->execute([$game['game_id']]);
    $original = $original_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original) {
        die("No original schedule found for game {$game_number}, exiting\n");
    }
    
    // Create cancellation request
    $insert_request = $db->prepare("
        INSERT INTO schedule_change_requests (
            game_id, requested_by, request_type, 
            original_date, original_time, original_location,
            requested_date, requested_time, requested_location,
            reason, request_status, created_date, reviewed_at
        ) VALUES (?, 'ADMIN', 'Cancel', ?, ?, ?, ?, ?, ?, 'No availibility', 'Approved', NOW(), NOW())
    ");
    
    $insert_request->execute([
        $game['game_id'],
        $original['game_date'], $original['game_time'], $original['location'],
        $original['game_date'], $original['game_time'], $original['location']
    ]);
    
    $request_id = $db->lastInsertId();
    echo "  ✓ Created new cancellation request (ID: {$request_id})\n";
}

// Update game status to Cancelled
$update_game = $db->prepare("UPDATE games SET game_status = 'Cancelled', modified_date = NOW() WHERE game_id = ?");
$update_game->execute([$game['game_id']]);
echo "  ✓ Updated game status to Cancelled\n";

// Update schedule history to link to the cancellation request
$history_query = "SELECT history_id, version_number FROM schedule_history WHERE game_id = ? AND notes LIKE '%availibility%'";
$history_stmt = $db->prepare($history_query);
$history_stmt->execute([$game['game_id']]);
$history = $history_stmt->fetch(PDO::FETCH_ASSOC);

if ($history) {
    echo "  Found history entry with 'No availibility' note (ID: {$history['history_id']}, version: {$history['version_number']})\n";
    
    // Update history entry to link to the cancellation request
    $update_history = $db->prepare("UPDATE schedule_history SET change_request_id = ? WHERE history_id = ?");
    $update_history->execute([$request_id, $history['history_id']]);
    echo "  ✓ Linked history entry to cancellation request\n";
    
    // Set this version as current
    $update_current = $db->prepare("
        UPDATE schedule_history 
        SET is_current = CASE 
            WHEN version_number = ? THEN 1 
            ELSE 0 
        END 
        WHERE game_id = ?
    ");
    $update_current->execute([$history['version_number'], $game['game_id']]);
    echo "  ✓ Updated is_current flag for version {$history['version_number']}\n";
}

echo "\n=== Fix Complete ===\n";

// Verify the game is now marked as cancelled
$verify_query = "SELECT g.game_number, g.game_status FROM games g WHERE g.game_number = ?";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->execute([$game_number]);
$result = $verify_stmt->fetch(PDO::FETCH_ASSOC);

echo "Verification result:\n";
echo "  Game {$result['game_number']}: {$result['game_status']}\n";

// Verify the change request is now a cancellation
$verify_request_query = "
    SELECT scr.request_id, scr.request_type, scr.request_status 
    FROM schedule_change_requests scr 
    JOIN games g ON scr.game_id = g.game_id 
    WHERE g.game_number = ?
";
$verify_request_stmt = $db->prepare($verify_request_query);
$verify_request_stmt->execute([$game_number]);
$request_result = $verify_request_stmt->fetch(PDO::FETCH_ASSOC);

echo "Change request:\n";
echo "  ID: {$request_result['request_id']}, Type: {$request_result['request_type']}, Status: {$request_result['request_status']}\n";

// Verify the correct history entry is marked as current
$verify_history_query = "
    SELECT sh.version_number, sh.notes, sh.is_current 
    FROM schedule_history sh 
    JOIN games g ON sh.game_id = g.game_id 
    WHERE g.game_number = ? 
    ORDER BY sh.version_number
";
$verify_history_stmt = $db->prepare($verify_history_query);
$verify_history_stmt->execute([$game_number]);
$history_results = $verify_history_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Schedule history:\n";
foreach ($history_results as $history) {
    $current = $history['is_current'] ? 'Yes' : 'No';
    echo "  Version {$history['version_number']}: {$history['notes']} (Current: {$current})\n";
}
