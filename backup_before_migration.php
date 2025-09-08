<?php
/**
 * Backup Script - Creates backup before migration
 */

require_once 'includes/bootstrap.php';

$backup_dir = __DIR__ . '/backups';
$backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

// Create backups directory if it doesn't exist
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

echo "=== Creating Backup Before Migration ===\n";
echo "Backup file: $backup_file\n\n";

try {
    // Use MySQLi for consistency
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Failed to connect to database: " . $db->connect_error);
    }
    
    // Get all tables
    $tables_result = $db->query("SHOW TABLES");
    $tables = [];
    while ($row = $tables_result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $backup_content = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Before Data Migration from Old System\n\n";
    
    foreach ($tables as $table) {
        echo "Backing up table: $table\n";
        
        // Get table structure
        $create_result = $db->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_result->fetch_array();
        $backup_content .= "\n-- Table structure for `$table`\n";
        $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_content .= $create_row[1] . ";\n\n";
        
        // Get table data
        $data_result = $db->query("SELECT * FROM `$table`");
        if ($data_result->num_rows > 0) {
            $backup_content .= "-- Data for table `$table`\n";
            
            while ($row = $data_result->fetch_assoc()) {
                $columns = array_keys($row);
                $values = array_values($row);
                
                // Escape values
                $escaped_values = array_map(function($value) use ($db) {
                    return $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'";
                }, $values);
                
                $backup_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ");\n";
            }
            $backup_content .= "\n";
        }
    }
    
    // Write backup file
    if (file_put_contents($backup_file, $backup_content)) {
        echo "\n✓ Backup created successfully: $backup_file\n";
        echo "File size: " . formatBytes(filesize($backup_file)) . "\n";
    } else {
        throw new Exception("Failed to write backup file");
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
