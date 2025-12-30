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
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
        
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
