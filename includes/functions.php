<?php
/**
 * District 8 Travel League - Utility Functions
 * 
 * Common utility functions used throughout the application
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    if ($email === null || $email === '') {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get application timezone
 */
function getAppTimezone() {
    static $timezone = null;
    if ($timezone === null) {
        $timezone = getSetting('timezone', 'America/New_York');
    }
    return $timezone;
}

/**
 * Format date for display with timezone support
 */
function formatDate($date, $format = 'M j, Y') {
    if (!$date) return '';
    
    $timezone = new DateTimeZone(getAppTimezone());
    $dateObj = new DateTime($date);
    $dateObj->setTimezone($timezone);
    
    return $dateObj->format($format);
}

/**
 * Format time for display with timezone support
 */
function formatTime($time, $format = 'g:i A') {
    if (!$time) return '';
    
    $timezone = new DateTimeZone(getAppTimezone());
    
    // Handle both full datetime and time-only strings
    $timeStr = (string)$time; // Ensure we have a string for strpos
    if (strpos($timeStr, ':') !== false && strpos($timeStr, ' ') === false) {
        // Time only - create a date object for today with this time
        $dateObj = new DateTime('today ' . $timeStr);
    } else {
        $dateObj = new DateTime($timeStr);
    }
    
    $dateObj->setTimezone($timezone);
    return $dateObj->format($format);
}

/**
 * Format datetime for display with timezone support
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (!$datetime) return '';
    
    $timezone = new DateTimeZone(getAppTimezone());
    $dateObj = new DateTime($datetime);
    $dateObj->setTimezone($timezone);
    
    return $dateObj->format($format);
}

/**
 * Convert date to application timezone for JavaScript
 */
function formatDateForJS($date) {
    if (!$date) return '';
    
    $timezone = new DateTimeZone(getAppTimezone());
    $dateObj = new DateTime($date);
    $dateObj->setTimezone($timezone);
    
    // Return in ISO format but with timezone offset
    return $dateObj->format('c');
}

/**
 * Calculate games back in standings
 */
function calculateGamesBack($leaderWins, $leaderLosses, $teamWins, $teamLosses) {
    return ($leaderWins - $teamWins + $teamLosses - $leaderLosses) / 2;
}

/**
 * Calculate win percentage
 */
function calculateWinPercentage($wins, $losses, $ties = 0) {
    $totalGames = $wins + $losses + $ties;
    if ($totalGames == 0) return 0;
    
    // Ties count as half wins
    $adjustedWins = $wins + ($ties * 0.5);
    return $adjustedWins / $totalGames;
}

/**
 * Generate unique game number
 */
function generateGameNumber($seasonId, $gameCount) {
    return $seasonId . str_pad($gameCount + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Get upcoming games (next 7 days)
 */
function getUpcomingGames($days = 7) {
    $db = Database::getInstance();
    
    $sql = "SELECT g.*, s.game_date, s.game_time, s.location,
                   ht.team_name as home_team, at.team_name as away_team,
                   ht.league_name as home_league, at.league_name as away_league
            FROM games g
            JOIN schedules s ON g.game_id = s.game_id
            JOIN teams ht ON g.home_team_id = ht.team_id
            JOIN teams at ON g.away_team_id = at.team_id
            WHERE s.game_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND g.game_status = 'Active'
            AND ht.active_status = 'Active'
            AND at.active_status = 'Active'
            ORDER BY s.game_date, s.game_time";
    
    return $db->fetchAll($sql, [$days]);
}

/**
 * Get today's games
 */
function getTodaysGames() {
    $db = Database::getInstance();
    
    $sql = "SELECT g.*, s.game_date, s.game_time, s.location,
                   ht.team_name as home_team, at.team_name as away_team,
                   ht.league_name as home_league, at.league_name as away_league
            FROM games g
            JOIN schedules s ON g.game_id = s.game_id
            JOIN teams ht ON g.home_team_id = ht.team_id
            JOIN teams at ON g.away_team_id = at.team_id
            WHERE s.game_date = CURDATE()
            AND g.game_status = 'Active'
            AND ht.active_status = 'Active'
            AND at.active_status = 'Active'
            ORDER BY s.game_time";
    
    return $db->fetchAll($sql);
}

/**
 * Get standings for a division
 */
function getDivisionStandings($divisionId) {
    $db = Database::getInstance();
    
    $sql = "SELECT t.team_id, t.team_name, t.league_name,
                   COUNT(CASE WHEN (g.home_team_id = t.team_id AND g.home_score > g.away_score) 
                              OR (g.away_team_id = t.team_id AND g.away_score > g.home_score) 
                         THEN 1 END) as wins,
                   COUNT(CASE WHEN (g.home_team_id = t.team_id AND g.home_score < g.away_score) 
                              OR (g.away_team_id = t.team_id AND g.away_score < g.home_score) 
                         THEN 1 END) as losses,
                   COUNT(CASE WHEN (g.home_team_id = t.team_id OR g.away_team_id = t.team_id) 
                              AND g.home_score = g.away_score AND g.game_status = 'Completed'
                         THEN 1 END) as ties,
                   SUM(CASE WHEN g.home_team_id = t.team_id THEN COALESCE(g.home_score, 0)
                            WHEN g.away_team_id = t.team_id THEN COALESCE(g.away_score, 0)
                            ELSE 0 END) as runs_scored,
                   SUM(CASE WHEN g.home_team_id = t.team_id THEN COALESCE(g.away_score, 0)
                            WHEN g.away_team_id = t.team_id THEN COALESCE(g.home_score, 0)
                            ELSE 0 END) as runs_against
            FROM teams t
            LEFT JOIN games g ON (g.home_team_id = t.team_id OR g.away_team_id = t.team_id)
                              AND g.game_status = 'Completed'
            WHERE t.division_id = ? AND t.active_status = 'Active'
            GROUP BY t.team_id, t.team_name, t.league_name
            ORDER BY wins DESC, losses ASC, runs_scored DESC";
    
    $standings = $db->fetchAll($sql, [$divisionId]);
    
    // Calculate games back and win percentage
    $leader = $standings[0] ?? null;
    foreach ($standings as &$team) {
        $team['win_percentage'] = calculateWinPercentage($team['wins'], $team['losses'], $team['ties']);
        $team['games_back'] = $leader ? calculateGamesBack($leader['wins'], $leader['losses'], $team['wins'], $team['losses']) : 0;
    }
    
    return $standings;
}

/**
 * Log activity for audit trail
 */
function logActivity($action, $details = '', $userId = null) {
    $db = Database::getInstance();
    
    $user = Auth::getCurrentUser();
    $userId = $userId ?: ($user['id'] ?? null);
    $userType = $user['type'];
    
    $db->insert('activity_log', [
        'user_id' => $userId,
        'user_type' => $userType,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Send notification email
 */
function sendNotification($templateName, $gameId = null, $scheduleChangeId = null, $additionalContext = []) {
    try {
        // Include EmailService if not already loaded
        if (!class_exists('EmailService')) {
            require_once __DIR__ . '/EmailService.php';
        }
        
        $emailService = new EmailService();
        
        // Build context for email template
        $context = array_merge($additionalContext, [
            'game_id' => $gameId,
            'schedule_change_id' => $scheduleChangeId
        ]);
        
        // Remove null values
        $context = array_filter($context, function($value) {
            return $value !== null;
        });
        
        // Trigger the notification
        $success = $emailService->triggerNotification($templateName, $context);
        
        if ($success) {
            logActivity('notification_sent', "Email notification sent: Template: {$templateName}, Game: {$gameId}, Change: {$scheduleChangeId}");
        } else {
            logActivity('notification_failed', "Email notification failed: Template: {$templateName}, Game: {$gameId}, Change: {$scheduleChangeId}");
        }
        
        return $success;
        
    } catch (Exception $e) {
        logActivity('notification_error', "Email notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get setting value
 */
function getSetting($key, $default = '') {
    $db = Database::getInstance();
    $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $setting ? $setting['setting_value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting($key, $value) {
    $db = Database::getInstance();
    
    $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
    
    if ($existing) {
        $db->update('settings', ['setting_value' => $value], 'setting_key = :setting_key', ['setting_key' => $key]);
    } else {
        $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

/**
 * Output JavaScript timezone configuration
 */
function outputTimezoneJS() {
    $timezone = getAppTimezone();
    echo "<script>\n";
    echo "// Set application timezone for JavaScript\n";
    echo "if (typeof setAppTimezone === 'function') {\n";
    echo "    setAppTimezone('" . addslashes($timezone) . "');\n";
    echo "}\n";
    echo "</script>\n";
}

/**
 * Get available timezones for settings
 */
function getAvailableTimezones() {
    return [
        'America/New_York' => 'Eastern Time (New York)',
        'America/Chicago' => 'Central Time (Chicago)', 
        'America/Denver' => 'Mountain Time (Denver)',
        'America/Los_Angeles' => 'Pacific Time (Los Angeles)',
        'America/Phoenix' => 'Mountain Standard Time (Phoenix)',
        'America/Anchorage' => 'Alaska Time (Anchorage)',
        'Pacific/Honolulu' => 'Hawaii Time (Honolulu)',
        'UTC' => 'Coordinated Universal Time (UTC)'
    ];
}
