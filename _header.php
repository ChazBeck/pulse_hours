<?php
/**
 * Global Header Component
 * Include this file in your pages to show the navigation header
 */

// Load app config for url() helper
require_once __DIR__ . '/config/app_config.php';

// Get current user if authenticated
$current_user = null;

if (function_exists('auth_get_user')) {
    $current_user = auth_get_user();
}
?>
<header class="main-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?= url('/') ?>">
                    <h1>PluseHours</h1>
                </a>
            </div>
            
            <?php if ($current_user): ?>
            <div class="user-menu">
                <span class="user-name"><?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></span>
                <span class="user-role">(<?= htmlspecialchars($current_user['role']) ?>)</span>
                <a href="<?= url('auth/logout.php') ?>" class="btn-logout">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>
