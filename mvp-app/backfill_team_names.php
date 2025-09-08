<?php
/**
 * Backfill team names using league_name-manager_last_name format
 * for teams that don't have a team name set
 */

require_once 'includes/bootstrap.php';

$db = Database::getInstance();

try {
    // Get all teams with null or empty team names
    $teams = $db->fetchAll("
        SELECT team_id, league_name, manager_last_name, team_name
        FROM teams 
        WHERE team_name IS NULL 
           OR team_name = ''
    ");

    if (empty($teams)) {
        echo "No teams found that need team name backfill.\n";
        exit(0);
    }

    echo "Found " . count($teams) . " teams that need team name backfill.\n";
    
    // Process each team
    foreach ($teams as $team) {
        try {
            if (empty($team['league_name']) || empty($team['manager_last_name'])) {
                echo "WARNING: Team ID {$team['team_id']} is missing required data (league_name or manager_last_name). Skipping.\n";
                continue;
            }

            $newTeamName = $team['league_name'] . '-' . $team['manager_last_name'];
            
            // Update the team
            $db->update(
                'teams',
                ['team_name' => $newTeamName],
                'team_id = :team_id',
                ['team_id' => $team['team_id']]
            );

            echo "Updated team {$team['team_id']}: Set team_name to '{$newTeamName}'\n";
            
            // Log the change
            Logger::info("Team name backfilled", [
                'team_id' => $team['team_id'],
                'old_name' => $team['team_name'],
                'new_name' => $newTeamName
            ]);

        } catch (Exception $e) {
            echo "ERROR: Failed to update team {$team['team_id']}: " . $e->getMessage() . "\n";
            Logger::error("Team name backfill failed", [
                'team_id' => $team['team_id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    echo "\nTeam name backfill completed.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    Logger::error("Team name backfill script failed", [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
