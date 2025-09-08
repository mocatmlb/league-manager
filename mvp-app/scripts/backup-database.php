#!/usr/bin/env php
<?php
/**
 * Database Backup Script
 * Creates a backup before deployment migrations
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../includes/config.php';

class DatabaseBackup {
    private $host;
    private $username;
    private $password;
    private $database;
    private $backup_dir;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->database = DB_NAME;
        $this->backup_dir = __DIR__ . '/../backups';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $this->backup_dir . "/backup_pre_deployment_{$timestamp}.sql";
        
        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($this->host),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->database),
            escapeshellarg($backup_file)
        );
        
        // Execute backup
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($backup_file) && filesize($backup_file) > 0) {
            echo "Database backup created successfully: " . basename($backup_file) . "\n";
            
            // Clean up old backups (keep last 10)
            $this->cleanupOldBackups();
            
            return $backup_file;
        } else {
            throw new Exception("Database backup failed");
        }
    }
    
    private function cleanupOldBackups() {
        $backup_files = glob($this->backup_dir . '/backup_pre_deployment_*.sql');
        
        if (count($backup_files) > 10) {
            // Sort by modification time (oldest first)
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest backups, keep last 10
            $files_to_remove = array_slice($backup_files, 0, -10);
            foreach ($files_to_remove as $file) {
                unlink($file);
                echo "Removed old backup: " . basename($file) . "\n";
            }
        }
    }
}

// Run backup
try {
    $backup = new DatabaseBackup();
    $backup->createBackup();
    echo "Pre-deployment backup completed successfully.\n";
} catch (Exception $e) {
    error_log("Database backup error: " . $e->getMessage());
    echo "Backup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
