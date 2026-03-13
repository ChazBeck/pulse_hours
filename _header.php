<?php
/**
 * Global Header Component
 * Include this file in your pages to show the navigation header
 */

// Load app config for url() helper
require_once __DIR__ . '/config/app_config.php';

// Current user (provided by sso_include.php on protected pages)
$current_user = isset($user) ? $user : null;
?>
<header class="main-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?= url('/') ?>">
                    <img src="<?= url('assets/images/veerless-logo-sunrise-rgb-1920px-w-144ppi.png') ?>" alt="Veerless" class="veerless-logo" style="height: 50px; width: auto;">
                </a>
            </div>
            
            <?php if ($current_user): ?>
            <div class="user-menu">
                <span class="user-name"><?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></span>
                <span class="user-role">(<?= htmlspecialchars($current_user['role']) ?>)</span>
                <a href="/auth/logout.php" class="btn-logout">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>
