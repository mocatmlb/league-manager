<?php
/**
 * District 8 Travel League - Fix Schedule History Properly
 * Correctly rebuilds schedule history based on old system architecture
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$temp_db_name = 'temp_d8tl_fix_proper';

echo "=== District 8 Travel League Proper Schedule History Fix ===\n";
echo "Rebuilding schedule history based on correct old system understanding...\n\n";

try {
    // Connect to MySQL server
    $mysql = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysql->connect_error) {
        throw new Exception("Failed to connect to MySQL: " . $mysql->connect_error);
    }
    
    echo "✓ Connected to MySQL server\n";
    
    // Create temporary database
    echo "Creating temporary database...\n";
    $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    $mysql->query("CREATE DATABASE $temp_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql->select_db($temp_db_name);
    
    echo "✓ Created temporary database: $temp_db_name\n";
    
    // Import SQL dump
    echo "Importing SQL dump...\n";
    $password_part = DB_PASS ? " -p" . DB_PASS : "";
    $command = "mysql -h " . DB_HOST . " -u " . DB_USER . $password_part . " $temp_db_name < " . escapeshellarg($sql_dump_file);
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);
    
    if ($return_code !== 0) {
        throw new Exception("Failed to import SQL dump. Return code: $return_code");
    }
    
    echo "✓ SQL dump imported successfully\n\n";
    
    // Connect to new database
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to new database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to new database\n";
    
    // Start fixing process
    echo "\nStarting proper schedule history rebuild...\n\n";
    
    // 1. Clear existing incorrect data
    echo "1. Clearing existing incorrect schedule history...\n";
    clearIncorrectData($new_db);
    
    // 2. Rebuild schedules and history from old system properly
    echo "2. Rebuilding schedules and history from old system...\n";
    rebuildSchedulesAndHistory($mysql, $new_db);
    
    // 3. Fix schedule change requests with proper original data
    echo "3. Fixing schedule change requests with proper original data...\n";
    fixScheduleChangeRequestsProperly($new_db);
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Proper Schedule History Fix Complete ===\n";
    echo "Schedule history has been properly rebuilt!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Clear existing incorrect data
 */
function clearIncorrectData($new_db) {
    // Clear schedule history for 2024 games
    $new_db->query("
        DELETE sh FROM schedule_history sh 
        JOIN games g ON sh.game_id = g.game_id 
        JOIN seasons s ON g.season_id = s.season_id 
        WHERE s.season_year = 2024
    ");
    
    echo "  ✓ Cleared existing schedule history\n";
}

/**
 * Rebuild schedules and history from old system properly
 */
function rebuildSchedulesAndHistory($old_db, $new_db) {
    // Get all schedule entries for 2024 games, ordered by submission date
    $query = "SELECT 
                f2.game_no, f2.sched_date, f2.sched_time, f2.sched_location, 
                f2.comment, f2.approved, f2.submission_date, f2.submitter_name, f2.submitter_email
              FROM d8ll_form_2 f2
              WHERE f2.season = '2024' 
              AND f2.sched_date IS NOT NULL 
              AND f2.sched_date != '0000-00-00'
              ORDER BY f2.game_no, f2.submission_date ASC";
              
    $result = $old_db->query($query);
    
    $games_processed = [];
    $schedules_updated = 0;
    $history_created = 0;
    
    echo "  Found " . $result->num_rows . " schedule entries in old system\n";
    
    while ($row = $result->fetch_assoc()) {
        // Find the corresponding game in the new system
        $game_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $game_stmt->bind_param('s', $row['game_no']);
        $game_stmt->execute();
        $game_result = $game_stmt->get_result()->fetch_assoc();
        
        if (!$game_result) {
            continue; // Skip if game not found
        }
        
        $game_id = $game_result['game_id'];
        
        // Determine if this is the original schedule or a change
        $is_original = ($row['comment'] == 'ORIGINAL SCHEDULE');
        
        if ($is_original) {
            // This is the original schedule - update the schedules table
            $update_stmt = $new_db->prepare("
                UPDATE schedules 
                SET game_date = ?, game_time = ?, location = ?
                WHERE game_id = ?
            ");
            
            $game_date = date('Y-m-d', strtotime($row['sched_date']));
            $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : '18:00:00';
            
            $update_stmt->bind_param('sssi', $game_date, $game_time, $row['sched_location'], $game_id);
            $update_stmt->execute();
            
            // Create the original history entry (version 1)
            $insert_history_stmt = $new_db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, is_current, notes, created_at
                ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Original schedule', ?)
            ");
            
            $created_at = ($row['submission_date'] && $row['submission_date'] != '0000-00-00 00:00:00') ? 
                         $row['submission_date'] : date('Y-m-d H:i:s');
            $insert_history_stmt->bind_param('issss', $game_id, $game_date, $game_time, $row['sched_location'], $created_at);
            $insert_history_stmt->execute();
            
            $games_processed[$row['game_no']] = [
                'game_id' => $game_id,
                'version' => 1,
                'original_date' => $game_date,
                'original_time' => $game_time,
                'original_location' => $row['sched_location']
            ];
            
            $schedules_updated++;
            $history_created++;
            
            echo "  ✓ Set original schedule for game {$row['game_no']}: {$game_date} {$game_time} at {$row['sched_location']}\n";
            
        } else {
            // This is a schedule change
            if (!isset($games_processed[$row['game_no']])) {
                continue; // Skip if we haven't processed the original yet
            }
            
            $game_info = $games_processed[$row['game_no']];
            $version = $game_info['version'] + 1;
            
            // Create history entry for this change
            $insert_history_stmt = $new_db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, is_current, notes, created_at
                ) VALUES (?, ?, 'Changed', ?, ?, ?, 0, ?, ?)
            ");
            
            $game_date = date('Y-m-d', strtotime($row['sched_date']));
            $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : '18:00:00';
            $notes = $row['comment'] ?: 'Schedule change';
            $created_at = ($row['submission_date'] && $row['submission_date'] != '0000-00-00 00:00:00') ? 
                         $row['submission_date'] : date('Y-m-d H:i:s');
            
            $insert_history_stmt->bind_param('iisssss', 
                $game_id, $version, $game_date, $game_time, $row['sched_location'], $notes, $created_at
            );
            $insert_history_stmt->execute();
            
            // Update current schedule if this change was approved
            if ($row['approved'] == '1') {
                $update_current_stmt = $new_db->prepare("
                    UPDATE schedules 
                    SET game_date = ?, game_time = ?, location = ?
                    WHERE game_id = ?
                ");
                $update_current_stmt->bind_param('sssi', $game_date, $game_time, $row['sched_location'], $game_id);
                $update_current_stmt->execute();
                
                // Update is_current flags
                $new_db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = $game_id");
                $new_db->query("UPDATE schedule_history SET is_current = 1 WHERE game_id = $game_id AND version_number = $version");
            }
            
            $games_processed[$row['game_no']]['version'] = $version;
            $history_created++;
            
            echo "  ✓ Added change v{$version} for game {$row['game_no']}: {$game_date} {$game_time} at {$row['sched_location']} ({$notes})\n";
        }
    }
    
    echo "\n  Schedules updated: $schedules_updated\n";
    echo "  History entries created: $history_created\n\n";
}

/**
 * Fix schedule change requests with proper original data
 */
function fixScheduleChangeRequestsProperly($new_db) {
    // Get all schedule change requests for 2024 games
    $requests_result = $new_db->query("
        SELECT scr.request_id, scr.game_id, g.game_number, 
               sh_orig.game_date as original_date, 
               sh_orig.game_time as original_time, 
               sh_orig.location as original_location
        FROM schedule_change_requests scr
        JOIN games g ON scr.game_id = g.game_id
        JOIN seasons s ON g.season_id = s.season_id
        LEFT JOIN schedule_history sh_orig ON g.game_id = sh_orig.game_id AND sh_orig.version_number = 1
        WHERE s.season_year = 2024
        ORDER BY scr.request_id
    ");
    
    $requests_fixed = 0;
    
    echo "  Found " . $requests_result->num_rows . " change requests to fix\n";
    
    while ($request = $requests_result->fetch_assoc()) {
        // Update the request with proper original schedule data
        $update_stmt = $new_db->prepare("
            UPDATE schedule_change_requests 
            SET original_date = ?, original_time = ?, original_location = ?
            WHERE request_id = ?
        ");
        
        $update_stmt->bind_param('sssi', 
            $request['original_date'], 
            $request['original_time'], 
            $request['original_location'], 
            $request['request_id']
        );
        
        if ($update_stmt->execute()) {
            $requests_fixed++;
            echo "  ✓ Fixed request #{$request['request_id']} for game {$request['game_number']} with original: {$request['original_date']} {$request['original_time']} at {$request['original_location']}\n";
        }
    }
    
    echo "\n  Schedule change requests fixed: $requests_fixed\n\n";
}
