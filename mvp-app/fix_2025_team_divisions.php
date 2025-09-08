<?php
/**
 * District 8 Travel League - Fix 2025 Team Division Assignments
 * This script fixes inconsistencies in team division assignments
 */

require_once 'includes/bootstrap.php';

echo "=== District 8 Travel League - Fix 2025 Team Division Assignments ===\n";

// Connect to the database
$db = Database::getInstance()->getConnection();

// Get division IDs
$divisions_query = "SELECT d.division_id, d.division_name, s.season_id, s.season_name 
                    FROM divisions d 
                    JOIN seasons s ON d.season_id = s.season_id 
                    WHERE s.season_year = '2025'
                    ORDER BY d.division_id";
$divisions_stmt = $db->query($divisions_query);

$divisions = [];
$season_divisions = [];
while ($division = $divisions_stmt->fetch(PDO::FETCH_ASSOC)) {
    $divisions[$division['division_name']] = [
        'id' => $division['division_id'],
        'season_id' => $division['season_id'],
        'season_name' => $division['season_name']
    ];
    
    // Map season to its divisions
    if (!isset($season_divisions[$division['season_id']])) {
        $season_divisions[$division['season_id']] = [];
    }
    $season_divisions[$division['season_id']][] = [
        'id' => $division['division_id'],
        'name' => $division['division_name']
    ];
}

echo "Found divisions:\n";
foreach ($divisions as $name => $info) {
    echo "  {$name} (ID: {$info['id']}) in {$info['season_name']}\n";
}

// Define the correct assignments
$fixes = [
    // Fix Junior teams in Senior division
    [
        'condition' => "league_name = 'FM' AND season_id = 1 AND division_id = 3",
        'division_id' => $divisions['American']['id'],
        'season_id' => $divisions['American']['season_id']
    ],
    [
        'condition' => "league_name = 'CICERO' AND season_id = 1 AND division_id = 3",
        'division_id' => $divisions['American']['id'],
        'season_id' => $divisions['American']['season_id']
    ],
    [
        'condition' => "league_name = 'MEXICO (SR)' AND season_id = 1",
        'division_id' => $divisions['16U Senior Baseabll']['id'],
        'season_id' => $divisions['16U Senior Baseabll']['season_id']
    ],
    
    // Fix Senior teams in Junior division
    [
        'condition' => "league_name = 'SYRACUSE' AND season_id = 2 AND division_id = 2",
        'division_id' => $divisions['16U Senior Baseabll']['id'],
        'season_id' => $divisions['16U Senior Baseabll']['season_id']
    ]
];

$teams_fixed = 0;

foreach ($fixes as $fix) {
    $update_query = "UPDATE teams 
                    SET division_id = :division_id, 
                        season_id = :season_id
                    WHERE {$fix['condition']}";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':division_id', $fix['division_id'], PDO::PARAM_INT);
    $update_stmt->bindParam(':season_id', $fix['season_id'], PDO::PARAM_INT);
    $update_stmt->execute();
    
    $affected_rows = $update_stmt->rowCount();
    if ($affected_rows > 0) {
        echo "✓ Fixed {$affected_rows} teams matching condition: {$fix['condition']}\n";
        $teams_fixed += $affected_rows;
    }
}

echo "\n=== Fix Complete ===\n";
echo "Teams fixed: $teams_fixed\n";
