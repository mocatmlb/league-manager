<?php
/**
 * District 8 Travel League - Environment Path Handler
 * 
 * Automatically detects environment and sets correct include paths
 * Eliminates need for fix_production_paths.php script
 */

if (!class_exists('EnvLoader')) {
class EnvLoader {
    private static $isProduction = null;
    private static $basePath = null;
    
    /**
     * Initialize environment detection
     */
    public static function init() {
        // Only initialize once
        if (self::$isProduction !== null) {
            return;
        }
        
        // Detect environment based on directory structure
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
        $currentDir = dirname($scriptPath);
        
        // Check if we're in production (cPanel) environment
        // In production: includes/ and admin/ are siblings in the web root
        // In development: includes/ is parent of public/
        
        // Look for the characteristic cPanel deployment structure
        $webRoot = $currentDir;
        while ($webRoot && $webRoot !== '/') {
            if (file_exists($webRoot . '/includes/bootstrap.php') && 
                file_exists($webRoot . '/admin/index.php')) {
                // Found production structure
                self::$isProduction = true;
                self::$basePath = $webRoot;
                break;
            }
            $webRoot = dirname($webRoot);
        }
        
        // If not found, assume development
        if (self::$isProduction === null) {
            self::$isProduction = false;
            // In development, go up from public/ to project root
            $projectRoot = $currentDir;
            while ($projectRoot && $projectRoot !== '/') {
                if (file_exists($projectRoot . '/includes/bootstrap.php') && 
                    file_exists($projectRoot . '/public/index.php')) {
                    self::$basePath = $projectRoot;
                    break;
                }
                $projectRoot = dirname($projectRoot);
            }
            
            // Fallback
            if (!self::$basePath) {
                self::$basePath = dirname(dirname(__FILE__)); // Go up from includes/
            }
        }
        
        // Verify includes directory exists and is readable
        if (!is_dir(self::$basePath . '/includes') || !is_readable(self::$basePath . '/includes')) {
            error_log('Includes directory not found or not readable at: ' . self::$basePath . '/includes');
            die('Application configuration error. Please check error logs.');
        }
        
        // Define constant for use in require statements
        if (!defined('D8TL_BASE_PATH')) {
            define('D8TL_BASE_PATH', self::$basePath);
        }
    }
    
    /**
     * Get the correct path for including files
     */
    public static function getPath($relativePath) {
        self::init();
        return self::$basePath . '/' . ltrim($relativePath, '/');
    }
    
    /**
     * Check if running in production environment
     */
    public static function isProduction() {
        self::init();
        return self::$isProduction;
    }
    
    /**
     * Get base path for the application
     */
    public static function getBasePath() {
        self::init();
        return self::$basePath;
    }

    /**
     * Get an environment variable or default
     */
    public static function get($key, $default = null) {
        self::init();
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        if (getenv($key) !== false) {
            return getenv($key);
        }
        return $default;
    }

    /**
     * Get an environment variable as integer or default
     */
    public static function getInt($key, $default = 0) {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }
}
}