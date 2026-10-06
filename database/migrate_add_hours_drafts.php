<?php
/**
 * Migration: draft hours sent in by other applications.
 *
 * - hours_draft_batches: one row per "send" (a user, a source app, a date range)
 * - hours_drafts:        the per-day, per-task hours in that send, awaiting the
 *                        user's confirmation on the hours page
 * - hours.source:        NULL = typed into Pulse by hand; otherwise the source
 *                        app that a confirmed draft came from
 *
 * Drafts live in their own tables so that every report reading `hours`
 * (summary, client hours, budget vs actual, burn-up) is untouched until a user
 * confirms. Safe to run more than once: each step checks before it changes.
 *
 * Usage: php database/migrate_add_hours_drafts.php
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();

    $hasSource = (bool) $pdo->query("SHOW COLUMNS FROM hours LIKE 'source'")->fetch();
    if ($hasSource) {
        echo "hours.source already exists.\n";
    } else {
        echo "Adding hours.source...\n";
        $pdo->exec("ALTER TABLE hours ADD COLUMN source VARCHAR(32) NULL DEFAULT NULL AFTER hours");
        echo "✓ Added hours.source\n";
    }

    echo "Creating hours_draft_batches (if missing)...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hours_draft_batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            source VARCHAR(32) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            source_ref VARCHAR(80) NULL,
            confirmed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_source (user_id, source),
            CONSTRAINT fk_hdb_user_id FOREIGN KEY (user_id)
                REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "Creating hours_drafts (if missing)...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hours_drafts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            project_id INT UNSIGNED NULL,
            date_worked DATE NOT NULL,
            year_week VARCHAR(10) NOT NULL,
            hours DECIMAL(5,2) NOT NULL,
            UNIQUE KEY unique_batch_task_date (batch_id, task_id, date_worked),
            INDEX idx_task_id (task_id),
            CONSTRAINT fk_hd_batch_id FOREIGN KEY (batch_id)
                REFERENCES hours_draft_batches(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_hd_task_id FOREIGN KEY (task_id)
                REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "\n✓ Migration completed!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
