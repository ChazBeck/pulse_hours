<?php
/**
 * Web interface to run the data seeder
 * 
 * Access via: http://localhost/plusehours/run_seeder.php
 */

// Prevent running in production
require_once __DIR__ . '/config/app_config.php';
if (APP_ENV === 'production') {
    die('Seeding is disabled in production environment.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Data Seeder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .btn {
            background: #F7941D;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #e07510;
        }
        .output {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Data Seeder</h1>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> This will create fake data in your database:
            <ul>
                <li>5 test users</li>
                <li>3 clients with multiple projects</li>
                <li>Tasks for each project</li>
                <li>~3 months of hours data (20-45 hours per user per week)</li>
                <li>Pulse check-in entries</li>
            </ul>
        </div>

        <?php if (isset($_GET['run'])): ?>
            <div class="output">
<?php
// Run the seeder script
ob_start();
try {
    require_once __DIR__ . '/database/seed_fake_data.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo htmlspecialchars($output);
?>
            </div>
            <p><a href="?" class="btn">Back</a></p>
        <?php else: ?>
            <p>Click the button below to run the seeder and populate your database with test data.</p>
            <a href="?run=1" class="btn">Run Seeder</a>
        <?php endif; ?>
    </div>
</body>
</html>
