-- Farsi Comments: اسکریپت کامل ساخت پایگاه داده برای سیستم مدیریت اشتراک FTTH
-- نسخه ۳: شامل گردش‌کار نصب و پشتیبانی

-- جدول شرکت‌های پیمانکار
CREATE TABLE `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول مراکز مخابراتی
CREATE TABLE `telecom_centers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `company_id` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_name_company` (`name`, `company_id`),
  CONSTRAINT `fk_tc_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول FAT ها
CREATE TABLE `fats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fat_number` VARCHAR(255) NOT NULL,
  `telecom_center_id` INT NOT NULL,
  `company_id` INT NULL DEFAULT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `address` TEXT,
  `splitter_type` ENUM('1:2', '1:4', '1:8', '1:16', '1:32') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_fat_number_company` (`fat_number`, `company_id`),
  INDEX `idx_telecom_center_id` (`telecom_center_id`),
  INDEX `idx_company_id` (`company_id`),
  CONSTRAINT `fk_fat_telecom_center` FOREIGN KEY (`telecom_center_id`) REFERENCES `telecom_centers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fat_company_rel` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول مشترکین
CREATE TABLE `subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `mobile_number` VARCHAR(20) NOT NULL UNIQUE,
  `national_id` VARCHAR(20) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول کاربران سیستم
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'company_admin', 'installer', 'support') NOT NULL,
  `company_id` INT NULL DEFAULT NULL,
  `permissions` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_user_company_rel` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اشتراک‌ها (نسخه ۳)
CREATE TABLE `subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscriber_id` INT NOT NULL,
  `fat_id` INT NOT NULL,
  `port_number` INT NOT NULL,
  `virtual_subscriber_number` VARCHAR(255) NOT NULL UNIQUE,
  `address` TEXT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `assigned_installer_id` INT NULL DEFAULT NULL,
  `installation_status` ENUM('pending', 'assigned', 'completed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_subscriber_id` (`subscriber_id`),
  INDEX `idx_fat_id` (`fat_id`),
  UNIQUE KEY `uk_fat_port` (`fat_id`, `port_number`),
  CONSTRAINT `fk_sub_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_fat` FOREIGN KEY (`fat_id`) REFERENCES `fats`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_installer` FOREIGN KEY (`assigned_installer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول گزارش‌های نصب
CREATE TABLE `installation_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `installer_id` INT NOT NULL,
  `materials_used` JSON,
  `cable_length` DECIMAL(8, 2),
  `cable_type` VARCHAR(100),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ir_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ir_installer` FOREIGN KEY (`installer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تیکت‌های پشتیبانی (نسخه کامل شده)
CREATE TABLE `support_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `issue_description` TEXT NOT NULL,
  `status` ENUM('open', 'assigned', 'resolved', 'needs_investigation', 'needs_recabling') NOT NULL DEFAULT 'open',
  `created_by_user_id` INT NOT NULL,
  `assigned_support_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_st_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_support_user` FOREIGN KEY (`assigned_support_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول گزارش‌های پشتیبانی
CREATE TABLE `support_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `support_id` INT NOT NULL,
  `notes` TEXT,
  `materials_used` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sr_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_support_user` FOREIGN KEY (`support_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- افزودن ایندکس برای بهبود عملکرد جستجو
ALTER TABLE `subscribers` ADD INDEX `idx_mobile_number` (`mobile_number`);
ALTER TABLE `users` ADD INDEX `idx_username` (`username`);

-- کامنت: پایگاه داده برای اجرا آماده است.
-- Comment: Database is ready for setup.
