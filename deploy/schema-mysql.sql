-- PTW (Sikkerjob) MySQL Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'entreprenor', 'drift', 'opgaveansvarlig') NOT NULL,
    `approved` BOOLEAN DEFAULT FALSE,
    `entreprenor_firma` VARCHAR(100) DEFAULT NULL,
    `must_change_password` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work Orders table
CREATE TABLE IF NOT EXISTS `work_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_no` VARCHAR(50) DEFAULT NULL,
    `p_number` VARCHAR(50) DEFAULT NULL,
    `mps_nr` VARCHAR(50) DEFAULT NULL,
    `description` TEXT,
    `p_description` TEXT,
    `jobansvarlig` VARCHAR(100) DEFAULT NULL,
    `telefon` VARCHAR(20) DEFAULT NULL,
    `oprettet_af` VARCHAR(50) DEFAULT NULL,
    `oprettet_dato` DATE DEFAULT NULL,
    `components` TEXT,
    `entreprenor_firma` VARCHAR(100) DEFAULT NULL,
    `entreprenor_kontakt` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
    `status_dag` VARCHAR(50) DEFAULT 'kræver_dagsgodkendelse',
    `ikon` VARCHAR(50) DEFAULT 'blue',
    `starttid` DATETIME DEFAULT NULL,
    `sluttid` DATETIME DEFAULT NULL,
    `latitude` FLOAT DEFAULT NULL,
    `longitude` FLOAT DEFAULT NULL,
    `notes` TEXT,
    `approvals` JSON DEFAULT NULL,
    `approval_history` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_work_orders_status` (`status`),
    INDEX `idx_work_orders_firma` (`entreprenor_firma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approvals table
CREATE TABLE IF NOT EXISTS `approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT,
    `role` ENUM('entreprenor', 'opgaveansvarlig', 'drift') NOT NULL,
    `approved_date` DATE DEFAULT NULL,
    `approved_by` VARCHAR(50) DEFAULT NULL,
    `approved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `approvals_work_order_role` (`work_order_id`, `role`),
    INDEX `idx_approvals_work_order` (`work_order_id`),
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT,
    `link` VARCHAR(255) DEFAULT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_created` (`created_at` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved Filters table
CREATE TABLE IF NOT EXISTS `saved_filters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `name` VARCHAR(100) NOT NULL,
    `filters` JSON NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_saved_filters_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time Entries table
CREATE TABLE IF NOT EXISTS `time_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `entry_date` DATE NOT NULL,
    `hours` DECIMAL(4,2) NOT NULL CHECK (`hours` >= 0 AND `hours` <= 24),
    `description` TEXT DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_daily_entry` (`work_order_id`, `user_id`, `entry_date`),
    INDEX `idx_time_entries_work_order` (`work_order_id`),
    INDEX `idx_time_entries_user` (`user_id`),
    INDEX `idx_time_entries_date` (`entry_date`),
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tutorial Progress table
CREATE TABLE IF NOT EXISTS `tutorial_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `tutorial_id` VARCHAR(50) NOT NULL,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `tutorial_progress_user_tutorial` (`user_id`, `tutorial_id`),
    INDEX `idx_tutorial_progress_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SJA Entries table (Safety Job Analysis)
CREATE TABLE IF NOT EXISTS `sja_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT,
    `content` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sja_work_order` (`work_order_id`),
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accident Counter table
CREATE TABLE IF NOT EXISTS `accident_counter` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `last_accident_date` DATE DEFAULT NULL,
    `days_without_accident` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `password_hash`, `role`, `approved`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE)
ON DUPLICATE KEY UPDATE `id` = `id`;
