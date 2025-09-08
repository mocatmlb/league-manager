<?php
/**
 * District 8 Travel League - Comprehensive Migration Validation
 * Validates migrated games, schedules, and schedule changes
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League Migration Validation ===\n";
echo "Validating migrated data integrity...\n\n";

try {
    $new_db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($new_db->connect_error) {
        throw new Exception("Failed to connect to database: " . $new_db->connect_error);
    }
    
    echo "✓ Connected to database\n\n";
    
    // Run validation checks
    $validation_results = [];
    
    echo "=== VALIDATION CHECKS ===\n\n";
    
    // 1. Games validation
    echo "1. Games Validation:\n";
    $validation_results['games'] = validateGames($new_db);
    
    // 2. Schedules validation
    echo "2. Schedules Validation:\n";
    $validation_results['schedules'] = validateSchedules($new_db);
    
    // 3. Schedule history validation
    echo "3. Schedule History Validation:\n";
    $validation_results['schedule_history'] = validateScheduleHistory($new_db);
    
    // 4. Schedule change requests validation
    echo "4. Schedule Change Requests Validation:\n";
    $validation_results['change_requests'] = validateScheduleChangeRequests($new_db);
    
    // 5. Data integrity validation
    echo "5. Data Integrity Validation:\n";
    $validation_results['integrity'] = validateDataIntegrity($new_db);
    
    // Summary
    echo "\n=== VALIDATION SUMMARY ===\n";
    $total_checks = 0;
    $passed_checks = 0;
    
    foreach ($validation_results as $category => $results) {
        $category_passed = 0;
        $category_total = count($results);
        
        foreach ($results as $check => $passed) {
            if ($passed) $category_passed++;
        }
        
        $total_checks += $category_total;
        $passed_checks += $category_passed;
        
        $status = ($category_passed == $category_total) ? "✅ PASS" : "❌ FAIL";
        echo sprintf("%-20s: %s (%d/%d checks passed)\n", 
                    ucfirst($category), $status, $category_passed, $category_total);
    }
    
    echo "\nOverall: ";
    if ($passed_checks == $total_checks) {
        echo "✅ ALL VALIDATIONS PASSED ($passed_checks/$total_checks)\n";
        echo "Migration data integrity is confirmed!\n";
    } else {
        echo "❌ SOME VALIDATIONS FAILED ($passed_checks/$total_checks)\n";
        echo "Please review the failed checks above.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Validate games data
 */
function validateGames($db) {
    $results = [];
    
    // Check games exist
    $games_count = $db->query("SELECT COUNT(*) as count FROM games")->fetch_assoc()['count'];
    $results['games_exist'] = $games_count > 0;
    echo "  Games count: $games_count " . ($results['games_exist'] ? "✓" : "✗") . "\n";
    
    // Check games have valid teams
    $invalid_teams = $db->query("
        SELECT COUNT(*) as count FROM games g 
        LEFT JOIN teams ht ON g.home_team_id = ht.team_id 
        LEFT JOIN teams at ON g.away_team_id = at.team_id 
        WHERE ht.team_id IS NULL OR at.team_id IS NULL
    ")->fetch_assoc()['count'];
    $results['valid_teams'] = $invalid_teams == 0;
    echo "  Games with invalid teams: $invalid_teams " . ($results['valid_teams'] ? "✓" : "✗") . "\n";
    
    // Check games have valid seasons
    $invalid_seasons = $db->query("
        SELECT COUNT(*) as count FROM games g 
        LEFT JOIN seasons s ON g.season_id = s.season_id 
        WHERE s.season_id IS NULL
    ")->fetch_assoc()['count'];
    $results['valid_seasons'] = $invalid_seasons == 0;
    echo "  Games with invalid seasons: $invalid_seasons " . ($results['valid_seasons'] ? "✓" : "✗") . "\n";
    
    // Check unique game numbers
    $duplicate_games = $db->query("
        SELECT COUNT(*) - COUNT(DISTINCT game_number) as duplicates FROM games
    ")->fetch_assoc()['duplicates'];
    $results['unique_game_numbers'] = $duplicate_games == 0;
    echo "  Duplicate game numbers: $duplicate_games " . ($results['unique_game_numbers'] ? "✓" : "✗") . "\n";
    
    echo "\n";
    return $results;
}

/**
 * Validate schedules data
 */
function validateSchedules($db) {
    $results = [];
    
    // Check schedules exist
    $schedules_count = $db->query("SELECT COUNT(*) as count FROM schedules")->fetch_assoc()['count'];
    $results['schedules_exist'] = $schedules_count > 0;
    echo "  Schedules count: $schedules_count " . ($results['schedules_exist'] ? "✓" : "✗") . "\n";
    
    // Check all games have schedules
    $games_without_schedules = $db->query("
        SELECT COUNT(*) as count FROM games g 
        LEFT JOIN schedules s ON g.game_id = s.game_id 
        WHERE s.schedule_id IS NULL
    ")->fetch_assoc()['count'];
    $results['all_games_scheduled'] = $games_without_schedules == 0;
    echo "  Games without schedules: $games_without_schedules " . ($results['all_games_scheduled'] ? "✓" : "✗") . "\n";
    
    // Check schedules have valid dates
    $invalid_dates = $db->query("
        SELECT COUNT(*) as count FROM schedules 
        WHERE game_date IS NULL OR game_date < '1900-01-01'
    ")->fetch_assoc()['count'];
    $results['valid_dates'] = $invalid_dates == 0;
    echo "  Schedules with invalid dates: $invalid_dates " . ($results['valid_dates'] ? "✓" : "✗") . "\n";
    
    // Check schedules have valid times
    $invalid_times = $db->query("
        SELECT COUNT(*) as count FROM schedules 
        WHERE game_time IS NULL OR game_time = '00:00:00'
    ")->fetch_assoc()['count'];
    $results['valid_times'] = $invalid_times == 0;
    echo "  Schedules with invalid times: $invalid_times " . ($results['valid_times'] ? "✓" : "✗") . "\n";
    
    echo "\n";
    return $results;
}

/**
 * Validate schedule history data
 */
function validateScheduleHistory($db) {
    $results = [];
    
    // Check history exists
    $history_count = $db->query("SELECT COUNT(*) as count FROM schedule_history")->fetch_assoc()['count'];
    $results['history_exists'] = $history_count > 0;
    echo "  History entries count: $history_count " . ($results['history_exists'] ? "✓" : "✗") . "\n";
    
    // Check all games have original history (version 1)
    $games_without_original = $db->query("
        SELECT COUNT(DISTINCT g.game_id) as count FROM games g 
        LEFT JOIN schedule_history sh ON g.game_id = sh.game_id AND sh.version_number = 1 
        WHERE sh.history_id IS NULL
    ")->fetch_assoc()['count'];
    $results['all_games_have_original'] = $games_without_original == 0;
    echo "  Games without original history: $games_without_original " . ($results['all_games_have_original'] ? "✓" : "✗") . "\n";
    
    // Check version numbering is sequential
    $invalid_versions = $db->query("
        SELECT COUNT(*) as count FROM (
            SELECT game_id, version_number, 
                   LAG(version_number) OVER (PARTITION BY game_id ORDER BY version_number) as prev_version
            FROM schedule_history
        ) t 
        WHERE prev_version IS NOT NULL AND version_number != prev_version + 1
    ")->fetch_assoc()['count'];
    $results['sequential_versions'] = $invalid_versions == 0;
    echo "  Non-sequential version numbers: $invalid_versions " . ($results['sequential_versions'] ? "✓" : "✗") . "\n";
    
    // Check only one current entry per game
    $multiple_current = $db->query("
        SELECT COUNT(*) as count FROM (
            SELECT game_id, COUNT(*) as current_count 
            FROM schedule_history 
            WHERE is_current = 1 
            GROUP BY game_id 
            HAVING current_count > 1
        ) t
    ")->fetch_assoc()['count'];
    $results['single_current'] = $multiple_current == 0;
    echo "  Games with multiple current entries: $multiple_current " . ($results['single_current'] ? "✓" : "✗") . "\n";
    
    // Check that changed history entries are linked to change requests
    $unlinked_changes = $db->query("
        SELECT COUNT(*) as count 
        FROM schedule_history 
        WHERE schedule_type = 'Changed' 
        AND change_request_id IS NULL
    ")->fetch_assoc()['count'];
    $results['changes_linked_to_requests'] = $unlinked_changes == 0;
    echo "  Changed history entries not linked to requests: $unlinked_changes " . ($results['changes_linked_to_requests'] ? "✓" : "✗") . "\n";
    
    echo "\n";
    return $results;
}

/**
 * Validate schedule change requests data
 */
function validateScheduleChangeRequests($db) {
    $results = [];
    
    // Check change requests exist
    $requests_count = $db->query("SELECT COUNT(*) as count FROM schedule_change_requests")->fetch_assoc()['count'];
    $results['requests_exist'] = $requests_count > 0;
    echo "  Change requests count: $requests_count " . ($results['requests_exist'] ? "✓" : "✗") . "\n";
    
    // Check requests have valid games
    $invalid_games = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests scr 
        LEFT JOIN games g ON scr.game_id = g.game_id 
        WHERE g.game_id IS NULL
    ")->fetch_assoc()['count'];
    $results['valid_games'] = $invalid_games == 0;
    echo "  Requests with invalid games: $invalid_games " . ($results['valid_games'] ? "✓" : "✗") . "\n";
    
    // Check requests have original data
    $missing_original = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests 
        WHERE original_date IS NULL OR original_time IS NULL OR original_location IS NULL
    ")->fetch_assoc()['count'];
    $results['have_original_data'] = $missing_original == 0;
    echo "  Requests missing original data: $missing_original " . ($results['have_original_data'] ? "✓" : "✗") . "\n";
    
    // Check requests have requested data
    $missing_requested = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests 
        WHERE requested_date IS NULL OR requested_time IS NULL OR requested_location IS NULL
    ")->fetch_assoc()['count'];
    $results['have_requested_data'] = $missing_requested == 0;
    echo "  Requests missing requested data: $missing_requested " . ($results['have_requested_data'] ? "✓" : "✗") . "\n";
    
    // Check for incorrect original schedule requests
    $original_schedule_requests = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests 
        WHERE reason LIKE '%ORIGINAL SCHEDULE%'
    ")->fetch_assoc()['count'];
    $results['no_original_schedule_requests'] = $original_schedule_requests == 0;
    echo "  Incorrect 'ORIGINAL SCHEDULE' requests: $original_schedule_requests " . ($results['no_original_schedule_requests'] ? "✓" : "✗") . "\n";
    
    // Check for requests where original and requested are identical (no actual change)
    $identical_requests = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests 
        WHERE original_date = requested_date 
        AND original_time = requested_time 
        AND original_location = requested_location
    ")->fetch_assoc()['count'];
    $results['no_identical_requests'] = $identical_requests == 0;
    echo "  Requests with no actual change: $identical_requests " . ($results['no_identical_requests'] ? "✓" : "✗") . "\n";
    
    echo "\n";
    return $results;
}

/**
 * Validate data integrity across tables
 */
function validateDataIntegrity($db) {
    $results = [];
    
    // Check schedule consistency between schedules and history
    $inconsistent_schedules = $db->query("
        SELECT COUNT(*) as count FROM schedules s 
        JOIN schedule_history sh ON s.game_id = sh.game_id AND sh.is_current = 1 
        WHERE s.game_date != sh.game_date 
           OR s.game_time != sh.game_time 
           OR s.location != sh.location
    ")->fetch_assoc()['count'];
    $results['schedule_history_consistency'] = $inconsistent_schedules == 0;
    echo "  Inconsistent schedules vs history: $inconsistent_schedules " . ($results['schedule_history_consistency'] ? "✓" : "✗") . "\n";
    
    // Check original data consistency in change requests
    $inconsistent_originals = $db->query("
        SELECT COUNT(*) as count FROM schedule_change_requests scr 
        JOIN schedule_history sh ON scr.game_id = sh.game_id AND sh.version_number = 1 
        WHERE scr.original_date != sh.game_date 
           OR scr.original_time != sh.game_time 
           OR scr.original_location != sh.location
    ")->fetch_assoc()['count'];
    $results['original_data_consistency'] = $inconsistent_originals == 0;
    echo "  Inconsistent original data in requests: $inconsistent_originals " . ($results['original_data_consistency'] ? "✓" : "✗") . "\n";
    
    // Check for orphaned records
    $orphaned_schedules = $db->query("
        SELECT COUNT(*) as count FROM schedules s 
        LEFT JOIN games g ON s.game_id = g.game_id 
        WHERE g.game_id IS NULL
    ")->fetch_assoc()['count'];
    $results['no_orphaned_schedules'] = $orphaned_schedules == 0;
    echo "  Orphaned schedules: $orphaned_schedules " . ($results['no_orphaned_schedules'] ? "✓" : "✗") . "\n";
    
    $orphaned_history = $db->query("
        SELECT COUNT(*) as count FROM schedule_history sh 
        LEFT JOIN games g ON sh.game_id = g.game_id 
        WHERE g.game_id IS NULL
    ")->fetch_assoc()['count'];
    $results['no_orphaned_history'] = $orphaned_history == 0;
    echo "  Orphaned history entries: $orphaned_history " . ($results['no_orphaned_history'] ? "✓" : "✗") . "\n";
    
    echo "\n";
    return $results;
}
