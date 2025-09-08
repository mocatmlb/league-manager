<?php
/**
 * District 8 Travel League - Update 2025 Division Structure
 * This script updates the division assignments for 2025 teams
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Update 2025 Division Structure ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Get division IDs
$divisions_query = "SELECT d.division_id, d.division_name, s.season_name 
                    FROM divisions d 
                    JOIN seasons s ON d.season_id = s.season_id 
                    WHERE s.season_year = '2025'
                    ORDER BY d.division_id";
$divisions_stmt = $db->query($divisions_query);

$divisions = [];
while ($division = $divisions_stmt->fetch(PDO::FETCH_ASSOC)) {
    $divisions[$division['division_name']] = [
        'id' => $division['division_id'],
        'season_name' => $division['season_name']
    ];
}

echo "Found divisions:\n";
foreach ($divisions as $name => $info) {
    echo "  {$name} (ID: {$info['id']}) in {$info['season_name']}\n";
}

// Define the team assignments
// Format: [league_name => division_name]
$team_assignments = [
    // Junior teams to American division
    'CATO (D5)' => 'American',
    'CENTRAL SQUARE' => 'American',
    'CICERO' => 'American',
    'MEXICO (JR)' => 'American',
    'NORTH SYRACUSE' => 'American',
    
    // Junior teams to National division
    'OSWEGO' => 'National',
    'PHOENIX' => 'National',
    'SYRACUSE' => 'National',
    
    // Senior teams to 16U Senior Baseball division
    'FM' => '16U Senior Baseabll',
    'INNER CITY' => '16U Senior Baseabll',
    'SALINA' => '16U Senior Baseabll',
    'MEXICO (SR)' => '16U Senior Baseabll',
    'Cicero' => '16U Senior Baseabll' // This will handle both Cicero Mets and Yankees
];

// Update team assignments
$teams_updated = 0;

foreach ($team_assignments as $league_name => $division_name) {
    if (!isset($divisions[$division_name])) {
        echo "! Error: Division '{$division_name}' not found\n";
        continue;
    }
    
    $division_id = $divisions[$division_name]['id'];
    
    // Handle all team updates using the same query
    $update_query = "UPDATE teams t
                    JOIN seasons s ON t.season_id = s.season_id
                    SET t.division_id = :division_id
                    WHERE t.league_name = :league_name
                    AND s.season_year = '2025'";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':division_id', $division_id, PDO::PARAM_INT);
    $update_stmt->bindParam(':league_name', $league_name, PDO::PARAM_STR);
    $update_stmt->execute();
    
    $affected_rows = $update_stmt->rowCount();
    if ($affected_rows > 0) {
        echo "✓ Assigned {$league_name} to {$division_name} division (affected rows: {$affected_rows})\n";
        $teams_updated += $affected_rows;
    } else {
        echo "! No teams found for {$league_name}\n";
    }
}

echo "\n=== Update Complete ===\n";
echo "Teams updated: $teams_updated\n";
