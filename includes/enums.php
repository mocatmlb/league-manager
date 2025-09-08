<?php
/**
 * District 8 Travel League - PHP 8.1 Enums
 * 
 * Modern enum definitions for better type safety and code clarity
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

/**
 * Game Status Enum
 */
enum GameStatus: string {
    case ACTIVE = 'Active';
    case COMPLETED = 'Completed';
    case CANCELLED = 'Cancelled';
    case POSTPONED = 'Postponed';
    
    public function getDisplayName(): string {
        return match($this) {
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::POSTPONED => 'Postponed'
        };
    }
    
    public function isFinished(): bool {
        return match($this) {
            self::COMPLETED, self::CANCELLED => true,
            self::ACTIVE, self::POSTPONED => false
        };
    }
}

/**
 * Season Status Enum
 */
enum SeasonStatus: string {
    case PLANNING = 'Planning';
    case REGISTRATION = 'Registration';
    case ACTIVE = 'Active';
    case COMPLETED = 'Completed';
    case CANCELLED = 'Cancelled';
    
    public function getDisplayName(): string {
        return match($this) {
            self::PLANNING => 'Planning',
            self::REGISTRATION => 'Registration',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled'
        };
    }
    
    public function isActive(): bool {
        return match($this) {
            self::ACTIVE, self::REGISTRATION => true,
            self::PLANNING, self::COMPLETED, self::CANCELLED => false
        };
    }
}

/**
 * User Type Enum
 */
enum UserType: string {
    case PUBLIC = 'public';
    case COACH = 'coach';
    case ADMIN = 'admin';
    
    public function getDisplayName(): string {
        return match($this) {
            self::PUBLIC => 'Public',
            self::COACH => 'Coach',
            self::ADMIN => 'Administrator'
        };
    }
    
    public function hasCoachAccess(): bool {
        return match($this) {
            self::COACH, self::ADMIN => true,
            self::PUBLIC => false
        };
    }
    
    public function hasAdminAccess(): bool {
        return $this === self::ADMIN;
    }
}

/**
 * Log Level Enum
 */
enum LogLevel: int {
    case DEBUG = 1;
    case INFO = 2;
    case WARN = 3;
    case ERROR = 4;
    case FATAL = 5;
    
    public function getName(): string {
        return match($this) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::WARN => 'WARN',
            self::ERROR => 'ERROR',
            self::FATAL => 'FATAL'
        };
    }
    
    public function isHigherThan(LogLevel $other): bool {
        return $this->value > $other->value;
    }
}

/**
 * Email Status Enum
 */
enum EmailStatus: string {
    case PENDING = 'Pending';
    case SENT = 'Sent';
    case FAILED = 'Failed';
    
    public function getDisplayName(): string {
        return match($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed'
        };
    }
    
    public function getBootstrapClass(): string {
        return match($this) {
            self::PENDING => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger'
        };
    }
}

/**
 * Request Status Enum
 */
enum RequestStatus: string {
    case PENDING = 'Pending';
    case APPROVED = 'Approved';
    case DENIED = 'Denied';
    case CANCELLED = 'Cancelled';
    
    public function getDisplayName(): string {
        return match($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::DENIED => 'Denied',
            self::CANCELLED => 'Cancelled'
        };
    }
    
    public function getBootstrapClass(): string {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::DENIED => 'danger',
            self::CANCELLED => 'secondary'
        };
    }
    
    public function isFinal(): bool {
        return match($this) {
            self::APPROVED, self::DENIED, self::CANCELLED => true,
            self::PENDING => false
        };
    }
}
