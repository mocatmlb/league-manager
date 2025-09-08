<?php
/**
 * District 8 Travel League - Backwards Compatible Enums
 * 
 * Enum-like class definitions for better type safety and code clarity
 * Compatible with PHP 7.4+ (no PHP 8.1 enum syntax)
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

/**
 * Game Status Constants
 */
class GameStatus {
    const ACTIVE = 'Active';
    const COMPLETED = 'Completed';
    const CANCELLED = 'Cancelled';
    const POSTPONED = 'Postponed';
    
    public static function getDisplayName(string $status): string {
        switch($status) {
            case self::ACTIVE:
                return 'Active';
            case self::COMPLETED:
                return 'Completed';
            case self::CANCELLED:
                return 'Cancelled';
            case self::POSTPONED:
                return 'Postponed';
            default:
                return $status;
        }
    }
    
    public static function isFinished(string $status): bool {
        return in_array($status, [self::COMPLETED, self::CANCELLED]);
    }
    
    public static function getAllStatuses(): array {
        return [self::ACTIVE, self::COMPLETED, self::CANCELLED, self::POSTPONED];
    }
}

/**
 * Season Status Constants
 */
class SeasonStatus {
    const PLANNING = 'Planning';
    const REGISTRATION = 'Registration';
    const ACTIVE = 'Active';
    const COMPLETED = 'Completed';
    const CANCELLED = 'Cancelled';
    
    public static function getDisplayName(string $status): string {
        switch($status) {
            case self::PLANNING:
                return 'Planning';
            case self::REGISTRATION:
                return 'Registration';
            case self::ACTIVE:
                return 'Active';
            case self::COMPLETED:
                return 'Completed';
            case self::CANCELLED:
                return 'Cancelled';
            default:
                return $status;
        }
    }
    
    public static function isActive(string $status): bool {
        return in_array($status, [self::ACTIVE, self::REGISTRATION]);
    }
    
    public static function getAllStatuses(): array {
        return [self::PLANNING, self::REGISTRATION, self::ACTIVE, self::COMPLETED, self::CANCELLED];
    }
}

/**
 * User Type Constants
 */
class UserType {
    const PUBLIC = 'public';
    const COACH = 'coach';
    const ADMIN = 'admin';
    
    public static function getDisplayName(string $type): string {
        switch($type) {
            case self::PUBLIC:
                return 'Public';
            case self::COACH:
                return 'Coach';
            case self::ADMIN:
                return 'Administrator';
            default:
                return $type;
        }
    }
    
    public static function hasCoachAccess(string $type): bool {
        return in_array($type, [self::COACH, self::ADMIN]);
    }
    
    public static function hasAdminAccess(string $type): bool {
        return $type === self::ADMIN;
    }
    
    public static function getAllTypes(): array {
        return [self::PUBLIC, self::COACH, self::ADMIN];
    }
}

/**
 * Log Level Constants
 */
class LogLevel {
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;
    const FATAL = 5;
    
    public static function getName(int $level): string {
        switch($level) {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::WARN:
                return 'WARN';
            case self::ERROR:
                return 'ERROR';
            case self::FATAL:
                return 'FATAL';
            default:
                return 'UNKNOWN';
        }
    }
    
    public static function isHigherThan(int $level, int $other): bool {
        return $level > $other;
    }
    
    public static function getAllLevels(): array {
        return [self::DEBUG, self::INFO, self::WARN, self::ERROR, self::FATAL];
    }
}

/**
 * Email Status Constants
 */
class EmailStatus {
    const PENDING = 'Pending';
    const SENT = 'Sent';
    const FAILED = 'Failed';
    
    public static function getDisplayName(string $status): string {
        switch($status) {
            case self::PENDING:
                return 'Pending';
            case self::SENT:
                return 'Sent';
            case self::FAILED:
                return 'Failed';
            default:
                return $status;
        }
    }
    
    public static function getBootstrapClass(string $status): string {
        switch($status) {
            case self::PENDING:
                return 'warning';
            case self::SENT:
                return 'success';
            case self::FAILED:
                return 'danger';
            default:
                return 'secondary';
        }
    }
    
    public static function getAllStatuses(): array {
        return [self::PENDING, self::SENT, self::FAILED];
    }
}

/**
 * Request Status Constants
 */
class RequestStatus {
    const PENDING = 'Pending';
    const APPROVED = 'Approved';
    const DENIED = 'Denied';
    const CANCELLED = 'Cancelled';
    
    public static function getDisplayName(string $status): string {
        switch($status) {
            case self::PENDING:
                return 'Pending Review';
            case self::APPROVED:
                return 'Approved';
            case self::DENIED:
                return 'Denied';
            case self::CANCELLED:
                return 'Cancelled';
            default:
                return $status;
        }
    }
    
    public static function getBootstrapClass(string $status): string {
        switch($status) {
            case self::PENDING:
                return 'warning';
            case self::APPROVED:
                return 'success';
            case self::DENIED:
                return 'danger';
            case self::CANCELLED:
                return 'secondary';
            default:
                return 'secondary';
        }
    }
    
    public static function isFinal(string $status): bool {
        return in_array($status, [self::APPROVED, self::DENIED, self::CANCELLED]);
    }
    
    public static function getAllStatuses(): array {
        return [self::PENDING, self::APPROVED, self::DENIED, self::CANCELLED];
    }
}