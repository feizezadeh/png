-- Farsi Comments: اسکریپت ساخت پایگاه داده برای سیستم مدیریت اشتراک FTTH

-- جدول مراکز مخابراتی
-- telecom_centers table
CREATE TABLE `telecom_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول FAT ها
-- fats table
CREATE TABLE `fats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fat_number` VARCHAR(255) NOT NULL UNIQUE,
  `telecom_center_id` INT NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `address` TEXT,
  `splitter_type` ENUM('1:2', '1:4', '1:8', '1:16', '1:32') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_telecom_center_id` (`telecom_center_id`),
  FOREIGN KEY (`telecom_center_id`) REFERENCES `telecom_centers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول مشترکین
-- subscribers table
CREATE TABLE `subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `mobile_number` VARCHAR(20) NOT NULL UNIQUE,
  `national_id` VARCHAR(20) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اشتراک‌ها
-- subscriptions table
CREATE TABLE `subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscriber_id` INT NOT NULL,
  `fat_id` INT NOT NULL,
  `port_number` INT NOT NULL,
  `virtual_subscriber_number` VARCHAR(255) NOT NULL UNIQUE,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_subscriber_id` (`subscriber_id`),
  INDEX `idx_fat_id` (`fat_id`),
  UNIQUE KEY `uk_fat_port` (`fat_id`, `port_number`),
  FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`fat_id`) REFERENCES `fats`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول کاربران سیستم
-- users table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'support') NOT NULL,
  `permissions` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- افزودن ایندکس برای بهبود عملکرد جستجو
-- Adding indexes for performance
ALTER TABLE `fats` ADD INDEX `idx_fat_number` (`fat_number`);
ALTER TABLE `subscribers` ADD INDEX `idx_mobile_number` (`mobile_number`);
ALTER TABLE `subscribers` ADD INDEX `idx_national_id` (`national_id`);
ALTER TABLE `users` ADD INDEX `idx_username` (`username`);

-- کامنت: پایگاه داده برای اجرا آماده است.
-- Comment: Database is ready for setup.
