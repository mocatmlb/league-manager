<?php
/**
 * District 8 Travel League - Data Migration from SQL Dump
 * Migrates data from the SQL dump file to new MVP application
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$current_season = 2024; // Set the season you want to migrate

echo "=== District 8 Travel League Data Migration from SQL Dump ===\n";
echo "Migrating data from SQL dump to MVP app\n";
echo "SQL Dump: $sql_dump_file\n";
echo "Current Season: $current_season\n\n";

if (!file_exists($sql_dump_file)) {
    echo "ERROR: SQL dump file not found: $sql_dump_file\n";
    exit(1);
}

try {
    // Connect to new database using MySQLi
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to new database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to new database\n";
    echo "✓ Found SQL dump file\n\n";
    
    // Parse the SQL dump to extract data
    echo "Parsing SQL dump file...\n";
    $old_data = parseSqlDump($sql_dump_file);
    echo "✓ SQL dump parsed successfully\n\n";
    
    // Start migration process
    echo "Starting migration process...\n\n";
    
    // 1. Migrate Programs
    echo "1. Migrating Programs...\n";
    migratePrograms($old_data, $new_db, $current_season);
    
    // 2. Migrate Seasons
    echo "2. Migrating Seasons...\n";
    migrateSeasons($old_data, $new_db, $current_season);
    
    // 3. Migrate Divisions
    echo "3. Migrating Divisions...\n";
    migrateDivisions($old_data, $new_db, $current_season);
    
    // 4. Migrate Locations
    echo "4. Migrating Locations...\n";
    migrateLocations($old_data, $new_db, $current_season);
    
    // 5. Migrate Teams
    echo "5. Migrating Teams...\n";
    migrateTeams($old_data, $new_db, $current_season);
    
    // 6. Migrate Games and Schedules
    echo "6. Migrating Games and Schedules...\n";
    migrateGamesAndSchedules($old_data, $new_db, $current_season);
    
    echo "\n=== Migration Complete ===\n";
    echo "All data has been successfully migrated!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Parse SQL dump file to extract INSERT statements
 */
function parseSqlDump($file_path) {
    echo "  Reading SQL dump file...\n";
    $content = file_get_contents($file_path);
    
    if (!$content) {
        throw new Exception("Failed to read SQL dump file");
    }
    
    $data = [
        'd8ll_form_1' => [],
        'd8ll_form_2' => [],
        'd8ll_form_3' => [],
        'd8ll_form_5' => []
    ];
    
    // Extract INSERT statements for each table
    foreach ($data as $table => &$table_data) {
        echo "  Extracting data from $table...\n";
        
        // Find INSERT statements for this table
        $pattern = "/INSERT INTO `$table` \([^)]+\) VALUES\s*(.+?);/s";
        preg_match_all($pattern, $content, $matches);
        
        foreach ($matches[1] as $values_string) {
            // Parse the VALUES part
            $rows = parseInsertValues($values_string);
            $table_data = array_merge($table_data, $rows);
        }
        
        echo "    Found " . count($table_data) . " records\n";
    }
    
    return $data;
}

/**
 * Parse VALUES portion of INSERT statement
 */
function parseInsertValues($values_string) {
    $rows = [];
    
    // Split by '),(' to get individual rows
    $values_string = trim($values_string);
    if (substr($values_string, 0, 1) === '(') {
        $values_string = substr($values_string, 1);
    }
    if (substr($values_string, -1) === ')') {
        $values_string = substr($values_string, 0, -1);
    }
    
    // Simple parsing - split by '),(' 
    $row_strings = explode('),(', $values_string);
    
    foreach ($row_strings as $row_string) {
        $row_string = trim($row_string);
        
        // Parse individual values (basic CSV parsing)
        $values = [];
        $in_quotes = false;
        $current_value = '';
        $quote_char = '';
        
        for ($i = 0; $i < strlen($row_string); $i++) {
            $char = $row_string[$i];
            
            if (!$in_quotes && ($char === "'" || $char === '"')) {
                $in_quotes = true;
                $quote_char = $char;
            } elseif ($in_quotes && $char === $quote_char) {
                // Check for escaped quote
                if ($i + 1 < strlen($row_string) && $row_string[$i + 1] === $quote_char) {
                    $current_value .= $char;
                    $i++; // Skip next quote
                } else {
                    $in_quotes = false;
                }
            } elseif (!$in_quotes && $char === ',') {
                $values[] = trim($current_value) === 'NULL' ? null : trim($current_value, "'\"");
                $current_value = '';
            } else {
                $current_value .= $char;
            }
        }
        
        // Add the last value
        $values[] = trim($current_value) === 'NULL' ? null : trim($current_value, "'\"");
        
        $rows[] = $values;
    }
    
    return $rows;
}

/**
 * Migrate Programs from parsed data
 */
function migratePrograms($old_data, $new_db, $current_season) {
    // Get distinct programs from form_1 data
    $programs = [];
    
    foreach ($old_data['d8ll_form_1'] as $row) {
        if (count($row) >= 39 && $row[1] == $current_season && $row[38] == '1') { // season and active
            $program_id = $row[4]; // program_id column
            if (!isset($programs[$program_id])) {
                $programs[$program_id] = [
                    'program_id' => $program_id,
                    'program_name' => $program_id == '1' ? 'Junior Baseball' : 
                                    ($program_id == '2' ? 'Senior Baseball' : 'Majors Baseball'),
                    'program_code' => $program_id == '1' ? 'JR' : 
                                    ($program_id == '2' ? 'SR' : 'MAJ')
                ];
            }
        }
    }
    
    $migrated = 0;
    foreach ($programs as $program) {
        // Check if program already exists
        $check_stmt = $new_db->prepare("SELECT program_id FROM programs WHERE program_code = ?");
        $check_stmt->bind_param('s', $program['program_code']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0) {
            // Insert new program
            $age_min = ($program['program_id'] == '1') ? 9 : (($program['program_id'] == '2') ? 13 : 15);
            $age_max = ($program['program_id'] == '1') ? 12 : (($program['program_id'] == '2') ? 15 : 18);
            
            $insert_stmt = $new_db->prepare("
                INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max, active_status) 
                VALUES (?, ?, 'Baseball', ?, ?, 'Active')
            ");
            
            $insert_stmt->bind_param('ssii', 
                $program['program_name'], 
                $program['program_code'], 
                $age_min, 
                $age_max
            );
            
            if ($insert_stmt->execute()) {
                $migrated++;
                echo "  ✓ Migrated program: {$program['program_name']} ({$program['program_code']})\n";
            }
        } else {
            echo "  - Program {$program['program_code']} already exists\n";
        }
    }
    
    echo "  Programs migrated: $migrated\n\n";
}

/**
 * Migrate Seasons
 */
function migrateSeasons($old_data, $new_db, $current_season) {
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
            // Create season
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
function migrateDivisions($old_data, $new_db, $current_season) {
    // Get distinct divisions from form_1 data
    $divisions = [];
    
    foreach ($old_data['d8ll_form_1'] as $row) {
        if (count($row) >= 39 && $row[1] == $current_season && $row[38] == '1' && $row[24]) { // season, active, division
            $program_id = $row[4];
            $division = $row[24];
            
            $key = $program_id . '_' . $division;
            if (!isset($divisions[$key])) {
                $divisions[$key] = [
                    'program_id' => $program_id,
                    'division' => $division,
                    'division_name' => $division == '1' ? 'American League' : 
                                    ($division == '2' ? 'National League' : 'Central League')
                ];
            }
        }
    }
    
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
    foreach ($divisions as $division) {
        if (isset($seasons[$division['program_id']])) {
            $season_id = $seasons[$division['program_id']];
            
            // Check if division already exists
            $check_stmt = $new_db->prepare("SELECT division_id FROM divisions WHERE season_id = ? AND division_name = ?");
            $check_stmt->bind_param('is', $season_id, $division['division_name']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                $division_code = 'D' . $division['division'];
                
                $insert_stmt = $new_db->prepare("
                    INSERT INTO divisions (season_id, division_name, division_code) 
                    VALUES (?, ?, ?)
                ");
                
                $insert_stmt->bind_param('iss', $season_id, $division['division_name'], $division_code);
                
                if ($insert_stmt->execute()) {
                    $migrated++;
                    echo "  ✓ Created division: {$division['division_name']} (Program {$division['program_id']})\n";
                }
            }
        }
    }
    
    echo "  Divisions migrated: $migrated\n\n";
}

/**
 * Migrate Locations
 */
function migrateLocations($old_data, $new_db, $current_season) {
    // Get distinct locations from form_2 data
    $locations = [];
    
    foreach ($old_data['d8ll_form_2'] as $row) {
        if (count($row) >= 22 && $row[7]) { // sched_location column
            $location = trim($row[7]);
            if ($location && !isset($locations[$location])) {
                $locations[$location] = true;
            }
        }
    }
    
    $migrated = 0;
    foreach (array_keys($locations) as $location_name) {
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
function migrateTeams($old_data, $new_db, $current_season) {
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
    foreach ($old_data['d8ll_form_1'] as $row) {
        if (count($row) >= 39 && $row[1] == $current_season && $row[38] == '1') { // season and active
            $program_id = $row[4];
            
            if (isset($seasons[$program_id])) {
                $season_id = $seasons[$program_id];
                
                // Determine division
                $division_id = null;
                if ($row[24]) { // division column
                    $division_name = ($row[24] == '1') ? 'American League' : 
                                    (($row[24] == '2') ? 'National League' : 'Central League');
                    if (isset($divisions[$program_id][$division_name])) {
                        $division_id = $divisions[$program_id][$division_name];
                    }
                }
                
                // Extract team data
                $league_name = $row[5] ?? '';
                $team_name = $row[25] ?? '';
                $manager_first = $row[7] ?? '';
                $manager_last = $row[9] ?? '';
                $manager_phone = $row[11] ?? '';
                $manager_email = $row[13] ?? '';
                $home_field = $row[6] ?? '';
                $home_field_5070 = $row[26] ?? '';
                
                // Create team display name
                $team_display_name = $team_name ?: $league_name . '-' . $manager_last;
                
                // Check if team already exists
                $check_stmt = $new_db->prepare("SELECT team_id FROM teams WHERE season_id = ? AND league_name = ? AND manager_email = ?");
                $check_stmt->bind_param('iss', $season_id, $league_name, $manager_email);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows == 0) {
                    $avail_weekend = ($row[30] == '1' || $row[30] == 'yes') ? 1 : 0;
                    $avail_weekday = ($row[31] == '1' || $row[31] == 'yes') ? 1 : 0;
                    $fees_paid = ($row[28] == '1' || $row[28] == 'yes') ? 1 : 0;
                    $reg_date = $row[15] ?: date('Y-m-d H:i:s'); // submission_date
                    
                    $insert_stmt = $new_db->prepare("
                        INSERT INTO teams (
                            season_id, division_id, league_name, team_name, 
                            manager_first_name, manager_last_name, manager_phone, manager_email,
                            home_field, home_field_5070, avail_weekend, avail_weekday_4pm,
                            registration_date, registration_fee_paid, active_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                    ");
                    
                    $insert_stmt->bind_param('iissssssssiisi', 
                        $season_id, $division_id, $league_name, $team_display_name,
                        $manager_first, $manager_last, $manager_phone, $manager_email,
                        $home_field, $home_field_5070, $avail_weekend, $avail_weekday, 
                        $reg_date, $fees_paid
                    );
                    
                    if ($insert_stmt->execute()) {
                        $migrated++;
                        echo "  ✓ Migrated team: $team_display_name ($league_name)\n";
                    }
                }
            }
        }
    }
    
    echo "  Teams migrated: $migrated\n\n";
}

/**
 * Migrate Games and Schedules
 */
function migrateGamesAndSchedules($old_data, $new_db, $current_season) {
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
        SELECT t.team_id, t.league_name, t.manager_last_name, t.team_name, s.season_id, p.program_code
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
    
    // Create schedule lookup from form_2 data
    $schedules = [];
    foreach ($old_data['d8ll_form_2'] as $row) {
        if (count($row) >= 22 && $row[4]) { // game_no
            $game_no = $row[4];
            $schedules[$game_no] = [
                'date' => $row[5] ?? null,
                'time' => $row[6] ?? null,
                'location' => $row[7] ?? null
            ];
        }
    }
    
    $games_migrated = 0;
    $schedules_migrated = 0;
    
    foreach ($old_data['d8ll_form_3'] as $row) {
        if (count($row) >= 18 && $row[17] == $current_season) { // season column
            $game_no = $row[0];
            $program_id = $row[16];
            $division = $row[2];
            $away_team = strtoupper($row[5] ?? '');
            $home_team = strtoupper($row[6] ?? '');
            $away_score = $row[7] === '' ? null : $row[7];
            $home_score = $row[8] === '' ? null : $row[8];
            $score_submitted_by = $row[14] ?? null;
            
            if (!isset($seasons[$program_id])) {
                continue;
            }
            
            $season_id = $seasons[$program_id];
            $division_id = isset($divisions[$program_id][$division]) ? 
                          $divisions[$program_id][$division] : null;
            
            // Find team IDs
            $away_team_id = isset($teams[$program_id][$away_team]) ? 
                           $teams[$program_id][$away_team] : null;
            $home_team_id = isset($teams[$program_id][$home_team]) ? 
                           $teams[$program_id][$home_team] : null;
            
            if (!$away_team_id || !$home_team_id) {
                echo "  ! Skipping game $game_no: Could not find teams ($away_team vs $home_team)\n";
                continue;
            }
            
            // Check if game already exists
            $check_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
            $check_stmt->bind_param('s', $game_no);
            $check_stmt->execute();
            $existing_game = $check_stmt->get_result()->fetch_assoc();
            
            if (!$existing_game) {
                // Insert game
                $game_status = ($away_score !== null && $home_score !== null) ? 'Completed' : 'Active';
                
                $insert_game_stmt = $new_db->prepare("
                    INSERT INTO games (
                        game_number, season_id, division_id, home_team_id, away_team_id,
                        home_score, away_score, game_status, score_submitted_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insert_game_stmt->bind_param('siiiiiiss',
                    $game_no, $season_id, $division_id, $home_team_id, $away_team_id,
                    $home_score, $away_score, $game_status, $score_submitted_by
                );
                
                if ($insert_game_stmt->execute()) {
                    $game_id = $new_db->insert_id;
                    $games_migrated++;
                    echo "  ✓ Migrated game: $game_no\n";
                    
                    // Insert schedule if we have schedule data
                    if (isset($schedules[$game_no]) && $schedules[$game_no]['date']) {
                        $schedule = $schedules[$game_no];
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
    }
    
    echo "  Games migrated: $games_migrated\n";
    echo "  Schedules migrated: $schedules_migrated\n\n";
}
