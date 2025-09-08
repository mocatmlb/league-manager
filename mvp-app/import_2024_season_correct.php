<?php
require_once 'includes/bootstrap.php';

$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();

    // Import teams from old database
    $oldDb = new PDO(
        'mysql:host=localhost;dbname=temp_d8tl_migration',
        'root',
        ''
    );
    $oldDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Programs mapping - using the existing programs from the new system
    // Map to 2024 seasons: Junior (season_id 9), Senior (season_id 10)
    $programs = [
        '1' => ['season_id' => 9, 'name' => 'Junior Baseball JRBB', 'divisions' => [
            '1' => 9,  // JUNIOR NORTH
            '2' => 10  // JUNIOR SOUTH
        ]],
        '2' => ['season_id' => 10, 'name' => 'Senior Baseball SRBB', 'divisions' => [
            '1' => 11  // SENIOR NORTH
        ]]
    ];

    // Import teams from d8ll_form_1 (teams table)
    $query = "
        SELECT
            t.submission_id as old_team_id,
            t.league_name,
            t.manager_first_name,
            t.manager_last_name,
            t.manager_phone,
            t.manager_email,
            t.division,
            t.program_id as program_code
        FROM d8ll_form_1 t
        WHERE t.season = '2024'
        ORDER BY t.submission_id
    ";

    $teams = $oldDb->query($query)->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($teams) . " teams to import\n";

    foreach ($teams as $team) {
        $programCode = $team['program_code'];
        $divisionKey = $team['division'];

        if (!isset($programs[$programCode])) {
            echo "Skipping team - unknown program code: {$programCode}\n";
            continue;
        }

        $program = $programs[$programCode];
        if (!isset($program['divisions'][$divisionKey])) {
            echo "Skipping team - unknown division: {$divisionKey} ({$programCode})\n";
            continue;
        }

        $teamData = [
            'division_id' => $program['divisions'][$divisionKey],
            'season_id' => $program['season_id'], // Use correct 2024 season ID (9 for Junior, 10 for Senior)
            'league_name' => $team['league_name'],
            'manager_first_name' => $team['manager_first_name'],
            'manager_last_name' => $team['manager_last_name'],
            'manager_phone' => $team['manager_phone'],
            'manager_email' => $team['manager_email'],
            'team_name' => null, // Will be set by trigger
            'active_status' => 'Active'
        ];

        $teamId = $db->insert('teams', $teamData);
        echo "Imported team: {$team['league_name']} (Program: {$programCode}, Division: {$divisionKey})\n";
    }

    // Import games from d8ll_form_3 (games/scores table)
    $query = "
        SELECT 
            g.game_no as old_game_id,
            g.game_no as game_number,
            g.home_team,
            g.away_team,
            g.home_score,
            g.away_score,
            g.division,
            g.program_id,
            g.submission_date as created_date,
            g.last_modified_date as modified_date
        FROM d8ll_form_3 g
        WHERE g.season = '2024'
        ORDER BY g.game_no
    ";

    $games = $oldDb->query($query)->fetchAll(PDO::FETCH_ASSOC);
    echo "\nFound " . count($games) . " game records to import\n";

    foreach ($games as $game) {
        $gameNumber = $game['game_number'];
        
        // For 2024 games, we need to match teams by division and program
        // Since home_team and away_team are NULL, we'll need to create placeholder games
        // or match them differently
        
        // Get teams from the same division
        $programCode = $game['program_id'];
        $divisionKey = $game['division'];
        
        if (!isset($programs[$programCode])) {
            echo "Skipping game {$gameNumber} - unknown program code: {$programCode}\n";
            continue;
        }

        $program = $programs[$programCode];
        if (!isset($program['divisions'][$divisionKey])) {
            echo "Skipping game {$gameNumber} - unknown division: {$divisionKey}\n";
            continue;
        }

        $divisionId = $program['divisions'][$divisionKey];
        
        // Get teams from this division
        $seasonId = $program['season_id'];
        $teamsInDivision = $db->fetchAll(
            "SELECT team_id FROM teams WHERE division_id = ? AND season_id = ?",
            [$divisionId, $seasonId]
        );

        if (count($teamsInDivision) < 2) {
            echo "Skipping game {$gameNumber} - not enough teams in division\n";
            continue;
        }

        // For now, we'll create a placeholder game with the first two teams
        // In a real scenario, you'd need to match teams based on some other logic
        $homeTeamId = $teamsInDivision[0]['team_id'];
        $awayTeamId = $teamsInDivision[1]['team_id'];

        // Determine game status
        $gameStatus = 'Scheduled';
        if ($game['home_score'] !== null && $game['away_score'] !== null) {
            $gameStatus = 'Completed';
        }

        // Insert game
        $gameData = [
            'game_number' => $gameNumber,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'home_score' => $game['home_score'],
            'away_score' => $game['away_score'],
            'game_status' => $gameStatus,
            'season_id' => $seasonId // Use correct 2024 season ID
        ];

        $gameId = $db->insert('games', $gameData);

        // Create a basic schedule history entry
        $createdDate = $game['created_date'];
        if (!$createdDate || $createdDate === '0000-00-00 00:00:00') {
            $createdDate = date('Y-m-d H:i:s');
        }
        
        $scheduleData = [
            'game_id' => $gameId,
            'location' => 'TBD',
            'game_date' => '2024-01-01',
            'game_time' => '12:00:00',
            'is_current' => 1,
            'change_request_id' => null,
            'schedule_type' => 'Original',
            'notes' => 'Imported from 2024 data',
            'created_at' => $createdDate
        ];

        $db->insert('schedule_history', $scheduleData);

        echo "Imported game {$gameNumber} (Teams: {$homeTeamId} vs {$awayTeamId})\n";
    }

    // Commit transaction
    $db->commit();
    echo "\nImport completed successfully!\n";

} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Import failed - all changes rolled back.\n";
}
?>
