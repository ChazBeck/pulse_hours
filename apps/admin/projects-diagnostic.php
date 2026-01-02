<?php
// Diagnostic version of projects.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "STEP 1: Starting projects.php<br>";

require __DIR__ . '/../../auth/include/auth_include.php';
echo "STEP 2: Auth included<br>";

auth_init();
echo "STEP 3: Auth init done<br>";

auth_require_admin();
echo "STEP 4: Auth require admin done<br>";

$pdo = get_db_connection();
echo "STEP 5: DB connected<br>";

$user = auth_get_user();
echo "STEP 6: Got user: " . $user['email'] . "<br>";

// Test the main query from projects.php
echo "STEP 7: Testing main projects query...<br>";
try {
    $query = "
        SELECT 
            p.id,
            p.name,
            p.client_id,
            p.status,
            p.active,
            c.name AS client_name,
            c.client_color,
            c.client_logo,
            pt.name AS template_name,
            COUNT(DISTINCT t.id) AS task_count,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks
        FROM projects p
        INNER JOIN clients c ON p.client_id = c.id
        LEFT JOIN project_templates pt ON p.project_template_id = pt.id
        LEFT JOIN tasks t ON p.id = t.project_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    $stmt = $pdo->query($query);
    $projects = $stmt->fetchAll();
    echo "STEP 8: Query successful! Found " . count($projects) . " projects<br>";
} catch (Exception $e) {
    echo "STEP 8: QUERY FAILED: " . $e->getMessage() . "<br>";
    die();
}

// Test getting clients
echo "STEP 9: Getting clients...<br>";
try {
    $stmt = $pdo->query("SELECT id, name FROM clients WHERE active = 1 ORDER BY name");
    $clients = $stmt->fetchAll();
    echo "STEP 10: Found " . count($clients) . " clients<br>";
} catch (Exception $e) {
    echo "STEP 10: CLIENTS QUERY FAILED: " . $e->getMessage() . "<br>";
    die();
}

// Test getting templates
echo "STEP 11: Getting templates...<br>";
try {
    $stmt = $pdo->query("SELECT id, name FROM project_templates WHERE active = 1 ORDER BY name");
    $templates = $stmt->fetchAll();
    echo "STEP 12: Found " . count($templates) . " templates<br>";
} catch (Exception $e) {
    echo "STEP 12: TEMPLATES QUERY FAILED: " . $e->getMessage() . "<br>";
    die();
}

echo "STEP 13: All database queries passed!<br>";
echo "STEP 14: Starting HTML rendering...<br>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Projects Test</title>
    <link rel="stylesheet" href="<?= url('/assets/admin-styles.css') ?>">
</head>
<body>
    <h1>Projects Page Diagnostic</h1>
    <?php include __DIR__ . '/../../_header.php'; ?>
    <p>Header included</p>
    <?php include __DIR__ . '/_admin_nav.php'; ?>
    <p>Nav included</p>
    <p>All checks passed!</p>
</body>
</html>
