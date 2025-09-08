<?php
/**
 * District 8 Travel League - Test Cancelled Games Migration
 * This script tests the migration of cancelled games from the old system
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Test Cancelled Games Migration ===\n";

// Connect to MySQL server
$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($mysql->connect_error) {
    die("Failed to connect to MySQL: " . $mysql->connect_error);
}

// Temporarily disable strict date checking for migration
$mysql->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

echo "✓ Connected to MySQL server\n";

// Create temporary database
echo "Creating temporary database...\n";
$temp_db_name = 'temp_d8tl_migration';
$mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
$mysql->query("CREATE DATABASE $temp_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysql->select_db($temp_db_name);

echo "✓ Created temporary database: $temp_db_name\n";

// Import SQL dump
echo "Importing SQL dump...\n";
$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$password_part = DB_PASS ? " -p" . DB_PASS : "";
$command = "mysql -h " . DB_HOST . " -u " . DB_USER . $password_part . " $temp_db_name < " . escapeshellarg($sql_dump_file);
exec($command);

echo "✓ SQL dump imported successfully\n";

// Connect to old and new databases
$old_db = new mysqli(DB_HOST, DB_USER, DB_PASS, $temp_db_name);
$new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Temporarily disable strict date checking for migration in both connections
$old_db->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$new_db->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

if ($old_db->connect_error || $new_db->connect_error) {
    die("Database connection failed");
}

echo "✓ Connected to databases\n\n";

// Find cancelled games in the old system
echo "Finding cancelled games in the old system...\n";
$cancelled_query = "SELECT f2.game_no, f2.comment, f2.approved, f2.sched_date, f2.sched_time, f2.sched_location, f2.submitter_name 
                   FROM d8ll_form_2 f2 
                   WHERE (f2.comment LIKE '%cancel%' OR f2.comment LIKE '%CANCEL%') 
                   AND f2.approved = '3' 
                   LIMIT 5";
$cancelled_result = $old_db->query($cancelled_query);

$cancelled_games = [];
$processed_game_numbers = [];
while ($game = $cancelled_result->fetch_assoc()) {
    // Skip duplicate game numbers
    if (in_array($game['game_no'], $processed_game_numbers)) {
        echo "Skipping duplicate game number {$game['game_no']}\n";
        continue;
    }
    
    $cancelled_games[] = $game;
    $processed_game_numbers[] = $game['game_no'];
    echo "Found cancelled game {$game['game_no']}: {$game['comment']} (Approval: {$game['approved']})\n";
}

if (empty($cancelled_games)) {
    echo "No cancelled games found in the old system.\n";
    exit;
}

// Create a test table in the new database
echo "\nCreating test tables...\n";
$new_db->query("DROP TABLE IF EXISTS test_games");
$new_db->query("DROP TABLE IF EXISTS test_schedules");
$new_db->query("DROP TABLE IF EXISTS test_schedule_history");
$new_db->query("DROP TABLE IF EXISTS test_schedule_change_requests");

$new_db->query("CREATE TABLE test_games LIKE games");
$new_db->query("CREATE TABLE test_schedules LIKE schedules");
$new_db->query("CREATE TABLE test_schedule_history LIKE schedule_history");
$new_db->query("CREATE TABLE test_schedule_change_requests LIKE schedule_change_requests");

// Create some test games
echo "Creating test games...\n";
foreach ($cancelled_games as $game) {
    // Create a test game
    $game_number = $game['game_no'];
    $insert_game = $new_db->prepare("
        INSERT INTO test_games (game_number, season_id, division_id, home_team_id, away_team_id, game_status, created_date)
        VALUES (?, 1, 1, 1, 2, 'Active', NOW())
    ");
    $insert_game->bind_param('s', $game_number);
    $insert_game->execute();
    $game_id = $new_db->insert_id;
    
    // Create initial schedule
    $game_date = date('Y-m-d', strtotime($game['sched_date']));
    $game_time = $game['sched_time'] ? date('H:i:s', strtotime($game['sched_time'])) : '18:00:00';
    $insert_schedule = $new_db->prepare("
        INSERT INTO test_schedules (game_id, game_date, game_time, location, created_date)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $insert_schedule->bind_param('isss', $game_id, $game_date, $game_time, $game['sched_location']);
    $insert_schedule->execute();
    
    // Create initial history entry
    $insert_history = $new_db->prepare("
        INSERT INTO test_schedule_history (
            game_id, version_number, schedule_type, game_date, game_time, 
            location, is_current, notes, created_at
        ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Original Schedule', NOW())
    ");
    $insert_history->bind_param('isss', $game_id, $game_date, $game_time, $game['sched_location']);
    $insert_history->execute();
    
    echo "Created test game {$game_number} with ID {$game_id}\n";
    
    // Now process the cancellation
    $version = 2;
    $notes = $game['comment'] ?: 'Game cancelled';
    $is_cancellation = (stripos($notes, 'cancel') !== false);
    
    // Create history entry for cancellation
    $insert_history = $new_db->prepare("
        INSERT INTO test_schedule_history (
            game_id, version_number, schedule_type, game_date, game_time, 
            location, is_current, notes, created_at
        ) VALUES (?, ?, 'Changed', ?, ?, ?, 0, ?, NOW())
    ");
    $insert_history->bind_param('iissss', $game_id, $version, $game_date, $game_time, $game['sched_location'], $notes);
    $insert_history->execute();
    $history_id = $new_db->insert_id;
    
    // Map approval status from old system to new system
    $request_status = 'Pending';
    if ($game['approved'] == '1') {
        $request_status = 'Approved';
    } elseif ($game['approved'] == '2') {
        $request_status = 'Denied';
    } elseif ($game['approved'] == '3') {
        $request_status = 'Approved'; // Status 3 was used for approved cancellations
    }
    
    // Create change request
    $request_type = 'Cancel';
    $requested_by = $game['submitter_name'] ?: 'ADMIN';
    
    $insert_request = $new_db->prepare("
        INSERT INTO test_schedule_change_requests (
            game_id, requested_by, request_type, 
            original_date, original_time, original_location,
            requested_date, requested_time, requested_location,
            reason, request_status, created_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $insert_request->bind_param('issssssssss',
        $game_id, $requested_by, $request_type,
        $game_date, $game_time, $game['sched_location'],
        $game_date, $game_time, $game['sched_location'],
        $notes, $request_status
    );
    
    $insert_request->execute();
    $request_id = $new_db->insert_id;
    
    // Link request to history
    $update_history = $new_db->prepare("
        UPDATE test_schedule_history 
        SET change_request_id = ? 
        WHERE history_id = ?
    ");
    $update_history->bind_param('ii', $request_id, $history_id);
    $update_history->execute();
    
    // Update game status for cancellations with approval status 3
    if ($is_cancellation && ($game['approved'] == '1' || $game['approved'] == '3')) {
        $update_game = $new_db->prepare("
            UPDATE test_games 
            SET game_status = 'Cancelled'
            WHERE game_id = ?
        ");
        $update_game->bind_param('i', $game_id);
        $update_game->execute();
        
        // Update is_current flags
        $new_db->query("UPDATE test_schedule_history SET is_current = 0 WHERE game_id = $game_id");
        $new_db->query("UPDATE test_schedule_history SET is_current = 1 WHERE game_id = $game_id AND version_number = $version");
        
        echo "  ✓ Updated game {$game_number} status to Cancelled\n";
    }
}

// Check the results
echo "\nChecking results...\n";
$results = $new_db->query("
    SELECT g.game_number, g.game_status, scr.request_status, scr.request_type, sh.notes, sh.is_current
    FROM test_games g
    JOIN test_schedule_change_requests scr ON g.game_id = scr.game_id
    JOIN test_schedule_history sh ON g.game_id = sh.game_id AND sh.version_number = 2
");

while ($row = $results->fetch_assoc()) {
    echo "Game {$row['game_number']}:\n";
    echo "  - Game Status: {$row['game_status']}\n";
    echo "  - Request Status: {$row['request_status']}\n";
    echo "  - Request Type: {$row['request_type']}\n";
    echo "  - Notes: {$row['notes']}\n";
    echo "  - History is Current: " . ($row['is_current'] ? 'Yes' : 'No') . "\n\n";
}

// Clean up
echo "Cleaning up...\n";
$new_db->query("DROP TABLE IF EXISTS test_games");
$new_db->query("DROP TABLE IF EXISTS test_schedules");
$new_db->query("DROP TABLE IF EXISTS test_schedule_history");
$new_db->query("DROP TABLE IF EXISTS test_schedule_change_requests");
$mysql->query("DROP DATABASE IF EXISTS $temp_db_name");

echo "\n=== Test Complete ===\n";
