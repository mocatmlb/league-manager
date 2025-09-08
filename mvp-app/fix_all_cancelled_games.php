<?php
/**
 * District 8 Travel League - Fix All Cancelled Games
 * This script fixes all games that should be marked as cancelled based on the old system data
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix All Cancelled Games ===\n\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// List of games that should be cancelled based on old system data
// These games had approved = '3' and non-original, non-duplicate comments in the old system
$games_to_fix = [
    // 2023 Season
    '2023001' => ['reason' => 'Modified season still ongoing'],
    '2023002' => ['reason' => 'Modified season still ongoing'],
    '2023004' => ['reason' => 'Air quality'],
    '2023005' => ['reason' => 'FM not enough players'],
    '2023007' => ['reason' => 'Cannot field a team'],
    '2023034' => ['reason' => 'Weather'],
    '2023037' => ['reason' => 'Players shortage / Weather'],
    '2023039' => ['reason' => 'Field conditions/short on players'],
    '2023041' => ['reason' => 'Air Quality'],
    '2023047' => ['reason' => 'Cannot field a team'],
    '2023061' => ['reason' => 'Cannot field a team'],
    '2023074' => ['reason' => 'Cannot field a team'],
    '2023077' => ['reason' => 'Cannot field a team'],
    
    // 2024 Season - Already correctly marked as cancelled
    // '2024003' => ['reason' => 'Schedule conflict'],
    // '2024021' => ['reason' => 'No players on either team'],
    // '2024029' => ['reason' => 'Heat'],
    // '2024035' => ['reason' => 'Heat'],
    // '2024048' => ['reason' => 'Lack of players'],
    
    // 2025 Season - Some already fixed, others need fixing
    // '2025011' => ['reason' => 'CANCEL'], // Already fixed
    // '2025015' => ['reason' => 'No availability/WX'], // Already fixed
    // '2025035' => ['reason' => 'Cancel/Weather'], // Already fixed
    // '2025041' => ['reason' => 'CANCEL'], // Already fixed
    // '2025053' => ['reason' => 'Must have been added by mistake'], // Already fixed
    // '2025102' => ['reason' => 'CANCEL'], // Already fixed
    '2025037' => ['reason' => 'Weather'],
    '2025055' => ['reason' => 'Weather'],
    '2025060' => ['reason' => 'Rain flooded the field'],
    '2025066' => ['reason' => 'No availability'],
    '2025075' => ['reason' => 'Conflict on 7-12, only able to reschedule 1 of the 2 games'],
    '2025090' => ['reason' => 'Oswego unable to get enough players'],
    '2025092' => ['reason' => 'Weather/heat'],
    '2025098' => ['reason' => 'Player availability'],
    '2025103' => ['reason' => 'Lack of players'],
    '2025125' => ['reason' => 'Juneteenth'],
    '2025126' => ['reason' => 'Conflict'],
    '2025128' => ['reason' => 'Time'],
    '2025134' => ['reason' => 'Unable to field a team']
];

$total_games = count($games_to_fix);
$fixed_count = 0;
$already_cancelled = 0;
$errors = 0;

echo "Found {$total_games} games to check and fix.\n\n";

foreach ($games_to_fix as $game_number => $details) {
    echo "Processing game {$game_number}...\n";
    
    // Get game info
    $game_query = "SELECT g.game_id, g.game_number, g.game_status FROM games g WHERE g.game_number = ?";
    $game_stmt = $db->prepare($game_query);
    $game_stmt->execute([$game_number]);
    $game = $game_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo "  ❌ Game {$game_number} not found in the database, skipping\n";
        $errors++;
        continue;
    }
    
    echo "  Game ID: {$game['game_id']}, Current status: {$game['game_status']}\n";
    
    // If already cancelled, skip
    if ($game['game_status'] === 'Cancelled') {
        echo "  ✓ Game already marked as Cancelled, skipping\n";
        $already_cancelled++;
        continue;
    }
    
    // Check for existing change requests
    $request_query = "SELECT scr.request_id, scr.request_type, scr.request_status, scr.reason 
                      FROM schedule_change_requests scr 
                      WHERE scr.game_id = ? 
                      ORDER BY scr.created_date DESC";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->execute([$game['game_id']]);
    $change_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $request_id = null;
    
    // Update existing request to be a cancellation if it exists
    if (!empty($change_requests)) {
        $request = $change_requests[0]; // Use the most recent request
        echo "  Found existing change request (ID: {$request['request_id']}, type: {$request['request_type']}, status: {$request['request_status']})\n";
        
        // Update request type to Cancel and status to Approved
        $update_request = $db->prepare("
            UPDATE schedule_change_requests 
            SET request_type = 'Cancel', 
                request_status = 'Approved', 
                reviewed_at = NOW(),
                review_notes = 'Corrected during migration verification'
            WHERE request_id = ?
        ");
        $update_request->execute([$request['request_id']]);
        echo "  ✓ Updated request type to Cancel and status to Approved\n";
        
        $request_id = $request['request_id'];
    } else {
        // Create a new cancellation request
        echo "  No existing request found, creating a new cancellation request\n";
        
        // Get original schedule
        $original_query = "SELECT sh.game_date, sh.game_time, sh.location 
                          FROM schedule_history sh 
                          WHERE sh.game_id = ? AND sh.version_number = 1";
        $original_stmt = $db->prepare($original_query);
        $original_stmt->execute([$game['game_id']]);
        $original = $original_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original) {
            echo "  ❌ No original schedule found for game {$game_number}, skipping\n";
            $errors++;
            continue;
        }
        
        // Create cancellation request
        $insert_request = $db->prepare("
            INSERT INTO schedule_change_requests (
                game_id, requested_by, request_type, 
                original_date, original_time, original_location,
                requested_date, requested_time, requested_location,
                reason, request_status, created_date, reviewed_at
            ) VALUES (?, 'ADMIN', 'Cancel', ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW(), NOW())
        ");
        
        $insert_request->execute([
            $game['game_id'],
            $original['game_date'], $original['game_time'], $original['location'],
            $original['game_date'], $original['game_time'], $original['location'],
            $details['reason']
        ]);
        
        $request_id = $db->lastInsertId();
        echo "  ✓ Created new cancellation request (ID: {$request_id})\n";
    }
    
    // Update game status to Cancelled
    $update_game = $db->prepare("UPDATE games SET game_status = 'Cancelled', modified_date = NOW() WHERE game_id = ?");
    $update_game->execute([$game['game_id']]);
    echo "  ✓ Updated game status to Cancelled\n";
    
    // Find the latest schedule history entry
    $history_query = "SELECT history_id, version_number, is_current 
                     FROM schedule_history 
                     WHERE game_id = ? 
                     ORDER BY version_number DESC 
                     LIMIT 1";
    $history_stmt = $db->prepare($history_query);
    $history_stmt->execute([$game['game_id']]);
    $latest_history = $history_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latest_history) {
        echo "  Latest history entry: ID: {$latest_history['history_id']}, version: {$latest_history['version_number']}, is_current: {$latest_history['is_current']}\n";
        
        // Create a new history entry for the cancellation if needed
        if ($latest_history['is_current'] == 1) {
            // Create a new history entry
            $new_version = $latest_history['version_number'] + 1;
            
            // Get the current schedule
            $current_query = "SELECT sh.game_date, sh.game_time, sh.location 
                             FROM schedule_history sh 
                             WHERE sh.history_id = ?";
            $current_stmt = $db->prepare($current_query);
            $current_stmt->execute([$latest_history['history_id']]);
            $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Insert new history entry
            $insert_history = $db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, notes, is_current, change_request_id
                ) VALUES (?, ?, 'Changed', ?, ?, ?, ?, 1, ?)
            ");
            
            $insert_history->execute([
                $game['game_id'],
                $new_version,
                $current['game_date'],
                $current['game_time'],
                $current['location'],
                $details['reason'],
                $request_id
            ]);
            
            // Update previous entry to not be current
            $update_previous = $db->prepare("UPDATE schedule_history SET is_current = 0 WHERE history_id = ?");
            $update_previous->execute([$latest_history['history_id']]);
            
            echo "  ✓ Created new history entry (version: {$new_version}) and linked to cancellation request\n";
        } else {
            // Find the current history entry
            $current_query = "SELECT history_id FROM schedule_history WHERE game_id = ? AND is_current = 1";
            $current_stmt = $db->prepare($current_query);
            $current_stmt->execute([$game['game_id']]);
            $current_history = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_history) {
                // Update the current history entry to link to the cancellation request
                $update_history = $db->prepare("UPDATE schedule_history SET change_request_id = ? WHERE history_id = ?");
                $update_history->execute([$request_id, $current_history['history_id']]);
                echo "  ✓ Updated current history entry to link to cancellation request\n";
            } else {
                // Make the latest history entry current and link to the cancellation request
                $update_history = $db->prepare("
                    UPDATE schedule_history 
                    SET is_current = 1, change_request_id = ? 
                    WHERE history_id = ?
                ");
                $update_history->execute([$request_id, $latest_history['history_id']]);
                echo "  ✓ Updated latest history entry to be current and linked to cancellation request\n";
            }
        }
    } else {
        echo "  ❌ No history entries found for game {$game_number}, skipping history update\n";
    }
    
    $fixed_count++;
    echo "  ✅ Successfully fixed game {$game_number}\n\n";
}

echo "=== Fix Complete ===\n";
echo "Total games processed: {$total_games}\n";
echo "Games already cancelled: {$already_cancelled}\n";
echo "Games fixed: {$fixed_count}\n";
echo "Errors: {$errors}\n\n";

// Verify all games are now marked as cancelled
echo "Verification Results:\n";
$game_numbers = implode("','", array_keys($games_to_fix));
$verify_query = "SELECT g.game_number, g.game_status FROM games g WHERE g.game_number IN ('{$game_numbers}') ORDER BY g.game_number";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->execute();
$results = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $result) {
    $status_indicator = ($result['game_status'] === 'Cancelled') ? '✓' : '❌';
    echo "  {$status_indicator} Game {$result['game_number']}: {$result['game_status']}\n";
}

echo "\nChange Request Verification:\n";
$verify_request_query = "
    SELECT g.game_number, scr.request_type, scr.request_status 
    FROM schedule_change_requests scr 
    JOIN games g ON scr.game_id = g.game_id 
    WHERE g.game_number IN ('{$game_numbers}')
    ORDER BY g.game_number, scr.created_date DESC
";
$verify_request_stmt = $db->prepare($verify_request_query);
$verify_request_stmt->execute();
$request_results = $verify_request_stmt->fetchAll(PDO::FETCH_ASSOC);

$current_game = null;
foreach ($request_results as $request) {
    if ($current_game !== $request['game_number']) {
        echo "\n  Game {$request['game_number']}:\n";
        $current_game = $request['game_number'];
    }
    $status_indicator = ($request['request_type'] === 'Cancel' && $request['request_status'] === 'Approved') ? '✓' : ' ';
    echo "    {$status_indicator} {$request['request_type']} - {$request['request_status']}\n";
}
