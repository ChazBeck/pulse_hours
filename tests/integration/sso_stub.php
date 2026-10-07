<?php
// Test-only SSO: the local-dev stub, plus the CSRF helpers the real SSO
// library provides (docker/local/jwt_local_stub.php lacks them).
require __DIR__ . '/../../docker/local/jwt_local_stub.php';
if (!function_exists('auth_csrf_token')) {
    function auth_csrf_token() {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('auth_verify_csrf')) {
    function auth_verify_csrf($token) {
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
