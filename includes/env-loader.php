<?php
/**
 * Simple Environment Variable Loader
 * 
 * Loads environment variables set by .htaccess SetEnv directives
 * or system environment variables
 */

class EnvLoader {
    public static function get($key, $default = null) {
        // Try $_SERVER first (where SetEnv variables appear), then $_ENV, then getenv()
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
    
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
    
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
}
?>
