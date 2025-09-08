<?php
/**
 * Migration Validation Script
 * Validates that data was migrated correctly
 */

require_once 'includes/bootstrap.php';

$current_season = 2025; // Set the season to validate

echo "=== Migration Validation Report ===\n";
echo "Validating migration for season: $current_season\n\n";

try {
    // Use MySQLi for consistency
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Failed to connect to database: " . $db->connect_error);
    }
    
    // 1. Validate Programs
    echo "1. Programs Validation:\n";
    $programs_result = $db->query("SELECT COUNT(*) as count FROM programs WHERE active_status = 'Active'");
    $programs_count = $programs_result->fetch_assoc()['count'];
    echo "   Active Programs: $programs_count\n";
    
    $programs_list = $db->query("SELECT program_name, program_code FROM programs WHERE active_status = 'Active'");
    while ($row = $programs_list->fetch_assoc()) {
        echo "   - {$row['program_name']} ({$row['program_code']})\n";
    }
    echo "\n";
    
    // 2. Validate Seasons
    echo "2. Seasons Validation:\n";
    $seasons_result = $db->query("SELECT COUNT(*) as count FROM seasons WHERE season_year = $current_season");
    $seasons_count = $seasons_result->fetch_assoc()['count'];
    echo "   Seasons for $current_season: $seasons_count\n";
    
    $seasons_list = $db->query("
        SELECT s.season_name, p.program_code, s.season_status 
        FROM seasons s 
        JOIN programs p ON s.program_id = p.program_id 
        WHERE s.season_year = $current_season
    ");
    while ($row = $seasons_list->fetch_assoc()) {
        echo "   - {$row['season_name']} ({$row['program_code']}) - {$row['season_status']}\n";
    }
    echo "\n";
    
    // 3. Validate Divisions
    echo "3. Divisions Validation:\n";
    $divisions_result = $db->query("
        SELECT COUNT(*) as count 
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        WHERE s.season_year = $current_season
    ");
    $divisions_count = $divisions_result->fetch_assoc()['count'];
    echo "   Divisions for $current_season: $divisions_count\n";
    
    $divisions_list = $db->query("
        SELECT d.division_name, p.program_code 
        FROM divisions d
        JOIN seasons s ON d.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season
        ORDER BY p.program_code, d.division_name
    ");
    while ($row = $divisions_list->fetch_assoc()) {
        echo "   - {$row['division_name']} ({$row['program_code']})\n";
    }
    echo "\n";
    
    // 4. Validate Locations
    echo "4. Locations Validation:\n";
    $locations_result = $db->query("SELECT COUNT(*) as count FROM locations WHERE active_status = 'Active'");
    $locations_count = $locations_result->fetch_assoc()['count'];
    echo "   Active Locations: $locations_count\n";
    
    $locations_list = $db->query("SELECT location_name FROM locations WHERE active_status = 'Active' ORDER BY location_name LIMIT 10");
    while ($row = $locations_list->fetch_assoc()) {
        echo "   - {$row['location_name']}\n";
    }
    if ($locations_count > 10) {
        echo "   ... and " . ($locations_count - 10) . " more\n";
    }
    echo "\n";
    
    // 5. Validate Teams
    echo "5. Teams Validation:\n";
    $teams_result = $db->query("
        SELECT COUNT(*) as count 
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        WHERE s.season_year = $current_season AND t.active_status = 'Active'
    ");
    $teams_count = $teams_result->fetch_assoc()['count'];
    echo "   Active Teams for $current_season: $teams_count\n";
    
    // Teams by program
    $teams_by_program = $db->query("
        SELECT p.program_code, COUNT(*) as team_count
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season AND t.active_status = 'Active'
        GROUP BY p.program_code
        ORDER BY p.program_code
    ");
    while ($row = $teams_by_program->fetch_assoc()) {
        echo "   - {$row['program_code']}: {$row['team_count']} teams\n";
    }
    
    // Sample teams
    echo "   Sample teams:\n";
    $sample_teams = $db->query("
        SELECT t.league_name, t.team_name, t.manager_last_name, p.program_code
        FROM teams t
        JOIN seasons s ON t.season_id = s.season_id
        JOIN programs p ON s.program_id = p.program_id
        WHERE s.season_year = $current_season AND t.active_status = 'Active'
        ORDER BY p.program_code, t.league_name
        LIMIT 5
    ");
    while ($row = $sample_teams->fetch_assoc()) {
        $team_display = $row['team_name'] ?: ($row['league_name'] . '-' . $row['manager_last_name']);
        echo "     - $team_display ({$row['program_code']})\n";
    }
    echo "\n";
    
    // 6. Validate Games
    echo "6. Games Validation:\n";
    $games_result = $db->query("
        SELECT COUNT(*) as count 
        FROM games g
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = $current_season
    ");
    $games_count = $games_result->fetch_assoc()['count'];
    echo "   Total Games for $current_season: $games_count\n";
    
    // Games by status
    $games_by_status = $db->query("
        SELECT g.game_status, COUNT(*) as game_count
        FROM games g
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = $current_season
        GROUP BY g.game_status
    ");
    while ($row = $games_by_status->fetch_assoc()) {
        echo "   - {$row['game_status']}: {$row['game_count']} games\n";
    }
    
    // Games with scores
    $scored_games = $db->query("
        SELECT COUNT(*) as count 
        FROM games g
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = $current_season 
        AND g.home_score IS NOT NULL AND g.away_score IS NOT NULL
    ");
    $scored_count = $scored_games->fetch_assoc()['count'];
    echo "   - Games with scores: $scored_count\n";
    echo "\n";
    
    // 7. Validate Schedules
    echo "7. Schedules Validation:\n";
    $schedules_result = $db->query("
        SELECT COUNT(*) as count 
        FROM schedules sc
        JOIN games g ON sc.game_id = g.game_id
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = $current_season
    ");
    $schedules_count = $schedules_result->fetch_assoc()['count'];
    echo "   Total Schedules for $current_season: $schedules_count\n";
    
    // Schedules with locations
    $located_schedules = $db->query("
        SELECT COUNT(*) as count 
        FROM schedules sc
        JOIN games g ON sc.game_id = g.game_id
        JOIN seasons s ON g.season_id = s.season_id
        WHERE s.season_year = $current_season AND sc.location_id IS NOT NULL
    ");
    $located_count = $located_schedules->fetch_assoc()['count'];
    echo "   - Schedules with locations: $located_count\n";
    echo "\n";
    
    // 8. Data Integrity Checks
    echo "8. Data Integrity Checks:\n";
    
    // Check for orphaned teams
    $orphaned_teams = $db->query("
        SELECT COUNT(*) as count 
        FROM teams t 
        LEFT JOIN seasons s ON t.season_id = s.season_id 
        WHERE s.season_id IS NULL
    ");
    $orphaned_teams_count = $orphaned_teams->fetch_assoc()['count'];
    echo "   - Orphaned teams: $orphaned_teams_count " . ($orphaned_teams_count > 0 ? "❌" : "✅") . "\n";
    
    // Check for games without teams
    $invalid_games = $db->query("
        SELECT COUNT(*) as count 
        FROM games g 
        LEFT JOIN teams ht ON g.home_team_id = ht.team_id 
        LEFT JOIN teams at ON g.away_team_id = at.team_id 
        WHERE ht.team_id IS NULL OR at.team_id IS NULL
    ");
    $invalid_games_count = $invalid_games->fetch_assoc()['count'];
    echo "   - Games with invalid teams: $invalid_games_count " . ($invalid_games_count > 0 ? "❌" : "✅") . "\n";
    
    // Check for schedules without games
    $orphaned_schedules = $db->query("
        SELECT COUNT(*) as count 
        FROM schedules sc 
        LEFT JOIN games g ON sc.game_id = g.game_id 
        WHERE g.game_id IS NULL
    ");
    $orphaned_schedules_count = $orphaned_schedules->fetch_assoc()['count'];
    echo "   - Orphaned schedules: $orphaned_schedules_count " . ($orphaned_schedules_count > 0 ? "❌" : "✅") . "\n";
    
    echo "\n";
    
    // 9. Summary
    echo "9. Migration Summary:\n";
    echo "   ✅ Programs: $programs_count\n";
    echo "   ✅ Seasons: $seasons_count\n";
    echo "   ✅ Divisions: $divisions_count\n";
    echo "   ✅ Locations: $locations_count\n";
    echo "   ✅ Teams: $teams_count\n";
    echo "   ✅ Games: $games_count\n";
    echo "   ✅ Schedules: $schedules_count\n";
    
    $total_issues = $orphaned_teams_count + $invalid_games_count + $orphaned_schedules_count;
    if ($total_issues == 0) {
        echo "\n🎉 Migration validation PASSED! No data integrity issues found.\n";
    } else {
        echo "\n⚠️  Migration validation found $total_issues data integrity issues that should be reviewed.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
