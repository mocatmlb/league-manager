#!/usr/bin/env php
<?php
/**
 * Post-deployment Health Check Script
 * Verifies that the deployment was successful
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../includes/config.php';

class DeploymentMonitor {
    private $checks = [];
    private $failed_checks = [];
    
    public function runAllChecks() {
        echo "Running post-deployment health checks...\n";
        echo "========================================\n";
        
        $this->checkDatabase();
        $this->checkFilePermissions();
        $this->checkConfiguration();
        $this->checkComposerDependencies();
        $this->checkLogDirectories();
        
        $this->reportResults();
        
        return empty($this->failed_checks);
    }
    
    private function checkDatabase() {
        echo "Checking database connection... ";
        
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: " . $mysqli->connect_error);
            }
            
            // Test a simple query
            $result = $mysqli->query("SELECT 1");
            if (!$result) {
                throw new Exception("Query failed: " . $mysqli->error);
            }
            
            $mysqli->close();
            
            $this->checks['database'] = ['status' => 'PASS', 'message' => 'Database connection OK'];
            echo "✓ PASS\n";
            
        } catch (Exception $e) {
            $this->checks['database'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->failed_checks[] = 'database';
            echo "✗ FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    private function checkFilePermissions() {
        echo "Checking file permissions... ";
        
        $critical_paths = [
            __DIR__ . '/../includes/config.php' => 644,
            __DIR__ . '/../logs' => 755,
            __DIR__ . '/../uploads' => 755,
            __DIR__ . '/../backups' => 755
        ];
        
        $permission_issues = [];
        
        foreach ($critical_paths as $path => $expected_perms) {
            if (file_exists($path)) {
                $actual_perms = substr(sprintf('%o', fileperms($path)), -3);
                if ($actual_perms != $expected_perms) {
                    $permission_issues[] = basename($path) . " (expected: {$expected_perms}, actual: {$actual_perms})";
                }
            } else {
                $permission_issues[] = basename($path) . " (missing)";
            }
        }
        
        if (empty($permission_issues)) {
            $this->checks['permissions'] = ['status' => 'PASS', 'message' => 'File permissions OK'];
            echo "✓ PASS\n";
        } else {
            $this->checks['permissions'] = ['status' => 'FAIL', 'message' => 'Issues: ' . implode(', ', $permission_issues)];
            $this->failed_checks[] = 'permissions';
            echo "✗ FAIL - " . implode(', ', $permission_issues) . "\n";
        }
    }
    
    private function checkConfiguration() {
        echo "Checking configuration... ";
        
        $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_NAME', 'APP_VERSION'];
        $missing_constants = [];
        
        foreach ($required_constants as $constant) {
            if (!defined($constant)) {
                $missing_constants[] = $constant;
            }
        }
        
        if (empty($missing_constants)) {
            $this->checks['configuration'] = ['status' => 'PASS', 'message' => 'Configuration constants OK'];
            echo "✓ PASS\n";
        } else {
            $this->checks['configuration'] = ['status' => 'FAIL', 'message' => 'Missing constants: ' . implode(', ', $missing_constants)];
            $this->failed_checks[] = 'configuration';
            echo "✗ FAIL - Missing: " . implode(', ', $missing_constants) . "\n";
        }
    }
    
    private function checkComposerDependencies() {
        echo "Checking Composer dependencies... ";
        
        $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
        
        if (file_exists($vendor_autoload)) {
            // Check if PHPMailer is available (our main dependency)
            require_once $vendor_autoload;
            
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $this->checks['composer'] = ['status' => 'PASS', 'message' => 'Composer dependencies OK'];
                echo "✓ PASS\n";
            } else {
                $this->checks['composer'] = ['status' => 'FAIL', 'message' => 'PHPMailer not found'];
                $this->failed_checks[] = 'composer';
                echo "✗ FAIL - PHPMailer not found\n";
            }
        } else {
            $this->checks['composer'] = ['status' => 'FAIL', 'message' => 'Vendor autoload not found'];
            $this->failed_checks[] = 'composer';
            echo "✗ FAIL - Vendor autoload not found\n";
        }
    }
    
    private function checkLogDirectories() {
        echo "Checking log directories... ";
        
        $log_dirs = [
            __DIR__ . '/../logs',
            __DIR__ . '/../uploads',
            __DIR__ . '/../backups'
        ];
        
        $missing_dirs = [];
        
        foreach ($log_dirs as $dir) {
            if (!is_dir($dir)) {
                $missing_dirs[] = basename($dir);
            } elseif (!is_writable($dir)) {
                $missing_dirs[] = basename($dir) . " (not writable)";
            }
        }
        
        if (empty($missing_dirs)) {
            $this->checks['log_directories'] = ['status' => 'PASS', 'message' => 'Log directories OK'];
            echo "✓ PASS\n";
        } else {
            $this->checks['log_directories'] = ['status' => 'FAIL', 'message' => 'Issues: ' . implode(', ', $missing_dirs)];
            $this->failed_checks[] = 'log_directories';
            echo "✗ FAIL - " . implode(', ', $missing_dirs) . "\n";
        }
    }
    
    private function reportResults() {
        echo "\n========================================\n";
        echo "Health Check Summary:\n";
        echo "========================================\n";
        
        $total_checks = count($this->checks);
        $passed_checks = $total_checks - count($this->failed_checks);
        
        echo "Total checks: {$total_checks}\n";
        echo "Passed: {$passed_checks}\n";
        echo "Failed: " . count($this->failed_checks) . "\n";
        
        if (!empty($this->failed_checks)) {
            echo "\nFailed checks:\n";
            foreach ($this->failed_checks as $check) {
                echo "- {$check}: " . $this->checks[$check]['message'] . "\n";
            }
            echo "\nDeployment may have issues. Please review failed checks.\n";
        } else {
            echo "\n✓ All health checks passed! Deployment successful.\n";
        }
        
        // Log results
        $log_entry = date('Y-m-d H:i:s') . " - Health Check: {$passed_checks}/{$total_checks} passed";
        if (!empty($this->failed_checks)) {
            $log_entry .= " - FAILED: " . implode(', ', $this->failed_checks);
        }
        $log_entry .= "\n";
        
        file_put_contents(__DIR__ . '/../logs/deployment.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Run health checks
try {
    $monitor = new DeploymentMonitor();
    $success = $monitor->runAllChecks();
    
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    error_log("Deployment monitoring error: " . $e->getMessage());
    echo "Health check failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
