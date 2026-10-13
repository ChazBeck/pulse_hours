<?php
/**
 * Global Header Component
 * Include this file right after <body> to show the Veerless suite header.
 *
 * The header is the shared one served by the SSO app at /auth/ (same as CRM
 * and Supplier Risk): suite-header.js asks /auth/api/auth/check.php who is
 * logged in and renders logo, Home, avatar/name and Logout into #suite-header.
 * Pulse-side styling (colours, sticky mount, admin-nav offset) lives in the
 * "Suite Header" section of assets/admin-styles.css.
 */
?>
<link rel="stylesheet" href="/auth/assets/css/header.css">
<div id="suite-header"></div>
<script defer src="/auth/assets/js/suite-header.js"></script>
<script>
// Pulse's admin nav is sticky too; publish the header's height so the nav
// (top: var(--suite-header-h)) sits just under it instead of behind it.
(function () {
    var mount = document.getElementById('suite-header');
    if (!mount) return;
    function setHeight() {
        document.documentElement.style.setProperty('--suite-header-h', mount.getBoundingClientRect().height + 'px');
    }
    setHeight();
    if ('ResizeObserver' in window) {
        new ResizeObserver(setHeight).observe(mount);
    } else {
        window.addEventListener('load', setHeight);
        window.addEventListener('resize', setHeight);
    }
})();
</script>
