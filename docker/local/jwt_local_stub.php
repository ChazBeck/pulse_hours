<?php
/**
 * LOCAL-DEV-ONLY JWT stub.
 *
 * Replaces the loginveerles JWT auth library on the Docker local stack so the
 * app boots without the central SSO service. Auto-"logs in" as the first Admin
 * user in the database (default: charlie@veerless.com).
 *
 * Wired in via the SSO_JWT_INCLUDE env var set in docker-compose.local.yml.
 * NEVER reachable in production: the env var is unset there, so sso_include.php
 * falls back to the real /var/www/sites/.../jwt_include.php.
 *
 * Override the impersonated user by setting LOCAL_DEV_USER_EMAIL on the app
 * container (must match an existing row in the users table).
 */

if (!function_exists('jwt_init')) {
    function jwt_init() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }
        // Mirror the SSO user into $_SESSION so the session-based auth_* helpers
        // (auth_is_logged_in / auth_get_user / auth_csrf_token) see a logged-in user too.
        $user = _jwt_stub_user();
        if ($user && empty($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_data'] = $user;
        }
    }
}

if (!function_exists('_jwt_stub_user')) {
    function _jwt_stub_user() {
        static $cached = null;
        if ($cached !== null) return $cached;

        $email = getenv('LOCAL_DEV_USER_EMAIL') ?: 'charlie@veerless.com';
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, role, is_active FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row) {
                $cached = $row;
                return $cached;
            }
        } catch (Throwable $e) {
            // Fall through to a minimal hardcoded fallback if the DB lookup fails.
        }
        $cached = [
            'id' => 0,
            'email' => $email,
            'first_name' => 'Local',
            'last_name' => 'Dev',
            'role' => 'Admin',
            'is_active' => 1,
        ];
        return $cached;
    }
}

if (!function_exists('jwt_is_logged_in')) {
    function jwt_is_logged_in() { return true; }
}

if (!function_exists('jwt_is_admin')) {
    function jwt_is_admin() {
        $u = _jwt_stub_user();
        return isset($u['role']) && strcasecmp($u['role'], 'Admin') === 0;
    }
}

if (!function_exists('jwt_get_user')) {
    function jwt_get_user() { return _jwt_stub_user(); }
}
