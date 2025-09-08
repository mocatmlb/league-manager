<?php
/**
 * District 8 Travel League - Migrate 2025 Teams
 * Migrates 2025 teams from old system where team_name is NULL and league_name is used
 */

require_once 'includes/bootstrap.php';

$sql_dump_file = '/Users/Mike.Oconnell/IdeaProjects/D8TL/oldsite/district8travelleague.com/moc835_ftoo886.sql';
$temp_db_name = 'temp_d8tl_migration';

echo "=== District 8 Travel League 2025 Teams Migration ===\n";
echo "Migrating 2025 teams from old system...\n\n";

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
    
    echo "✓ Connected to new database\n";
    
    // Get 2025 seasons info
    $seasons_result = $new_db->query("SELECT season_id, season_name, season_year FROM seasons WHERE season_year = '2025'");
    $seasons_info = [];
    
    while ($season = $seasons_result->fetch_assoc()) {
        $seasons_info[] = $season;
    }
    
    if (empty($seasons_info)) {
        throw new Exception("2025 seasons not found in new system");
    }
    
    echo "✓ Found " . count($seasons_info) . " 2025 seasons\n";
    foreach ($seasons_info as $season) {
        echo "  - {$season['season_name']} (ID: {$season['season_id']})\n";
    }
    
    // Get divisions for 2025 seasons
    $divisions_result = $new_db->query("
        SELECT d.division_id, d.season_id, d.division_name, d.division_code 
        FROM divisions d 
        JOIN seasons s ON d.season_id = s.season_id 
        WHERE s.season_year = '2025'
    ");
    
    $divisions_info = [];
    while ($division = $divisions_result->fetch_assoc()) {
        $divisions_info[$division['season_id']][] = $division;
    }
    
    echo "✓ Found divisions for 2025 seasons\n";
    
    // Get 2025 teams from old system
    $teams_query = "SELECT submission_id, league_name, home_field, division, manager_first_name, manager_last_name, manager_phone, manager_email 
                    FROM d8ll_form_1 
                    WHERE season = '2025' AND active = '1' AND league_name IS NOT NULL
                    ORDER BY league_name";
    
    $teams_result = $mysql->query($teams_query);
    
    if (!$teams_result) {
        throw new Exception("Failed to fetch teams: " . $mysql->error);
    }
    
    echo "Found " . $teams_result->num_rows . " teams in old system for 2025\n\n";
    
    $teams_migrated = 0;
    
    while ($team = $teams_result->fetch_assoc()) {
        $league_name = $team['league_name'];
        $old_division = $team['division'] ?: '1';
        
        // Determine which season and division this team belongs to
        // Division 1 = Junior (season_id 1), Division 2 = Senior (season_id 2)
        $target_season_id = ($old_division == '1') ? 1 : 2;  // Junior or Senior
        $target_division_id = null;
        
        // Find the appropriate division
        if (isset($divisions_info[$target_season_id])) {
            $target_division_id = $divisions_info[$target_season_id][0]['division_id'];
        }
        
        if (!$target_division_id) {
            echo "  ! No division found for team '$league_name' in division $old_division, skipping\n";
            continue;
        }
        
        // Check if team already exists
        $check_stmt = $new_db->prepare("SELECT team_id FROM teams WHERE league_name = ? AND season_id = ?");
        $check_stmt->bind_param('si', $league_name, $target_season_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            echo "  ! Team '$league_name' already exists, skipping\n";
            continue;
        }
        
        // Prepare team data
        $home_field = $team['home_field'] ?: 'TBD';
        $manager_first_name = $team['manager_first_name'] ?: '';
        $manager_last_name = $team['manager_last_name'] ?: '';
        $manager_phone = $team['manager_phone'] ?: '';
        $manager_email = $team['manager_email'] ?: '';
        
        // Insert team using the correct table structure
        $insert_team_stmt = $new_db->prepare("
            INSERT INTO teams (
                season_id, division_id, league_name, team_name, 
                manager_first_name, manager_last_name, manager_phone, manager_email,
                home_field, active_status, created_date, modified_date
            ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 'Active', NOW(), NOW())
        ");
        
        $insert_team_stmt->bind_param('iissssss',
            $target_season_id, $target_division_id, $league_name,
            $manager_first_name, $manager_last_name, $manager_phone, $manager_email, $home_field
        );
        
        if ($insert_team_stmt->execute()) {
            $teams_migrated++;
            $season_name = ($target_season_id == 1) ? 'Junior' : 'Senior';
            echo "  ✓ Migrated team: $league_name (Field: $home_field, Season: $season_name)\n";
        } else {
            echo "  ! Failed to migrate team: $league_name - " . $new_db->error . "\n";
        }
    }
    
    // Cleanup
    echo "\nCleaning up temporary database...\n";
    $mysql->query("DROP DATABASE $temp_db_name");
    echo "✓ Temporary database removed\n";
    
    echo "\n=== 2025 Teams Migration Complete ===\n";
    echo "Teams migrated: $teams_migrated\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Cleanup on error
    if (isset($mysql)) {
        $mysql->query("DROP DATABASE IF EXISTS $temp_db_name");
    }
    
    exit(1);
}
