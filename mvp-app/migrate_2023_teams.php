<?php
/**
 * District 8 Travel League - 2023 Teams Migration
 * This script imports teams from the 2023 season from the old system
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League 2023 Teams Migration ===\n";

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

// Check if 2023 season exists in the new system
$season_check = $db->query("SELECT season_id, season_name FROM seasons WHERE season_year = '2023'");

if ($season_check->num_rows == 0) {
    echo "Creating 2023 seasons...\n";
    
    // Check if we need to create programs
    $junior_program_id = null;
    $senior_program_id = null;
    
    $program_check = $db->query("SELECT program_id, program_name FROM programs WHERE program_name IN ('Junior Baseball', 'Senior Baseball')");
    
    while ($program = $program_check->fetch_assoc()) {
        if ($program['program_name'] == 'Junior Baseball') {
            $junior_program_id = $program['program_id'];
        } elseif ($program['program_name'] == 'Senior Baseball') {
            $senior_program_id = $program['program_id'];
        }
    }
    
    if (!$junior_program_id) {
        $db->query("INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max, default_season_type, active_status, created_date, modified_date) 
                    VALUES ('Junior Baseball', 'JR23', 'Baseball', 9, 12, 'Spring', 'Active', NOW(), NOW())");
        $junior_program_id = $db->insert_id;
        echo "  Created Junior program (ID: $junior_program_id)\n";
    }
    
    if (!$senior_program_id) {
        $db->query("INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max, default_season_type, active_status, created_date, modified_date) 
                    VALUES ('Senior Baseball', 'SR23', 'Baseball', 13, 15, 'Spring', 'Active', NOW(), NOW())");
        $senior_program_id = $db->insert_id;
        echo "  Created Senior program (ID: $senior_program_id)\n";
    }
    
    // Create Junior season
    $db->query("INSERT INTO seasons (program_id, season_name, season_year, start_date, end_date, season_status, created_date, modified_date) 
                VALUES ($junior_program_id, '2023 Junior Baseball', '2023', '2023-05-01', '2023-08-31', 'Completed', NOW(), NOW())");
    $junior_season_id = $db->insert_id;
    echo "  Created Junior season (ID: $junior_season_id)\n";
    
    // Create Senior season
    $db->query("INSERT INTO seasons (program_id, season_name, season_year, start_date, end_date, season_status, created_date, modified_date) 
                VALUES ($senior_program_id, '2023 Senior Baseball', '2023', '2023-05-01', '2023-08-31', 'Completed', NOW(), NOW())");
    $senior_season_id = $db->insert_id;
    echo "  Created Senior season (ID: $senior_season_id)\n";
    
    // Create divisions for Junior season
    $db->query("INSERT INTO divisions (season_id, division_name, division_code, created_date) 
                VALUES ($junior_season_id, 'Junior', 'JR', NOW())");
    $junior_division_id = $db->insert_id;
    echo "  Created Junior division (ID: $junior_division_id)\n";
    
    // Create divisions for Senior season
    $db->query("INSERT INTO divisions (season_id, division_name, division_code, created_date) 
                VALUES ($senior_season_id, 'Senior', 'SR', NOW())");
    $senior_division_id = $db->insert_id;
    echo "  Created Senior division (ID: $senior_division_id)\n";
} else {
    // Get existing season IDs
    $seasons = [];
    while ($season = $season_check->fetch_assoc()) {
        $seasons[] = $season;
    }
    
    // Get division IDs
    $divisions = [];
    foreach ($seasons as $season) {
        $division_check = $db->query("SELECT division_id, division_name FROM divisions WHERE season_id = {$season['season_id']}");
        while ($division = $division_check->fetch_assoc()) {
            $divisions[$season['season_id']][$division['division_name']] = $division['division_id'];
        }
    }
    
    $junior_season_id = null;
    $senior_season_id = null;
    $junior_division_id = null;
    $senior_division_id = null;
    
    foreach ($seasons as $season) {
        if (strpos($season['season_name'], 'Junior') !== false) {
            $junior_season_id = $season['season_id'];
            $junior_division_id = $divisions[$junior_season_id]['Junior'] ?? null;
        } elseif (strpos($season['season_name'], 'Senior') !== false) {
            $senior_season_id = $season['season_id'];
            $senior_division_id = $divisions[$senior_season_id]['Senior'] ?? null;
        }
    }
    
    // Create divisions if they don't exist
    if ($junior_season_id && !$junior_division_id) {
        $db->query("INSERT INTO divisions (season_id, division_name, division_code, created_date) 
                    VALUES ($junior_season_id, 'Junior', 'JR', NOW())");
        $junior_division_id = $db->insert_id;
        echo "  Created Junior division (ID: $junior_division_id) for existing season\n";
    }
    
    if ($senior_season_id && !$senior_division_id) {
        $db->query("INSERT INTO divisions (season_id, division_name, division_code, created_date) 
                    VALUES ($senior_season_id, 'Senior', 'SR', NOW())");
        $senior_division_id = $db->insert_id;
        echo "  Created Senior division (ID: $senior_division_id) for existing season\n";
    }
    
    echo "Using existing 2023 seasons:\n";
    echo "  Junior season ID: $junior_season_id, Division ID: $junior_division_id\n";
    echo "  Senior season ID: $senior_season_id, Division ID: $senior_division_id\n";
}

// Get teams from the old system for 2023
echo "\nFetching 2023 teams from old system...\n";

$teams_query = "SELECT submission_id, team_name, league_name, home_field, division, 
                       manager_first_name, manager_last_name, manager_phone, manager_email 
                FROM d8ll_form_1 
                WHERE season = '2023' AND active = '1'";
$teams_result = $old_db->query($teams_query);

$teams_imported = 0;

while ($team = $teams_result->fetch_assoc()) {
    // Determine which season/division to use
    $target_season_id = null;
    $target_division_id = null;
    
    if ($team['division'] == '1') {
        $target_season_id = $junior_season_id;
        $target_division_id = $junior_division_id;
    } elseif ($team['division'] == '2') {
        $target_season_id = $senior_season_id;
        $target_division_id = $senior_division_id;
    } else {
        echo "  ! Unknown division '{$team['division']}' for team ID {$team['submission_id']}, skipping\n";
        continue;
    }
    
    // Check if team already exists
    $team_check_query = "SELECT team_id FROM teams 
                         WHERE season_id = ? AND 
                               (team_name = ? OR (league_name = ? AND home_field = ?))";
    $team_check_stmt = $db->prepare($team_check_query);
    
    $team_name = $team['team_name'] ?: $team['league_name']; // Use team_name if available, otherwise league_name
    $league_name = $team['league_name'];
    $home_field = $team['home_field'];
    
    $team_check_stmt->bind_param('isss', $target_season_id, $team_name, $league_name, $home_field);
    $team_check_stmt->execute();
    $team_check_result = $team_check_stmt->get_result();
    
    if ($team_check_result->num_rows > 0) {
        echo "  Team already exists: {$team_name} ({$league_name}), skipping\n";
        continue;
    }
    
    // Insert the team
    $insert_team_stmt = $db->prepare("
        INSERT INTO teams (
            season_id, division_id, league_name, team_name, 
            manager_first_name, manager_last_name, manager_phone, manager_email,
            home_field, active_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $active_status = 'Active';
    
    $insert_team_stmt->bind_param('iissssssss',
        $target_season_id, $target_division_id, $league_name, $team_name,
        $team['manager_first_name'], $team['manager_last_name'], $team['manager_phone'], $team['manager_email'],
        $home_field, $active_status
    );
    
    if ($insert_team_stmt->execute()) {
        $teams_imported++;
        echo "  ✓ Imported team: {$team_name} ({$league_name}) - {$home_field}\n";
    } else {
        echo "  ! Failed to import team {$team_name}: " . $insert_team_stmt->error . "\n";
    }
}

// Clean up
echo "\nCleaning up temporary database...\n";
$db->query("DROP DATABASE IF EXISTS $temp_db_name");

echo "\n=== 2023 Teams Migration Complete ===\n";
echo "Teams imported: $teams_imported\n";
echo "\nNow run migrate_games_schedules_comprehensive.php to import games and schedules.\n";
