<?php
/**
 * District 8 Travel League - Link Schedule History
 * Links schedule change requests to schedule history entries
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Link Schedule History ===\n";
echo "Linking schedule change requests to schedule history entries...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Get all games that have both schedule history and change requests
    $games_result = $new_db->query("
        SELECT DISTINCT g.game_id, g.game_number
        FROM games g
        JOIN schedule_history sh ON g.game_id = sh.game_id
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id
        WHERE g.season_id IN (SELECT season_id FROM seasons WHERE season_year = 2024)
        ORDER BY g.game_number
    ");
    
    echo "Found " . $games_result->num_rows . " games with history and change requests\n\n";
    
    $history_entries_created = 0;
    
    while ($game = $games_result->fetch_assoc()) {
        echo "Processing game {$game['game_number']}...\n";
        
        // Get all approved schedule changes for this game in chronological order
        $changes_result = $new_db->query("
            SELECT request_id, request_type, requested_date, requested_time, requested_location, 
                   reason, reviewed_at, created_date, request_status
            FROM schedule_change_requests 
            WHERE game_id = {$game['game_id']} 
            AND request_status = 'Approved'
            ORDER BY created_date ASC
        ");
        
        $version = 2; // Start from version 2 (version 1 is original)
        
        while ($change = $changes_result->fetch_assoc()) {
            // Check if history entry already exists for this change
            $check_stmt = $new_db->prepare("
                SELECT history_id FROM schedule_history 
                WHERE game_id = ? AND change_request_id = ?
            ");
            $check_stmt->bind_param('ii', $game['game_id'], $change['request_id']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                echo "  - History entry already exists for change request #{$change['request_id']}\n";
                continue;
            }
            
            // Determine schedule type (must be 'Original' or 'Changed')
            $schedule_type = 'Changed';
            
            // Create history entry for this change
            $insert_stmt = $new_db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, is_current, change_request_id, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
            ");
            
            $notes = $change['reason'] ?: "Schedule {$change['request_type']}";
            $created_at = $change['reviewed_at'] ?: $change['created_date'];
            
            // For cancelled games, use original date but mark as cancelled
            if ($change['request_type'] == 'Cancel') {
                // Get original schedule data for cancelled games
                $original = $new_db->query("
                    SELECT game_date, game_time FROM schedules WHERE game_id = {$game['game_id']}
                ")->fetch_assoc();
                
                $game_date = $original['game_date'];
                $game_time = $original['game_time'];
                $location = 'CANCELLED';
            } else {
                $game_date = $change['requested_date'];
                $game_time = $change['requested_time'];
                $location = $change['requested_location'];
            }
            
            $insert_stmt->bind_param('iissssiss', 
                $game['game_id'], 
                $version, 
                $schedule_type,
                $game_date, 
                $game_time, 
                $location,
                $change['request_id'],
                $notes,
                $created_at
            );
            
            if ($insert_stmt->execute()) {
                $history_entries_created++;
                echo "  ✓ Created history entry v{$version} for change request #{$change['request_id']} ({$schedule_type})\n";
            } else {
                echo "  ! Failed to create history entry for change request #{$change['request_id']}\n";
            }
            
            $version++;
        }
        
        // Update the current schedule to reflect the latest approved change
        updateCurrentSchedule($new_db, $game['game_id']);
        
        echo "\n";
    }
    
    echo "=== Link Schedule History Complete ===\n";
    echo "History entries created: $history_entries_created\n";
    echo "Schedule history has been successfully linked!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Update the current schedule to reflect the latest approved change
 */
function updateCurrentSchedule($db, $game_id) {
    // Get the latest approved change
    $latest_change = $db->query("
        SELECT requested_date, requested_time, requested_location, request_type
        FROM schedule_change_requests 
        WHERE game_id = $game_id 
        AND request_status = 'Approved'
        ORDER BY created_date DESC
        LIMIT 1
    ")->fetch_assoc();
    
    if ($latest_change && $latest_change['request_type'] != 'Cancel') {
        // Update the schedule with the latest approved change
        $update_stmt = $db->prepare("
            UPDATE schedules 
            SET game_date = ?, game_time = ?, location = ?
            WHERE game_id = ?
        ");
        
        $update_stmt->bind_param('sssi', 
            $latest_change['requested_date'],
            $latest_change['requested_time'],
            $latest_change['requested_location'],
            $game_id
        );
        
        $update_stmt->execute();
        
        // Mark the latest history entry as current
        $db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = $game_id");
        $db->query("
            UPDATE schedule_history 
            SET is_current = 1 
            WHERE game_id = $game_id 
            ORDER BY version_number DESC 
            LIMIT 1
        ");
    }
}
