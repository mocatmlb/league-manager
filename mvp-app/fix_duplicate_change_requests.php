<?php
/**
 * District 8 Travel League - Fix Duplicate Change Requests
 * Removes duplicate change requests and fixes any data inconsistencies
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix Duplicate Change Requests ===\n";
echo "Finding and removing duplicate change requests...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Find duplicate change requests
    echo "\nFinding duplicate change requests...\n";
    
    $duplicates = $new_db->query("
        SELECT 
            g.game_number,
            scr.original_date, scr.original_time, scr.original_location,
            scr.requested_date, scr.requested_time, scr.requested_location,
            scr.request_type, scr.reason,
            COUNT(*) as duplicate_count,
            GROUP_CONCAT(scr.request_id ORDER BY scr.created_date) as request_ids
        FROM games g 
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id
        WHERE g.game_number LIKE '2024%'
        GROUP BY 
            g.game_id, 
            scr.original_date, scr.original_time, scr.original_location,
            scr.requested_date, scr.requested_time, scr.requested_location,
            scr.request_type, scr.reason
        HAVING COUNT(*) > 1
        ORDER BY g.game_number
    ");
    
    echo "Found " . $duplicates->num_rows . " sets of duplicate change requests\n";
    
    $duplicates_removed = 0;
    
    while ($duplicate = $duplicates->fetch_assoc()) {
        echo "\nGame {$duplicate['game_number']}: {$duplicate['duplicate_count']} duplicates\n";
        echo "  Request: {$duplicate['original_location']} → {$duplicate['requested_location']} ({$duplicate['reason']})\n";
        
        $request_ids = explode(',', $duplicate['request_ids']);
        
        // Keep the first request (oldest), remove the rest
        $keep_id = array_shift($request_ids);
        $remove_ids = $request_ids;
        
        echo "  Keeping request #$keep_id, removing: " . implode(', ', $remove_ids) . "\n";
        
        foreach ($remove_ids as $remove_id) {
            // First, update any schedule_history entries that reference this request
            $update_history = $new_db->prepare("
                UPDATE schedule_history 
                SET change_request_id = ? 
                WHERE change_request_id = ?
            ");
            $update_history->bind_param('ii', $keep_id, $remove_id);
            $update_history->execute();
            
            if ($update_history->affected_rows > 0) {
                echo "    ✓ Updated {$update_history->affected_rows} history entries to reference request #$keep_id\n";
            }
            
            // Remove the duplicate request
            $delete_request = $new_db->prepare("DELETE FROM schedule_change_requests WHERE request_id = ?");
            $delete_request->bind_param('i', $remove_id);
            $delete_request->execute();
            
            if ($delete_request->affected_rows > 0) {
                $duplicates_removed++;
                echo "    ✓ Removed duplicate request #$remove_id\n";
            }
        }
    }
    
    echo "\nSummary:\n";
    echo "  Duplicate requests removed: $duplicates_removed\n";
    
    // Check for any other data inconsistencies
    echo "\nChecking for data inconsistencies...\n";
    
    // Check for change requests not linked to history
    $unlinked_requests = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedule_change_requests scr
        LEFT JOIN schedule_history sh ON scr.request_id = sh.change_request_id
        WHERE sh.change_request_id IS NULL
    ")->fetch_assoc()['count'];
    
    echo "  Change requests not linked to history: $unlinked_requests " . ($unlinked_requests == 0 ? "✓" : "✗") . "\n";
    
    // Check for history entries not linked to requests (should be only Original type)
    $unlinked_history = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedule_history sh
        WHERE sh.schedule_type = 'Changed' 
        AND sh.change_request_id IS NULL
    ")->fetch_assoc()['count'];
    
    echo "  Changed history entries not linked to requests: $unlinked_history " . ($unlinked_history == 0 ? "✓" : "✗") . "\n";
    
    // Check for schedule consistency
    $inconsistent_schedules = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedules s 
        JOIN schedule_history sh ON s.game_id = sh.game_id AND sh.is_current = 1 
        WHERE s.game_date != sh.game_date 
           OR s.game_time != sh.game_time 
           OR s.location != sh.location
    ")->fetch_assoc()['count'];
    
    echo "  Schedules inconsistent with current history: $inconsistent_schedules " . ($inconsistent_schedules == 0 ? "✓" : "✗") . "\n";
    
    // Final counts
    echo "\nFinal counts for 2024:\n";
    $final_counts = $new_db->query("
        SELECT 
            'Games with change requests' as metric, COUNT(DISTINCT g.game_id) as count 
        FROM games g 
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id
        JOIN seasons se ON g.season_id = se.season_id 
        WHERE se.season_year = '2024'
        UNION ALL
        SELECT 'Total change requests', COUNT(*) 
        FROM schedule_change_requests scr 
        JOIN games g ON scr.game_id = g.game_id
        JOIN seasons se ON g.season_id = se.season_id 
        WHERE se.season_year = '2024'
        UNION ALL
        SELECT 'Linked history entries', COUNT(*)
        FROM schedule_history sh
        JOIN games g ON sh.game_id = g.game_id
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND sh.change_request_id IS NOT NULL
    ");
    
    while ($row = $final_counts->fetch_assoc()) {
        echo "  {$row['metric']}: {$row['count']}\n";
    }
    
    echo "\n=== Fix Duplicate Change Requests Complete ===\n";
    echo "Duplicate change requests have been cleaned up!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

