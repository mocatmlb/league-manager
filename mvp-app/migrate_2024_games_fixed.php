<?php
/**
 * District 8 Travel League - 2024 Games Migration (Fixed)
 * Correctly maps team IDs from old system to new system
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$target_season = '2024';
$temp_db_name = 'temp_d8tl_2024_games_fixed';

echo "=== District 8 Travel League 2024 Games Migration (Fixed) ===\n";
echo "Target Season: $target_season\n\n";

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
    
    // Start migration process
    echo "\nStarting 2024 games migration (fixed)...\n\n";
    
    // 1. Migrate Games
    echo "1. Migrating Games for $target_season...\n";
    migrateGamesFixed($mysql, $new_db, $target_season);
    
    // 2. Migrate Schedule Changes
    echo "2. Migrating Schedule Changes for $target_season...\n";
    migrateScheduleChangesFixed($mysql, $new_db, $target_season);
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== 2024 Games Migration Complete ===\n";
    echo "Migration finished successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}

/**
 * Migrate 2024 Games with correct team ID mapping
 */
function migrateGamesFixed($old_db, $new_db, $season) {
    // First, build a mapping of old team IDs to team names
    echo "  Building team ID to name mapping...\n";
    $old_team_mapping = buildOldTeamMapping($old_db, $season);
    echo "  Found " . count($old_team_mapping) . " teams in old system\n";
    
    // Get new system mappings
    $new_mappings = getNewSystemMappings($new_db, $season);
    
    // Get games from old system using team IDs
    $query = "SELECT DISTINCT 
                f3.game_no, f3.program_id, f3.season, f3.division,
                f3.away_team_id, f3.home_team_id, f3.away_score, f3.home_score, 
                f3.score_submitted_by, f3.submission_date, f3.last_modified_date,
                f2.sched_date, f2.sched_time, f2.sched_location, f2.comment as schedule_comment,
                f2.approved as schedule_approved, f2.admin_note
              FROM d8ll_form_3 f3
              LEFT JOIN d8ll_form_2 f2 ON f3.game_no = f2.game_no AND f2.comment = 'INITIAL SCHEDULE'
              WHERE f3.season = ?
              ORDER BY f3.program_id, f3.game_no";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $games_migrated = 0;
    $schedules_migrated = 0;
    $skipped_games = [];
    
    echo "  Found " . $result->num_rows . " games in old system\n";
    
    while ($row = $result->fetch_assoc()) {
        // Map old program_id to new program codes
        $program_code = mapProgramIdFixed($row['program_id']);
        
        if (!$program_code || !isset($new_mappings['seasons'][$program_code])) {
            $skipped_games[] = "Game {$row['game_no']}: No season found for program {$row['program_id']} (mapped to $program_code)";
            continue;
        }
        
        $season_id = $new_mappings['seasons'][$program_code];
        $division_id = isset($new_mappings['divisions'][$program_code][$row['division']]) ? 
                      $new_mappings['divisions'][$program_code][$row['division']] : null;
        
        // Get team names from old team mapping
        $away_team_name = isset($old_team_mapping[$row['away_team_id']]) ? 
                         $old_team_mapping[$row['away_team_id']]['name'] : null;
        $home_team_name = isset($old_team_mapping[$row['home_team_id']]) ? 
                         $old_team_mapping[$row['home_team_id']]['name'] : null;
        
        if (!$away_team_name || !$home_team_name) {
            $skipped_games[] = "Game {$row['game_no']}: Could not find team names for IDs {$row['away_team_id']} and {$row['home_team_id']}";
            continue;
        }
        
        // Find team IDs in new system
        $away_team_id = findNewTeamId($new_mappings['teams'][$program_code] ?? [], $away_team_name, $old_team_mapping[$row['away_team_id']]);
        $home_team_id = findNewTeamId($new_mappings['teams'][$program_code] ?? [], $home_team_name, $old_team_mapping[$row['home_team_id']]);
        
        if (!$away_team_id || !$home_team_id) {
            $skipped_games[] = "Game {$row['game_no']}: Could not find teams ($away_team_name vs $home_team_name) in program $program_code";
            continue;
        }
        
        // Check if game already exists
        $check_stmt = $new_db->prepare("SELECT game_id FROM games WHERE game_number = ?");
        $check_stmt->bind_param('s', $row['game_no']);
        $check_stmt->execute();
        $existing_game = $check_stmt->get_result()->fetch_assoc();
        
        if (!$existing_game) {
            // Determine game status
            $game_status = 'Active';
            if ($row['away_score'] !== null && $row['home_score'] !== null && 
                ($row['away_score'] > 0 || $row['home_score'] > 0)) {
                $game_status = 'Completed';
            } elseif ($row['schedule_approved'] == '3') {
                $game_status = 'Cancelled';
            }
            
            // Insert game
            $insert_game_stmt = $new_db->prepare("
                INSERT INTO games (
                    game_number, season_id, division_id, home_team_id, away_team_id,
                    home_score, away_score, game_status, score_submitted_by,
                    created_date, modified_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $created_date = $row['submission_date'] && $row['submission_date'] != '0000-00-00 00:00:00' ? 
                           $row['submission_date'] : date('Y-m-d H:i:s');
            $modified_date = $row['last_modified_date'] && $row['last_modified_date'] != '0000-00-00 00:00:00' ? 
                           $row['last_modified_date'] : $created_date;
            
            $insert_game_stmt->bind_param('siiiiiissss',
                $row['game_no'], $season_id, $division_id, $home_team_id, $away_team_id,
                $row['home_score'], $row['away_score'], $game_status, $row['score_submitted_by'],
                $created_date, $modified_date
            );
            
            if ($insert_game_stmt->execute()) {
                $game_id = $new_db->insert_id;
                $games_migrated++;
                echo "  ✓ Migrated game: {$row['game_no']} ($away_team_name vs $home_team_name) - $game_status [{$row['away_score']}-{$row['home_score']}]\n";
                
                // Insert schedule if we have schedule data
                if ($row['sched_date'] && $row['sched_date'] != '0000-00-00') {
                    $location_id = isset($new_mappings['locations'][strtoupper($row['sched_location'])]) ? 
                                  $new_mappings['locations'][strtoupper($row['sched_location'])] : null;
                    
                    $insert_schedule_stmt = $new_db->prepare("
                        INSERT INTO schedules (game_id, game_date, game_time, location, location_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $game_date = date('Y-m-d', strtotime($row['sched_date']));
                    $game_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : null;
                    
                    $insert_schedule_stmt->bind_param('isssi',
                        $game_id, $game_date, $game_time, $row['sched_location'], $location_id
                    );
                    
                    if ($insert_schedule_stmt->execute()) {
                        $schedules_migrated++;
                    }
                }
            }
        } else {
            echo "  - Game {$row['game_no']} already exists\n";
        }
    }
    
    echo "\n  Games migrated: $games_migrated\n";
    echo "  Schedules migrated: $schedules_migrated\n";
    
    if (!empty($skipped_games)) {
        echo "  Skipped games (" . count($skipped_games) . "):\n";
        foreach (array_slice($skipped_games, 0, 5) as $skip) {
            echo "    ! $skip\n";
        }
        if (count($skipped_games) > 5) {
            echo "    ... and " . (count($skipped_games) - 5) . " more\n";
        }
    }
    echo "\n";
}

/**
 * Build mapping of old team IDs to team information
 */
function buildOldTeamMapping($old_db, $season) {
    $query = "SELECT submission_id, program_id, league_name, team_name, manager_last_name, division
              FROM d8ll_form_1 
              WHERE season = ? AND active = '1'";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mapping = [];
    while ($row = $result->fetch_assoc()) {
        $team_name = $row['team_name'] ?: $row['league_name'] . '-' . $row['manager_last_name'];
        $mapping[$row['submission_id']] = [
            'name' => $team_name,
            'league_name' => $row['league_name'],
            'manager_last_name' => $row['manager_last_name'],
            'program_id' => $row['program_id'],
            'division' => $row['division']
        ];
    }
    
    return $mapping;
}

/**
 * Get mappings from new system
 */
function getNewSystemMappings($new_db, $season) {
    $mappings = [
        'seasons' => [],
        'divisions' => [],
        'teams' => [],
        'locations' => []
    ];
    
    // Season mappings
    $season_result = $new_db->query("
        SELECT s.season_id, p.program_code 
        FROM seasons s 
        JOIN programs p ON s.program_id = p.program_id 
        WHERE s.season_year = $season
    ");
    while ($row = $season_result->fetch_assoc()) {
        $mappings['seasons'][$row['program_code']] = $row['season_id'];
    }
    
    // Division mappings
    $division_result = $new_db->query("
        SELECT d.division_id, d.division_name, s.season_id, p.program_code
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $season
    ");
    while ($row = $division_result->fetch_assoc()) {
        $division_key = ($row['division_name'] == 'American League') ? '1' : 
                       (($row['division_name'] == 'National League') ? '2' : '3');
        $mappings['divisions'][$row['program_code']][$division_key] = $row['division_id'];
    }
    
    // Team mappings with multiple lookup strategies
    $team_result = $new_db->query("
        SELECT t.team_id, t.league_name, t.manager_last_name, t.team_name, s.season_id, p.program_code
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $season
    ");
    while ($row = $team_result->fetch_assoc()) {
        // Multiple team name formats for better matching
        $team_keys = [
            strtoupper($row['league_name'] . '-' . $row['manager_last_name']),
            strtoupper($row['league_name']),
            strtoupper($row['team_name']),
            strtoupper($row['manager_last_name']),
            strtoupper($row['team_name'] ?: $row['league_name'])
        ];
        
        foreach ($team_keys as $key) {
            if ($key) {
                $mappings['teams'][$row['program_code']][$key] = $row['team_id'];
            }
        }
    }
    
    // Location mappings
    $location_result = $new_db->query("SELECT location_id, location_name FROM locations");
    while ($row = $location_result->fetch_assoc()) {
        $mappings['locations'][strtoupper($row['location_name'])] = $row['location_id'];
    }
    
    return $mappings;
}

/**
 * Find team ID in new system using multiple strategies
 */
function findNewTeamId($team_mappings, $team_name, $old_team_info) {
    if (!$team_mappings) {
        return null;
    }
    
    // Try multiple variations of the team name
    $search_keys = [
        strtoupper($team_name),
        strtoupper($old_team_info['league_name'] . '-' . $old_team_info['manager_last_name']),
        strtoupper($old_team_info['league_name']),
        strtoupper($old_team_info['manager_last_name'])
    ];
    
    foreach ($search_keys as $key) {
        if ($key && isset($team_mappings[$key])) {
            return $team_mappings[$key];
        }
    }
    
    // Try partial matches
    foreach ($team_mappings as $key => $team_id) {
        foreach ($search_keys as $search_key) {
            if ($search_key && (strpos($key, $search_key) !== false || strpos($search_key, $key) !== false)) {
                return $team_id;
            }
        }
    }
    
    return null;
}

/**
 * Map old program_id to new program codes
 */
function mapProgramIdFixed($program_id) {
    switch ($program_id) {
        case '1':
            return 'JR';
        case '2':
            return 'SR';
        case '3':
            return 'JRBB'; // Majors
        default:
            return null;
    }
}

/**
 * Migrate 2024 Schedule Changes
 */
function migrateScheduleChangesFixed($old_db, $new_db, $season) {
    // Get schedule change requests from old system
    $query = "SELECT 
                f2.submission_id, f2.game_no, f2.season, f2.sched_date, f2.sched_time, 
                f2.sched_location, f2.comment, f2.submitter_name, f2.submitter_email,
                f2.approved, f2.admin_note, f2.submission_date, f2.last_modified_date
              FROM d8ll_form_2 f2
              WHERE f2.season = ? 
              AND f2.comment IS NOT NULL 
              AND f2.comment != '' 
              AND f2.comment != 'INITIAL SCHEDULE'
              AND f2.comment != '*ORIGINGAL GAME*'
              ORDER BY f2.submission_date";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $changes_migrated = 0;
    $skipped_changes = [];
    
    echo "  Found " . $result->num_rows . " schedule change requests in old system\n";
    
    while ($row = $result->fetch_assoc()) {
        // Find the corresponding game in the new system
        $game_query = "SELECT game_id FROM games WHERE game_number = ?";
        $game_stmt = $new_db->prepare($game_query);
        $game_stmt->bind_param('s', $row['game_no']);
        $game_stmt->execute();
        $game_result = $game_stmt->get_result()->fetch_assoc();
        
        if (!$game_result) {
            $skipped_changes[] = "Change for game {$row['game_no']}: Game not found in new system";
            continue;
        }
        
        $game_id = $game_result['game_id'];
        
        // Check if this change request already exists
        $created_date = $row['submission_date'] && $row['submission_date'] != '0000-00-00 00:00:00' ? 
                       $row['submission_date'] : date('Y-m-d H:i:s');
        
        $check_stmt = $new_db->prepare("
            SELECT request_id FROM schedule_change_requests 
            WHERE game_id = ? AND created_date = ?
        ");
        $check_stmt->bind_param('is', $game_id, $created_date);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            continue; // Skip duplicates
        }
        
        // Determine request type and status
        $request_type = determineRequestTypeFixed($row['comment'], $row['approved']);
        $request_status = mapApprovalStatusFixed($row['approved']);
        
        // Get original schedule data (if available)
        $original_schedule = getOriginalScheduleFixed($new_db, $game_id);
        
        // Insert schedule change request
        $insert_stmt = $new_db->prepare("
            INSERT INTO schedule_change_requests (
                game_id, requested_by, request_type, 
                original_date, original_time, original_location,
                requested_date, requested_time, requested_location,
                reason, request_status, review_notes, created_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $requested_by = $row['submitter_name'] ?: $row['submitter_email'] ?: 'Unknown';
        $requested_date = $row['sched_date'] && $row['sched_date'] != '0000-00-00' ? 
                         date('Y-m-d', strtotime($row['sched_date'])) : null;
        $requested_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : null;
        $reason = $row['comment'];
        $review_notes = $row['admin_note'];
        
        $insert_stmt->bind_param('issssssssssss',
            $game_id, $requested_by, $request_type,
            $original_schedule['date'], $original_schedule['time'], $original_schedule['location'],
            $requested_date, $requested_time, $row['sched_location'],
            $reason, $request_status, $review_notes, $created_date
        );
        
        if ($insert_stmt->execute()) {
            $changes_migrated++;
            echo "  ✓ Migrated schedule change: Game {$row['game_no']} - $request_type ($request_status)\n";
        }
    }
    
    echo "\n  Schedule changes migrated: $changes_migrated\n";
    
    if (!empty($skipped_changes)) {
        echo "  Skipped changes (" . count($skipped_changes) . "):\n";
        foreach (array_slice($skipped_changes, 0, 3) as $skip) {
            echo "    ! $skip\n";
        }
        if (count($skipped_changes) > 3) {
            echo "    ... and " . (count($skipped_changes) - 3) . " more\n";
        }
    }
    echo "\n";
}

/**
 * Determine request type from comment and approval status
 */
function determineRequestTypeFixed($comment, $approved) {
    $comment_lower = strtolower($comment);
    
    if ($approved == '3' || strpos($comment_lower, 'cancel') !== false) {
        return 'Cancel';
    } elseif (strpos($comment_lower, 'field') !== false || 
              strpos($comment_lower, 'location') !== false) {
        return 'Location Change';
    } else {
        return 'Reschedule';
    }
}

/**
 * Map old approval status to new system
 */
function mapApprovalStatusFixed($approved) {
    switch ($approved) {
        case '1':
            return 'Approved';
        case '2':
            return 'Denied';
        case '3':
            return 'Approved'; // Cancelled games are approved cancellations
        case '0':
        default:
            return 'Pending';
    }
}

/**
 * Get original schedule data for a game
 */
function getOriginalScheduleFixed($new_db, $game_id) {
    $stmt = $new_db->prepare("
        SELECT game_date, game_time, location 
        FROM schedules 
        WHERE game_id = ? 
        ORDER BY created_date ASC 
        LIMIT 1
    ");
    $stmt->bind_param('i', $game_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return [
        'date' => $result['game_date'] ?? null,
        'time' => $result['game_time'] ?? null,
        'location' => $result['location'] ?? null
    ];
}
