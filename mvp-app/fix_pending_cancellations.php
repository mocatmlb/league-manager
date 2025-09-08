<?php
/**
 * District 8 Travel League - Fix Pending Cancellation Requests
 * This script updates pending cancellation requests to approved and updates game status
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix Pending Cancellation Requests ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Find games with pending cancellation requests
$query = "
    SELECT g.game_id, g.game_number, g.game_status, scr.request_id, scr.request_type, scr.request_status, scr.reason
    FROM games g
    JOIN schedule_change_requests scr ON g.game_id = scr.game_id
    WHERE scr.request_type = 'Cancel'
    AND scr.request_status = 'Pending'
    AND g.game_number LIKE '2025%'
";

$stmt = $db->query($query);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($games) . " games with pending cancellation requests.\n";

// Update each game
$updated_count = 0;
foreach ($games as $game) {
    echo "Processing game {$game['game_number']} (ID: {$game['game_id']}, current status: {$game['game_status']})...\n";
    
    // Update request status to Approved
    $update_request_query = "UPDATE schedule_change_requests SET request_status = 'Approved', reviewed_at = NOW() WHERE request_id = ?";
    $update_request_stmt = $db->prepare($update_request_query);
    $update_request_stmt->execute([$game['request_id']]);
    
    // Update game status to Cancelled
    $update_game_query = "UPDATE games SET game_status = 'Cancelled', modified_date = NOW() WHERE game_id = ?";
    $update_game_stmt = $db->prepare($update_game_query);
    $update_game_stmt->execute([$game['game_id']]);
    
    // Get the latest version number for this game
    $version_query = "SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?";
    $version_stmt = $db->prepare($version_query);
    $version_stmt->execute([$game['game_id']]);
    $version_result = $version_stmt->fetch(PDO::FETCH_ASSOC);
    $latest_version = $version_result['max_version'];
    
    // Update is_current flags in schedule history
    $update_history_query = "
        UPDATE schedule_history 
        SET is_current = CASE 
            WHEN version_number = ? THEN 1 
            ELSE 0 
        END 
        WHERE game_id = ?
    ";
    $update_history_stmt = $db->prepare($update_history_query);
    $update_history_stmt->execute([$latest_version, $game['game_id']]);
    
    echo "  ✓ Updated game {$game['game_number']} status to Cancelled and request to Approved\n";
    echo "  ✓ Set version {$latest_version} as current in schedule history\n";
    $updated_count++;
}

echo "\n=== Fix Complete ===\n";
echo "Updated {$updated_count} games and requests.\n";
