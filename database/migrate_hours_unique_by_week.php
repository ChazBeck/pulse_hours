<?php
/**
 * Migration: an `hours` row is unique per user, task, day AND week.
 *
 * Why: apps/hours.php stamps a hand entry with the day it was TYPED and the
 * week it is FOR, and people enter last week's hours early the next week. A
 * row for 168 Hours work actually done on Monday Oct 5 (week 41) and last
 * week's hours typed on Monday Oct 5 (week 40) are different facts, but the old
 * key (user, task, day) let only one of them exist.
 *
 * Swapped in ONE statement, so there is no moment without a unique key. The new
 * key is the old one plus a column, so every existing row already satisfies it.
 * Safe to run more than once.
 *
 * Usage: php database/migrate_hours_unique_by_week.php
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();
    $keys = $pdo->query("SHOW INDEX FROM hours WHERE Non_unique = 0")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_unique(array_column($keys, 'Key_name'));

    if (in_array('unique_user_task_date_week', $names, true)) {
        echo "hours already unique by user, task, day and week.\n";
        exit(0);
    }
    $drop = in_array('unique_user_task_date', $names, true) ? 'DROP INDEX unique_user_task_date, ' : '';
    $pdo->exec("ALTER TABLE hours {$drop}ADD UNIQUE KEY unique_user_task_date_week (user_id, task_id, date_worked, year_week)");
    echo "✓ hours is now unique by user, task, day and week\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
