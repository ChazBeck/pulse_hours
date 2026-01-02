-- ============================================================================
-- PluseHours Time Tracking Application - Database Setup
-- ============================================================================
-- This script creates the database, tables, and initial admin user
-- Run this script once during initial setup using phpMyAdmin or MySQL CLI
-- ============================================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS `plusehours` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `plusehours`;

-- ============================================================================
-- Users Table
-- ============================================================================
-- Stores user accounts with authentication and profile information
-- ============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `role` ENUM('Admin', 'User') NOT NULL DEFAULT 'User',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Sessions Table
-- ============================================================================
-- Stores active user sessions for authentication tracking
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`),
    
    CONSTRAINT `fk_sessions_user_id` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Clients Table
-- ============================================================================
-- Stores client information for time tracking and project management
-- ============================================================================

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `client_color` VARCHAR(50) NULL,
    `client_logo` VARCHAR(255) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_active` (`active`),
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Projects Table
-- ============================================================================
-- Stores projects associated with clients
-- ============================================================================

CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` ENUM('active', 'completed', 'on-hold', 'cancelled') NOT NULL DEFAULT 'active',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_active` (`active`),
    
    CONSTRAINT `fk_projects_client_id`
        FOREIGN KEY (`client_id`)
        REFERENCES `clients`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Project Templates Table
-- ============================================================================
-- Stores reusable project types (e.g., "Report", "Stakeholder Quotient Study")
-- ============================================================================

CREATE TABLE IF NOT EXISTS `project_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Task Templates Table
-- ============================================================================
-- Stores reusable tasks for project templates
-- ============================================================================

CREATE TABLE IF NOT EXISTS `task_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_template_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_project_template_id` (`project_template_id`),
    INDEX `idx_sort_order` (`sort_order`),
    
    CONSTRAINT `fk_task_templates_project_template_id`
        FOREIGN KEY (`project_template_id`)
        REFERENCES `project_templates`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Tasks Table
-- ============================================================================
-- Stores tasks associated with projects
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('not-started', 'in-progress', 'completed', 'blocked') NOT NULL DEFAULT 'not-started',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_status` (`status`),
    
    CONSTRAINT `fk_tasks_project_id`
        FOREIGN KEY (`project_id`)
        REFERENCES `projects`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Pulse Table
-- ============================================================================
-- Stores weekly team pulse check-ins (mood and workload)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `pulse` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `year_week` VARCHAR(10) NOT NULL,
    `pulse` TINYINT NOT NULL CHECK (`pulse` BETWEEN 1 AND 5),
    `work_load` TINYINT NOT NULL CHECK (`work_load` BETWEEN 1 AND 10),
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_year_week` (`year_week`),
    INDEX `idx_date_created` (`date_created`),
    UNIQUE KEY `unique_user_week` (`user_id`, `year_week`),
    
    CONSTRAINT `fk_pulse_user_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Hours Table
-- ============================================================================
-- Stores time tracking entries for users working on tasks
-- ============================================================================

CREATE TABLE IF NOT EXISTS `hours` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `project_id` INT UNSIGNED NOT NULL,
    `task_id` INT UNSIGNED NOT NULL,
    `date_worked` DATE NOT NULL,
    `year_week` VARCHAR(10) NOT NULL,
    `hours` DECIMAL(5,2) NOT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_task_id` (`task_id`),
    INDEX `idx_date_worked` (`date_worked`),
    INDEX `idx_year_week` (`year_week`),
    UNIQUE KEY `unique_user_task_date` (`user_id`, `task_id`, `date_worked`),
    
    CONSTRAINT `fk_hours_user_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_hours_project_id`
        FOREIGN KEY (`project_id`)
        REFERENCES `projects`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_hours_task_id`
        FOREIGN KEY (`task_id`)
        REFERENCES `tasks`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Initial Data - Default Admin User
-- ============================================================================
-- Email: admin@plusehours.com
-- Password: admin123
-- IMPORTANT: Change this password immediately after first login!
-- ============================================================================

INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`)
VALUES (
    'admin@plusehours.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'User',
    'Admin',
    1
);

-- ============================================================================
-- Database Setup Complete
-- ============================================================================
-- Next steps:
-- 1. Update config/db_config.php with your database credentials
-- 2. Login with admin@plusehours.com / admin123
-- 3. Change the default admin password immediately
-- ============================================================================
