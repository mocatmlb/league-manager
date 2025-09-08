<?php
/**
 * Simple Migration Script - Import SQL dump to temp database then migrate
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$current_season = '2024'; // Set the season you want to migrate
$temp_db_name = 'temp_d8tl_old';

echo "=== District 8 Travel League Simple Migration ===\n";
echo "Current Season: $current_season\n\n";

try {
    // Connect to MySQL server
    $mysql = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysql->connect_error) {
        throw new Exception("Failed to connect to MySQL: " . $mysql->connect_error);
    }
    
    echo "✓ Connected to MySQL server\n";
    
    // Create temporary database
    echo "Creating temporary database...\n";
    $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    $mysql->query("CREATE DATABASE $temp_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql->select_db($temp_db_name);
    
    echo "✓ Created temporary database: $temp_db_name\n";
    
    // Import SQL dump
    echo "Importing SQL dump (this may take a moment)...\n";
    $password_part = DB_PASS ? " -p" . DB_PASS : "";
    $command = "mysql -h " . DB_HOST . " -u " . DB_USER . $password_part . " $temp_db_name < " . escapeshellarg($sql_dump_file);
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);
    
    if ($return_code !== 0) {
        throw new Exception("Failed to import SQL dump. Return code: $return_code");
    }
    
    echo "✓ SQL dump imported successfully\n\n";
    
    // Connect to new database
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to new database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to new database\n";
    
    // Now migrate data using the imported database
    echo "Starting migration process...\n\n";
    
    // 1. Migrate Programs
    echo "1. Migrating Programs...\n";
    migratePrograms($mysql, $new_db, $current_season);
    
    // 2. Migrate Seasons  
    echo "2. Migrating Seasons...\n";
    migrateSeasons($mysql, $new_db, $current_season);
    
    // 3. Migrate Divisions
    echo "3. Migrating Divisions...\n";
    migrateDivisions($mysql, $new_db, $current_season);
    
    // 4. Migrate Locations
    echo "4. Migrating Locations...\n";
    migrateLocations($mysql, $new_db, $current_season);
    
    // 5. Migrate Teams
    echo "5. Migrating Teams...\n";
    migrateTeams($mysql, $new_db, $current_season);
    
    // 6. Migrate Games and Schedules
    echo "6. Migrating Games and Schedules...\n";
    migrateGamesAndSchedules($mysql, $new_db, $current_season);
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Migration Complete ===\n";
    echo "All data has been successfully migrated!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Migrate Programs
 */
function migratePrograms($old_db, $new_db, $current_season) {
    $query = "SELECT DISTINCT program_id FROM d8ll_form_1 WHERE season = ? AND active = '1' ORDER BY program_id";
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $current_season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $migrated = 0;
    while ($row = $result->fetch_assoc()) {
        $program_id = $row['program_id'];
        
        $program_name = ($program_id == '1') ? 'Junior Baseball' : 
                       (($program_id == '2') ? 'Senior Baseball' : 'Majors Baseball');
        $program_code = ($program_id == '1') ? 'JR' : 
                       (($program_id == '2') ? 'SR' : 'MAJ');
        
        // Check if program already exists
        $check_stmt = $new_db->prepare("SELECT program_id FROM programs WHERE program_code = ?");
        $check_stmt->bind_param('s', $program_code);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            $age_min = ($program_id == '1') ? 9 : (($program_id == '2') ? 13 : 15);
            $age_max = ($program_id == '1') ? 12 : (($program_id == '2') ? 15 : 18);
            
            $insert_stmt = $new_db->prepare("
                INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max, active_status) 
                VALUES (?, ?, 'Baseball', ?, ?, 'Active')
            ");
            
            $insert_stmt->bind_param('ssii', $program_name, $program_code, $age_min, $age_max);
            
            if ($insert_stmt->execute()) {
                $migrated++;
                echo "  ✓ Migrated program: $program_name ($program_code)\n";
            }
        } else {
            echo "  - Program $program_code already exists\n";
        }
    }
    
    echo "  Programs migrated: $migrated\n\n";
}

/**
 * Migrate Seasons
 */
function migrateSeasons($old_db, $new_db, $current_season) {
    // Get programs from new database
    $programs_result = $new_db->query("SELECT program_id, program_code FROM programs");
    $programs = [];
    while ($row = $programs_result->fetch_assoc()) {
        $programs[$row['program_code']] = $row['program_id'];
    }
    
    $migrated = 0;
    foreach ($programs as $code => $program_id) {
        // Check if season already exists
        $check_stmt = $new_db->prepare("SELECT season_id FROM seasons WHERE program_id = ? AND season_year = ?");
        $check_stmt->bind_param('ii', $program_id, $current_season);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            $season_name = "$current_season " . ($code == 'JR' ? 'Junior' : ($code == 'SR' ? 'Senior' : 'Majors')) . " Baseball";
            
            $insert_stmt = $new_db->prepare("
                INSERT INTO seasons (program_id, season_name, season_year, season_status, start_date, end_date) 
                VALUES (?, ?, ?, 'Active', ?, ?)
            ");
            
            $start_date = "$current_season-05-01";
            $end_date = "$current_season-08-31";
            
            $insert_stmt->bind_param('isiss', $program_id, $season_name, $current_season, $start_date, $end_date);
            
            if ($insert_stmt->execute()) {
                $migrated++;
                echo "  ✓ Created season: $season_name\n";
            }
        } else {
            echo "  - Season for $code $current_season already exists\n";
        }
    }
    
    echo "  Seasons migrated: $migrated\n\n";
}

/**
 * Migrate Divisions
 */
function migrateDivisions($old_db, $new_db, $current_season) {
    $query = "SELECT DISTINCT program_id, division FROM d8ll_form_1 
              WHERE season = ? AND active = '1' AND division IS NOT NULL AND division != ''
              ORDER BY program_id, division";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $current_season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get season mappings
    $seasons = [];
    $season_result = $new_db->query("
        SELECT s.season_id, p.program_code 
        FROM seasons s 
        JOIN programs p ON s.program_id = p.program_id 
        WHERE s.season_year = $current_season
    ");
    while ($row = $season_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $seasons[$program_key] = $row['season_id'];
    }
    
    $migrated = 0;
    while ($row = $result->fetch_assoc()) {
        if (isset($seasons[$row['program_id']])) {
            $season_id = $seasons[$row['program_id']];
            
            $division_name = ($row['division'] == '1') ? 'American League' : 
                           (($row['division'] == '2') ? 'National League' : 'Central League');
            
            // Check if division already exists
            $check_stmt = $new_db->prepare("SELECT division_id FROM divisions WHERE season_id = ? AND division_name = ?");
            $check_stmt->bind_param('is', $season_id, $division_name);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                $division_code = 'D' . $row['division'];
                
                $insert_stmt = $new_db->prepare("
                    INSERT INTO divisions (season_id, division_name, division_code) 
                    VALUES (?, ?, ?)
                ");
                
                $insert_stmt->bind_param('iss', $season_id, $division_name, $division_code);
                
                if ($insert_stmt->execute()) {
                    $migrated++;
                    echo "  ✓ Created division: $division_name (Program {$row['program_id']})\n";
                }
            }
        }
    }
    
    echo "  Divisions migrated: $migrated\n\n";
}

/**
 * Migrate Locations
 */
function migrateLocations($old_db, $new_db, $current_season) {
    $query = "SELECT DISTINCT sched_location FROM d8ll_form_2 
              WHERE sched_location IS NOT NULL AND sched_location != '' 
              ORDER BY sched_location";
              
    $result = $old_db->query($query);
    
    $migrated = 0;
    while ($row = $result->fetch_assoc()) {
        $location_name = trim($row['sched_location']);
        
        // Check if location already exists
        $check_stmt = $new_db->prepare("SELECT location_id FROM locations WHERE location_name = ?");
        $check_stmt->bind_param('s', $location_name);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            $insert_stmt = $new_db->prepare("
                INSERT INTO locations (location_name, active_status) 
                VALUES (?, 'Active')
            ");
            
            $insert_stmt->bind_param('s', $location_name);
            
            if ($insert_stmt->execute()) {
                $migrated++;
                echo "  ✓ Added location: $location_name\n";
            }
        }
    }
    
    echo "  Locations migrated: $migrated\n\n";
}

/**
 * Migrate Teams
 */
function migrateTeams($old_db, $new_db, $current_season) {
    $query = "SELECT * FROM d8ll_form_1 WHERE season = ? AND active = '1' ORDER BY program_id, league_name";
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $current_season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get season and division mappings
    $seasons = [];
    $divisions = [];
    
    $season_result = $new_db->query("
        SELECT s.season_id, p.program_code 
        FROM seasons s 
        JOIN programs p ON s.program_id = p.program_id 
        WHERE s.season_year = $current_season
    ");
    while ($row = $season_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $seasons[$program_key] = $row['season_id'];
    }
    
    $division_result = $new_db->query("
        SELECT d.division_id, d.division_name, s.season_id, p.program_code
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season
    ");
    while ($row = $division_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $divisions[$program_key][$row['division_name']] = $row['division_id'];
    }
    
    $migrated = 0;
    while ($row = $result->fetch_assoc()) {
        if (isset($seasons[$row['program_id']])) {
            $season_id = $seasons[$row['program_id']];
            
            // Determine division
            $division_id = null;
            if ($row['division']) {
                $division_name = ($row['division'] == '1') ? 'American League' : 
                               (($row['division'] == '2') ? 'National League' : 'Central League');
                if (isset($divisions[$row['program_id']][$division_name])) {
                    $division_id = $divisions[$row['program_id']][$division_name];
                }
            }
            
            // Create team name
            $team_display_name = $row['team_name'] ?: $row['league_name'] . '-' . $row['manager_last_name'];
            
            // Check if team already exists
            $check_stmt = $new_db->prepare("SELECT team_id FROM teams WHERE season_id = ? AND league_name = ? AND manager_email = ?");
            $check_stmt->bind_param('iss', $season_id, $row['league_name'], $row['manager_email']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                $avail_weekend = ($row['avail_weekend'] == '1' || $row['avail_weekend'] == 'yes') ? 1 : 0;
                $avail_weekday = ($row['avail_weekday_4pm'] == '1' || $row['avail_weekday_4pm'] == 'yes') ? 1 : 0;
                $fees_paid = ($row['fees_paid'] == '1' || $row['fees_paid'] == 'yes') ? 1 : 0;
                $reg_date = $row['submission_date'] ?: date('Y-m-d H:i:s');
                
                $insert_stmt = $new_db->prepare("
                    INSERT INTO teams (
                        season_id, division_id, league_name, team_name, 
                        manager_first_name, manager_last_name, manager_phone, manager_email,
                        home_field, home_field_5070, avail_weekend, avail_weekday_4pm,
                        registration_date, registration_fee_paid, active_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                ");
                
                $insert_stmt->bind_param('iissssssssiisi', 
                    $season_id, $division_id, $row['league_name'], $team_display_name,
                    $row['manager_first_name'], $row['manager_last_name'], 
                    $row['manager_phone'], $row['manager_email'],
                    $row['home_field'], $row['home_field_5070'],
                    $avail_weekend, $avail_weekday, $reg_date, $fees_paid
                );
                
                if ($insert_stmt->execute()) {
                    $migrated++;
                    echo "  ✓ Migrated team: $team_display_name ({$row['league_name']})\n";
                }
            }
        }
    }
    
    echo "  Teams migrated: $migrated\n\n";
}

/**
 * Migrate Games and Schedules
 */
function migrateGamesAndSchedules($old_db, $new_db, $current_season) {
    // Get games from old system
    $query = "SELECT DISTINCT 
                f3.game_no, f3.program_id, f3.season, f3.division,
                f3.away_team, f3.home_team, f3.away_score, f3.home_score, 
                f3.score_submitted_by
              FROM d8ll_form_3 f3
              WHERE f3.season = ?
              ORDER BY f3.program_id, f3.game_no";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $current_season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get mappings
    $seasons = [];
    $divisions = [];
    $teams = [];
    $locations = [];
    
    // Season mappings
    $season_result = $new_db->query("
        SELECT s.season_id, p.program_code 
        FROM seasons s 
        JOIN programs p ON s.program_id = p.program_id 
        WHERE s.season_year = $current_season
    ");
    while ($row = $season_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $seasons[$program_key] = $row['season_id'];
    }
    
    // Division mappings
    $division_result = $new_db->query("
        SELECT d.division_id, d.division_name, s.season_id, p.program_code
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season
    ");
    while ($row = $division_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $division_name = ($row['division_name'] == 'American League') ? '1' : 
                        (($row['division_name'] == 'National League') ? '2' : '3');
        $divisions[$program_key][$division_name] = $row['division_id'];
    }
    
    // Team mappings
    $team_result = $new_db->query("
        SELECT t.team_id, t.league_name, t.manager_last_name, s.season_id, p.program_code
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season
    ");
    while ($row = $team_result->fetch_assoc()) {
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $old_team_name = strtoupper($row['league_name'] . '-' . $row['manager_last_name']);
        $teams[$program_key][$old_team_name] = $row['team_id'];
    }
    
    // Location mappings
    $location_result = $new_db->query("SELECT location_id, location_name FROM locations");
    while ($row = $location_result->fetch_assoc()) {
        $locations[strtoupper($row['location_name'])] = $row['location_id'];
    }
    
    // Get schedule data
    $schedules = [];
    $schedule_query = "SELECT game_no, sched_date, sched_time, sched_location 
                       FROM d8ll_form_2 
                       WHERE game_no IS NOT NULL AND game_no != ''";
    $schedule_result = $old_db->query($schedule_query);
    while ($row = $schedule_result->fetch_assoc()) {
        $schedules[$row['game_no']] = [
            'date' => $row['sched_date'],
            'time' => $row['sched_time'],
            'location' => $row['sched_location']
        ];
    }
    
    $games_migrated = 0;
    $schedules_migrated = 0;
    
    while ($row = $result->fetch_assoc()) {
        if (!isset($seasons[$row['program_id']])) {
            continue;
        }
        
        $season_id = $seasons[$row['program_id']];
        $division_id = isset($divisions[$row['program_id']][$row['division']]) ? 
                      $divisions[$row['program_id']][$row['division']] : null;
        
        // Find team IDs
        $away_team_key = strtoupper($row['away_team']);
        $home_team_key = strtoupper($row['home_team']);
        
        $away_team_id = isset($teams[$row['program_id']][$away_team_key]) ? 
                       $teams[$row['program_id']][$away_team_key] : null;
        $home_team_id = isset($teams[$row['program_id']][$home_team_key]) ? 
                       $teams[$row['program_id']][$home_team_key] : null;
        
        if (!$away_team_id || !$home_team_id) {
            echo "  ! Skipping game {$row['game_no']}: Could not find teams ($away_team_key vs $home_team_key)\n";
            continue;
        }
        
        // Check if game already exists
        $check_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $check_stmt->bind_param('s', $row['game_no']);
        $check_stmt->execute();
        $existing_game = $check_stmt->get_result()->fetch_assoc();
        
        if (!$existing_game) {
            // Insert game
            $game_status = ($row['away_score'] !== null && $row['home_score'] !== null) ? 'Completed' : 'Active';
            
            $insert_game_stmt = $new_db->prepare("
                INSERT INTO games (
                    game_number, season_id, division_id, home_team_id, away_team_id,
                    home_score, away_score, game_status, score_submitted_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_game_stmt->bind_param('siiiiiiss',
                $row['game_no'], $season_id, $division_id, $home_team_id, $away_team_id,
                $row['home_score'], $row['away_score'], $game_status, $row['score_submitted_by']
            );
            
            if ($insert_game_stmt->execute()) {
                $game_id = $new_db->insert_id;
                $games_migrated++;
                echo "  ✓ Migrated game: {$row['game_no']}\n";
                
                // Insert schedule if we have schedule data
                if (isset($schedules[$row['game_no']]) && $schedules[$row['game_no']]['date']) {
                    $schedule = $schedules[$row['game_no']];
                    $location_id = isset($locations[strtoupper($schedule['location'])]) ? 
                                  $locations[strtoupper($schedule['location'])] : null;
                    
                    $insert_schedule_stmt = $new_db->prepare("
                        INSERT INTO schedules (game_id, game_date, game_time, location, location_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $game_date = date('Y-m-d', strtotime($schedule['date']));
                    $game_time = $schedule['time'] ? date('H:i:s', strtotime($schedule['time'])) : null;
                    
                    $insert_schedule_stmt->bind_param('isssi',
                        $game_id, $game_date, $game_time, $schedule['location'], $location_id
                    );
                    
                    if ($insert_schedule_stmt->execute()) {
                        $schedules_migrated++;
                    }
                }
            }
        }
    }
    
    echo "  Games migrated: $games_migrated\n";
    echo "  Schedules migrated: $schedules_migrated\n\n";
}
