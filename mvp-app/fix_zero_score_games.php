<?php
/**
 * District 8 Travel League - Fix Zero Score Games
 * 
 * This script updates games with 0-0 scores from "Completed" to "Scheduled" status
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix Zero Score Games ===\n\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Get count of games with 0-0 scores marked as Completed
$zeroScoreCount = $db->query("
    SELECT COUNT(*) as count 
    FROM games 
    WHERE game_status = 'Completed' 
    AND home_score = 0 
    AND away_score = 0
")->fetch(PDO::FETCH_ASSOC)['count'];

echo "Found {$zeroScoreCount} games with 0-0 scores marked as Completed.\n\n";

// Get list of games with 0-0 scores
$zeroScoreGames = $db->query("
    SELECT game_id, game_number, game_status, home_score, away_score 
    FROM games 
    WHERE game_status = 'Completed' 
    AND home_score = 0 
    AND away_score = 0
    ORDER BY game_number
")->fetchAll(PDO::FETCH_ASSOC);

// Display list of games that will be updated
echo "Games to be updated:\n";
echo str_pad("Game ID", 10) . str_pad("Game #", 15) . str_pad("Current Status", 15) . "Scores\n";
echo str_repeat("-", 50) . "\n";

foreach ($zeroScoreGames as $game) {
    echo str_pad($game['game_id'], 10) . 
         str_pad($game['game_number'], 15) . 
         str_pad($game['game_status'], 15) . 
         "{$game['away_score']}-{$game['home_score']}\n";
}

echo "\n";

// Confirm update
echo "Do you want to update these games to 'Scheduled' status? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "Update cancelled.\n";
    exit;
}

// Begin transaction
$db->beginTransaction();

try {
    // Update games with 0-0 scores to Scheduled status
    $updateCount = $db->exec("
        UPDATE games 
        SET game_status = 'Scheduled', 
            home_score = NULL, 
            away_score = NULL, 
            modified_date = NOW() 
        WHERE game_status = 'Completed' 
        AND home_score = 0 
        AND away_score = 0
    ");
    
    // Commit transaction
    $db->commit();
    
    echo "Successfully updated {$updateCount} games from 'Completed' to 'Scheduled' status.\n";
    echo "Scores have been set to NULL.\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify the update
$verifyCount = $db->query("
    SELECT COUNT(*) as count 
    FROM games 
    WHERE game_status = 'Completed' 
    AND home_score = 0 
    AND away_score = 0
")->fetch(PDO::FETCH_ASSOC)['count'];

echo "\nVerification: {$verifyCount} games with 0-0 scores remain marked as 'Completed'.\n";

if ($verifyCount == 0) {
    echo "All games with 0-0 scores have been successfully updated!\n";
} else {
    echo "Warning: Some games with 0-0 scores were not updated. Please check the database.\n";
}

echo "\n=== Fix Complete ===\n";
