<?php
// Simulate the exact update logic from hours-log.php

require __DIR__ . '/auth/include/auth_include.php';
$pdo = get_db_connection();

echo "Testing Hours Update to 0\n";
echo "========================\n\n";

// Get a test entry
$stmt = $pdo->query("SELECT * FROM hours ORDER BY id DESC LIMIT 1");
$entry = $stmt->fetch();

if (!$entry) {
    die("No entries found in hours table\n");
}

echo "Original Entry:\n";
echo "ID: {$entry['id']}\n";
echo "Hours: {$entry['hours']}\n";
echo "Date: {$entry['date_worked']}\n\n";

// Simulate the form submission with hours = 0
$_POST = [
    'entry_id' => $entry['id'],
    'hours' => '0',  // This is what the form sends
    'date_worked' => $entry['date_worked']
];

echo "Simulating POST data:\n";
print_r($_POST);
echo "\n";

// Copy the exact logic from hours-log.php
try {
    $entry_id = $_POST['entry_id'];
    $hours = $_POST['hours'];
    $date_worked = $_POST['date_worked'];
    
    echo "Step 1: Extract values\n";
    echo "  entry_id: " . var_export($entry_id, true) . "\n";
    echo "  hours: " . var_export($hours, true) . " (type: " . gettype($hours) . ")\n";
    echo "  date_worked: " . var_export($date_worked, true) . "\n\n";
    
    // Convert empty string to 0
    if ($hours === '' || $hours === null) {
        echo "Step 2: Converting empty/null to 0\n";
        $hours = 0;
    } else {
        echo "Step 2: No conversion needed\n";
    }
    echo "  hours after conversion: " . var_export($hours, true) . " (type: " . gettype($hours) . ")\n\n";
    
    echo "Step 3: Validation check\n";
    if (!is_numeric($hours) || $hours < 0) {
        throw new Exception('Hours must be a non-negative number');
    }
    echo "  ✓ Validation passed\n\n";
    
    // Calculate year_week from date_worked
    $year_week = date('o-W', strtotime($date_worked));
    echo "Step 4: Calculate year_week: $year_week\n\n";
    
    echo "Step 5: Executing UPDATE query\n";
    $stmt = $pdo->prepare("
        UPDATE hours 
        SET hours = ?, date_worked = ?, year_week = ?
        WHERE id = ?
    ");
    $result = $stmt->execute([$hours, $date_worked, $year_week, $entry_id]);
    $rows = $stmt->rowCount();
    
    echo "  Execute result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "  Rows affected: $rows\n\n";
    
    // Verify
    echo "Step 6: Verify database value\n";
    $stmt = $pdo->prepare("SELECT hours FROM hours WHERE id = ?");
    $stmt->execute([$entry_id]);
    $updated = $stmt->fetch();
    echo "  Current DB value: " . var_export($updated['hours'], true) . "\n\n";
    
    if ($updated['hours'] == 0) {
        echo "✓✓✓ SUCCESS! Hours successfully updated to 0\n";
    } else {
        echo "✗✗✗ FAILED! Hours is still: {$updated['hours']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
