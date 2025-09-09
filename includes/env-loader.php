<?php
/**
 * District 8 Travel League - Environment Path Handler
 * 
 * Automatically detects environment and sets correct include paths
 * Eliminates need for fix_production_paths.php script
 */

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
        $scriptPath = $_SERVER['SCRIPT_FILENAME'];
        $publicPath = dirname($scriptPath);
        
        // Check if we're in production (cPanel) environment
        // In production, includes/ is in the web root
        self::$isProduction = file_exists($publicPath . '/includes/bootstrap.php');
        
        // Set base path for includes
        if (self::$isProduction) {
            self::$basePath = $publicPath;
            
            // In production, ensure includes/ exists in web root
            if (!is_dir(self::$basePath . '/includes')) {
                // Try to create includes directory if it doesn't exist
                if (!mkdir(self::$basePath . '/includes', 0755, true)) {
                    error_log('Failed to create includes directory in production environment');
                }
            }
        } else {
            self::$basePath = dirname($publicPath); // Go up one level in development
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
}
?>