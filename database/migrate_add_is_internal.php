<?php
/**
 * Migration: Add is_internal column to clients table
 */

require_once __DIR__ . '/../config/db_config.php';

try {
    $pdo = get_db_connection();
    
    echo "Adding is_internal column to clients table...\n";
    
    $pdo->exec("
        ALTER TABLE clients 
        ADD COLUMN is_internal TINYINT(1) NOT NULL DEFAULT 0 
        AFTER active
    ");
    
    echo "✓ Successfully added is_internal column\n";
    
    // Create Veerless internal client if it doesn't exist
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = 'Veerless' LIMIT 1");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo "\nCreating Veerless internal client...\n";
        $stmt = $pdo->prepare("
            INSERT INTO clients (name, active, is_internal, client_color) 
            VALUES ('Veerless', 1, 1, 'rgba(247, 148, 29, 0.8)')
        ");
        $stmt->execute();
        echo "✓ Created Veerless client (id: " . $pdo->lastInsertId() . ")\n";
    }
    
    echo "\n✓ Migration completed!\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
