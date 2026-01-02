<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. PHP is working<br>";

echo "2. Current directory: " . __DIR__ . "<br>";

echo "3. Checking if auth_include.php exists: ";
if (file_exists(__DIR__ . '/../../auth/include/auth_include.php')) {
    echo "YES<br>";
} else {
    echo "NO - FILE MISSING!<br>";
}

echo "4. Checking if app_config.php exists: ";
if (file_exists(__DIR__ . '/../../config/app_config.php')) {
    echo "YES<br>";
} else {
    echo "NO - FILE MISSING!<br>";
}

echo "5. Attempting to load auth_include.php...<br>";
require __DIR__ . '/../../auth/include/auth_include.php';
echo "6. Auth loaded successfully!<br>";

echo "7. Checking url() function: ";
if (function_exists('url')) {
    echo "EXISTS - " . url('/test') . "<br>";
} else {
    echo "MISSING!<br>";
}

echo "8. Testing auth functions...<br>";
auth_init();
echo "9. auth_init() succeeded<br>";
auth_require_admin();
echo "10. auth_require_admin() succeeded<br>";

echo "11. Testing database connection...<br>";
$pdo = get_db_connection();
echo "12. Database connected: " . ($pdo ? "YES" : "NO") . "<br>";

echo "13. Testing query...<br>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hours LIMIT 1");
    $result = $stmt->fetch();
    echo "14. Query successful! Count: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "14. QUERY FAILED: " . $e->getMessage() . "<br>";
}

echo "15. Testing hours-log.php query...<br>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            h.id,
            h.date_worked,
            h.hours,
            h.year_week,
            h.date_created,
            u.email,
            u.first_name,
            u.last_name,
            c.name as client_name,
            p.name as project_name,
            t.name as task_name
        FROM hours h
        JOIN users u ON h.user_id = u.id
        JOIN tasks t ON h.task_id = t.id
        JOIN projects p ON h.project_id = p.id
        JOIN clients c ON p.client_id = c.id
        ORDER BY h.date_worked DESC
        LIMIT 5
    ");
    $stmt->execute();
    $hours_entries = $stmt->fetchAll();
    echo "16. Hours-log query successful! Found " . count($hours_entries) . " entries<br>";
} catch (Exception $e) {
    echo "16. HOURS-LOG QUERY FAILED: " . $e->getMessage() . "<br>";
}

echo "17. Testing projects.php query...<br>";
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.client_id,
            c.name AS client_name
        FROM projects p
        INNER JOIN clients c ON p.client_id = c.id
        LIMIT 5
    ");
    $projects = $stmt->fetchAll();
    echo "18. Projects query successful! Found " . count($projects) . " projects<br>";
} catch (Exception $e) {
    echo "18. PROJECTS QUERY FAILED: " . $e->getMessage() . "<br>";
}

echo "19. All checks passed!";
