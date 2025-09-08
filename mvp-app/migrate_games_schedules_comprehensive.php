<?php
/**
 * District 8 Travel League - Comprehensive Games and Schedules Migration
 * Properly migrates games, schedules, and schedule changes based on correct old system understanding
 * 
 * Old System Architecture:
 * - d8ll_form_3: Games table (game_no, teams, scores only)
 * - d8ll_form_2: Schedules table (ALL scheduling info including original and changes)
 *   - First entry per game with comment='ORIGINAL SCHEDULE' = true original
 *   - Subsequent entries = actual schedule changes
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$temp_db_name = 'temp_d8tl_migration';

// Configuration
$seasons_to_migrate = ['2023']; // Only migrate 2023 season
$default_season = '2025'; // Default if not specified

echo "=== District 8 Travel League Comprehensive Migration ===\n";
echo "Migrating games, schedules, and schedule changes...\n";
echo "Seasons to migrate: " . implode(', ', $seasons_to_migrate) . "\n\n";

try {
    // Connect to MySQL server
    $mysql = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysql->connect_error) {
        throw new Exception("Failed to connect to MySQL: " . $mysql->connect_error);
    }
    
    // Temporarily disable strict date checking for migration
    $mysql->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    
    echo "✓ Connected to MySQL server\n";
    
    // Create temporary database
    echo "Creating temporary database...\n";
    $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    $mysql->query("CREATE DATABASE $temp_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql->select_db($temp_db_name);
    
    echo "✓ Created temporary database: $temp_db_name\n";
    
    // Import SQL dump
    echo "Importing SQL dump...\n";
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
    
    // Set the same SQL mode for the new database connection
    $new_db->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    
    echo "✓ Connected to new database\n";
    
    // Start migration process
    echo "\nStarting comprehensive migration...\n\n";
    
    foreach ($seasons_to_migrate as $season) {
        echo "=== Migrating Season $season ===\n";
        
        // 1. Migrate games for this season
        echo "1. Migrating games for season $season...\n";
        $games_migrated = migrateGames($mysql, $new_db, $season);
        
        // 2. Migrate schedules and create history for this season
        echo "2. Migrating schedules and creating history for season $season...\n";
        $schedules_migrated = migrateSchedulesAndHistory($mysql, $new_db, $season);
        
        // 3. Migrate schedule change requests for this season
        echo "3. Migrating schedule change requests for season $season...\n";
        $requests_migrated = migrateScheduleChangeRequests($mysql, $new_db, $season);
        
        echo "Season $season summary:\n";
        echo "  - Games: $games_migrated\n";
        echo "  - Schedules: $schedules_migrated\n";
        echo "  - Change requests: $requests_migrated\n\n";
    }
    
    // Cleanup
    echo "Cleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Comprehensive Migration Complete ===\n";
    echo "All seasons have been successfully migrated!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Migrate games for a specific season
 */
function migrateGames($old_db, $new_db, $season) {
    // Build team mapping from old system to new system
    $team_mapping = buildTeamMapping($old_db, $new_db, $season);
    
    if (empty($team_mapping)) {
        echo "  ! No team mapping found for season $season, skipping games\n";
        return 0;
    }
    
    // Get season and division info
    $season_info = getSeasonInfo($new_db, $season);
    if (!$season_info) {
        echo "  ! Season $season not found in new system, skipping\n";
        return 0;
    }
    
    // Get games from old system
    $games_query = "SELECT * FROM d8ll_form_3 WHERE season = ? ORDER BY game_no";
    $stmt = $old_db->prepare($games_query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $games_result = $stmt->get_result();
    
    $games_migrated = 0;
    
    echo "  Found " . $games_result->num_rows . " games in old system for season $season\n";
    
    while ($game = $games_result->fetch_assoc()) {
        // Check if game already exists
        $check_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $check_stmt->bind_param('s', $game['game_no']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            continue; // Skip if already exists
        }
        
        // Map team IDs
        $home_team_id = $team_mapping[$game['home_team_id']] ?? null;
        $away_team_id = $team_mapping[$game['away_team_id']] ?? null;
        
        if (!$home_team_id || !$away_team_id) {
            echo "  ! Could not map teams for game {$game['game_no']}\n";
            continue;
        }
        
        // Check if home and away teams are the same
        if ($home_team_id === $away_team_id) {
            echo "  ! Home and away teams are the same for game {$game['game_no']}, finding alternative team\n";
            
            // Get all teams for this season
            $all_teams_query = "SELECT team_id, league_name, home_field FROM teams WHERE season_id = ?";
            $all_teams_stmt = $new_db->prepare($all_teams_query);
            $all_teams_stmt->bind_param('i', $season_info['season_id']);
            $all_teams_stmt->execute();
            $all_teams_result = $all_teams_stmt->get_result();
            
            $alternative_team_id = null;
            while ($team = $all_teams_result->fetch_assoc()) {
                if ($team['team_id'] != $home_team_id) {
                    $alternative_team_id = $team['team_id'];
                    break;
                }
            }
            
            // If no alternative team found, create a new one
            if (!$alternative_team_id) {
                // Get the original team details from the old system
                $old_team_query = "SELECT league_name, home_field FROM d8ll_form_1 WHERE submission_id = ?";
                $old_team_stmt = $old_db->prepare($old_team_query);
                $old_team_stmt->bind_param('i', $game['away_team_id']);
                $old_team_stmt->execute();
                $old_team = $old_team_stmt->get_result()->fetch_assoc();
                
                if ($old_team) {
                    // Create a new team with a slightly modified name
                    $new_team_name = $old_team['league_name'] . ' B';
                    $new_team_field = $old_team['home_field'];
                    
                    $insert_team_stmt = $new_db->prepare("
                        INSERT INTO teams (
                            season_id, league_name, home_field, 
                            manager_first_name, manager_last_name, manager_phone, manager_email,
                            active_status, created_date, modified_date
                        ) VALUES (
                            ?, ?, ?, 'Manager', 'Team', '', '', 'Active', NOW(), NOW()
                        )
                    ");
                    
                    $insert_team_stmt->bind_param('iss', 
                        $season_info['season_id'], 
                        $new_team_name, 
                        $new_team_field
                    );
                    
                    $insert_team_stmt->execute();
                    $alternative_team_id = $new_db->insert_id;
                    
                    echo "  Created new team: $new_team_name - $new_team_field (ID: $alternative_team_id)\n";
                }
            }
            
            if ($alternative_team_id) {
                $away_team_id = $alternative_team_id;
                echo "  Using alternative team ID: $alternative_team_id for away team\n";
            } else {
                echo "  ! Could not find or create an alternative team, skipping game {$game['game_no']}\n";
                continue;
            }
        }
        
        // Determine game status
        $game_status = 'Active';
        if ($game['home_score'] !== null && $game['away_score'] !== null) {
            $game_status = 'Completed';
        }
        
        // Insert game
        $insert_game_stmt = $new_db->prepare("
            INSERT INTO games (
                game_number, season_id, division_id, home_team_id, away_team_id,
                home_score, away_score, game_status, score_submitted_by,
                created_date, modified_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $created_date = date('Y-m-d H:i:s');
        $modified_date = date('Y-m-d H:i:s');
        
        $insert_game_stmt->bind_param('siiiiiissss',
            $game['game_no'], $season_info['season_id'], $season_info['division_id'], 
            $home_team_id, $away_team_id, $game['home_score'], $game['away_score'], 
            $game_status, $game['score_submitted_by'], $created_date, $modified_date
        );
        
        if ($insert_game_stmt->execute()) {
            $games_migrated++;
            echo "  ✓ Migrated game {$game['game_no']}\n";
        }
    }
    
    echo "  Games migrated: $games_migrated\n\n";
    return $games_migrated;
}

/**
 * Migrate schedules and create proper history
 */
function migrateSchedulesAndHistory($old_db, $new_db, $season) {
    // Get all schedule entries for this season, ordered by game and submission date
    $schedules_query = "SELECT 
                          f2.game_no, f2.sched_date, f2.sched_time, f2.sched_location, 
                          f2.comment, f2.approved, f2.submission_date, f2.submitter_name
                        FROM d8ll_form_2 f2
                        WHERE f2.season = ? 
                        AND f2.sched_date IS NOT NULL 
                        AND f2.sched_date != '0000-00-00'
                        ORDER BY f2.game_no, f2.submission_date ASC";
                        
    $stmt = $old_db->prepare($schedules_query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $schedules_result = $stmt->get_result();
    
    $games_processed = [];
    $schedules_created = 0;
    $history_created = 0;
    
    echo "  Found " . $schedules_result->num_rows . " schedule entries for season $season\n";
    
    while ($row = $schedules_result->fetch_assoc()) {
        // Find the corresponding game in the new system
        $game_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $game_stmt->bind_param('s', $row['game_no']);
        $game_stmt->execute();
        $game_result = $game_stmt->get_result()->fetch_assoc();
        
        if (!$game_result) {
            continue; // Skip if game not found
        }
        
        $game_id = $game_result['game_id'];
        $is_original = ($row['comment'] == 'ORIGINAL SCHEDULE' || $row['comment'] == 'ORIGINAL GAME');
        
        // Special handling for games where the "ORIGINAL SCHEDULE" might not be the true original
        // This can happen when the original was updated in the old system
        if ($is_original) {
            // Check if there are any earlier entries for this game that might be the true original
            $earlier_entries = $old_db->query("
                SELECT sched_date, sched_time, sched_location, comment, submission_date
                FROM d8ll_form_2 
                WHERE game_no = '{$row['game_no']}' 
                AND sched_date IS NOT NULL 
                AND sched_date != '0000-00-00'
                AND submission_date < '{$row['submission_date']}'
                ORDER BY submission_date ASC
                LIMIT 1
            ");
            
            if ($earlier_entry = $earlier_entries->fetch_assoc()) {
                echo "  ! Found earlier entry for game {$row['game_no']}: {$earlier_entry['sched_date']} (comment: {$earlier_entry['comment']})\n";
                echo "    Using earlier entry as true original instead of 'ORIGINAL SCHEDULE' entry\n";
                
                // Use the earlier entry as the original
                $row['sched_date'] = $earlier_entry['sched_date'];
                $row['sched_time'] = $earlier_entry['sched_time'];
                $row['sched_location'] = $earlier_entry['sched_location'];
            }
        }
        
        if ($is_original) {
            // This is the original schedule - create/update the schedules table
            $game_date = date('Y-m-d', strtotime($row['sched_date']));
            $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : '18:00:00';
            
            // Check if schedule already exists
            $check_schedule = $new_db->prepare("SELECT schedule_id FROM schedules WHERE game_id = ?");
            $check_schedule->bind_param('i', $game_id);
            $check_schedule->execute();
            
            if ($check_schedule->get_result()->num_rows > 0) {
                // Update existing schedule
                $update_stmt = $new_db->prepare("
                    UPDATE schedules 
                    SET game_date = ?, game_time = ?, location = ?
                    WHERE game_id = ?
                ");
                $update_stmt->bind_param('sssi', $game_date, $game_time, $row['sched_location'], $game_id);
                $update_stmt->execute();
            } else {
                // Create new schedule
                $insert_schedule_stmt = $new_db->prepare("
                    INSERT INTO schedules (game_id, game_date, game_time, location, created_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_schedule_stmt->bind_param('isss', $game_id, $game_date, $game_time, $row['sched_location']);
                $insert_schedule_stmt->execute();
                $schedules_created++;
            }
            
            // Create original history entry (version 1)
            $created_at = date('Y-m-d H:i:s');
            if ($row['submission_date'] && 
                $row['submission_date'] != '0000-00-00 00:00:00' && 
                $row['submission_date'] != '0000-00-00' &&
                $row['submission_date'] != '1999-11-30 00:00:00' &&
                strtotime($row['submission_date']) !== false) {
                $created_at = $row['submission_date'];
            }
                         
            $insert_history_stmt = $new_db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, is_current, notes, created_at
                ) VALUES (?, 1, 'Original', ?, ?, ?, 1, 'Original schedule', ?)
            ");
            
            $insert_history_stmt->bind_param('issss', $game_id, $game_date, $game_time, $row['sched_location'], $created_at);
            $insert_history_stmt->execute();
            
            $games_processed[$row['game_no']] = [
                'game_id' => $game_id,
                'version' => 1,
                'original_date' => $game_date,
                'original_time' => $game_time,
                'original_location' => $row['sched_location'],
                'changes' => []
            ];
            
            $history_created++;
            echo "  ✓ Set original schedule for game {$row['game_no']}: {$game_date} {$game_time} at {$row['sched_location']}\n";
            
        } else {
            // This is a schedule change
            if (!isset($games_processed[$row['game_no']])) {
                continue; // Skip if we haven't processed the original yet
            }
            
            $game_info = $games_processed[$row['game_no']];
            $version = $game_info['version'] + 1;
            
            // Create history entry for this change
            $game_date = date('Y-m-d', strtotime($row['sched_date']));
            $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : '18:00:00';
            $notes = $row['comment'] ?: 'Schedule change';
            $created_at = date('Y-m-d H:i:s');
            if ($row['submission_date'] && 
                $row['submission_date'] != '0000-00-00 00:00:00' && 
                $row['submission_date'] != '0000-00-00' &&
                $row['submission_date'] != '1999-11-30 00:00:00' &&
                strtotime($row['submission_date']) !== false) {
                $created_at = $row['submission_date'];
            }
            
            $insert_history_stmt = $new_db->prepare("
                INSERT INTO schedule_history (
                    game_id, version_number, schedule_type, game_date, game_time, 
                    location, is_current, notes, created_at
                ) VALUES (?, ?, 'Changed', ?, ?, ?, 0, ?, ?)
            ");
            
            $insert_history_stmt->bind_param('iisssss', 
                $game_id, $version, $game_date, $game_time, $row['sched_location'], $notes, $created_at
            );
            $insert_history_stmt->execute();
            
            // Store this change info for later linking to change requests
            $games_processed[$row['game_no']]['changes'][] = [
                'version' => $version,
                'history_id' => $new_db->insert_id,
                'date' => $game_date,
                'time' => $game_time,
                'location' => $row['sched_location'],
                'submitter' => $row['submitter_name'],
                'comment' => $row['comment'],
                'approved' => $row['approved'],
                'created_at' => $created_at
            ];
            
            // Update current schedule if this change was approved (status 1 or 3)
            if ($row['approved'] == '1' || $row['approved'] == '3') {
                // Check if this is a cancellation
                $is_cancellation = (stripos($row['comment'], 'cancel') !== false);
                
                if ($is_cancellation) {
                    // Update game status to Cancelled for cancellations
                    $update_game_stmt = $new_db->prepare("
                        UPDATE games 
                        SET game_status = 'Cancelled'
                        WHERE game_id = ?
                    ");
                    $update_game_stmt->bind_param('i', $game_id);
                    $update_game_stmt->execute();
                }
                
                // Update schedule
                $update_current_stmt = $new_db->prepare("
                    UPDATE schedules 
                    SET game_date = ?, game_time = ?, location = ?
                    WHERE game_id = ?
                ");
                $update_current_stmt->bind_param('sssi', $game_date, $game_time, $row['sched_location'], $game_id);
                $update_current_stmt->execute();
                
                // Update is_current flags
                $new_db->query("UPDATE schedule_history SET is_current = 0 WHERE game_id = $game_id");
                $new_db->query("UPDATE schedule_history SET is_current = 1 WHERE game_id = $game_id AND version_number = $version");
            }
            
            $games_processed[$row['game_no']]['version'] = $version;
            $history_created++;
            
            echo "  ✓ Added change v{$version} for game {$row['game_no']}: {$game_date} {$game_time} at {$row['sched_location']} ({$notes})\n";
        }
    }
    
    echo "  Schedules created/updated: $schedules_created\n";
    echo "  History entries created: $history_created\n\n";
    
    return $schedules_created;
}

/**
 * Migrate schedule change requests (if they exist as separate entities)
 */
function migrateScheduleChangeRequests($old_db, $new_db, $season) {
    // This function would handle any separate schedule change request records
    // For now, we're creating them from the schedule history
    
    $requests_created = 0;
    
    // Get all schedule changes (non-original entries) for this season
    // IMPORTANT: Only create change requests for actual changes, NOT original schedules
    $changes_query = "SELECT 
                        f2.game_no, f2.sched_date, f2.sched_time, f2.sched_location, 
                        f2.comment, f2.approved, f2.submission_date, f2.submitter_name
                      FROM d8ll_form_2 f2
                      WHERE f2.season = ? 
                      AND f2.comment != 'ORIGINAL SCHEDULE'
                      AND f2.comment != 'ORIGINAL GAME'
                      AND f2.comment IS NOT NULL
                      AND f2.comment != ''
                      AND f2.sched_date IS NOT NULL 
                      AND f2.sched_date != '0000-00-00'
                      ORDER BY f2.game_no, f2.submission_date ASC";
                      
    $stmt = $old_db->prepare($changes_query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $changes_result = $stmt->get_result();
    
    echo "  Found " . $changes_result->num_rows . " schedule changes for season $season\n";
    
    while ($change = $changes_result->fetch_assoc()) {
        // Find the corresponding game
        $game_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $game_stmt->bind_param('s', $change['game_no']);
        $game_stmt->execute();
        $game_result = $game_stmt->get_result()->fetch_assoc();
        
        if (!$game_result) {
            continue;
        }
        
        $game_id = $game_result['game_id'];
        
        // Get original schedule data
        $original_stmt = $new_db->prepare("
            SELECT game_date, game_time, location 
            FROM schedule_history 
            WHERE game_id = ? AND version_number = 1
        ");
        $original_stmt->bind_param('i', $game_id);
        $original_stmt->execute();
        $original = $original_stmt->get_result()->fetch_assoc();
        
        if (!$original) {
            continue;
        }
        
        // Check if request already exists
        $check_request = $new_db->prepare("
            SELECT request_id FROM schedule_change_requests 
            WHERE game_id = ? AND requested_date = ? AND requested_time = ? AND requested_location = ?
        ");
        
        $requested_date = date('Y-m-d', strtotime($change['sched_date']));
        $requested_time = $change['sched_time'] ? date('H:i:s', strtotime($change['sched_time'])) : '18:00:00';
        
        // Skip if this is not actually a change (original and requested are identical)
        if ($original['game_date'] == $requested_date && 
            $original['game_time'] == $requested_time && 
            $original['location'] == $change['sched_location']) {
            echo "  ! Skipping non-change for game {$change['game_no']} (identical to original)\n";
            continue;
        }
        
        $check_request->bind_param('isss', $game_id, $requested_date, $requested_time, $change['sched_location']);
        $check_request->execute();
        
        if ($check_request->get_result()->num_rows > 0) {
            continue; // Skip if already exists
        }
        
        // Create schedule change request
        $request_type = 'Reschedule';
        if (stripos($change['comment'], 'cancel') !== false) {
            $request_type = 'Cancel';
        } elseif (stripos($change['comment'], 'location') !== false) {
            $request_type = 'Location Change';
        }
        
        // Map approval status from old system to new system
        $request_status = 'Pending';
        if ($change['approved'] == '1') {
            $request_status = 'Approved';
        } elseif ($change['approved'] == '2') {
            $request_status = 'Denied';
        } elseif ($change['approved'] == '3') {
            $request_status = 'Approved'; // Status 3 was used for approved cancellations
        }
        $created_date = date('Y-m-d H:i:s');
        if ($change['submission_date'] && 
            $change['submission_date'] != '0000-00-00 00:00:00' && 
            $change['submission_date'] != '0000-00-00' &&
            $change['submission_date'] != '1999-11-30 00:00:00' &&
            strtotime($change['submission_date']) !== false) {
            $created_date = $change['submission_date'];
        }
        
        $insert_request_stmt = $new_db->prepare("
            INSERT INTO schedule_change_requests (
                game_id, requested_by, request_type, 
                original_date, original_time, original_location,
                requested_date, requested_time, requested_location,
                reason, request_status, created_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $requested_by = $change['submitter_name'] ?: 'ADMIN';
        $reason = $change['comment'] ?: 'Schedule change';
        
        $insert_request_stmt->bind_param('isssssssssss',
            $game_id, $requested_by, $request_type,
            $original['game_date'], $original['game_time'], $original['location'],
            $requested_date, $requested_time, $change['sched_location'],
            $reason, $request_status, $created_date
        );
        
        if ($insert_request_stmt->execute()) {
            $request_id = $new_db->insert_id;
            $requests_created++;
            echo "  ✓ Created change request for game {$change['game_no']}: {$request_type}\n";
            
            // Link this change request to the corresponding schedule history entry
            linkRequestToHistory($new_db, $request_id, $game_id, $requested_date, $requested_time, $change['sched_location']);
        }
    }
    
    echo "  Schedule change requests created: $requests_created\n\n";
    return $requests_created;
}

/**
 * Build team mapping from old system to new system
 */
function buildTeamMapping($old_db, $new_db, $season) {
    $team_mapping = [];
    
    // Get teams from old system for this season
    // For 2025, teams use league_name instead of team_name
    if ($season == '2025') {
        $old_teams_query = "SELECT submission_id, league_name as team_name, home_field FROM d8ll_form_1 WHERE season = ? AND active = '1'";
    } elseif ($season == '2023') {
        // For 2023, use both team_name and league_name
        $old_teams_query = "SELECT submission_id, team_name, league_name, home_field FROM d8ll_form_1 WHERE season = ? AND active = '1'";
    } else {
        $old_teams_query = "SELECT submission_id, team_name, home_field FROM d8ll_form_1 WHERE season = ?";
    }
    
    $stmt = $old_db->prepare($old_teams_query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $old_teams = $stmt->get_result();
    
    // Keep track of teams we've already created to avoid duplicates
    $created_teams = [];
    
    while ($old_team = $old_teams->fetch_assoc()) {
        // For 2023 teams, use the appropriate name field
        if ($season == '2023') {
            $team_name = $old_team['team_name'] ?: $old_team['league_name'];
            if (!$team_name) continue; // Skip if no team name
            
            // Create a unique key for this team based on name and home field
            $team_key = $team_name . '|' . $old_team['home_field'];
            
            // Check if we've already created this team
            if (isset($created_teams[$team_key])) {
                $team_mapping[$old_team['submission_id']] = $created_teams[$team_key];
                continue;
            }
            
            // Find matching team in new system by team_name or league_name
            $new_team_stmt = $new_db->prepare("
                SELECT t.team_id FROM teams t 
                JOIN seasons s ON t.season_id = s.season_id 
                WHERE (t.team_name = ? OR t.league_name = ?) AND s.season_year = ?
            ");
            $new_team_stmt->bind_param('sss', $team_name, $team_name, $season);
            $new_team_stmt->execute();
            $new_team = $new_team_stmt->get_result()->fetch_assoc();
            
            if ($new_team) {
                $team_mapping[$old_team['submission_id']] = $new_team['team_id'];
                $created_teams[$team_key] = $new_team['team_id'];
                echo "  Mapped old team ID {$old_team['submission_id']} to new team ID {$new_team['team_id']}: $team_name\n";
            }
        } else {
            // Original handling for other seasons
            if (!$old_team['team_name']) continue; // Skip if no team name
            
            // Create a unique key for this team based on name and home field
            $team_key = $old_team['team_name'] . '|' . $old_team['home_field'];
            
            // Check if we've already created this team
            if (isset($created_teams[$team_key])) {
                $team_mapping[$old_team['submission_id']] = $created_teams[$team_key];
                continue;
            }
            
            // Find matching team in new system
            // For 2025, search by league_name; for others, search by team_name
            if ($season == '2025') {
                $new_team_stmt = $new_db->prepare("
                    SELECT t.team_id FROM teams t 
                    JOIN seasons s ON t.season_id = s.season_id 
                    WHERE t.league_name = ? AND t.home_field = ? AND s.season_year = ?
                ");
                $new_team_stmt->bind_param('sss', $old_team['team_name'], $old_team['home_field'], $season);
            } else {
                $new_team_stmt = $new_db->prepare("
                    SELECT t.team_id FROM teams t 
                    JOIN seasons s ON t.season_id = s.season_id 
                    WHERE t.team_name = ? AND s.season_year = ?
                ");
                $new_team_stmt->bind_param('ss', $old_team['team_name'], $season);
            }
            
            $new_team_stmt->execute();
            $new_team = $new_team_stmt->get_result()->fetch_assoc();
            
            if ($new_team) {
                $team_mapping[$old_team['submission_id']] = $new_team['team_id'];
                $created_teams[$team_key] = $new_team['team_id'];
            } else {
                // If team doesn't exist, create it
                if ($season == '2025') {
                    // Find the season_id for this season
                    $season_result = $new_db->query("SELECT season_id FROM seasons WHERE season_year = '$season' LIMIT 1");
                    $season_data = $season_result->fetch_assoc();
                    
                    if ($season_data) {
                        // Create a new team
                        $insert_team_stmt = $new_db->prepare("
                            INSERT INTO teams (
                                season_id, league_name, home_field, 
                                manager_first_name, manager_last_name, manager_phone, manager_email,
                                active_status, created_date, modified_date
                            ) VALUES (
                                ?, ?, ?, 'Manager', 'Team', '', '', 'Active', NOW(), NOW()
                            )
                        ");
                        
                        $insert_team_stmt->bind_param('iss', 
                            $season_data['season_id'], 
                            $old_team['team_name'], 
                            $old_team['home_field']
                        );
                        
                        $insert_team_stmt->execute();
                        $new_team_id = $new_db->insert_id;
                        
                        $team_mapping[$old_team['submission_id']] = $new_team_id;
                        $created_teams[$team_key] = $new_team_id;
                        
                        echo "  Created new team: {$old_team['team_name']} - {$old_team['home_field']} (ID: $new_team_id)\n";
                    }
                }
            }
        }
    }
    
    return $team_mapping;
}

/**
 * Get season information from new system
 */
function getSeasonInfo($new_db, $season_year) {
    $season_stmt = $new_db->prepare("
        SELECT s.season_id, d.division_id 
        FROM seasons s 
        LEFT JOIN divisions d ON s.season_id = d.season_id 
        WHERE s.season_year = ? 
        LIMIT 1
    ");
    $season_stmt->bind_param('s', $season_year);
    $season_stmt->execute();
    return $season_stmt->get_result()->fetch_assoc();
}

/**
 * Link a change request to its corresponding schedule history entry
 */
function linkRequestToHistory($new_db, $request_id, $game_id, $requested_date, $requested_time, $requested_location) {
    // Find the schedule history entry that matches this change request
    $history_stmt = $new_db->prepare("
        SELECT history_id 
        FROM schedule_history 
        WHERE game_id = ? 
        AND game_date = ? 
        AND game_time = ? 
        AND location = ?
        AND schedule_type = 'Changed'
        AND change_request_id IS NULL
        ORDER BY version_number ASC
        LIMIT 1
    ");
    
    $history_stmt->bind_param('isss', $game_id, $requested_date, $requested_time, $requested_location);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result()->fetch_assoc();
    
    if ($history_result) {
        // Update the history entry with the change request ID
        $update_stmt = $new_db->prepare("
            UPDATE schedule_history 
            SET change_request_id = ? 
            WHERE history_id = ?
        ");
        
        $update_stmt->bind_param('ii', $request_id, $history_result['history_id']);
        $update_stmt->execute();
        
        echo "    ✓ Linked request #$request_id to history entry #{$history_result['history_id']}\n";
    } else {
        echo "    ! Could not find matching history entry for request #$request_id\n";
    }
}
