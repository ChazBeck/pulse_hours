<?php
// Temporary simplified version to isolate the 500 error
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "STEP 1: Starting<br>";

require __DIR__ . '/../../auth/include/auth_include.php';
echo "STEP 2: Auth included<br>";

auth_init();
echo "STEP 3: Auth init done<br>";

auth_require_admin();
echo "STEP 4: Auth require admin done<br>";

$pdo = get_db_connection();
echo "STEP 5: DB connected<br>";

$stmt = $pdo->query("SELECT COUNT(*) as count FROM hours");
$result = $stmt->fetch();
echo "STEP 6: Query done - count: " . $result['count'] . "<br>";

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
