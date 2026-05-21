<?php
/**
 * Migration: Add sort_order column to tasks table
 *
 * Lets the hours-entry picker (apps/hours.php) display tasks in a non-alphabetical
 * order when desired (e.g., putting Professional Development / Volunteer Hours
 * last in the Veerless internal task list). Default is 0 so existing tasks keep
 * their previous alphabetical fallback order.
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();

    echo "Adding sort_order column to tasks table...\n";

    $pdo->exec("
        ALTER TABLE tasks
        ADD COLUMN sort_order INT NOT NULL DEFAULT 0
        AFTER status
    ");

    echo "✓ Successfully added sort_order column\n";
    echo "\n✓ Migration completed!\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
