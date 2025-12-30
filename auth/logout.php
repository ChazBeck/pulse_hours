<?php
/**
 * Logout Handler for PluseHours
 * 
 * Terminates the user's session and redirects to login page.
 */

require __DIR__ . '/include/auth_include.php';
auth_init();

// Log out the user
auth_logout();

// Redirect to login page
header('Location: ' . url('auth/login.php'));
exit;
