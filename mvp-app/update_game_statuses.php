<?php
/**
 * District 8 Travel League - Update Game Statuses
 * 
 * This script updates game statuses according to the new status system:
 * - Created: Games without date, time, or location
 * - Scheduled: Games with schedule and no pending changes
 * - Pending Change: Games with pending change requests
 * - Completed: Games with scores
 * - Cancelled: Games that were cancelled
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Update Game Statuses ===\n\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// First, update the schema to allow NULL values temporarily
echo "Modifying game_status column to allow NULL values...\n";
$db->exec("ALTER TABLE games MODIFY COLUMN game_status ENUM('Active', 'Completed', 'Cancelled', 'Postponed') NULL");
echo "Done.\n\n";

// Count games by current status
$currentStats = $db->query("SELECT game_status, COUNT(*) as count FROM games GROUP BY game_status")->fetchAll(PDO::FETCH_ASSOC);
echo "Current game status distribution:\n";
foreach ($currentStats as $stat) {
    echo "- {$stat['game_status']}: {$stat['count']} games\n";
}
echo "\n";

// 1. Set all games to NULL status temporarily
echo "Setting all games to NULL status temporarily...\n";
$db->exec("UPDATE games SET game_status = NULL");
echo "Done.\n\n";

// 2. Update the schema to include new statuses
echo "Updating schema with new game statuses...\n";
$db->exec("ALTER TABLE games MODIFY COLUMN game_status ENUM('Created', 'Scheduled', 'Pending Change', 'Completed', 'Cancelled') DEFAULT 'Created'");
echo "Done.\n\n";

// 3. Update games with appropriate statuses
echo "Updating games with new statuses...\n";

// 3.1 Mark games as 'Created' if they don't have a schedule
echo "- Marking games without schedules as 'Created'...\n";
$createdCount = $db->exec("
    UPDATE games g
    LEFT JOIN schedules s ON g.game_id = s.game_id
    SET g.game_status = 'Created'
    WHERE s.game_id IS NULL
");
echo "  Updated $createdCount games to 'Created' status.\n";

// 3.2 Mark games as 'Cancelled' if they were previously cancelled
echo "- Marking previously cancelled games as 'Cancelled'...\n";
$cancelledCount = $db->exec("
    UPDATE games g
    JOIN (
        SELECT DISTINCT g.game_id 
        FROM games g 
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id 
        WHERE scr.request_type = 'Cancel' AND scr.request_status = 'Approved'
    ) cancelled ON g.game_id = cancelled.game_id
    SET g.game_status = 'Cancelled'
");
echo "  Updated $cancelledCount games to 'Cancelled' status.\n";

// 3.3 Mark games as 'Completed' if they have scores
echo "- Marking games with scores as 'Completed'...\n";
$completedCount = $db->exec("
    UPDATE games g
    SET g.game_status = 'Completed'
    WHERE (g.home_score IS NOT NULL OR g.away_score IS NOT NULL)
    AND g.game_status IS NULL
");
echo "  Updated $completedCount games to 'Completed' status.\n";

// 3.4 Mark games as 'Pending Change' if they have pending change requests
echo "- Marking games with pending changes as 'Pending Change'...\n";
$pendingCount = $db->exec("
    UPDATE games g
    JOIN (
        SELECT DISTINCT g.game_id 
        FROM games g 
        JOIN schedule_change_requests scr ON g.game_id = scr.game_id 
        WHERE scr.request_status = 'Pending'
    ) pending ON g.game_id = pending.game_id
    SET g.game_status = 'Pending Change'
    WHERE g.game_status IS NULL
");
echo "  Updated $pendingCount games to 'Pending Change' status.\n";

// 3.5 Mark remaining games as 'Scheduled'
echo "- Marking remaining games with schedules as 'Scheduled'...\n";
$scheduledCount = $db->exec("
    UPDATE games g
    JOIN schedules s ON g.game_id = s.game_id
    SET g.game_status = 'Scheduled'
    WHERE g.game_status IS NULL
");
echo "  Updated $scheduledCount games to 'Scheduled' status.\n";

// Count games by new status
$newStats = $db->query("SELECT game_status, COUNT(*) as count FROM games GROUP BY game_status")->fetchAll(PDO::FETCH_ASSOC);
echo "\nNew game status distribution:\n";
foreach ($newStats as $stat) {
    echo "- {$stat['game_status']}: {$stat['count']} games\n";
}

// Check for any games with NULL status
$nullCount = $db->query("SELECT COUNT(*) as count FROM games WHERE game_status IS NULL")->fetch(PDO::FETCH_ASSOC)['count'];
if ($nullCount > 0) {
    echo "\nWARNING: Found $nullCount games with NULL status. These need manual review.\n";
} else {
    echo "\nAll games have been assigned a valid status.\n";
}

echo "\n=== Game Status Update Complete ===\n";
