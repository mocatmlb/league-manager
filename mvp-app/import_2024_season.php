<?php
require_once 'includes/bootstrap.php';

$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();

    // Get existing programs and divisions
    $programs = [
        '1' => ['id' => 1, 'name' => 'Junior Baseball JRBB', 'divisions' => [
            '1' => 9,  // JUNIOR NORTH
            '2' => 10  // JUNIOR SOUTH
        ]],
        '2' => ['id' => 2, 'name' => 'Senior Baseball SRBB', 'divisions' => [
            '1' => 11  // SENIOR NORTH
        ]]
    ];

    // Import teams from old database
    $oldDb = new PDO(
        'mysql:host=localhost;dbname=temp_d8tl_migration', 
        'root', 
        ''
    );
    $oldDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get teams from old database
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

    // Import each team
    foreach ($teams as $team) {
        // Skip if program not found
        if (!isset($programs[$team['program_code']])) {
            echo "Skipping team - unknown program code: {$team['program_code']}\n";
            continue;
        }

        $program = $programs[$team['program_code']];
        $divisionKey = trim(str_replace(['JUNIOR ', 'SENIOR '], '', $team['division']));

        // Skip if division not found
        if (!isset($program['divisions'][$divisionKey])) {
            echo "Skipping team - unknown division: {$team['division']} ({$divisionKey})\n";
            continue;
        }

        // Insert team
        $teamData = [
            'division_id' => $program['divisions'][$divisionKey],
            'season_id' => 2, // 2024 season ID
            'league_name' => $team['league_name'],
            'manager_first_name' => $team['manager_first_name'],
            'manager_last_name' => $team['manager_last_name'],
            'manager_phone' => $team['manager_phone'],
            'manager_email' => $team['manager_email'],
            'team_name' => null, // Will be set by trigger
            'active_status' => 'Active'
        ];

        $db->insert('teams', $teamData);
        echo "Imported team: {$team['league_name']} ({$team['division']})\n";
    }

    // Import games and schedules
    $query = "
        SELECT 
            g.game_no as old_game_id,
            g.game_no as game_number,
            g.home_team,
            g.away_team,
            g.home_score,
            g.away_score,
            s.sched_location as location,
            s.sched_date as game_date,
            s.sched_time as game_time,
            s.approved as is_current,
            s.schedule_id as change_request_id,
            CASE 
                WHEN s.approved = 1 THEN 'Approved'
                ELSE 'Pending'
            END as approval_status,
            'Original' as change_type,
            s.comment as change_reason,
            s.updated as created_date,
            s.updated as modified_date
        FROM d8ll_games g
        LEFT JOIN d8ll_schedule s ON g.game_no = s.game_no
        WHERE g.division IN ('JUNIORS', 'SENIORS')
        ORDER BY g.game_no, s.updated
    ";

    $games = $oldDb->query($query)->fetchAll(PDO::FETCH_ASSOC);
    echo "\nFound " . count($games) . " game records to import\n";

    // Group games by game_id to handle schedule history
    $gamesByNumber = [];
    foreach ($games as $game) {
        $gameNum = $game['game_number'];
        if (!isset($gamesByNumber[$gameNum])) {
            $gamesByNumber[$gameNum] = [];
        }
        $gamesByNumber[$gameNum][] = $game;
    }

    // Import each game and its schedule history
    foreach ($gamesByNumber as $gameNumber => $gameVersions) {
        // Get the current version of the game (use the first version if no current is marked)
        $currentVersion = null;
        foreach ($gameVersions as $version) {
            if ($version['is_current']) {
                $currentVersion = $version;
                break;
            }
        }
        
        // If no current version is marked, use the first one
        if (!$currentVersion && !empty($gameVersions)) {
            $currentVersion = $gameVersions[0];
        }

        if (!$currentVersion) {
            echo "Skipping game {$gameNumber} - no version found\n";
            continue;
        }

        // Get team IDs
        $homeTeamName = $currentVersion['home_team'] ? explode('-', $currentVersion['home_team'])[0] : '';
        $awayTeamName = $currentVersion['away_team'] ? explode('-', $currentVersion['away_team'])[0] : '';
        
        $homeTeamQuery = $db->fetchOne(
            "SELECT team_id FROM teams WHERE league_name = ? AND season_id = 2", 
            [$homeTeamName]
        );
        $awayTeamQuery = $db->fetchOne(
            "SELECT team_id FROM teams WHERE league_name = ? AND season_id = 2", 
            [$awayTeamName]
        );

        if (!$homeTeamQuery || !$awayTeamQuery) {
            echo "Skipping game {$gameNumber} - teams not found\n";
            continue;
        }

        $homeTeamId = $homeTeamQuery['team_id'];
        $awayTeamId = $awayTeamQuery['team_id'];

        // Determine game status
        $gameStatus = 'Scheduled';
        if ($currentVersion['approval_status'] === 'Cancelled') {
            $gameStatus = 'Cancelled';
        } elseif ($currentVersion['home_score'] !== null && $currentVersion['away_score'] !== null) {
            $gameStatus = 'Completed';
        }

        // Insert game
        $gameData = [
            'game_number' => $gameNumber,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'home_score' => $currentVersion['home_score'],
            'away_score' => $currentVersion['away_score'],
            'game_status' => $gameStatus,
            'season_id' => 2 // 2024 season ID
        ];

        $gameId = $db->insert('games', $gameData);

        // Insert schedule history in chronological order
        usort($gameVersions, function($a, $b) {
            return strtotime($a['created_date']) - strtotime($b['created_date']);
        });

        foreach ($gameVersions as $version) {
            // Convert time format from "7:00 PM" to "19:00:00"
            $gameTime = '12:00:00';
            if ($version['game_time']) {
                $time = strtotime($version['game_time']);
                if ($time !== false) {
                    $gameTime = date('H:i:s', $time);
                }
            }
            
            $scheduleData = [
                'game_id' => $gameId,
                'location' => $version['location'] ?: 'TBD',
                'game_date' => $version['game_date'] ?: '2024-01-01',
                'game_time' => $gameTime,
                'is_current' => $version['is_current'],
                'change_request_id' => null, // Set to null since we don't have valid change requests
                'schedule_type' => $version['change_type'],
                'notes' => $version['change_reason'],
                'created_at' => $version['created_date'] ?: date('Y-m-d H:i:s')
            ];

            $db->insert('schedule_history', $scheduleData);
        }

        echo "Imported game {$gameNumber} with " . count($gameVersions) . " schedule versions\n";
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
