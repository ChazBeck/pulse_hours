<?php
/**
 * Application Configuration
 * 
 * Central configuration file for application-wide settings.
 * Update these values when deploying to production.
 */

// ============================================================================
// Environment Configuration
// ============================================================================

/**
 * Environment Mode
 * Options: 'development' or 'production'
 * 
 * In production:
 * - Errors are logged, not displayed
 * - Debug features are disabled
 * - Security is tightened
 */
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // Change to 'production' when deploying
}

// ============================================================================
// URL Configuration
// ============================================================================

/**
 * Base URL for the application
 * 
 * DEVELOPMENT: /plusehours/
 * PRODUCTION: / (or your domain's base path)
 * 
 * This is used for redirects and asset paths
 */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/apps/pulsehours/'); // Production path
}

/**
 * Helper function to generate full URLs
 * 
 * Usage: url('/apps/admin/') returns '/plusehours/apps/admin/' in dev
 *        or '/apps/admin/' in production
 */
if (!function_exists('url')) {
    function url($path) {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}

// ============================================================================
// Error Handling
// ============================================================================

if (APP_ENV === 'production') {
    // Production: Log errors, don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    // Development: Display all errors
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ============================================================================
// Session Configuration
// ============================================================================

/**
 * Session lifetime in seconds
 * Default: 8 hours (28800 seconds)
 */
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 28800);
}

/**
 * Session cookie settings
 */
if (!defined('SESSION_COOKIE_HTTPONLY')) {
    define('SESSION_COOKIE_HTTPONLY', true);
}

if (!defined('SESSION_COOKIE_SECURE')) {
    // In production with HTTPS, set this to true
    define('SESSION_COOKIE_SECURE', APP_ENV === 'production');
}

// ============================================================================
// File Upload Configuration
// ============================================================================

/**
 * Maximum file upload size (in bytes)
 * Default: 5MB
 */
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
}

/**
 * Allowed file extensions for uploads
 */
if (!defined('ALLOWED_UPLOAD_EXTENSIONS')) {
    define('ALLOWED_UPLOAD_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf']);
}

/**
 * Upload directory path
 */
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
}

// ============================================================================
// Application Information
// ============================================================================

if (!defined('APP_NAME')) {
    define('APP_NAME', 'PluseHours');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// ============================================================================
// Timezone
// ============================================================================

date_default_timezone_set('America/New_York'); // Change to your timezone

?>
