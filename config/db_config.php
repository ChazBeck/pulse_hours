<?php
/**
 * Database Configuration for PluseHours Time Tracking Application
 * 
 * This file contains database connection settings and provides a function
 * to establish PDO database connections with proper error handling.
 * 
 * SECURITY NOTE: Keep this file outside the web root in production environments
 * or ensure .htaccess prevents direct access to .php files in the config directory.
 */

// ============================================================================
// Database Connection Constants
// ============================================================================

/**
 * Database host - localhost for XAMPP local development
 */
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');

/**
 * Database username - default XAMPP MySQL user
 * IMPORTANT: Change this in production for security
 */
if (!defined('DB_USER')) define('DB_USER', 'root');

/**
 * Database password - default XAMPP MySQL has no password
 * IMPORTANT: Set a strong password in production
 */
if (!defined('DB_PASS')) define('DB_PASS', '');

/**
 * Database name - must match the database created in setup_database.sql
 */
if (!defined('DB_NAME')) define('DB_NAME', 'plusehours');

/**
 * Database charset - UTF-8 for international character support
 */
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// Database Connection Function
// ============================================================================

/**
 * Get a PDO database connection
 * 
 * Creates and returns a configured PDO connection to the MySQL database.
 * Uses prepared statements for security and includes error handling.
 * 
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 * 
 * Usage example:
 * ```php
 * try {
 *     $pdo = get_db_connection();
 *     $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
 *     $stmt->execute([$email]);
 *     $user = $stmt->fetch();
 * } catch (PDOException $e) {
 *     error_log("Database error: " . $e->getMessage());
 *     die("Database connection failed");
 * }
 * ```
 */
if (!function_exists('get_db_connection')) {
function get_db_connection() {
    static $pdo = null;
    
    // Return existing connection if already established (singleton pattern)
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Build the DSN (Data Source Name)
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        // PDO connection options
        $options = [
            // Use real prepared statements (more secure)
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // Throw exceptions on errors (easier debugging)
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Don't convert numeric values to strings
            PDO::ATTR_STRINGIFY_FETCHES => false,
            
            // Persistent connection (reuse connection across requests)
            // Comment this out if you experience connection issues
            PDO::ATTR_PERSISTENT => true
        ];
        
        // Create the PDO connection
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log the error to file in production, not just error_log
        $error_message = "Database Connection Error: " . $e->getMessage();
        error_log($error_message);
        
        // Also log to application log file if in production
        if (defined('APP_ENV') && APP_ENV === 'production') {
            $log_file = __DIR__ . '/../logs/db_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "[$timestamp] $error_message\n", FILE_APPEND);
            die("Database connection failed. Please contact support.");
        } else {
            // In development, show detailed error
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
}
}

/**
 * Test database connection
 * 
 * Simple function to verify database connectivity
 * 
 * @return bool True if connection successful, false otherwise
 */
if (!function_exists('test_db_connection')) {
function test_db_connection() {
    try {
        $pdo = get_db_connection();
        $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}
}

// ============================================================================
// Environment Configuration (Optional)
// ============================================================================

/**
 * Uncomment to set environment mode
 * Use 'development' for local testing, 'production' for live server
 */
// define('ENVIRONMENT', 'development');

// ============================================================================
// Usage Notes
// ============================================================================

/*
 * BASIC USAGE:
 * 
 * 1. Include this file in your PHP scripts:
 *    require_once __DIR__ . '/../config/db_config.php';
 * 
 * 2. Get a database connection:
 *    $pdo = get_db_connection();
 * 
 * 3. Execute queries with prepared statements:
 *    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
 *    $stmt->execute([$email]);
 *    $user = $stmt->fetch();
 * 
 * SECURITY BEST PRACTICES:
 * 
 * - Always use prepared statements (never concatenate user input into queries)
 * - Set a strong DB_PASS in production
 * - Create a dedicated database user with limited privileges (not root)
 * - Keep this file outside the web root or protect with .htaccess
 * - Use HTTPS in production to encrypt data in transit
 * - Enable MySQL SSL connection in production environments
 * 
 * XAMPP SETUP:
 * 
 * 1. Start Apache and MySQL from XAMPP Control Panel
 * 2. Open phpMyAdmin (http://localhost/phpmyadmin)
 * 3. Import database/setup_database.sql or run it in SQL tab
 * 4. Verify the 'plusehours' database was created
 * 5. Test connection by creating a test PHP file with test_db_connection()
 */
