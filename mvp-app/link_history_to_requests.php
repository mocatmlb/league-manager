<?php
/**
 * District 8 Travel League - Link History to Requests
 * Links schedule history entries to their corresponding change requests
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Link History to Requests ===\n";
echo "Linking schedule history entries to change requests...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Get all schedule history entries that are changes (not original) and don't have change_request_id
    echo "\nFinding schedule history entries to link...\n";
    
    $unlinked_history = $new_db->query("
        SELECT sh.history_id, sh.game_id, sh.version_number, sh.game_date, sh.game_time, sh.location, sh.notes
        FROM schedule_history sh
        WHERE sh.schedule_type = 'Changed' 
        AND sh.change_request_id IS NULL
        ORDER BY sh.game_id, sh.version_number
    ");
    
    echo "Found " . $unlinked_history->num_rows . " history entries to link\n";
    
    $links_created = 0;
    $current_game_id = null;
    $game_requests = [];
    
    while ($history = $unlinked_history->fetch_assoc()) {
        // If we're processing a new game, load its change requests
        if ($current_game_id !== $history['game_id']) {
            $current_game_id = $history['game_id'];
            
            // Get all change requests for this game, ordered by creation date
            $requests_result = $new_db->query("
                SELECT request_id, requested_date, requested_time, requested_location, 
                       reason, created_date, request_type, requested_by
                FROM schedule_change_requests 
                WHERE game_id = {$current_game_id}
                ORDER BY created_date ASC
            ");
            
            $game_requests = [];
            while ($request = $requests_result->fetch_assoc()) {
                $game_requests[] = $request;
            }
            
            echo "\n  Game ID {$current_game_id}: Found " . count($game_requests) . " change requests\n";
        }
        
        // Try to match this history entry to a change request
        $matched_request = null;
        
        foreach ($game_requests as $request) {
            // Match based on requested date, time, and location
            if ($history['game_date'] == $request['requested_date'] &&
                $history['game_time'] == $request['requested_time'] &&
                $history['location'] == $request['requested_location']) {
                
                $matched_request = $request;
                break;
            }
        }
        
        if ($matched_request) {
            // Update the history entry with the change request ID
            $update_stmt = $new_db->prepare("
                UPDATE schedule_history 
                SET change_request_id = ?
                WHERE history_id = ?
            ");
            
            $update_stmt->bind_param('ii', $matched_request['request_id'], $history['history_id']);
            
            if ($update_stmt->execute()) {
                $links_created++;
                echo "    ✓ Linked history v{$history['version_number']} to request #{$matched_request['request_id']} ({$matched_request['reason']})\n";
            }
        } else {
            echo "    ! Could not match history v{$history['version_number']} ({$history['game_date']} {$history['game_time']} at {$history['location']})\n";
            
            // Show available requests for debugging
            if (!empty($game_requests)) {
                echo "      Available requests:\n";
                foreach ($game_requests as $req) {
                    echo "        - {$req['requested_date']} {$req['requested_time']} at {$req['requested_location']} ({$req['reason']})\n";
                }
            }
        }
    }
    
    echo "\nSummary:\n";
    echo "  History entries linked: $links_created\n";
    
    // Verification
    echo "\nVerification:\n";
    
    // Check how many history entries still don't have change_request_id
    $still_unlinked = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedule_history 
        WHERE schedule_type = 'Changed' 
        AND change_request_id IS NULL
    ")->fetch_assoc()['count'];
    
    echo "  History entries still unlinked: $still_unlinked " . ($still_unlinked == 0 ? "✓" : "✗") . "\n";
    
    // Check how many change requests are now linked to history
    $linked_requests = $new_db->query("
        SELECT COUNT(DISTINCT scr.request_id) as count
        FROM schedule_change_requests scr
        JOIN schedule_history sh ON scr.request_id = sh.change_request_id
    ")->fetch_assoc()['count'];
    
    echo "  Change requests linked to history: $linked_requests\n";
    
    // Test the AJAX endpoint data for a sample game
    echo "\nTesting AJAX endpoint data for game 2024024:\n";
    $test_game_id = $new_db->query("SELECT game_id FROM games WHERE game_number = '2024024'")->fetch_assoc()['game_id'];
    
    $test_data = $new_db->query("
        SELECT 
            sh.version_number,
            sh.schedule_type,
            sh.game_date,
            sh.game_time,
            sh.location,
            sh.notes,
            scr.request_type,
            scr.requested_by,
            scr.reason,
            scr.request_status
        FROM schedule_history sh
        LEFT JOIN schedule_change_requests scr ON sh.change_request_id = scr.request_id
        WHERE sh.game_id = $test_game_id
        ORDER BY sh.version_number ASC
    ");
    
    while ($row = $test_data->fetch_assoc()) {
        $details = $row['requested_by'] ? "by {$row['requested_by']}: {$row['reason']} ({$row['request_status']})" : "no details";
        echo "    v{$row['version_number']} ({$row['schedule_type']}): {$row['game_date']} {$row['game_time']} - $details\n";
    }
    
    echo "\n=== Link History to Requests Complete ===\n";
    echo "Schedule history has been linked to change requests!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

