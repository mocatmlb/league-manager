<?php
/**
 * District 8 Travel League - Fix Schedule History
 * Fixes missing schedule data and schedule change request history
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$temp_db_name = 'temp_d8tl_schedule_fix';

echo "=== District 8 Travel League Schedule History Fix ===\n";
echo "Fixing missing schedule data and change request history...\n\n";

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
    echo "\nStarting schedule history fix...\n\n";
    
    // 1. Create missing schedules from old system
    echo "1. Creating missing schedules...\n";
    createMissingSchedules($mysql, $new_db);
    
    // 2. Fix schedule change requests with proper original data
    echo "2. Fixing schedule change requests...\n";
    fixScheduleChangeRequests($mysql, $new_db);
    
    // 3. Create schedule history entries
    echo "3. Creating schedule history entries...\n";
    createScheduleHistory($new_db);
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Schedule History Fix Complete ===\n";
    echo "Schedule data has been successfully fixed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Create missing schedules from old system data
 */
function createMissingSchedules($old_db, $new_db) {
    // Get schedule data from old system for games that exist in new system
    $query = "SELECT DISTINCT 
                f2.game_no, f2.sched_date, f2.sched_time, f2.sched_location, 
                f2.comment, f2.approved
              FROM d8ll_form_2 f2
              WHERE f2.comment = 'INITIAL SCHEDULE' 
              AND f2.sched_date IS NOT NULL 
              AND f2.sched_date != '0000-00-00'
              ORDER BY f2.game_no";
              
    $result = $old_db->query($query);
    
    $schedules_created = 0;
    $skipped_schedules = [];
    
    echo "  Found " . $result->num_rows . " initial schedules in old system\n";
    
    while ($row = $result->fetch_assoc()) {
        // Find the corresponding game in the new system
        $game_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $game_stmt->bind_param('s', $row['game_no']);
        $game_stmt->execute();
        $game_result = $game_stmt->get_result()->fetch_assoc();
        
        if (!$game_result) {
            $skipped_schedules[] = "Game {$row['game_no']}: Not found in new system";
            continue;
        }
        
        $game_id = $game_result['game_id'];
        
        // Check if schedule already exists
        $check_stmt = $new_db->prepare("SELECT schedule_id FROM schedules WHERE game_id = ?");
        $check_stmt->bind_param('i', $game_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            continue; // Skip if schedule already exists
        }
        
        // Create schedule
        $insert_stmt = $new_db->prepare("
            INSERT INTO schedules (game_id, game_date, game_time, location)
            VALUES (?, ?, ?, ?)
        ");
        
        $game_date = date('Y-m-d', strtotime($row['sched_date']));
        $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : null;
        
        $insert_stmt->bind_param('isss', $game_id, $game_date, $game_time, $row['sched_location']);
        
        if ($insert_stmt->execute()) {
            $schedules_created++;
            echo "  ✓ Created schedule for game {$row['game_no']}: {$game_date} {$game_time} at {$row['sched_location']}\n";
        }
    }
    
    echo "\n  Schedules created: $schedules_created\n";
    
    if (!empty($skipped_schedules)) {
        echo "  Skipped schedules (" . count($skipped_schedules) . "):\n";
        foreach (array_slice($skipped_schedules, 0, 3) as $skip) {
            echo "    ! $skip\n";
        }
        if (count($skipped_schedules) > 3) {
            echo "    ... and " . (count($skipped_schedules) - 3) . " more\n";
        }
    }
    echo "\n";
}

/**
 * Fix schedule change requests with proper original data
 */
function fixScheduleChangeRequests($old_db, $new_db) {
    // Get all schedule change requests that need fixing
    $requests_result = $new_db->query("
        SELECT scr.request_id, scr.game_id, g.game_number, s.game_date, s.game_time, s.location
        FROM schedule_change_requests scr
        JOIN games g ON scr.game_id = g.game_id
        LEFT JOIN schedules s ON g.game_id = s.game_id
        WHERE scr.original_date IS NULL
        ORDER BY scr.request_id
    ");
    
    $requests_fixed = 0;
    
    echo "  Found " . $requests_result->num_rows . " change requests to fix\n";
    
    while ($request = $requests_result->fetch_assoc()) {
        // Update the request with original schedule data
        $update_stmt = $new_db->prepare("
            UPDATE schedule_change_requests 
            SET original_date = ?, original_time = ?, original_location = ?
            WHERE request_id = ?
        ");
        
        $update_stmt->bind_param('sssi', 
            $request['game_date'], 
            $request['game_time'], 
            $request['location'], 
            $request['request_id']
        );
        
        if ($update_stmt->execute()) {
            $requests_fixed++;
            echo "  ✓ Fixed request #{$request['request_id']} for game {$request['game_number']}\n";
        }
    }
    
    echo "\n  Schedule change requests fixed: $requests_fixed\n\n";
}

/**
 * Create schedule history entries for proper tracking
 */
function createScheduleHistory($new_db) {
    // Get all games with schedules
    $games_result = $new_db->query("
        SELECT g.game_id, g.game_number, s.schedule_id, s.game_date, s.game_time, s.location
        FROM games g
        JOIN schedules s ON g.game_id = s.game_id
        WHERE g.season_id IN (SELECT season_id FROM seasons WHERE season_year = 2024)
        ORDER BY g.game_id
    ");
    
    $history_created = 0;
    
    echo "  Found " . $games_result->num_rows . " games with schedules\n";
    
    while ($game = $games_result->fetch_assoc()) {
        // Check if history entry already exists
        $check_stmt = $new_db->prepare("
            SELECT history_id FROM schedule_history WHERE game_id = ? AND version_number = 1
        ");
        $check_stmt->bind_param('i', $game['game_id']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            continue; // Skip if history already exists
        }
        
        // Create initial history entry
        $insert_stmt = $new_db->prepare("
            INSERT INTO schedule_history (
                game_id, version_number, schedule_type, game_date, game_time, 
                location, is_current, notes
            ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Initial schedule from migration')
        ");
        
        $insert_stmt->bind_param('isss', 
            $game['game_id'], 
            $game['game_date'], 
            $game['game_time'], 
            $game['location']
        );
        
        if ($insert_stmt->execute()) {
            $history_created++;
            
            // Now create history entries for any schedule changes
            createChangeHistoryEntries($new_db, $game['game_id']);
        }
    }
    
    echo "\n  Schedule history entries created: $history_created\n\n";
}

/**
 * Create history entries for schedule changes
 */
function createChangeHistoryEntries($new_db, $game_id) {
    // Get all approved schedule changes for this game
    $changes_result = $new_db->query("
        SELECT request_id, request_type, requested_date, requested_time, requested_location, 
               reason, reviewed_at, created_date
        FROM schedule_change_requests 
        WHERE game_id = $game_id AND request_status = 'Approved'
        ORDER BY created_date ASC
    ");
    
    $version = 2; // Start from version 2 (version 1 is original)
    
    while ($change = $changes_result->fetch_assoc()) {
        // Create history entry for this change
        $insert_stmt = $new_db->prepare("
            INSERT INTO schedule_history (
                game_id, version_number, schedule_type, game_date, game_time, 
                location, is_current, change_request_id, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        
        $schedule_type = ($change['request_type'] == 'Cancel') ? 'Cancelled' : 'Rescheduled';
        $notes = $change['reason'] ?: 'Schedule change';
        $created_at = $change['reviewed_at'] ?: $change['created_date'];
        
        $insert_stmt->bind_param('iissssis', 
            $game_id, 
            $version, 
            $schedule_type,
            $change['requested_date'], 
            $change['requested_time'], 
            $change['requested_location'],
            $change['request_id'],
            $notes,
            $created_at
        );
        
        $insert_stmt->execute();
        $version++;
    }
}

