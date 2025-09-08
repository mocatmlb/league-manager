<?php
/**
 * District 8 Travel League - Fix 2025 Schedule Duplicates
 * This script removes duplicate schedule history entries for 2025 games
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix 2025 Schedule Duplicates ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Find all 2025 games
$games_query = "SELECT g.game_id, g.game_number 
                FROM games g 
                WHERE g.game_number LIKE '2025%'
                ORDER BY g.game_number";
$games_stmt = $db->query($games_query);

$fixed_count = 0;
$games_processed = 0;

while ($game = $games_stmt->fetch(PDO::FETCH_ASSOC)) {
    $games_processed++;
    $game_id = $game['game_id'];
    $game_number = $game['game_number'];
    
    // Get all history entries for this game, ordered by version number
    $history_query = "SELECT sh.history_id, sh.version_number, sh.game_date, sh.game_time, sh.location, sh.notes, sh.created_at
                      FROM schedule_history sh
                      WHERE sh.game_id = :game_id
                      ORDER BY sh.version_number, sh.history_id";
    $history_stmt = $db->prepare($history_query);
    $history_stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
    $history_stmt->execute();
    
    // Group history entries by version number
    $history_by_version = [];
    while ($history = $history_stmt->fetch(PDO::FETCH_ASSOC)) {
        $version = $history['version_number'];
        if (!isset($history_by_version[$version])) {
            $history_by_version[$version] = [];
        }
        $history_by_version[$version][] = $history;
    }
    
    $duplicates_found = false;
    
    // Check each version for duplicates
    foreach ($history_by_version as $version => $entries) {
        if (count($entries) > 1) {
            $duplicates_found = true;
            
            // Keep the entry with the lowest history_id (earliest created)
            // and delete the others
            usort($entries, function($a, $b) {
                return $a['history_id'] <=> $b['history_id'];
            });
            
            $keep_entry = array_shift($entries); // Keep the first entry
            
            foreach ($entries as $entry) {
                $delete_query = "DELETE FROM schedule_history WHERE history_id = :history_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(':history_id', $entry['history_id'], PDO::PARAM_INT);
                $delete_stmt->execute();
                
                echo "  Deleted duplicate history entry ID {$entry['history_id']} for game {$game_number} version {$version}\n";
                $fixed_count++;
            }
        }
    }
    
    if ($duplicates_found) {
        // Update is_current flag for the latest version
        $latest_version_query = "SELECT MAX(version_number) as max_version FROM schedule_history WHERE game_id = :game_id";
        $latest_version_stmt = $db->prepare($latest_version_query);
        $latest_version_stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $latest_version_stmt->execute();
        $latest_version_result = $latest_version_stmt->fetch(PDO::FETCH_ASSOC);
        $latest_version = $latest_version_result['max_version'];
        
        $update_current_query = "UPDATE schedule_history 
                                SET is_current = CASE 
                                    WHEN version_number = :version THEN 1 
                                    ELSE 0 
                                END 
                                WHERE game_id = :game_id";
        $update_current_stmt = $db->prepare($update_current_query);
        $update_current_stmt->bindParam(':version', $latest_version, PDO::PARAM_INT);
        $update_current_stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $update_current_stmt->execute();
        
        echo "✓ Updated is_current flag for game {$game_number}\n";
    }
}

echo "\n=== Fix Complete ===\n";
echo "Games processed: $games_processed\n";
echo "Duplicate history entries removed: $fixed_count\n";
