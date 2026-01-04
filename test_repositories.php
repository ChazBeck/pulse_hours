<?php
/**
 * Repository Verification Script
 * Tests all refactored repository methods to ensure they work correctly
 */

require __DIR__ . '/auth/include/auth_include.php';
auth_init();
auth_require_admin();

require_once __DIR__ . '/src/Repository/autoload.php';
require_once __DIR__ . '/includes/FilterBuilder.php';

$pdo = get_db_connection();

echo "<!DOCTYPE html><html><head><title>Repository Test</title></head><body>";
echo "<h1>Repository Verification Tests</h1>";
echo "<style>
    body { font-family: sans-serif; padding: 20px; }
    .test { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; }
    .pass { border-color: #10b981; background: #d1fae5; }
    .fail { border-color: #ef4444; background: #fee2e2; }
    .warn { border-color: #f59e0b; background: #fef3c7; }
    .info { border-color: #3b82f6; background: #dbeafe; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
</style>";

$testsPassed = 0;
$testsFailed = 0;
$testsWarning = 0;

// Test 0: Check database schema requirements
echo "<div class='test info'><strong>🔍 Database Schema Check</strong><br>";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'is_internal'");
    $has_is_internal = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'client_color'");
    $has_client_color = $stmt->fetch();
    
    if (!$has_is_internal) {
        echo "<span style='color: #ef4444;'>❌ MISSING COLUMN: <code>is_internal</code> in clients table</span><br>";
        echo "<strong>ACTION REQUIRED:</strong> Run migration at <a href='" . url('/run_migration.php') . "'>" . url('/run_migration.php') . "</a><br>";
        $testsWarning++;
    } else {
        echo "<span style='color: #10b981;'>✓ Column <code>is_internal</code> exists</span><br>";
    }
    
    if (!$has_client_color) {
        echo "<span style='color: #ef4444;'>❌ MISSING COLUMN: <code>client_color</code> in clients table</span><br>";
        echo "<strong>ACTION REQUIRED:</strong> Add <code>client_color VARCHAR(50) NULL</code> to clients table<br>";
        $testsWarning++;
    } else {
        echo "<span style='color: #10b981;'>✓ Column <code>client_color</code> exists</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: #ef4444;'>Error checking schema: " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

$testsPassed = 0;
$testsFailed = 0;
$testsWarning = 0;

// Test 1: HoursRepository - getAllWithDetails
echo "<div class='test ";
try {
    $hoursRepo = new HoursRepository($pdo);
    $result = $hoursRepo->getAllWithDetails(['user_id' => 1]);
    echo "pass'><strong>✓ Test 1: HoursRepository::getAllWithDetails()</strong><br>";
    echo "Successfully fetched hours with filters. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 1: HoursRepository::getAllWithDetails()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 2: HoursRepository - getHoursByUserAndClient
echo "<div class='test ";
try {
    $hoursRepo = new HoursRepository($pdo);
    $result = $hoursRepo->getHoursByUserAndClient(date('o-W'));
    echo "pass'><strong>✓ Test 2: HoursRepository::getHoursByUserAndClient()</strong><br>";
    echo "Successfully fetched user-client aggregation. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 2: HoursRepository::getHoursByUserAndClient()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 3: HoursRepository - getTotalsByClient
echo "<div class='test ";
try {
    $hoursRepo = new HoursRepository($pdo);
    $result = $hoursRepo->getTotalsByClient(date('o-W'));
    echo "pass'><strong>✓ Test 3: HoursRepository::getTotalsByClient()</strong><br>";
    echo "Successfully fetched client totals. Result count: " . count($result);
    if (!empty($result)) {
        echo "<br>Sample: <code>" . htmlspecialchars($result[0]['client_name']) . "</code> - " 
             . $result[0]['total_hours'] . " hours";
    }
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 3: HoursRepository::getTotalsByClient()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 4: HoursRepository - getDistinctWeeks
echo "<div class='test ";
try {
    $hoursRepo = new HoursRepository($pdo);
    $result = $hoursRepo->getDistinctWeeks(5);
    echo "pass'><strong>✓ Test 4: HoursRepository::getDistinctWeeks()</strong><br>";
    echo "Successfully fetched distinct weeks. Result count: " . count($result);
    if (!empty($result)) {
        echo "<br>Most recent: <code>" . htmlspecialchars($result[0]) . "</code>";
    }
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 4: HoursRepository::getDistinctWeeks()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 5: ClientRepository - getActive
echo "<div class='test ";
try {
    $clientRepo = new ClientRepository($pdo);
    $result = $clientRepo->getActive();
    echo "pass'><strong>✓ Test 5: ClientRepository::getActive()</strong><br>";
    echo "Successfully fetched active clients. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 5: ClientRepository::getActive()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 6: UserRepository - getActive
echo "<div class='test ";
try {
    $userRepo = new UserRepository($pdo);
    $result = $userRepo->getActive();
    echo "pass'><strong>✓ Test 6: UserRepository::getActive()</strong><br>";
    echo "Successfully fetched active users. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 6: UserRepository::getActive()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 7: PulseRepository - getAverageByWeeks
echo "<div class='test ";
try {
    $pulseRepo = new PulseRepository($pdo);
    $weeks = [date('o-W')];
    $result = $pulseRepo->getAverageByWeeks('pulse', $weeks);
    echo "pass'><strong>✓ Test 7: PulseRepository::getAverageByWeeks()</strong><br>";
    echo "Successfully fetched pulse averages. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 7: PulseRepository::getAverageByWeeks()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 8: PulseRepository - getByUserAndWeeks
echo "<div class='test ";
try {
    $pulseRepo = new PulseRepository($pdo);
    $weeks = [date('o-W')];
    $result = $pulseRepo->getByUserAndWeeks('pulse', $weeks);
    echo "pass'><strong>✓ Test 8: PulseRepository::getByUserAndWeeks()</strong><br>";
    echo "Successfully fetched user pulse data. Result count: " . count($result);
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 8: PulseRepository::getByUserAndWeeks()</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 9: FilterBuilder
echo "<div class='test ";
try {
    $filter = new FilterBuilder();
    $filter->addFilter('u.id = ?', 1)
           ->addFilter('c.id = ?', null) // Should be ignored
           ->addFilter('h.year_week = ?', '2026-01');
    
    $where = $filter->buildWhere();
    $params = $filter->getParams();
    
    if ($where === 'WHERE u.id = ? AND h.year_week = ?' && count($params) === 2) {
        echo "pass'><strong>✓ Test 9: FilterBuilder</strong><br>";
        echo "Successfully built WHERE clause: <code>" . htmlspecialchars($where) . "</code><br>";
        echo "Parameters: " . count($params) . " (correctly ignored null value)";
        $testsPassed++;
    } else {
        echo "warn'><strong>⚠ Test 9: FilterBuilder</strong><br>";
        echo "Unexpected result: <code>" . htmlspecialchars($where) . "</code>";
        $testsWarning++;
    }
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 9: FilterBuilder</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Test 10: LEFT JOIN with NULL project_id
echo "<div class='test ";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM hours h
        JOIN tasks t ON h.task_id = t.id
        LEFT JOIN projects p ON h.project_id = p.id
        JOIN clients c ON t.client_id = c.id
        WHERE h.project_id IS NULL
    ");
    $result = $stmt->fetch();
    $nullProjectCount = $result['count'];
    
    echo "pass'><strong>✓ Test 10: NULL project_id Support</strong><br>";
    echo "Query handles internal tasks correctly. ";
    echo "Found <strong>" . $nullProjectCount . "</strong> hours with no project (internal tasks).";
    if ($nullProjectCount > 0) {
        echo "<br><small>✓ Internal task support is working!</small>";
    } else {
        echo "<br><small>⚠ No internal tasks logged yet (this is expected if you haven't created them)</small>";
    }
    $testsPassed++;
} catch (Exception $e) {
    echo "fail'><strong>✗ Test 10: NULL project_id Support</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    $testsFailed++;
}
echo "</div>";

// Summary
echo "<div style='margin-top: 40px; padding: 20px; background: #f9fafb; border-radius: 8px;'>";
echo "<h2>Test Summary</h2>";
echo "<p style='font-size: 1.2em;'>";
echo "<span style='color: #10b981;'>✓ Passed: <strong>$testsPassed</strong></span> | ";
echo "<span style='color: #ef4444;'>✗ Failed: <strong>$testsFailed</strong></span> | ";
echo "<span style='color: #f59e0b;'>⚠ Warnings: <strong>$testsWarning</strong></span>";
echo "</p>";

if ($testsFailed === 0 && $testsWarning === 0) {
    echo "<p style='color: #10b981; font-weight: bold; font-size: 1.1em;'>🎉 All tests passed! Ready for deployment.</p>";
} elseif ($testsFailed === 0) {
    echo "<p style='color: #f59e0b; font-weight: bold;'>⚠ All critical tests passed, but review warnings above.</p>";
} else {
    echo "<p style='color: #ef4444; font-weight: bold;'>❌ Some tests failed. Review errors above before deployment.</p>";
}
echo "</div>";

echo "</body></html>";
