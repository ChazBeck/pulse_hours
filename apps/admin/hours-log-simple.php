<?php
/**
 * Hours Log Simple - Simplified version for testing
 */

require __DIR__ . '/../../auth/include/auth_include.php';

auth_init();
auth_require_admin();

$pdo = get_db_connection();

$stmt = $pdo->query("SELECT COUNT(*) as count FROM hours");
$result = $stmt->fetch();

echo "STEP 7: Starting HTML...<br>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
</head>
<body>
    <h1>HTML Rendering Works</h1>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <p>Header included</p>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    <p>Nav included</p>
    <p>Success!</p>
</body>
</html>
