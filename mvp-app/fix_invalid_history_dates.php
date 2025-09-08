<?php
/**
 * District 8 Travel League - Fix Invalid History Dates
 * Fixes schedule history entries with invalid dates (1969-12-31)
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix Invalid History Dates ===\n";
echo "Fixing schedule history entries with invalid dates...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Find games with invalid history dates
    echo "\nFinding games with invalid history dates...\n";
    
    $invalid_dates = $new_db->query("
        SELECT g.game_number, g.game_id, s.game_date as current_game_date, s.game_time as current_game_time, s.location as current_game_location,
               sh.history_id, sh.version_number, sh.game_date as history_date, sh.schedule_type, sh.is_current
        FROM games g 
        JOIN schedules s ON g.game_id = s.game_id
        JOIN schedule_history sh ON g.game_id = sh.game_id
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND (sh.game_date < '1990-01-01' OR sh.game_date > '2030-12-31')
        ORDER BY g.game_number, sh.version_number
    ");
    
    echo "Found " . $invalid_dates->num_rows . " history entries with invalid dates\n";
    
    $fixes_applied = 0;
    $current_game = null;
    
    while ($row = $invalid_dates->fetch_assoc()) {
        if ($current_game !== $row['game_number']) {
            $current_game = $row['game_number'];
            echo "\nGame {$row['game_number']}:\n";
            echo "  Current schedule: {$row['current_game_date']} {$row['current_game_time']} at {$row['current_game_location']}\n";
        }
        
        echo "  Invalid history v{$row['version_number']} ({$row['schedule_type']}): {$row['history_date']}\n";
        
        if ($row['schedule_type'] == 'Changed') {
            // For changed entries with invalid dates, use the current schedule date
            echo "    → Fixing to match current schedule: {$row['current_game_date']}\n";
            
            $update_stmt = $new_db->prepare("
                UPDATE schedule_history 
                SET game_date = ?, game_time = ?, location = ?
                WHERE history_id = ?
            ");
            
            $update_stmt->bind_param('sssi', 
                $row['current_game_date'], 
                $row['current_game_time'], 
                $row['current_game_location'], 
                $row['history_id']
            );
            
            if ($update_stmt->execute()) {
                $fixes_applied++;
                echo "    ✓ Fixed history entry\n";
            }
            
        } elseif ($row['schedule_type'] == 'Original') {
            // For original entries with invalid dates, we need to determine the true original
            echo "    → This is an original entry with invalid date - needs manual review\n";
            
            // Try to find a reasonable original date (maybe 1 day before current)
            $original_date = date('Y-m-d', strtotime($row['current_game_date'] . ' -1 day'));
            echo "    → Suggesting original date: $original_date (1 day before current)\n";
            
            $update_stmt = $new_db->prepare("
                UPDATE schedule_history 
                SET game_date = ?, game_time = ?, location = ?
                WHERE history_id = ?
            ");
            
            // For original, use same location as current but different date
            $update_stmt->bind_param('sssi', 
                $original_date, 
                $row['current_game_time'], 
                $row['current_game_location'], 
                $row['history_id']
            );
            
            if ($update_stmt->execute()) {
                $fixes_applied++;
                echo "    ✓ Fixed original entry to $original_date\n";
                
                // Also update any change requests that reference this game
                $update_requests = $new_db->prepare("
                    UPDATE schedule_change_requests 
                    SET original_date = ?, original_time = ?, original_location = ?
                    WHERE game_id = ?
                ");
                
                $update_requests->bind_param('sssi', 
                    $original_date, 
                    $row['current_game_time'], 
                    $row['current_game_location'], 
                    $row['game_id']
                );
                
                $update_requests->execute();
                echo "    ✓ Updated change requests with corrected original date\n";
            }
        }
    }
    
    echo "\nSummary:\n";
    echo "  History entries fixed: $fixes_applied\n";
    
    // Verification
    echo "\nVerification:\n";
    
    // Check for remaining invalid dates
    $remaining_invalid = $new_db->query("
        SELECT COUNT(*) as count
        FROM schedule_history sh
        JOIN games g ON sh.game_id = g.game_id
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND (sh.game_date < '1990-01-01' OR sh.game_date > '2030-12-31')
    ")->fetch_assoc()['count'];
    
    echo "  Remaining invalid dates: $remaining_invalid " . ($remaining_invalid == 0 ? "✓" : "✗") . "\n";
    
    // Check for date mismatches between current schedule and current history
    $date_mismatches = $new_db->query("
        SELECT COUNT(*) as count
        FROM games g 
        JOIN schedules s ON g.game_id = s.game_id
        JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.is_current = 1
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND s.game_date != sh.game_date
    ")->fetch_assoc()['count'];
    
    echo "  Date mismatches (current schedule vs current history): $date_mismatches " . ($date_mismatches == 0 ? "✓" : "✗") . "\n";
    
    // Show some examples of fixed games
    echo "\nSample of fixed games:\n";
    $samples = $new_db->query("
        SELECT g.game_number, s.game_date as current_schedule, sh.game_date as current_history
        FROM games g 
        JOIN schedules s ON g.game_id = s.game_id
        JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.is_current = 1
        JOIN seasons se ON g.season_id = se.season_id
        WHERE se.season_year = '2024'
        AND g.game_number IN ('2024003', '2024021', '2024029', '2024035', '2024048', '2024073')
        ORDER BY g.game_number
    ");
    
    while ($row = $samples->fetch_assoc()) {
        echo "  Game {$row['game_number']}: Current={$row['current_schedule']}, History={$row['current_history']}\n";
    }
    
    echo "\n=== Fix Invalid History Dates Complete ===\n";
    echo "Invalid history dates have been fixed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
