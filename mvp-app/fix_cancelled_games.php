<?php
/**
 * District 8 Travel League - Fix Cancelled Games Status
 * This script updates the game_status to 'Cancelled' for games with approved cancellation requests
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix Cancelled Games Status ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Find games with approved cancellation requests
$query = "
    SELECT g.game_id, g.game_number, g.game_status, scr.request_id, scr.request_type, scr.request_status
    FROM games g
    JOIN schedule_change_requests scr ON g.game_id = scr.game_id
    WHERE scr.request_type = 'Cancel'
    AND scr.request_status = 'Approved'
    AND g.game_status != 'Cancelled'
";

$stmt = $db->query($query);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($games) . " games with approved cancellation requests that are not marked as cancelled.\n";

// Update each game
$updated_count = 0;
foreach ($games as $game) {
    echo "Processing game {$game['game_number']} (ID: {$game['game_id']}, current status: {$game['game_status']})...\n";
    
    // Update game status to Cancelled
    $update_query = "UPDATE games SET game_status = 'Cancelled', modified_date = NOW() WHERE game_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([$game['game_id']]);
    
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
    
    echo "  ✓ Updated game {$game['game_number']} status to Cancelled and set version {$latest_version} as current\n";
    $updated_count++;
}

echo "\n=== Fix Complete ===\n";
echo "Updated {$updated_count} games to Cancelled status.\n";

// Now check for any games with pending cancellation requests
$pending_query = "
    SELECT g.game_id, g.game_number, g.game_status, scr.request_id, scr.request_type, scr.request_status, scr.reason
    FROM games g
    JOIN schedule_change_requests scr ON g.game_id = scr.game_id
    WHERE scr.request_type = 'Cancel'
    AND scr.request_status = 'Pending'
    LIMIT 10
";

$pending_stmt = $db->query($pending_query);
$pending_games = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pending_games) > 0) {
    echo "\nFound " . count($pending_games) . " games with pending cancellation requests:\n";
    foreach ($pending_games as $game) {
        echo "  - Game {$game['game_number']} (ID: {$game['game_id']}, status: {$game['game_status']}, reason: {$game['reason']})\n";
    }
    echo "\nTo update these games, you may need to review them manually or run an additional script.\n";
}
