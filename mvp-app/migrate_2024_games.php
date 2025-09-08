<?php
/**
 * District 8 Travel League - 2024 Games Migration
 * Focused migration for 2024 games and schedule changes
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$target_season = '2024';
$temp_db_name = 'temp_d8tl_2024_games';

echo "=== District 8 Travel League 2024 Games Migration ===\n";
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
    
    // Check existing data
    echo "Checking existing data...\n";
    checkExistingData($new_db, $target_season);
    
    // Start migration process
    echo "\nStarting 2024 games migration...\n\n";
    
    // 1. Migrate Games
    echo "1. Migrating Games for $target_season...\n";
    migrateGames2024($mysql, $new_db, $target_season);
    
    // 2. Migrate Schedule Changes
    echo "2. Migrating Schedule Changes for $target_season...\n";
    migrateScheduleChanges2024($mysql, $new_db, $target_season);
    
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
 * Check existing data in MVP database
 */
function checkExistingData($new_db, $season) {
    // Check programs and seasons
    $result = $new_db->query("
        SELECT p.program_code, p.program_name, s.season_year, s.season_name, s.season_id
        FROM programs p 
        JOIN seasons s ON p.program_id = s.program_id 
        WHERE s.season_year = $season
        ORDER BY p.program_code
    ");
    
    echo "  Available seasons for $season:\n";
    $season_mappings = [];
    while ($row = $result->fetch_assoc()) {
        echo "    - {$row['program_code']}: {$row['season_name']} (ID: {$row['season_id']})\n";
        $season_mappings[$row['program_code']] = $row['season_id'];
    }
    
    // Check teams
    $team_result = $new_db->query("
        SELECT COUNT(*) as count, p.program_code
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $season
        GROUP BY p.program_code
    ");
    
    echo "  Teams per program for $season:\n";
    while ($row = $team_result->fetch_assoc()) {
        echo "    - {$row['program_code']}: {$row['count']} teams\n";
    }
    
    return $season_mappings;
}

/**
 * Migrate 2024 Games with correct mappings
 */
function migrateGames2024($old_db, $new_db, $season) {
    // Get games from old system
    $query = "SELECT DISTINCT 
                f3.game_no, f3.program_id, f3.season, f3.division,
                f3.away_team, f3.home_team, f3.away_score, f3.home_score, 
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
    
    // Get mappings from new database
    $mappings = get2024Mappings($new_db, $season);
    
    $games_migrated = 0;
    $schedules_migrated = 0;
    $skipped_games = [];
    
    echo "  Found " . $result->num_rows . " games in old system\n";
    
    while ($row = $result->fetch_assoc()) {
        // Map old program_id to new program codes
        $program_code = mapProgramId($row['program_id']);
        
        if (!$program_code || !isset($mappings['seasons'][$program_code])) {
            $skipped_games[] = "Game {$row['game_no']}: No season found for program {$row['program_id']} (mapped to $program_code)";
            continue;
        }
        
        $season_id = $mappings['seasons'][$program_code];
        $division_id = isset($mappings['divisions'][$program_code][$row['division']]) ? 
                      $mappings['divisions'][$program_code][$row['division']] : null;
        
        // Find team IDs
        $away_team_id = findTeamId2024($mappings['teams'][$program_code] ?? [], $row['away_team']);
        $home_team_id = findTeamId2024($mappings['teams'][$program_code] ?? [], $row['home_team']);
        
        if (!$away_team_id || !$home_team_id) {
            $skipped_games[] = "Game {$row['game_no']}: Could not find teams ({$row['away_team']} vs {$row['home_team']}) in program $program_code";
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
            if ($row['away_score'] !== null && $row['home_score'] !== null) {
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
            
            $created_date = $row['submission_date'] ?: date('Y-m-d H:i:s');
            $modified_date = $row['last_modified_date'] ?: $created_date;
            
            $insert_game_stmt->bind_param('siiiiiisss',
                $row['game_no'], $season_id, $division_id, $home_team_id, $away_team_id,
                $row['home_score'], $row['away_score'], $game_status, $row['score_submitted_by'],
                $created_date, $modified_date
            );
            
            if ($insert_game_stmt->execute()) {
                $game_id = $new_db->insert_id;
                $games_migrated++;
                echo "  ✓ Migrated game: {$row['game_no']} ({$row['away_team']} vs {$row['home_team']}) - $game_status\n";
                
                // Insert schedule if we have schedule data
                if ($row['sched_date']) {
                    $location_id = isset($mappings['locations'][strtoupper($row['sched_location'])]) ? 
                                  $mappings['locations'][strtoupper($row['sched_location'])] : null;
                    
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
 * Migrate 2024 Schedule Changes
 */
function migrateScheduleChanges2024($old_db, $new_db, $season) {
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
        $check_stmt = $new_db->prepare("
            SELECT request_id FROM schedule_change_requests 
            WHERE game_id = ? AND created_date = ?
        ");
        $created_date = $row['submission_date'] ?: date('Y-m-d H:i:s');
        $check_stmt->bind_param('is', $game_id, $created_date);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            continue; // Skip duplicates
        }
        
        // Determine request type and status
        $request_type = determineRequestType2024($row['comment'], $row['approved']);
        $request_status = mapApprovalStatus2024($row['approved']);
        
        // Get original schedule data (if available)
        $original_schedule = getOriginalSchedule2024($new_db, $game_id);
        
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
        $requested_date = $row['sched_date'] ? date('Y-m-d', strtotime($row['sched_date'])) : null;
        $requested_time = $row['sched_time'] ? date('H:i:s', strtotime($row['sched_time'])) : null;
        $reason = $row['comment'];
        $review_notes = $row['admin_note'];
        
        $insert_stmt->bind_param('isssssssssss',
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
 * Map old program_id to new program codes
 */
function mapProgramId($program_id) {
    // Based on the old system analysis:
    // program_id 1 = Junior Baseball (JR)
    // program_id 2 = Senior Baseball (SR)
    // program_id 3 = Majors Baseball (could be JRBB or SRBB)
    
    switch ($program_id) {
        case '1':
            return 'JR';
        case '2':
            return 'SR';
        case '3':
            return 'JRBB'; // Default to JRBB for majors
        default:
            return null;
    }
}

/**
 * Get mappings for 2024 migration
 */
function get2024Mappings($new_db, $season) {
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
            strtoupper($row['manager_last_name'])
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
 * Find team ID using multiple strategies for 2024
 */
function findTeamId2024($team_mappings, $team_name) {
    if (!$team_name || !$team_mappings) {
        return null;
    }
    
    $team_name = strtoupper(trim($team_name));
    
    // Direct match
    if (isset($team_mappings[$team_name])) {
        return $team_mappings[$team_name];
    }
    
    // Try partial matches
    foreach ($team_mappings as $key => $team_id) {
        if (strpos($key, $team_name) !== false || strpos($team_name, $key) !== false) {
            return $team_id;
        }
    }
    
    return null;
}

/**
 * Determine request type from comment and approval status
 */
function determineRequestType2024($comment, $approved) {
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
function mapApprovalStatus2024($approved) {
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
function getOriginalSchedule2024($new_db, $game_id) {
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

