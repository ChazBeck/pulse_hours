<?php
/**
 * Migration: Add estimated_hours column to tasks table
 *
 * Lets each task carry the "initial assumption" of hours from the client budget
 * spreadsheets (the Projected Hours column). Actual hours are already tracked in
 * the `hours` table per task, so this column is what the Budget vs Actual report
 * (apps/admin/reports/budget-vs-actual.php) compares against.
 *
 * The Repository/Service layer (src/Repository/TaskRepository.php,
 * src/Service/ProjectService.php) already reads/writes this field; this migration
 * finally creates the column it expects. NULL means "no estimate set".
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();

    echo "Adding estimated_hours column to tasks table...\n";

    $pdo->exec("
        ALTER TABLE tasks
        ADD COLUMN estimated_hours DECIMAL(6,2) NULL DEFAULT NULL
        AFTER status
    ");

    echo "✓ Successfully added estimated_hours column\n";
    echo "\n✓ Migration completed!\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
