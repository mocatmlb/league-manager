<?php
/**
 * District 8 Travel League - Fix Missing Cancellations
 * This script fixes games that should be marked as cancelled but aren't
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix Missing Cancellations ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// List of games that need to be fixed
$games_to_fix = [
    '2025011',
    '2025035',
    '2025053'
];

echo "Fixing " . count($games_to_fix) . " games that should be marked as cancelled...\n";

foreach ($games_to_fix as $game_number) {
    // Get game info
    $game_query = "SELECT g.game_id, g.game_number, g.game_status FROM games g WHERE g.game_number = ?";
    $game_stmt = $db->prepare($game_query);
    $game_stmt->execute([$game_number]);
    $game = $game_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo "! Game {$game_number} not found in the database\n";
        continue;
    }
    
    echo "Processing game {$game_number} (ID: {$game['game_id']}, current status: {$game['game_status']})...\n";
    
    // Check for existing cancellation request
    $request_query = "SELECT scr.request_id, scr.request_type, scr.request_status, scr.reason 
                      FROM schedule_change_requests scr 
                      WHERE scr.game_id = ? AND scr.request_type = 'Cancel'";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->execute([$game['game_id']]);
    $cancel_request = $request_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cancel_request) {
        echo "  Found existing cancellation request (ID: {$cancel_request['request_id']}, status: {$cancel_request['request_status']})\n";
        
        // Update request status to Approved if needed
        if ($cancel_request['request_status'] != 'Approved') {
            $update_request = $db->prepare("UPDATE schedule_change_requests SET request_status = 'Approved', reviewed_at = NOW() WHERE request_id = ?");
            $update_request->execute([$cancel_request['request_id']]);
            echo "  ✓ Updated request status to Approved\n";
        }
        
        $request_id = $cancel_request['request_id'];
    } else {
        // Find any existing change request
        $change_query = "SELECT scr.request_id, scr.request_type, scr.request_status, scr.reason 
                        FROM schedule_change_requests scr 
                        WHERE scr.game_id = ? 
                        ORDER BY scr.request_id DESC 
                        LIMIT 1";
        $change_stmt = $db->prepare($change_query);
        $change_stmt->execute([$game['game_id']]);
        $change_request = $change_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($change_request) {
            echo "  Found existing change request (ID: {$change_request['request_id']}, type: {$change_request['request_type']}, status: {$change_request['request_status']})\n";
            
            // Update request type to Cancel and status to Approved
            $update_request = $db->prepare("UPDATE schedule_change_requests SET request_type = 'Cancel', request_status = 'Approved', reviewed_at = NOW() WHERE request_id = ?");
            $update_request->execute([$change_request['request_id']]);
            echo "  ✓ Updated request type to Cancel and status to Approved\n";
            
            $request_id = $change_request['request_id'];
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
                echo "  ! No original schedule found for game {$game_number}, skipping\n";
                continue;
            }
            
            // Create cancellation request
            $insert_request = $db->prepare("
                INSERT INTO schedule_change_requests (
                    game_id, requested_by, request_type, 
                    original_date, original_time, original_location,
                    requested_date, requested_time, requested_location,
                    reason, request_status, created_date, reviewed_at
                ) VALUES (?, 'ADMIN', 'Cancel', ?, ?, ?, ?, ?, ?, 'CANCEL', 'Approved', NOW(), NOW())
            ");
            
            $insert_request->execute([
                $game['game_id'],
                $original['game_date'], $original['game_time'], $original['location'],
                $original['game_date'], $original['game_time'], $original['location']
            ]);
            
            $request_id = $db->lastInsertId();
            echo "  ✓ Created new cancellation request (ID: {$request_id})\n";
            
            // Create a new history entry for the cancellation
            $history_query = "SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = ?";
            $history_stmt = $db->prepare($history_query);
            $history_stmt->execute([$game['game_id']]);
            $history_result = $history_stmt->fetch(PDO::FETCH_ASSOC);
            $next_version = ($history_result['max_version'] ?? 0) + 1;
            
            $insert_history = $db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, change_request_id, is_current, notes, created_at
                ) VALUES (?, ?, 'Changed', ?, ?, ?, ?, 1, 'CANCEL', NOW())
            ");
            
            $insert_history->execute([
                $game['game_id'], $next_version,
                $original['game_date'], $original['game_time'], $original['location'],
                $request_id
            ]);
            
            echo "  ✓ Created new history entry (version: {$next_version})\n";
            
            // Update existing history entries to not be current
            $update_history = $db->prepare("UPDATE schedule_history SET is_current = 0 WHERE game_id = ? AND version_number != ?");
            $update_history->execute([$game['game_id'], $next_version]);
        }
    }
    
    // Update game status to Cancelled
    $update_game = $db->prepare("UPDATE games SET game_status = 'Cancelled', modified_date = NOW() WHERE game_id = ?");
    $update_game->execute([$game['game_id']]);
    echo "  ✓ Updated game status to Cancelled\n";
}

echo "\n=== Fix Complete ===\n";

// Verify all games are now marked as cancelled
$verify_query = "SELECT g.game_number, g.game_status FROM games g WHERE g.game_number IN ('" . implode("','", $games_to_fix) . "')";
$verify_stmt = $db->query($verify_query);
$verify_results = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Verification results:\n";
foreach ($verify_results as $result) {
    echo "  Game {$result['game_number']}: {$result['game_status']}\n";
}
