<?php
/**
 * District 8 Travel League - Fix Game 2024065 Dates
 * Corrects the original date for game 2024065 from 7/16 to 7/15
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix Game 2024065 Dates ===\n";
echo "Correcting original date from 7/16 to 7/15...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n";
    
    // Get the game ID for 2024065
    $game_result = $new_db->query("SELECT game_id FROM games WHERE game_number = '2024065'");
    $game = $game_result->fetch_assoc();
    
    if (!$game) {
        throw new Exception("Game 2024065 not found");
    }
    
    $game_id = $game['game_id'];
    echo "✓ Found game 2024065 (ID: $game_id)\n";
    
    echo "\nCurrent state:\n";
    
    // Show current schedule history
    $current_history = $new_db->query("
        SELECT version_number, schedule_type, game_date, game_time, location, is_current
        FROM schedule_history 
        WHERE game_id = $game_id 
        ORDER BY version_number
    ");
    
    while ($row = $current_history->fetch_assoc()) {
        $current_flag = $row['is_current'] ? ' (CURRENT)' : '';
        echo "  v{$row['version_number']} ({$row['schedule_type']}): {$row['game_date']} {$row['game_time']} at {$row['location']}{$current_flag}\n";
    }
    
    // Show current schedule
    $current_schedule = $new_db->query("
        SELECT game_date, game_time, location 
        FROM schedules 
        WHERE game_id = $game_id
    ")->fetch_assoc();
    
    echo "  Current schedule: {$current_schedule['game_date']} {$current_schedule['game_time']} at {$current_schedule['location']}\n";
    
    // Show change requests
    $change_requests = $new_db->query("
        SELECT original_date, original_time, original_location, requested_date, requested_time, requested_location, reason
        FROM schedule_change_requests 
        WHERE game_id = $game_id
    ");
    
    echo "  Change requests:\n";
    while ($row = $change_requests->fetch_assoc()) {
        echo "    FROM: {$row['original_date']} {$row['original_time']} at {$row['original_location']}\n";
        echo "    TO:   {$row['requested_date']} {$row['requested_time']} at {$row['requested_location']} ({$row['reason']})\n";
    }
    
    echo "\nApplying fixes:\n";
    
    // 1. Update the original schedule history (v1) to show 7/15
    echo "1. Updating original schedule history (v1) from 7/16 to 7/15...\n";
    $update_original = $new_db->prepare("
        UPDATE schedule_history 
        SET game_date = '2024-07-15'
        WHERE game_id = ? AND version_number = 1
    ");
    $update_original->bind_param('i', $game_id);
    
    if ($update_original->execute()) {
        echo "   ✓ Updated original history to 7/15\n";
    } else {
        throw new Exception("Failed to update original history");
    }
    
    // 2. Update the change request original date to show 7/15
    echo "2. Updating change request original date from 7/16 to 7/15...\n";
    $update_request = $new_db->prepare("
        UPDATE schedule_change_requests 
        SET original_date = '2024-07-15'
        WHERE game_id = ?
    ");
    $update_request->bind_param('i', $game_id);
    
    if ($update_request->execute()) {
        echo "   ✓ Updated change request original date to 7/15\n";
    } else {
        throw new Exception("Failed to update change request");
    }
    
    echo "\nVerification - New state:\n";
    
    // Show updated schedule history
    $updated_history = $new_db->query("
        SELECT version_number, schedule_type, game_date, game_time, location, is_current
        FROM schedule_history 
        WHERE game_id = $game_id 
        ORDER BY version_number
    ");
    
    while ($row = $updated_history->fetch_assoc()) {
        $current_flag = $row['is_current'] ? ' (CURRENT)' : '';
        echo "  v{$row['version_number']} ({$row['schedule_type']}): {$row['game_date']} {$row['game_time']} at {$row['location']}{$current_flag}\n";
    }
    
    // Show updated change requests
    $updated_requests = $new_db->query("
        SELECT original_date, original_time, original_location, requested_date, requested_time, requested_location, reason
        FROM schedule_change_requests 
        WHERE game_id = $game_id
    ");
    
    echo "  Updated change requests:\n";
    while ($row = $updated_requests->fetch_assoc()) {
        echo "    FROM: {$row['original_date']} {$row['original_time']} at {$row['original_location']}\n";
        echo "    TO:   {$row['requested_date']} {$row['requested_time']} at {$row['requested_location']} ({$row['reason']})\n";
    }
    
    echo "\n=== Fix Game 2024065 Dates Complete ===\n";
    echo "Game 2024065 now correctly shows:\n";
    echo "  v1 (Original): 7/15 at EAGLE HILL MS\n";
    echo "  v2 (Changed):  7/16 at WELLWOOD MS (CURRENT)\n";
    echo "Change request now correctly shows FROM 7/15 TO 7/16\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

