<?php
/**
 * District 8 Travel League - Fix Original Schedule Requests
 * Removes incorrect schedule change requests for original schedules
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix Original Schedule Requests ===\n";
echo "Removing incorrect change requests for original schedules...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Find and remove change requests that are actually original schedules
    echo "\nAnalyzing schedule change requests...\n";
    
    // Find requests where reason contains "ORIGINAL SCHEDULE"
    $original_requests = $new_db->query("
        SELECT scr.request_id, g.game_number, scr.reason, scr.original_date, scr.requested_date
        FROM schedule_change_requests scr 
        JOIN games g ON scr.game_id = g.game_id
        WHERE scr.reason LIKE '%ORIGINAL SCHEDULE%'
        ORDER BY g.game_number
    ");
    
    echo "Found " . $original_requests->num_rows . " requests marked as 'ORIGINAL SCHEDULE'\n";
    
    $requests_to_remove = [];
    
    while ($request = $original_requests->fetch_assoc()) {
        echo "  Game {$request['game_number']}: Original={$request['original_date']}, Requested={$request['requested_date']} - ";
        
        // If original and requested are the same, this is definitely an incorrect original schedule request
        if ($request['original_date'] == $request['requested_date']) {
            echo "REMOVING (identical dates)\n";
            $requests_to_remove[] = $request['request_id'];
        } else {
            echo "KEEPING (different dates - might be legitimate change)\n";
        }
    }
    
    // Also find requests where original and requested data are identical
    $identical_requests = $new_db->query("
        SELECT scr.request_id, g.game_number, scr.reason
        FROM schedule_change_requests scr 
        JOIN games g ON scr.game_id = g.game_id
        WHERE scr.original_date = scr.requested_date 
        AND scr.original_time = scr.requested_time 
        AND scr.original_location = scr.requested_location
        AND scr.reason NOT LIKE '%ORIGINAL SCHEDULE%'
        ORDER BY g.game_number
    ");
    
    echo "\nFound " . $identical_requests->num_rows . " additional requests with identical original/requested data\n";
    
    while ($request = $identical_requests->fetch_assoc()) {
        echo "  Game {$request['game_number']}: {$request['reason']} - REMOVING (no actual change)\n";
        $requests_to_remove[] = $request['request_id'];
    }
    
    // Remove the incorrect requests
    if (!empty($requests_to_remove)) {
        echo "\nRemoving " . count($requests_to_remove) . " incorrect change requests...\n";
        
        $placeholders = str_repeat('?,', count($requests_to_remove) - 1) . '?';
        $delete_stmt = $new_db->prepare("DELETE FROM schedule_change_requests WHERE request_id IN ($placeholders)");
        
        $types = str_repeat('i', count($requests_to_remove));
        $delete_stmt->bind_param($types, ...$requests_to_remove);
        
        if ($delete_stmt->execute()) {
            echo "✓ Removed " . $delete_stmt->affected_rows . " incorrect change requests\n";
        } else {
            throw new Exception("Failed to remove incorrect requests");
        }
    } else {
        echo "No incorrect requests found to remove\n";
    }
    
    // Show summary of remaining change requests
    echo "\nSummary of remaining change requests by game:\n";
    $summary = $new_db->query("
        SELECT g.game_number, COUNT(scr.request_id) as change_count, 
               GROUP_CONCAT(DISTINCT scr.request_type) as request_types
        FROM games g 
        LEFT JOIN schedule_change_requests scr ON g.game_id = scr.game_id
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = '2024'
        GROUP BY g.game_id, g.game_number
        HAVING change_count > 0
        ORDER BY g.game_number
        LIMIT 10
    ");
    
    while ($row = $summary->fetch_assoc()) {
        echo "  Game {$row['game_number']}: {$row['change_count']} changes ({$row['request_types']})\n";
    }
    
    // Final counts
    echo "\nFinal counts:\n";
    $final_counts = $new_db->query("
        SELECT 
            'Total Games' as metric, COUNT(*) as count 
        FROM games g 
        JOIN seasons s ON g.season_id = s.season_id 
        WHERE s.season_year = '2024'
        UNION ALL
        SELECT 'Games with Changes', COUNT(DISTINCT g.game_id) 
        FROM games g 
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id
        JOIN seasons s ON g.season_id = s.season_id 
        WHERE s.season_year = '2024'
        UNION ALL
        SELECT 'Total Change Requests', COUNT(*) 
        FROM schedule_change_requests scr 
        JOIN games g ON scr.game_id = g.game_id
        JOIN seasons s ON g.season_id = s.season_id 
        WHERE s.season_year = '2024'
    ");
    
    while ($row = $final_counts->fetch_assoc()) {
        echo "  {$row['metric']}: {$row['count']}\n";
    }
    
    echo "\n=== Fix Original Schedule Requests Complete ===\n";
    echo "Original schedule requests have been cleaned up!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

