<?php
/**
 * Application Constants
 * 
 * Centralized constants for status values, magic numbers, and other fixed values.
 */

/**
 * Task Status Constants
 */
class TaskStatus {
    const NOT_STARTED = 'not-started';
    const IN_PROGRESS = 'in-progress';
    const COMPLETED = 'completed';
    const BLOCKED = 'blocked';
    
    /**
     * Get all valid task statuses
     * @return array
     */
    public static function all() {
        return [
            self::NOT_STARTED,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::BLOCKED
        ];
    }
    
    /**
     * Get display label for status
     * @param string $status
     * @return string
     */
    public static function label($status) {
        $labels = [
            self::NOT_STARTED => 'Not Started',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::BLOCKED => 'Blocked'
        ];
        return $labels[$status] ?? $status;
    }
}

/**
 * Project Status Constants
 */
class ProjectStatus {
    const ACTIVE = 'active';
    const COMPLETED = 'completed';
    const ON_HOLD = 'on-hold';
    const CANCELLED = 'cancelled';
    
    /**
     * Get all valid project statuses
     * @return array
     */
    public static function all() {
        return [
            self::ACTIVE,
            self::COMPLETED,
            self::ON_HOLD,
            self::CANCELLED
        ];
    }
    
    /**
     * Get display label for status
     * @param string $status
     * @return string
     */
    public static function label($status) {
        $labels = [
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
            self::CANCELLED => 'Cancelled'
        ];
        return $labels[$status] ?? $status;
    }
}

/**
 * User Role Constants
 */
class UserRole {
    const ADMIN = 'Admin';
    const USER = 'User';
    
    /**
     * Get all valid user roles
     * @return array
     */
    public static function all() {
        return [
            self::ADMIN,
            self::USER
        ];
    }
}

/**
 * Session Configuration Constants
 */
class SessionConfig {
    const TIMEOUT_SECONDS = 86400; // 24 hours
    const REGENERATION_INTERVAL = 300; // 5 minutes
}

/**
 * File Upload Constants
 */
class UploadConfig {
    const MAX_FILE_SIZE = 5242880; // 5MB in bytes
    const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const UPLOAD_DIR = 'uploads/logos/';
}

/**
 * Rate Limiting Constants
 */
class RateLimitConfig {
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_WINDOW_MINUTES = 15;
}

/**
 * Pagination Constants
 */
class PaginationConfig {
    const DEFAULT_PAGE_SIZE = 25;
    const MAX_PAGE_SIZE = 100;
}
