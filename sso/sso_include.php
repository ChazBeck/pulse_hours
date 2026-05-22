<?php
/**
 * SSO bridge: use loginveerles JWT cookie auth, then map/provision a local PulseHours user.
 *
 * Requirements:
 * - loginveerles deployed at /auth/ on same host, setting cookie sso_token for dev.veerless.net
 * - PulseHours has its own DB (tables from database/setup_database.sql)
 */

// Load PulseHours app + DB config
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_config.php';

// Do not load PulseHours' legacy auth_include.php here: loginveerles SSO also
// defines auth_csrf_token()/auth_verify_csrf(), so loading both causes fatal
// redeclare errors. Compatibility wrappers are defined below for pages that
// still call auth_init()/auth_require_admin() after including this bridge.

// Load loginveerles JWT auth (SSO_JWT_INCLUDE env var lets local dev point at a stub).
$jwt_include_path = getenv('SSO_JWT_INCLUDE') ?: '/var/www/sites/apps.veerless.net/auth/include/jwt_include.php';
require_once $jwt_include_path;
jwt_init();

// Compatibility wrappers for older PulseHours pages that still call auth_* helpers.
// CSRF helpers come from loginveerles common_functions.php via jwt_include.php.
if (!function_exists('auth_init')) {
    function auth_init() { jwt_init(); }
}
if (!function_exists('auth_is_logged_in')) {
    function auth_is_logged_in() { return jwt_is_logged_in(); }
}
if (!function_exists('auth_get_user')) {
    function auth_get_user() { return pulse_get_or_create_local_user(); }
}
if (!function_exists('auth_require_login')) {
    function auth_require_login() { pulse_require_login(); }
}
if (!function_exists('auth_require_admin')) {
    function auth_require_admin() { pulse_require_admin(); }
}
if (!function_exists('auth_logout')) {
    function auth_logout() { jwt_logout(); }
}

function pulse_require_login() {
    if (!jwt_is_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI']);
        header('Location: /auth/login.php?return_to=' . $returnTo);
        exit;
    }
}

function pulse_require_admin() {
    pulse_require_login();
    if (!jwt_is_admin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

/**
 * Get or create a PulseHours user row matching SSO email.
 */
function pulse_get_or_create_local_user() {
    $sso = jwt_get_user();
    if (!$sso || empty($sso['email'])) return null;

    $pdo = get_db_connection();

    // Attempt to find by email
    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, role, is_active, created_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$sso['email']]);
    $u = $stmt->fetch();

    if ($u) {
        return $u;
    }

    // Auto-provision (default role User, active)
    $first = $sso['first_name'] ?? '';
    $last  = $sso['last_name'] ?? '';

    // If SSO says admin, map to Admin in PulseHours.
    $role = (isset($sso['role']) && strcasecmp($sso['role'], 'admin') === 0) ? 'Admin' : 'User';

    $ins = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
    $randomPw = bin2hex(random_bytes(16));
    $pwHash = password_hash($randomPw, PASSWORD_DEFAULT);
    $ins->execute([$sso['email'], $pwHash, $first, $last, $role]);

    $id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, role, is_active, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Enforce login for any page that includes this file
pulse_require_login();

// Expose the local user as $user
$user = pulse_get_or_create_local_user();
if (!$user || (isset($user['is_active']) && (int)$user['is_active'] !== 1)) {
    http_response_code(403);
    echo 'User inactive or not provisioned';
    exit;
}
