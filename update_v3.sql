-- Farsi Comments: اسکریپت برای به‌روزرسانی پایگاه داده به نسخه ۳
-- این اسکریپت ساختار گردش‌کار نصب و پشتیبانی را اضافه می‌کند.

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

-- جدول تیکت‌های پشتیبانی
CREATE TABLE `support_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `assigned_support_id` INT NULL,
  `issue_description` TEXT NOT NULL,
  `status` ENUM('open', 'assigned', 'resolved', 'needs_investigation', 'needs_recabling') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_st_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_st_support_user` FOREIGN KEY (`assigned_support_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- Note: A separate support_reports table might be redundant if the support user can just update the ticket.
-- For now, we'll stick to the plan of having a separate reports table for history.
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


-- به‌روزرسانی جدول اشتراک‌ها برای ارجاع نصب
ALTER TABLE `subscriptions`
ADD COLUMN `assigned_installer_id` INT NULL DEFAULT NULL AFTER `is_active`,
ADD COLUMN `installation_status` ENUM('pending', 'assigned', 'completed') DEFAULT 'pending' AFTER `assigned_installer_id`,
ADD CONSTRAINT `fk_sub_installer` FOREIGN KEY (`assigned_installer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- کامنت: به‌روزرسانی پایگاه داده به نسخه ۳ با موفقیت انجام شد.
-- Comment: Database update to v3 is complete.
