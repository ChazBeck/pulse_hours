<?php
/**
 * Migration: High Fives — public kudos from one teammate to others.
 *
 * - high_fives:           one row per High Five (who gave it, for which Pulse
 *                         week, and the message)
 * - high_five_recipients: one row per teammate it was given to
 *
 * Each High Five also carries its Zoom Team Chat posting state
 * (includes/high_fives_zoom.php):
 *   zoom_status  off      not for posting (Zoom was not set up when it was given)
 *                pending  waiting to be posted
 *                sending  a post is in flight (claimed; reclaimed if stuck)
 *                posted   Zoom accepted it
 *                failed   the last attempt failed; scripts/post_high_fives.php retries
 * Rows that predate the Zoom columns are set to 'off', so switching Zoom on
 * never floods the channel with old High Fives.
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
            zoom_status VARCHAR(10) NOT NULL DEFAULT 'off',
            zoom_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            zoom_attempted_at TIMESTAMP NULL DEFAULT NULL,
            zoom_posted_at TIMESTAMP NULL DEFAULT NULL,
            zoom_error VARCHAR(255) NULL DEFAULT NULL,
            INDEX idx_giver_week (giver_user_id, year_week),
            INDEX idx_zoom_status (zoom_status),
            INDEX idx_year_week (year_week),
            CONSTRAINT fk_hf_giver_user_id FOREIGN KEY (giver_user_id)
                REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tables created before Zoom posting existed (dev, 2026-10-13) lack these
    $hasZoom = (bool) $pdo->query("SHOW COLUMNS FROM high_fives LIKE 'zoom_status'")->fetch();
    if ($hasZoom) {
        echo "high_fives Zoom columns already exist.\n";
    } else {
        echo "Adding high_fives Zoom columns (existing rows: off)...\n";
        $pdo->exec("
            ALTER TABLE high_fives
                ADD COLUMN zoom_status VARCHAR(10) NOT NULL DEFAULT 'off' AFTER created_at,
                ADD COLUMN zoom_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER zoom_status,
                ADD COLUMN zoom_attempted_at TIMESTAMP NULL DEFAULT NULL AFTER zoom_attempts,
                ADD COLUMN zoom_posted_at TIMESTAMP NULL DEFAULT NULL AFTER zoom_attempted_at,
                ADD COLUMN zoom_error VARCHAR(255) NULL DEFAULT NULL AFTER zoom_posted_at,
                ADD INDEX idx_zoom_status (zoom_status)
        ");
        echo "✓ Added Zoom columns\n";
    }

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
