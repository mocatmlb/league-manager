<?php
/**
 * District 8 Travel League - Environment Path Handler
 * 
 * Automatically detects environment and sets correct include paths
 * Eliminates need for fix_production_paths.php script
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

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
        // In production, includes/ is in the same directory as public files
        self::$isProduction = file_exists($publicPath . '/includes/bootstrap.php');
        
        // Set base path for includes
        if (self::$isProduction) {
            self::$basePath = $publicPath;
        } else {
            self::$basePath = dirname($publicPath); // Go up one level in development
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