<?php
/**
 * Migration Script - Add login_attempts table
 * 
 * Run this script once to add the login_attempts table for rate limiting.
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();
    
    echo "Creating login_attempts table...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(255) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        success BOOLEAN NOT NULL DEFAULT FALSE,
        INDEX idx_ip_time (ip_address, attempted_at),
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    
    echo "✓ login_attempts table created successfully!\n";
    echo "\nRate limiting is now enabled for login attempts.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
