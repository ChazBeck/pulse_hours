<?php
/**
 * Migration: High Fives — public kudos from one teammate to others.
 *
 * - high_fives:           one row per High Five (who gave it, for which Pulse
 *                         week, and the message)
 * - high_five_recipients: one row per teammate it was given to
 *
 * Until this has run, apps/high-fives.php steps aside and the check-in goes
 * straight on to the hours page, as it did before. Safe to run more than once.
 *
 * Usage: php database/migrate_add_high_fives.php
 */

require_once __DIR__ . '/../scripts/cli_only.php';
require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();

    echo "Creating high_fives (if missing)...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS high_fives (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            giver_user_id INT UNSIGNED NOT NULL,
            year_week VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_giver_week (giver_user_id, year_week),
            INDEX idx_year_week (year_week),
            CONSTRAINT fk_hf_giver_user_id FOREIGN KEY (giver_user_id)
                REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "Creating high_five_recipients (if missing)...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS high_five_recipients (
            high_five_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (high_five_id, user_id),
            INDEX idx_user_id (user_id),
            CONSTRAINT fk_hfr_high_five_id FOREIGN KEY (high_five_id)
                REFERENCES high_fives(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_hfr_user_id FOREIGN KEY (user_id)
                REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "\n✓ Migration completed!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
