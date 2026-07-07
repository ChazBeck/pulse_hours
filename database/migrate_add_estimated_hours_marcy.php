<?php
/**
 * Migration: Add estimated_hours_marcy column to tasks table
 *
 * Splits a task's budgeted hours into the senior/expensive portion (Marcy Twete,
 * billed at a higher rate) vs. everyone else. The existing estimated_hours stays
 * the TOTAL; "everyone else" = estimated_hours - estimated_hours_marcy.
 *
 * Feeds the Budget vs Actual report's cost view (Marcy @ MARCY_RATE, others @
 * STANDARD_RATE — see includes/rates.php). NULL means "no senior portion".
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();

    echo "Adding estimated_hours_marcy column to tasks table...\n";

    $pdo->exec("
        ALTER TABLE tasks
        ADD COLUMN estimated_hours_marcy DECIMAL(6,2) NULL DEFAULT NULL
        AFTER estimated_hours
    ");

    echo "✓ Successfully added estimated_hours_marcy column\n";
    echo "\n✓ Migration completed!\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
