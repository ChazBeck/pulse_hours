<?php
/**
 * Common HTML Head Tags
 * Include this file in the <head> section of your pages
 */

// Ensure app_config is loaded for url() helper
if (!function_exists('url')) {
    require_once __DIR__ . '/../config/app_config.php';
}
?>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/images/favicon-32x32.png') ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/images/favicon-16x16.png') ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/images/apple-touch-icon.png') ?>">
