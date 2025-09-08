<?php
/**
 * District 8 Travel League - Application Logger
 * 
 * A comprehensive logging system similar to log4j with:
 * - Multiple log levels (DEBUG, INFO, WARN, ERROR, FATAL)
 * - Configurable log levels via UI
 * - Date-based file rotation (yy-mm-dd_###.log)
 * - File size limits (5MB) with automatic splitting
 * - Automatic cleanup of files older than 14 days
 * - Thread-safe file operations
 */

class Logger {
    // Log levels (higher number = higher priority) - kept for backward compatibility
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;
    const FATAL = 5;
    
    // Log level names - kept for backward compatibility
    private static $levelNames = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARN => 'WARN',
        self::ERROR => 'ERROR',
        self::FATAL => 'FATAL'
    ];
    
    // Configuration
    private static $logDirectory = null;
    private static $maxFileSize = 5242880; // 5MB in bytes
    private static $maxAge = 14; // days
    private static $currentLogLevel = self::INFO; // Default log level
    private static $initialized = false;
    
    /**
     * Initialize the logger
     */
    private static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        self::$logDirectory = dirname(__DIR__) . '/logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$logDirectory)) {
            mkdir(self::$logDirectory, 0755, true);
        }
        
        // Load current log level from settings
        self::loadLogLevel();
        
        // Clean up old log files
        self::cleanupOldLogs();
        
        self::$initialized = true;
    }
    
    /**
     * Load log level from database settings
     */
    private static function loadLogLevel() {
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance();
                $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'log_level'");
                if ($setting) {
                    $levelName = strtoupper($setting['setting_value']);
                    $levelMap = array_flip(self::$levelNames);
                    if (isset($levelMap[$levelName])) {
                        self::$currentLogLevel = $levelMap[$levelName];
                    }
                }
            }
        } catch (Exception $e) {
            // If database is not available, use default level
            self::$currentLogLevel = self::INFO;
        }
    }
    
    /**
     * Set log level (called from admin settings)
     */
    public static function setLogLevel($level) {
        if (is_string($level)) {
            $levelMap = array_flip(self::$levelNames);
            $level = isset($levelMap[strtoupper($level)]) ? $levelMap[strtoupper($level)] : self::INFO;
        }
        
        if (isset(self::$levelNames[$level])) {
            self::$currentLogLevel = $level;
            
            // Save to database
            try {
                if (class_exists('Database')) {
                    $db = Database::getInstance();
                    $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = 'log_level'");
                    
                    if ($existing) {
                        $db->update('settings', 
                            ['setting_value' => self::$levelNames[$level]], 
                            'setting_key = :setting_key', 
                            ['setting_key' => 'log_level']
                        );
                    } else {
                        $db->insert('settings', [
                            'setting_key' => 'log_level',
                            'setting_value' => self::$levelNames[$level]
                        ]);
                    }
                }
            } catch (Exception $e) {
                // Continue even if database save fails
            }
        }
    }
    
    /**
     * Get current log level name
     */
    public static function getCurrentLogLevel() {
        self::initialize();
        return self::$levelNames[self::$currentLogLevel];
    }
    
    /**
     * Get all available log levels
     */
    public static function getLogLevels() {
        return self::$levelNames;
    }
    
    /**
     * Log a message at DEBUG level
     */
    public static function debug($message, $context = []) {
        self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log a message at INFO level
     */
    public static function info($message, $context = []) {
        self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log a message at WARN level
     */
    public static function warn($message, $context = []) {
        self::log(self::WARN, $message, $context);
    }
    
    /**
     * Log a message at ERROR level
     */
    public static function error($message, $context = []) {
        self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log a message at FATAL level
     */
    public static function fatal($message, $context = []) {
        self::log(self::FATAL, $message, $context);
    }
    
    /**
     * Log a message using LogLevel enum (PHP 8.1+)
     */
    public static function logWithLevel(LogLevel $level, $message, $context = []) {
        self::log($level->value, $message, $context);
    }
    
    /**
     * Main logging method
     */
    private static function log($level, $message, $context = []) {
        self::initialize();
        
        // Check if this level should be logged
        if ($level < self::$currentLogLevel) {
            return;
        }
        
        // Format the log entry
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$levelNames[$level];
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$levelName] $message$contextStr" . PHP_EOL;
        
        // Get the log file path
        $logFile = self::getLogFilePath();
        
        // Write to log file with file locking
        if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // If write fails, try to create directory and retry
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            }
        }
        
        // Check if file needs to be rotated
        self::rotateLogFileIfNeeded($logFile);
    }
    
    /**
     * Get the current log file path with rotation
     */
    private static function getLogFilePath() {
        $date = date('y-m-d');
        $counter = 1;
        
        do {
            $filename = sprintf('%s_%03d.log', $date, $counter);
            $filepath = self::$logDirectory . '/' . $filename;
            
            // If file doesn't exist or is under size limit, use it
            if (!file_exists($filepath) || filesize($filepath) < self::$maxFileSize) {
                return $filepath;
            }
            
            $counter++;
        } while ($counter <= 999); // Prevent infinite loop
        
        // If we somehow get here, use the last file
        return $filepath;
    }
    
    /**
     * Rotate log file if it exceeds size limit
     */
    private static function rotateLogFileIfNeeded($logFile) {
        if (file_exists($logFile) && filesize($logFile) >= self::$maxFileSize) {
            // File is at size limit, next write will create a new file
            // The getLogFilePath() method will automatically handle this
        }
    }
    
    /**
     * Clean up log files older than maxAge days
     */
    private static function cleanupOldLogs() {
        if (!is_dir(self::$logDirectory)) {
            return;
        }
        
        $cutoffTime = time() - (self::$maxAge * 24 * 60 * 60);
        $files = glob(self::$logDirectory . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get list of log files for admin viewer
     */
    public static function getLogFiles() {
        self::initialize();
        
        if (!is_dir(self::$logDirectory)) {
            return [];
        }
        
        $files = glob(self::$logDirectory . '/*.log');
        $logFiles = [];
        
        foreach ($files as $file) {
            $logFiles[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'readable_size' => self::formatBytes(filesize($file)),
                'readable_date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by modification time, newest first
        usort($logFiles, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $logFiles;
    }
    
    /**
     * Read log file content with optional filtering
     */
    public static function readLogFile($filename, $lines = 1000, $level = null) {
        self::initialize();
        
        $filepath = self::$logDirectory . '/' . basename($filename);
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $content = file_get_contents($filepath);
        $logLines = explode("\n", $content);
        
        // Filter by log level if specified
        if ($level) {
            $logLines = array_filter($logLines, function($line) use ($level) {
                return strpos($line, "[$level]") !== false;
            });
        }
        
        // Get last N lines
        $logLines = array_slice(array_reverse($logLines), 0, $lines);
        $logLines = array_reverse($logLines);
        
        return implode("\n", $logLines);
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get log statistics
     */
    public static function getLogStats() {
        self::initialize();
        
        $files = self::getLogFiles();
        $totalSize = 0;
        $totalFiles = count($files);
        $levelCounts = array_fill_keys(self::$levelNames, 0);
        
        foreach ($files as $file) {
            $totalSize += $file['size'];
            
            // Count log levels in recent files (last 3 files)
            if ($totalFiles <= 3 || array_search($file, $files) < 3) {
                $content = file_get_contents($file['filepath']);
                foreach (self::$levelNames as $levelName) {
                    $levelCounts[$levelName] += substr_count($content, "[$levelName]");
                }
            }
        }
        
        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'readable_size' => self::formatBytes($totalSize),
            'current_level' => self::getCurrentLogLevel(),
            'level_counts' => $levelCounts,
            'oldest_file' => !empty($files) ? end($files)['readable_date'] : 'N/A',
            'newest_file' => !empty($files) ? $files[0]['readable_date'] : 'N/A'
        ];
    }
    
    /**
     * Force cleanup of old logs (for admin use)
     */
    public static function forceCleanup() {
        self::initialize();
        self::cleanupOldLogs();
        return true;
    }
}
