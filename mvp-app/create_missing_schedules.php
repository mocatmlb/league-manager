<?php
/**
 * District 8 Travel League - Create Missing Schedules
 * Creates schedules for games that don't have them using old system data
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$temp_db_name = 'temp_d8tl_create_schedules';

echo "=== District 8 Travel League Create Missing Schedules ===\n";
echo "Creating schedules for games without schedule data...\n\n";

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
    
    // Start creating schedules
    echo "\nCreating missing schedules...\n\n";
    
    createSchedulesFromOldData($mysql, $new_db);
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Create Missing Schedules Complete ===\n";
    echo "Missing schedules have been created!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Create schedules from old system data
 */
function createSchedulesFromOldData($old_db, $new_db) {
    // Get games that don't have schedules
    $games_without_schedules = $new_db->query("
        SELECT g.game_id, g.game_number, g.season_id
        FROM games g
        LEFT JOIN schedules s ON g.game_id = s.game_id
        WHERE s.schedule_id IS NULL
        AND g.season_id IN (SELECT season_id FROM seasons WHERE season_year = 2024)
        ORDER BY g.game_number
    ");
    
    echo "  Found " . $games_without_schedules->num_rows . " games without schedules\n";
    
    $schedules_created = 0;
    $schedules_skipped = 0;
    
    while ($game = $games_without_schedules->fetch_assoc()) {
        // Try to find schedule data in old system
        $old_schedule_query = "
            SELECT sched_date, sched_time, sched_location, comment, approved
            FROM d8ll_form_2 
            WHERE game_no = ? 
            AND comment = 'INITIAL SCHEDULE'
            AND sched_date IS NOT NULL 
            AND sched_date != '0000-00-00'
            LIMIT 1
        ";
        
        $stmt = $old_db->prepare($old_schedule_query);
        $stmt->bind_param('s', $game['game_number']);
        $stmt->execute();
        $old_schedule = $stmt->get_result()->fetch_assoc();
        
        if ($old_schedule) {
            // Create the schedule
            $insert_stmt = $new_db->prepare("
                INSERT INTO schedules (game_id, game_date, game_time, location, created_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $game_date = date('Y-m-d', strtotime($old_schedule['sched_date']));
            $game_time = $old_schedule['sched_time'] ? date('H:i:s', strtotime($old_schedule['sched_time'])) : null;
            
            $insert_stmt->bind_param('isss', 
                $game['game_id'], 
                $game_date, 
                $game_time, 
                $old_schedule['sched_location']
            );
            
            if ($insert_stmt->execute()) {
                $schedules_created++;
                echo "  ✓ Created schedule for game {$game['game_number']}: {$game_date} {$game_time} at {$old_schedule['sched_location']}\n";
                
                // Create initial schedule history entry
                createInitialHistoryEntry($new_db, $game['game_id'], $game_date, $game_time, $old_schedule['sched_location']);
            }
        } else {
            // Try to find any schedule data (even non-initial)
            $any_schedule_query = "
                SELECT sched_date, sched_time, sched_location
                FROM d8ll_form_2 
                WHERE game_no = ? 
                AND sched_date IS NOT NULL 
                AND sched_date != '0000-00-00'
                ORDER BY submission_date ASC
                LIMIT 1
            ";
            
            $stmt2 = $old_db->prepare($any_schedule_query);
            $stmt2->bind_param('s', $game['game_number']);
            $stmt2->execute();
            $any_schedule = $stmt2->get_result()->fetch_assoc();
            
            if ($any_schedule) {
                // Create the schedule from any available data
                $insert_stmt = $new_db->prepare("
                    INSERT INTO schedules (game_id, game_date, game_time, location, created_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                $game_date = date('Y-m-d', strtotime($any_schedule['sched_date']));
                $game_time = $any_schedule['sched_time'] ? date('H:i:s', strtotime($any_schedule['sched_time'])) : null;
                
                $insert_stmt->bind_param('isss', 
                    $game['game_id'], 
                    $game_date, 
                    $game_time, 
                    $any_schedule['sched_location']
                );
                
                if ($insert_stmt->execute()) {
                    $schedules_created++;
                    echo "  ✓ Created schedule for game {$game['game_number']}: {$game_date} {$game_time} at {$any_schedule['sched_location']} (from any data)\n";
                    
                    // Create initial schedule history entry
                    createInitialHistoryEntry($new_db, $game['game_id'], $game_date, $game_time, $any_schedule['sched_location']);
                }
            } else {
                $schedules_skipped++;
                echo "  ! No schedule data found for game {$game['game_number']}\n";
            }
        }
    }
    
    echo "\n  Schedules created: $schedules_created\n";
    echo "  Schedules skipped: $schedules_skipped\n\n";
}

/**
 * Create initial schedule history entry
 */
function createInitialHistoryEntry($new_db, $game_id, $game_date, $game_time, $location) {
    // Check if history entry already exists
    $check_stmt = $new_db->prepare("
        SELECT history_id FROM schedule_history WHERE game_id = ? AND version_number = 1
    ");
    $check_stmt->bind_param('i', $game_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        return; // Already exists
    }
    
    // Create initial history entry
    $insert_stmt = $new_db->prepare("
        INSERT INTO schedule_history (
            game_id, version_number, schedule_type, game_date, game_time, 
            location, is_current, notes, created_at
        ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Initial schedule from migration', NOW())
    ");
    
    $insert_stmt->bind_param('isss', $game_id, $game_date, $game_time, $location);
    $insert_stmt->execute();
}

