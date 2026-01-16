<?php
/**
 * Create Admin User Script
 * 
 * Creates an admin user with username "admin" and password "admin123"
 * 
 * Usage: php create_admin_user.php
 */

require_once __DIR__ . '/config/db_config.php';

try {
    $pdo = get_db_connection();
    
    // Check if user already exists
    $email = 'admin';
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "User already exists:\n";
        echo "  ID: {$existingUser['id']}\n";
        echo "  Email: {$existingUser['email']}\n";
        echo "  Name: {$existingUser['first_name']} {$existingUser['last_name']}\n";
        echo "  Role: {$existingUser['role']}\n";
        echo "\nTo reset password, update the database manually or delete the user first.\n";
        exit(1);
    }
    
    // Create the admin user
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $email,           // email
        $passwordHash,    // password_hash
        'Admin',          // first_name
        'User',           // last_name
        'Admin',          // role
        1                 // is_active
    ]);
    
    $userId = $pdo->lastInsertId();
    
    echo "✓ Admin user created successfully!\n\n";
    echo "Login credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "  User ID: {$userId}\n";
    echo "  Role: Admin\n";
    echo "\nYou can now login with these credentials.\n";
    
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
