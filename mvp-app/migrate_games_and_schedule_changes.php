<?php
/**
 * District 8 Travel League - Games and Schedule Changes Migration
 * Migrates games and schedule change requests from the old FormTools system to MVP app
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$seasons_to_migrate = ['2024', '2023', '2022', '2021']; // Migrate multiple recent seasons
$temp_db_name = 'temp_d8tl_old_games';

echo "=== District 8 Travel League Games & Schedule Changes Migration ===\n";
echo "Migrating seasons: " . implode(', ', $seasons_to_migrate) . "\n\n";

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
    echo "Starting games and schedule changes migration...\n\n";
    
    foreach ($seasons_to_migrate as $season) {
        echo "=== Migrating Season $season ===\n";
        
        // 1. Migrate Games for this season
        echo "1. Migrating Games for $season...\n";
        migrateGames($mysql, $new_db, $season);
        
        // 2. Migrate Schedule Changes for this season
        echo "2. Migrating Schedule Changes for $season...\n";
        migrateScheduleChanges($mysql, $new_db, $season);
        
        echo "✓ Season $season migration complete\n\n";
    }
    
    // Cleanup
    echo "Cleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== Games & Schedule Changes Migration Complete ===\n";
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
 * Migrate Games from old system
 */
function migrateGames($old_db, $new_db, $season) {
    // Get games from old system with enhanced data
    $query = "SELECT DISTINCT 
                f3.game_no, f3.program_id, f3.season, f3.division,
                f3.away_team, f3.home_team, f3.away_score, f3.home_score, 
                f3.score_submitted_by, f3.submission_date, f3.last_modified_date,
                f2.sched_date, f2.sched_time, f2.sched_location, f2.comment as schedule_comment,
                f2.approved as schedule_approved, f2.admin_note, f2.umpire1_id, f2.umpire2_id
              FROM d8ll_form_3 f3
              LEFT JOIN d8ll_form_2 f2 ON f3.game_no = f2.game_no
              WHERE f3.season = ?
              ORDER BY f3.program_id, f3.game_no";
              
    $stmt = $old_db->prepare($query);
    $stmt->bind_param('s', $season);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get mappings from new database
    $mappings = getGameMappings($new_db, $season);
    
    $games_migrated = 0;
    $schedules_migrated = 0;
    $skipped_games = [];
    
    while ($row = $result->fetch_assoc()) {
        if (!isset($mappings['seasons'][$row['program_id']])) {
            $skipped_games[] = "Game {$row['game_no']}: No season found for program {$row['program_id']}";
            continue;
        }
        
        $season_id = $mappings['seasons'][$row['program_id']];
        $division_id = isset($mappings['divisions'][$row['program_id']][$row['division']]) ? 
                      $mappings['divisions'][$row['program_id']][$row['division']] : null;
        
        // Find team IDs using multiple strategies
        $away_team_id = findTeamId($mappings['teams'][$row['program_id']], $row['away_team']);
        $home_team_id = findTeamId($mappings['teams'][$row['program_id']], $row['home_team']);
        
        if (!$away_team_id || !$home_team_id) {
            $skipped_games[] = "Game {$row['game_no']}: Could not find teams ({$row['away_team']} vs {$row['home_team']})";
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
                echo "  ✓ Migrated game: {$row['game_no']} ($game_status)\n";
                
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
    
    echo "  Games migrated: $games_migrated\n";
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
 * Migrate Schedule Changes from old system
 */
function migrateScheduleChanges($old_db, $new_db, $season) {
    // Get schedule change requests from old system
    $query = "SELECT 
                f2.submission_id, f2.game_no, f2.season, f2.sched_date, f2.sched_time, 
                f2.sched_location, f2.comment, f2.submitter_name, f2.submitter_email,
                f2.approved, f2.admin_note, f2.submission_date, f2.last_modified_date,
                f2.away_team_email, f2.home_team_email
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
        $request_type = determineRequestType($row['comment'], $row['approved']);
        $request_status = mapApprovalStatus($row['approved']);
        
        // Get original schedule data (if available)
        $original_schedule = getOriginalSchedule($new_db, $game_id);
        
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
    
    echo "  Schedule changes migrated: $changes_migrated\n";
    
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
 * Get mappings for games migration
 */
function getGameMappings($new_db, $season) {
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
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $mappings['seasons'][$program_key] = $row['season_id'];
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
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        $division_name = ($row['division_name'] == 'American League') ? '1' : 
                        (($row['division_name'] == 'National League') ? '2' : '3');
        $mappings['divisions'][$program_key][$division_name] = $row['division_id'];
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
        $program_key = ($row['program_code'] == 'JR') ? '1' : (($row['program_code'] == 'SR') ? '2' : '3');
        
        // Multiple team name formats for better matching
        $team_keys = [
            strtoupper($row['league_name'] . '-' . $row['manager_last_name']),
            strtoupper($row['league_name']),
            strtoupper($row['team_name']),
            strtoupper($row['manager_last_name'])
        ];
        
        foreach ($team_keys as $key) {
            if ($key) {
                $mappings['teams'][$program_key][$key] = $row['team_id'];
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
 * Find team ID using multiple strategies
 */
function findTeamId($team_mappings, $team_name) {
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
function determineRequestType($comment, $approved) {
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
function mapApprovalStatus($approved) {
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
function getOriginalSchedule($new_db, $game_id) {
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

