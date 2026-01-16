-- Create Admin User with username 'admin' and password 'admin123'
-- Run this in phpMyAdmin or MySQL CLI
-- Usage: mysql -u root plusehours < create_admin_user.sql

USE plusehours;

-- Delete existing 'admin' user if exists
DELETE FROM users WHERE email = 'admin';

-- Create the admin user
-- Password 'admin123' is hashed using PHP's password_hash() function
INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) 
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: admin123
    'Admin',
    'User',
    'Admin',
    1
);

-- Show the created user
SELECT id, email, first_name, last_name, role, is_active, created_at 
FROM users 
WHERE email = 'admin';
