<?php
/**
 * Rollback Script - Restores database from backup
 */

require_once 'includes/bootstrap.php';

if ($argc < 2) {
    echo "Usage: php rollback_migration.php <backup_file>\n";
    echo "Example: php rollback_migration.php backups/backup_2025-01-27_14-30-15.sql\n";
    exit(1);
}

$backup_file = $argv[1];

if (!file_exists($backup_file)) {
    echo "ERROR: Backup file not found: $backup_file\n";
    exit(1);
}

echo "=== Rolling Back Migration ===\n";
echo "Restoring from: $backup_file\n";
echo "WARNING: This will completely restore the database to the backup state!\n";
echo "Are you sure you want to continue? (yes/no): ";

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo "Rollback cancelled.\n";
    exit(0);
}

try {
    // Use MySQLi for consistency
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Failed to connect to database: " . $db->connect_error);
    }
    
    echo "\nReading backup file...\n";
    $sql_content = file_get_contents($backup_file);
    
    if (!$sql_content) {
        throw new Exception("Failed to read backup file");
    }
    
    echo "Executing SQL statements...\n";
    
    // Split SQL into individual statements
    $statements = explode(';', $sql_content);
    $executed = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        if ($db->query($statement)) {
            $executed++;
        } else {
            echo "Warning: Failed to execute statement: " . substr($statement, 0, 100) . "...\n";
            echo "Error: " . $db->error . "\n";
        }
    }
    
    echo "\n✓ Rollback completed successfully!\n";
    echo "Executed $executed SQL statements\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
