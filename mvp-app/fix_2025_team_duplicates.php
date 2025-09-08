<?php
/**
 * District 8 Travel League - Fix 2025 Team Duplicates
 * Fixes games where the same team is assigned to both home and away
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Fix 2025 Team Duplicates ===\n";

// Connect to the database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Create a temporary database for the old data
$temp_db_name = 'temp_d8tl_migration';
$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';

echo "Creating temporary database for old data...\n";
$db->query("DROP DATABASE IF EXISTS $temp_db_name");
$db->query("CREATE DATABASE $temp_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Import the SQL dump
echo "Importing old data...\n";
$password_part = DB_PASS ? " -p" . DB_PASS : "";
$command = "mysql -h " . DB_HOST . " -u " . DB_USER . $password_part . " $temp_db_name < " . escapeshellarg($sql_dump_file);
exec($command);

// Connect to the temporary database
$old_db = new mysqli(DB_HOST, DB_USER, DB_PASS, $temp_db_name);

if ($old_db->connect_error) {
    die("Connection to temp database failed: " . $old_db->connect_error);
}

// Find games with duplicate teams
echo "Finding 2025 games with duplicate teams...\n";
$result = $db->query("
    SELECT g.game_id, g.game_number, t1.team_id as home_team_id, t1.league_name as home_team, 
           t2.team_id as away_team_id, t2.league_name as away_team 
    FROM games g 
    JOIN teams t1 ON g.home_team_id = t1.team_id 
    JOIN teams t2 ON g.away_team_id = t2.team_id 
    WHERE g.game_number LIKE '2025%' AND t1.team_id = t2.team_id 
    ORDER BY g.game_number
");

$games_to_fix = [];
while ($row = $result->fetch_assoc()) {
    $games_to_fix[] = $row;
}

echo "Found " . count($games_to_fix) . " games with duplicate teams.\n\n";

// Create a mapping of old team IDs to new team IDs
$team_mapping = [];

// Get all teams for 2025 in the new system
$new_teams = $db->query("
    SELECT t.team_id, t.league_name, t.home_field 
    FROM teams t 
    JOIN seasons s ON t.season_id = s.season_id 
    WHERE s.season_year = '2025'
");

while ($team = $new_teams->fetch_assoc()) {
    if (!isset($team_mapping[$team['league_name']])) {
        $team_mapping[$team['league_name']] = [];
    }
    $team_mapping[$team['league_name']][] = [
        'team_id' => $team['team_id'],
        'home_field' => $team['home_field']
    ];
}

// Fix each game
foreach ($games_to_fix as $game) {
    echo "Fixing game {$game['game_number']}...\n";
    
    // Get the original game data from the old system
    $old_game = $old_db->query("
        SELECT game_no, home_team_id, away_team_id 
        FROM d8ll_form_3 
        WHERE game_no = '{$game['game_number']}'
    ")->fetch_assoc();
    
    if (!$old_game) {
        echo "  ! Could not find game {$game['game_number']} in old system, skipping\n";
        continue;
    }
    
    // Get the team details from the old system
    $old_home_team = $old_db->query("
        SELECT submission_id, league_name, home_field 
        FROM d8ll_form_1 
        WHERE submission_id = {$old_game['home_team_id']}
    ")->fetch_assoc();
    
    $old_away_team = $old_db->query("
        SELECT submission_id, league_name, home_field 
        FROM d8ll_form_1 
        WHERE submission_id = {$old_game['away_team_id']}
    ")->fetch_assoc();
    
    if (!$old_home_team || !$old_away_team) {
        echo "  ! Could not find team details for game {$game['game_number']}, skipping\n";
        continue;
    }
    
    // Find the correct teams in the new system based on league_name and home_field
    $new_home_team_id = null;
    $new_away_team_id = null;
    
    // Find the season_id for 2025
    $season_result = $db->query("SELECT season_id FROM seasons WHERE season_year = '2025' LIMIT 1");
    $season = $season_result->fetch_assoc();
    $season_id = $season['season_id'];

    // Create new teams for both home and away to ensure they're different
    // Create home team
    $db->query("
        INSERT INTO teams (
            season_id, league_name, home_field, 
            manager_first_name, manager_last_name, manager_phone, manager_email,
            active_status, created_date, modified_date
        ) VALUES (
            {$season_id}, 
            '{$old_home_team['league_name']}', 
            '{$old_home_team['home_field']}',
            'Manager', 'Team', '', '',
            'Active', 
            NOW(), 
            NOW()
        )
    ");
    
    $new_home_team_id = $db->insert_id;
    echo "  Created new home team: {$old_home_team['league_name']} - {$old_home_team['home_field']} (ID: $new_home_team_id)\n";
    
    // Create away team
    $db->query("
        INSERT INTO teams (
            season_id, league_name, home_field, 
            manager_first_name, manager_last_name, manager_phone, manager_email,
            active_status, created_date, modified_date
        ) VALUES (
            {$season_id}, 
            '{$old_away_team['league_name']}', 
            '{$old_away_team['home_field']}',
            'Manager', 'Team', '', '',
            'Active', 
            NOW(), 
            NOW()
        )
    ");
    
    $new_away_team_id = $db->insert_id;
    echo "  Created new away team: {$old_away_team['league_name']} - {$old_away_team['home_field']} (ID: $new_away_team_id)\n";
    
    if ($new_home_team_id && $new_away_team_id) {
        // Update the game
        $db->query("
            UPDATE games 
            SET home_team_id = $new_home_team_id, away_team_id = $new_away_team_id 
            WHERE game_id = {$game['game_id']}
        ");
        
        echo "  ✓ Updated game {$game['game_number']}: home_team_id = $new_home_team_id, away_team_id = $new_away_team_id\n";
    } else {
        echo "  ! Could not find suitable teams for game {$game['game_number']}, skipping\n";
    }
}

// Clean up
echo "\nCleaning up temporary database...\n";
$db->query("DROP DATABASE IF EXISTS $temp_db_name");

echo "\n=== Fix Complete ===\n";
echo count($games_to_fix) . " games processed.\n";
