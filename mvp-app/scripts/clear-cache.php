#!/usr/bin/env php
<?php
/**
 * Cache Clearing Script
 * Clears PHP OpCache and any application caches
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

class CacheClearer {
    public function clearOpCache() {
        if (function_exists('opcache_reset')) {
            if (opcache_reset()) {
                echo "PHP OpCache cleared successfully.\n";
                return true;
            } else {
                echo "Failed to clear PHP OpCache.\n";
                return false;
            }
        } else {
            echo "PHP OpCache not available.\n";
            return true; // Not an error if OpCache isn't available
        }
    }
    
    public function clearApplicationCache() {
        // Clear any application-specific cache files
        $cache_dirs = [
            __DIR__ . '/../cache',
            __DIR__ . '/../tmp/cache'
        ];
        
        foreach ($cache_dirs as $cache_dir) {
            if (is_dir($cache_dir)) {
                $this->clearDirectory($cache_dir);
                echo "Cleared application cache directory: " . basename($cache_dir) . "\n";
            }
        }
        
        return true;
    }
    
    private function clearDirectory($dir) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->clearDirectory($file);
                rmdir($file);
            }
        }
    }
    
    public function clearSessionFiles() {
        // Clear old PHP session files if using file-based sessions
        $session_path = session_save_path();
        if (empty($session_path)) {
            $session_path = sys_get_temp_dir();
        }
        
        if (is_dir($session_path)) {
            $session_files = glob($session_path . '/sess_*');
            $old_sessions = 0;
            
            foreach ($session_files as $file) {
                // Remove sessions older than 24 hours
                if (filemtime($file) < (time() - 86400)) {
                    unlink($file);
                    $old_sessions++;
                }
            }
            
            if ($old_sessions > 0) {
                echo "Cleared {$old_sessions} old session files.\n";
            }
        }
        
        return true;
    }
}

// Run cache clearing
try {
    $clearer = new CacheClearer();
    
    $clearer->clearOpCache();
    $clearer->clearApplicationCache();
    $clearer->clearSessionFiles();
    
    echo "Cache clearing completed successfully.\n";
} catch (Exception $e) {
    error_log("Cache clearing error: " . $e->getMessage());
    echo "Cache clearing failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
