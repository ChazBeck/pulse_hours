<?php
/**
 * Authentication Library for PluseHours
 * 
 * Provides session management, authentication, and authorization functions.
 * Requires config/db_config.php for database access.
 */

// Load application configuration
require_once __DIR__ . '/../../config/app_config.php';

// Load database configuration
require_once __DIR__ . '/../../config/db_config.php';

/**
 * Initialize authentication session
 * 
 * Starts PHP session with secure settings and validates session integrity.
 * Call this at the beginning of every page that uses authentication.
 */
function auth_init() {
    // Only start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1); // Prevent JavaScript access
        ini_set('session.use_only_cookies', 1); // Only use cookies for session ID
        ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
        
        // Start session with secure cookie (requires HTTPS in production)
        session_start();
        
        // Check session timeout (24 hours of inactivity)
        if (isset($_SESSION['last_activity'])) {
            $timeout = 24 * 60 * 60; // 24 hours in seconds
            if (time() - $_SESSION['last_activity'] > $timeout) {
                // Session expired - destroy and redirect to login
                session_unset();
                session_destroy();
                session_start(); // Start new session for redirect message
                $_SESSION['login_message'] = 'Your session has expired. Please log in again.';
                return; // Don't continue with other session operations
            }
        }
        
        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically to prevent fixation attacks
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } else if (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if user is currently logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function auth_is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user information
 * 
 * @return array|null User data array or null if not logged in
 */
function auth_get_user() {
    if (!auth_is_logged_in()) {
        return null;
    }
    
    // Return cached user data if available
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    
    // Fetch user from database
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT id, email, first_name, last_name, role, is_active, created_at
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Cache user data in session
            $_SESSION['user_data'] = $user;
            return $user;
        } else {
            // User not found or inactive - log them out
            auth_logout();
            return null;
        }
    } catch (PDOException $e) {
        error_log("Database error in auth_get_user(): " . $e->getMessage());
        return null;
    }
}

/**
 * Require user to be logged in
 * 
 * Redirects to login page if user is not authenticated.
 * Call this at the top of protected pages.
 */
function auth_require_login() {
    if (!auth_is_logged_in()) {
        // Store the requested page to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: ' . url('auth/login.php'));
        exit;
    }
}

/**
 * Require user to be an administrator
 * 
 * Shows 403 error page if user is not logged in or not an admin.
 * Call this at the top of admin-only pages.
 */
function auth_require_admin() {
    if (!auth_is_logged_in()) {
        // Not logged in - redirect to login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('auth/login.php'));
        exit;
    }
    
    $user = auth_get_user();
    if (!$user || $user['role'] !== 'Admin') {
        // Not an admin - show 403 error
        header('Location: ' . url('auth/403.php'));
        exit;
    }
}

/**
 * Log out current user
 * 
 * Destroys session and clears all authentication data.
 */
function auth_logout() {
    // Clear all session data
    $_SESSION = array();
    
    // Delete the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Generate CSRF token for form protection
 * 
 * Creates a unique token and stores it in the session.
 * Include this in forms and verify on submission.
 * 
 * @return string CSRF token
 */
function auth_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from form submission
 * 
 * Compares submitted token with stored session token.
 * 
 * @param string $token Token from form submission
 * @return bool True if token is valid, false otherwise
 */
function auth_verify_csrf($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Use hash_equals() to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if IP address or email is rate limited
 * 
 * @param string $email User's email address
 * @param string $ip_address IP address making the request
 * @return array Result with 'is_blocked', 'attempts_remaining', and 'retry_after' keys
 */
function auth_check_rate_limit($email, $ip_address) {
    try {
        $pdo = get_db_connection();
        
        // Configuration: 5 attempts per 15 minutes
        $max_attempts = 5;
        $window_minutes = 15;
        
        // Check attempts from this IP in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count
            FROM login_attempts
            WHERE ip_address = ? 
            AND success = 0
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip_address, $window_minutes]);
        $ip_attempts = $stmt->fetch()['attempt_count'];
        
        // Check attempts for this email in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count, MAX(attempted_at) as last_attempt
            FROM login_attempts
            WHERE email = ? 
            AND success = 0
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$email, $window_minutes]);
        $email_result = $stmt->fetch();
        $email_attempts = $email_result['attempt_count'];
        
        // If either IP or email has exceeded limit, block
        $total_attempts = max($ip_attempts, $email_attempts);
        
        if ($total_attempts >= $max_attempts) {
            // Calculate retry time
            $last_attempt_time = strtotime($email_result['last_attempt']);
            $retry_after = ($last_attempt_time + ($window_minutes * 60)) - time();
            
            return [
                'is_blocked' => true,
                'attempts_remaining' => 0,
                'retry_after' => max($retry_after, 0),
                'message' => sprintf(
                    'Too many failed login attempts. Please try again in %d minutes.',
                    ceil($retry_after / 60)
                )
            ];
        }
        
        return [
            'is_blocked' => false,
            'attempts_remaining' => $max_attempts - $total_attempts,
            'retry_after' => 0,
            'message' => ''
        ];
        
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        // On error, allow the attempt (fail open)
        return [
            'is_blocked' => false,
            'attempts_remaining' => 5,
            'retry_after' => 0,
            'message' => ''
        ];
    }
}

/**
 * Record a login attempt
 * 
 * @param string $email User's email address
 * @param string $ip_address IP address making the request
 * @param bool $success Whether the login was successful
 */
function auth_record_login_attempt($email, $ip_address, $success) {
    try {
        $pdo = get_db_connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, success, attempted_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $ip_address, $success ? 1 : 0]);
        
        // Clean up old attempts (older than 24 hours) to keep table size manageable
        if (rand(1, 100) === 1) { // Only run 1% of the time
            $cleanup_stmt = $pdo->prepare("
                DELETE FROM login_attempts 
                WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $cleanup_stmt->execute();
        }
        
    } catch (PDOException $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
        // Don't fail the login process if we can't record the attempt
    }
}

/**
 * Perform login with email and password
 * 
 * Internal function used by login.php
 * 
 * @param string $email User's email address
 * @param string $password User's password (plain text)
 * @return array Result array with 'success' boolean and 'message' string
 */
function auth_login($email, $password) {
    try {
        $pdo = get_db_connection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check rate limiting
        $rate_limit = auth_check_rate_limit($email, $ip_address);
        if ($rate_limit['is_blocked']) {
            return [
                'success' => false,
                'message' => $rate_limit['message']
            ];
        }
        
        // Find user by email
        $stmt = $pdo->prepare("
            SELECT id, email, password_hash, first_name, last_name, role, is_active
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Check if user exists
        if (!$user) {
            auth_record_login_attempt($email, $ip_address, false);
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            auth_record_login_attempt($email, $ip_address, false);
            return [
                'success' => false,
                'message' => 'Your account has been deactivated'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            auth_record_login_attempt($email, $ip_address, false);
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
        
        // Record successful login attempt
        auth_record_login_attempt($email, $ip_address, true);
        
        // Login successful - regenerate session ID for security
        session_regenerate_id(true);
        
        // Store user ID in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_regeneration'] = time();
        
        // Cache user data (without password_hash)
        unset($user['password_hash']);
        $_SESSION['user_data'] = $user;
        
        // Update last_login timestamp
        $update_stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$user['id']]);
        
        // Optional: Track session in sessions table
        try {
            $session_stmt = $pdo->prepare("
                INSERT INTO sessions (user_id, session_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $session_stmt->execute([
                $user['id'],
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            // Sessions table tracking is optional, don't fail login if it errors
            error_log("Session tracking error: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'Login successful'
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in auth_login(): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again later.'
        ];
    }
}
