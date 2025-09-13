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
    private static $baseUrl = null;

    /**
     * Initialize environment detection
     */
    public static function init() {
        // Only initialize once
        if (self::$isProduction !== null) {
            return;
        }

        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
        $currentDir = dirname($scriptPath);

        // 1) Preferred: project root relative to this file (one level up from includes/)
        $candidate = dirname(__DIR__);
        if (file_exists($candidate . '/includes/bootstrap.php') && file_exists($candidate . '/public/index.php')) {
            self::$isProduction = false;
            self::$basePath = $candidate;
        }

        // 2) Search upward from the executing script and look for a directory that contains both 'includes' and 'public'
        if (self::$basePath === null) {
            $searchDir = $currentDir;
            while ($searchDir && $searchDir !== '/') {
                if (is_dir($searchDir . '/includes') && is_dir($searchDir . '/public') && file_exists($searchDir . '/includes/bootstrap.php')) {
                    // Found a likely project root (works for both dev and cPanel layouts where project sits under web root)
                    self::$basePath = $searchDir;
                    // Heuristic: if the public folder is directly under this dir and the script is inside that public folder, treat as production-like layout
                    if (strpos($scriptPath, $searchDir . '/public') === 0) {
                        self::$isProduction = true;
                    } else {
                        self::$isProduction = false;
                    }
                    break;
                }
                $searchDir = dirname($searchDir);
            }
        }

        // 3) Older fallback behavior (compatibility) - try the previous heuristics
        if (self::$basePath === null) {
            $webRoot = $currentDir;
            while ($webRoot && $webRoot !== '/') {
                if (file_exists($webRoot . '/includes/bootstrap.php') && file_exists($webRoot . '/admin/index.php')) {
                    self::$isProduction = true;
                    self::$basePath = $webRoot;
                    break;
                }
                $webRoot = dirname($webRoot);
            }
        }

        if (self::$basePath === null) {
            $projectRoot = $currentDir;
            while ($projectRoot && $projectRoot !== '/') {
                if (file_exists($projectRoot . '/includes/bootstrap.php') && file_exists($projectRoot . '/public/index.php')) {
                    self::$basePath = $projectRoot;
                    self::$isProduction = false;
                    break;
                }
                $projectRoot = dirname($projectRoot);
            }
        }

        // Final fallback: use parent of includes/ (this file's parent)
        if (!self::$basePath) {
            self::$basePath = dirname(__DIR__);
            self::$isProduction = false;
            error_log('EnvLoader: falling back to dirname(__DIR__) as basePath: ' . self::$basePath);
        }

        // Verify includes directory exists and is readable. If not, do not die — fall back gracefully and log.
        if (!is_dir(self::$basePath . '/includes') || !is_readable(self::$basePath . '/includes')) {
            error_log('EnvLoader: Includes directory not found or not readable at: ' . self::$basePath . '/includes');
            // Try one more fallback: assume includes is this directory
            if (is_dir(__DIR__) && is_readable(__DIR__)) {
                self::$basePath = dirname(__DIR__);
                self::$isProduction = false;
                error_log('EnvLoader: using fallback basePath: ' . self::$basePath);
            }
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
        return rtrim(self::$basePath, '/') . '/' . ltrim($relativePath, '/');
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
     * Get base URL path (relative to DOCUMENT_ROOT) for building links/assets
     */
    public static function getBaseUrl() {
        self::init();
        if (isset(self::$baseUrl)) {
            return self::$baseUrl;
        }

        // Derive baseUrl from DOCUMENT_ROOT
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $basePath = rtrim(self::$basePath ?? '', '/');

        if ($docRoot && strpos($basePath, $docRoot) === 0) {
            $baseUrl = substr($basePath, strlen($docRoot));
            $baseUrl = '/' . trim($baseUrl, '/');
            if ($baseUrl === '/') {
                $baseUrl = '';
            }
        } else {
            // If we cannot determine document root relation, attempt to use SCRIPT_NAME to guess
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $baseUrl = '';
            if ($scriptName) {
                // e.g. /subdir/index.php -> /subdir
                $parts = explode('/', trim($scriptName, '/'));
                if (count($parts) > 1) {
                    array_pop($parts);
                    $baseUrl = '/' . implode('/', $parts);
                }
            }
        }

        // Normalize
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            $baseUrl = '';
        }
        self::$baseUrl = $baseUrl;
        return self::$baseUrl;
    }

    /**
     * Get a URL path to an asset under the public assets directory
     */
    public static function getAssetPath($relativeAssetPath) {
        self::init();
        $baseUrl = self::getBaseUrl();
        $relative = ltrim($relativeAssetPath, '/');
        // Common location: /assets/* under the public web root
        $path = rtrim($baseUrl, '/');
        if ($path === '') {
            $path = '';
        }
        return $path . '/assets/' . $relative;
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