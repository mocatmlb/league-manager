<?php
/**
 * District 8 Travel League - Fix Missing Original History
 * Creates missing original schedule history entries and fixes change requests
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix Missing Original History ===\n";
echo "Creating missing original schedule history entries...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Find games missing original schedule history (version 1)
    echo "\nFinding games missing original schedule history...\n";
    
    $missing_history = $new_db->query("
        SELECT g.game_id, g.game_number, s.game_date, s.game_time, s.location
        FROM games g 
        JOIN seasons se ON g.season_id = se.season_id
        JOIN schedules s ON g.game_id = s.game_id
        LEFT JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.version_number = 1
        WHERE se.season_year = '2024' 
        AND sh.history_id IS NULL
        ORDER BY g.game_number
    ");
    
    echo "Found " . $missing_history->num_rows . " games missing original history\n";
    
    $history_created = 0;
    
    while ($game = $missing_history->fetch_assoc()) {
        echo "  Creating original history for game {$game['game_number']}...\n";
        
        // Create original schedule history entry (version 1)
        $insert_history_stmt = $new_db->prepare("
            INSERT INTO schedule_history (
                game_id, version_number, schedule_type, game_date, game_time, 
                location, is_current, notes, created_at
            ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Original schedule (created retroactively)', NOW())
        ");
        
        $insert_history_stmt->bind_param('isss', 
            $game['game_id'], 
            $game['game_date'], 
            $game['game_time'], 
            $game['location']
        );
        
        if ($insert_history_stmt->execute()) {
            $history_created++;
            echo "    ✓ Created original history entry\n";
            
            // Now fix any change requests for this game that have NULL original data
            $fix_requests_stmt = $new_db->prepare("
                UPDATE schedule_change_requests 
                SET original_date = ?, original_time = ?, original_location = ?
                WHERE game_id = ? AND original_date IS NULL
            ");
            
            $fix_requests_stmt->bind_param('sssi', 
                $game['game_date'], 
                $game['game_time'], 
                $game['location'], 
                $game['game_id']
            );
            
            $fix_requests_stmt->execute();
            $requests_fixed = $fix_requests_stmt->affected_rows;
            
            if ($requests_fixed > 0) {
                echo "    ✓ Fixed $requests_fixed change request(s) with original data\n";
            }
            
            // Check if there are any approved changes that should update the current schedule
            $approved_changes = $new_db->query("
                SELECT requested_date, requested_time, requested_location, created_date
                FROM schedule_change_requests 
                WHERE game_id = {$game['game_id']} 
                AND request_status = 'Approved'
                ORDER BY created_date DESC
                LIMIT 1
            ");
            
            if ($approved_change = $approved_changes->fetch_assoc()) {
                // Update current schedule to reflect the latest approved change
                $update_schedule_stmt = $new_db->prepare("
                    UPDATE schedules 
                    SET game_date = ?, game_time = ?, location = ?
                    WHERE game_id = ?
                ");
                
                $update_schedule_stmt->bind_param('sssi', 
                    $approved_change['requested_date'], 
                    $approved_change['requested_time'], 
                    $approved_change['requested_location'], 
                    $game['game_id']
                );
                
                $update_schedule_stmt->execute();
                echo "    ✓ Updated current schedule to latest approved change\n";
                
                // Update is_current flags in history
                $new_db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = {$game['game_id']}");
                
                // Find the latest history entry and mark it current
                $latest_history = $new_db->query("
                    SELECT history_id FROM schedule_history 
                    WHERE game_id = {$game['game_id']} 
                    ORDER BY version_number DESC 
                    LIMIT 1
                ")->fetch_assoc();
                
                if ($latest_history) {
                    $new_db->query("UPDATE schedule_history SET is_current = 1 WHERE history_id = {$latest_history['history_id']}");
                }
            }
            
        } else {
            echo "    ✗ Failed to create original history entry\n";
        }
        
        echo "\n";
    }
    
    echo "Summary:\n";
    echo "  Original history entries created: $history_created\n";
    
    // Verify the fix
    echo "\nVerification:\n";
    
    // Check for remaining games without original history
    $still_missing = $new_db->query("
        SELECT COUNT(*) as count
        FROM games g 
        JOIN seasons se ON g.season_id = se.season_id
        LEFT JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.version_number = 1
        WHERE se.season_year = '2024' 
        AND sh.history_id IS NULL
    ")->fetch_assoc()['count'];
    
    echo "  Games still missing original history: $still_missing " . ($still_missing == 0 ? "✓" : "✗") . "\n";
    
    // Check for change requests with NULL original data
    $null_originals = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedule_change_requests scr 
        JOIN games g ON scr.game_id = g.game_id
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND (scr.original_date IS NULL OR scr.original_time IS NULL OR scr.original_location IS NULL)
    ")->fetch_assoc()['count'];
    
    echo "  Change requests with NULL original data: $null_originals " . ($null_originals == 0 ? "✓" : "✗") . "\n";
    
    // Show final counts
    echo "\nFinal counts for 2024:\n";
    $final_counts = $new_db->query("
        SELECT 
            'Games with original history' as metric, COUNT(*) as count 
        FROM games g 
        JOIN seasons se ON g.season_id = se.season_id
        JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.version_number = 1
        WHERE se.season_year = '2024'
        UNION ALL
        SELECT 'Games with change requests', COUNT(DISTINCT g.game_id) 
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
    ");
    
    while ($row = $final_counts->fetch_assoc()) {
        echo "  {$row['metric']}: {$row['count']}\n";
    }
    
    echo "\n=== Fix Missing Original History Complete ===\n";
    echo "Missing original schedule history has been fixed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

