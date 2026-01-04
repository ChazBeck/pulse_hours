<?php
require __DIR__ . '/auth/include/auth_include.php';
auth_init();
auth_require_admin();

$pdo = get_db_connection();

echo "<!DOCTYPE html><html><head><title>Internal Clients Check</title>
<style>
body { font-family: sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background: #f3f4f6; font-weight: 600; }
.internal { background: #fef3c7; }
.external { background: #dbeafe; }
</style>
</head><body>";

echo "<h1>Client Analysis</h1>";

// Get all clients
$stmt = $pdo->query("
    SELECT id, name, is_internal, active, client_color
    FROM clients
    ORDER BY is_internal DESC, name
");
$clients = $stmt->fetchAll();

echo "<h2>All Clients (is_internal flag)</h2>";
echo "<table>";
echo "<tr><th>ID</th><th>Name</th><th>is_internal</th><th>Active</th><th>Color</th></tr>";
foreach ($clients as $client) {
    $class = $client['is_internal'] ? 'internal' : 'external';
    echo "<tr class='$class'>";
    echo "<td>{$client['id']}</td>";
    echo "<td>" . htmlspecialchars($client['name']) . "</td>";
    echo "<td>" . ($client['is_internal'] ? 'YES' : 'NO') . "</td>";
    echo "<td>" . ($client['active'] ? 'Active' : 'Inactive') . "</td>";
    echo "<td>" . htmlspecialchars($client['client_color']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Get tasks for each internal client
echo "<h2>Tasks Under Internal Clients</h2>";
$stmt = $pdo->query("
    SELECT c.name as client_name, t.name as task_name, t.status, t.project_id
    FROM clients c
    JOIN tasks t ON t.client_id = c.id
    WHERE c.is_internal = 1
    ORDER BY c.name, t.name
");
$tasks = $stmt->fetchAll();

if (empty($tasks)) {
    echo "<p style='color: red;'><strong>NO INTERNAL TASKS FOUND!</strong></p>";
    echo "<p>This means you need to:</p>";
    echo "<ol>";
    echo "<li>Create a 'Veerless' client with is_internal=1</li>";
    echo "<li>Create tasks under it (Admin, Business Development, etc.)</li>";
    echo "</ol>";
} else {
    echo "<table>";
    echo "<tr><th>Client</th><th>Task</th><th>Project ID</th><th>Status</th></tr>";
    foreach ($tasks as $task) {
        echo "<tr class='internal'>";
        echo "<td>" . htmlspecialchars($task['client_name']) . "</td>";
        echo "<td>" . htmlspecialchars($task['task_name']) . "</td>";
        echo "<td>" . ($task['project_id'] ? $task['project_id'] : '<em>NULL (client-level)</em>') . "</td>";
        echo "<td>" . htmlspecialchars($task['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Recommendation</h2>";
echo "<div style='background: #dbeafe; padding: 15px; border-radius: 8px;'>";
echo "<p><strong>Your setup should be:</strong></p>";
echo "<ul>";
echo "<li>ONE client: 'Veerless' with is_internal=1</li>";
echo "<li>Multiple tasks under Veerless:";
echo "<ul>";
echo "<li>Admin / Team Meetings</li>";
echo "<li>Business Development / External Meetings</li>";
echo "<li>Internal Decks / Rocks / Research</li>";
echo "<li>Professional Development</li>";
echo "<li>Volunteer Hours</li>";
echo "</ul></li>";
echo "</ul>";
echo "<p>If you currently have these as separate CLIENTS, you need to:</p>";
echo "<ol>";
echo "<li>Consolidate them into tasks under the single Veerless client</li>";
echo "<li>Update existing hours records to point to the new tasks</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
