<?php
require_once 'includes/bootstrap.php';

$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();

    // Connect to old database
    $oldDb = new PDO(
        'mysql:host=localhost;dbname=temp_d8tl_migration',
        'root',
        ''
    );
    $oldDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Map old program/division IDs to new season/division IDs
    $programMapping = [
        '1' => ['season_id' => 9, 'name' => 'Junior Baseball', 'divisions' => [
            '1' => 9,  // JUNIOR NORTH
            '2' => 10  // JUNIOR SOUTH
        ]],
        '2' => ['season_id' => 10, 'name' => 'Senior Baseball', 'divisions' => [
            '1' => 11  // SENIOR NORTH
        ]]
    ];

    echo "=== IMPORTING 2024 TEAMS ===\n";

    // Import teams (only finalized ones with valid data)
    $teamQuery = "
        SELECT submission_id, league_name, program_id, division, 
               manager_first_name, manager_last_name, manager_phone, manager_email
        FROM d8ll_form_1 
        WHERE season = '2024' 
        AND is_finalized = 'yes' 
        AND league_name IS NOT NULL 
        AND program_id IS NOT NULL 
        AND division IS NOT NULL
        ORDER BY program_id, division, league_name
    ";

    $teams = $oldDb->query($teamQuery)->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($teams) . " valid teams to import\n";

    $teamIdMapping = []; // Map old submission_id to new team_id

    foreach ($teams as $team) {
        $programId = $team['program_id'];
        $divisionId = $team['division'];

        if (!isset($programMapping[$programId])) {
            echo "Skipping team {$team['league_name']} - unknown program: {$programId}\n";
            continue;
        }

        if (!isset($programMapping[$programId]['divisions'][$divisionId])) {
            echo "Skipping team {$team['league_name']} - unknown division: {$divisionId}\n";
            continue;
        }

        $seasonId = $programMapping[$programId]['season_id'];
        $newDivisionId = $programMapping[$programId]['divisions'][$divisionId];

        // Create team name: league_name-manager_last_name
        $teamName = $team['league_name'];
        if ($team['manager_last_name']) {
            $teamName .= '-' . $team['manager_last_name'];
        }

        $teamData = [
            'division_id' => $newDivisionId,
            'season_id' => $seasonId,
            'league_name' => $team['league_name'],
            'team_name' => $teamName,
            'manager_first_name' => $team['manager_first_name'],
            'manager_last_name' => $team['manager_last_name'],
            'manager_phone' => $team['manager_phone'],
            'manager_email' => $team['manager_email'],
            'active_status' => 'Active'
        ];

        $newTeamId = $db->insert('teams', $teamData);
        $teamIdMapping[$team['submission_id']] = $newTeamId;

        echo "Imported team: {$teamName} (Program: {$programId}, Division: {$divisionId}) -> ID: {$newTeamId}\n";
    }

    echo "\n=== IMPORTING 2024 GAMES ===\n";

    // Import games (only finalized ones)
    $gameQuery = "
        SELECT game_no, division, home_team_id, away_team_id, 
               home_score, away_score, program_id
        FROM d8ll_form_3 
        WHERE season = '2024' 
        AND is_finalized = 'yes'
        AND home_team_id IS NOT NULL 
        AND away_team_id IS NOT NULL
        ORDER BY game_no
    ";

    $games = $oldDb->query($gameQuery)->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($games) . " games to import\n";

    $gameIdMapping = []; // Map old game_no to new game_id

    foreach ($games as $game) {
        $homeTeamOldId = $game['home_team_id'];
        $awayTeamOldId = $game['away_team_id'];

        // Check if we have both teams imported
        if (!isset($teamIdMapping[$homeTeamOldId]) || !isset($teamIdMapping[$awayTeamOldId])) {
            echo "Skipping game {$game['game_no']} - missing teams (Home: {$homeTeamOldId}, Away: {$awayTeamOldId})\n";
            continue;
        }

        $homeTeamId = $teamIdMapping[$homeTeamOldId];
        $awayTeamId = $teamIdMapping[$awayTeamOldId];
        $programId = $game['program_id'];

        if (!isset($programMapping[$programId])) {
            echo "Skipping game {$game['game_no']} - unknown program: {$programId}\n";
            continue;
        }

        $seasonId = $programMapping[$programId]['season_id'];
        $divisionId = $programMapping[$programId]['divisions'][$game['division']];

        // Determine game status
        $gameStatus = 'Scheduled';
        if ($game['home_score'] !== null && $game['away_score'] !== null) {
            $gameStatus = 'Completed';
        }

        $gameData = [
            'game_number' => $game['game_no'],
            'season_id' => $seasonId,
            'division_id' => $divisionId,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'home_score' => $game['home_score'],
            'away_score' => $game['away_score'],
            'game_status' => $gameStatus
        ];

        $newGameId = $db->insert('games', $gameData);
        $gameIdMapping[$game['game_no']] = $newGameId;

        echo "Imported game {$game['game_no']} -> ID: {$newGameId} ({$gameStatus})\n";
    }

    echo "\n=== IMPORTING 2024 SCHEDULES ===\n";

    // Import schedule data (only finalized and approved)
    $scheduleQuery = "
        SELECT game_no, sched_date, sched_time, sched_location, approved, comment
        FROM d8ll_form_2 
        WHERE season = '2024' 
        AND is_finalized = 'yes'
        AND game_no IS NOT NULL
        ORDER BY game_no, submission_date
    ";

    $schedules = $oldDb->query($scheduleQuery)->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($schedules) . " schedule records to import\n";

    foreach ($schedules as $schedule) {
        $gameNo = $schedule['game_no'];

        // Check if we imported this game
        if (!isset($gameIdMapping[$gameNo])) {
            echo "Skipping schedule for game {$gameNo} - game not imported\n";
            continue;
        }

        $gameId = $gameIdMapping[$gameNo];

        // Convert time format
        $gameTime = '12:00:00';
        if ($schedule['sched_time']) {
            $time = strtotime($schedule['sched_time']);
            if ($time !== false) {
                $gameTime = date('H:i:s', $time);
            }
        }

        // Handle date
        $gameDate = $schedule['sched_date'] ?: '2024-01-01';

        // Insert into schedules table
        $scheduleData = [
            'game_id' => $gameId,
            'game_date' => $gameDate,
            'game_time' => $gameTime,
            'location' => $schedule['sched_location'] ?: 'TBD'
        ];

        $db->insert('schedules', $scheduleData);

        // Insert into current_schedules
        $currentScheduleData = [
            'game_id' => $gameId,
            'game_date' => $gameDate,
            'game_time' => $gameTime,
            'location' => $schedule['sched_location'] ?: 'TBD',
            'version_number' => 1,
            'schedule_type' => 'Original'
        ];

        $db->insert('current_schedules', $currentScheduleData);

        // Insert into schedule_history
        $historyData = [
            'game_id' => $gameId,
            'version_number' => 1,
            'schedule_type' => 'Original',
            'game_date' => $gameDate,
            'game_time' => $gameTime,
            'location' => $schedule['sched_location'] ?: 'TBD',
            'change_request_id' => null,
            'created_by_type' => 'System',
            'is_current' => 1,
            'notes' => $schedule['comment']
        ];

        $db->insert('schedule_history', $historyData);

        echo "Imported schedule for game {$gameNo} on {$gameDate} at {$gameTime}\n";
    }

    // Commit transaction
    $db->commit();
    
    echo "\n=== IMPORT COMPLETED SUCCESSFULLY ===\n";
    echo "Teams imported: " . count($teamIdMapping) . "\n";
    echo "Games imported: " . count($gameIdMapping) . "\n";
    echo "Schedule records processed: " . count($schedules) . "\n";

} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Import failed - all changes rolled back.\n";
}
?>





