<?php
/**
 * District 8 Travel League - Admin Account Migration Script
 * 
 * Migrates existing admin accounts to the new user accounts system
 */

define('D8TL_APP', true);

// CLI-specific bootstrap to avoid session issues
require_once __DIR__ . '/../includes/env-loader.php';
require_once EnvLoader::getPath('includes/config.php');
require_once __DIR__ . '/../includes/enums.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/UserAccountManager.php';
require_once __DIR__ . '/../includes/AdminMigrationManager.php';

echo "District 8 Travel League - Admin Account Migration\n";
echo "================================================\n\n";

try {
    $migrationManager = new AdminMigrationManager();
    
    // Check current migration status
    echo "Checking current migration status...\n";
    $status = $migrationManager->getMigrationStatus();
    
    echo "Total admin accounts: {$status['total_admins']}\n";
    echo "Already migrated: {$status['migrated']}\n";
    echo "Pending migration: {$status['pending']}\n";
    echo "Failed migrations: {$status['failed']}\n\n";
    
    if ($status['pending'] === 0 && $status['failed'] === 0) {
        echo "All admin accounts have been successfully migrated!\n";
        exit(0);
    }
    
    // Show details
    if (!empty($status['details'])) {
        echo "Migration Details:\n";
        echo "-----------------\n";
        foreach ($status['details'] as $detail) {
            $statusIcon = $detail['status'] === 'completed' ? '✓' : 
                         ($detail['status'] === 'failed' ? '✗' : '○');
            echo sprintf("  %s %-20s %-30s %s\n", 
                $statusIcon,
                $detail['legacy_username'], 
                $detail['legacy_email'] ?: 'No email',
                ucfirst($detail['status'])
            );
        }
        echo "\n";
    }
    
    // Prompt for migration
    if ($status['pending'] > 0 || $status['failed'] > 0) {
        echo "Do you want to proceed with migration? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 'y') {
            echo "Migration cancelled.\n";
            exit(0);
        }
        
        echo "\nStarting migration...\n";
        $result = $migrationManager->migrateAdminAccounts();
        
        if ($result['success']) {
            echo "Migration completed successfully!\n";
            echo "Migrated: {$result['migrated']}\n";
            echo "Skipped: {$result['skipped']}\n";
            
            if (!empty($result['errors'])) {
                echo "\nErrors encountered:\n";
                foreach ($result['errors'] as $error) {
                    echo "  - $error\n";
                }
            }
        } else {
            echo "Migration failed: {$result['error']}\n";
            exit(1);
        }
    }
    
    // Verify migration
    echo "\nVerifying migration integrity...\n";
    $issues = $migrationManager->verifyMigration();
    
    if (empty($issues)) {
        echo "✓ Migration verification passed - no issues found.\n";
    } else {
        echo "⚠ Migration verification found issues:\n";
        foreach ($issues as $issue) {
            echo "  - $issue\n";
        }
    }
    
    echo "\nMigration process completed.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
