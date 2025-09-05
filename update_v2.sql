-- Farsi Comments: اسکریپت برای به‌روزرسانی پایگاه داده به نسخه ۲
-- این اسکریپت ساختار شرکت‌ها و نقش‌های کاربری جدید را اضافه می‌کند.

-- افزودن فیلد آدرس به جدول اشتراک‌ها
ALTER TABLE `subscriptions`
ADD COLUMN `address` TEXT NULL AFTER `virtual_subscriber_number`;

-- ایجاد جدول شرکت‌های پیمانکار
CREATE TABLE `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- به‌روزرسانی جدول کاربران
-- 1. تغییر ENUM برای نقش‌های جدید
-- 2. افزودن ستون برای اتصال به شرکت
ALTER TABLE `users`
MODIFY COLUMN `role` ENUM('super_admin', 'company_admin', 'admin', 'support', 'installer') NOT NULL,
ADD COLUMN `company_id` INT NULL DEFAULT NULL AFTER `role`,
ADD CONSTRAINT `fk_user_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL;

-- ارتقا دادن کاربر ادمین اولیه به نقش "ادمین کل"
UPDATE `users` SET `role` = 'super_admin' WHERE `username` = 'admin';
-- Note: The previous ENUM had 'admin', 'support'. We are changing it.
-- It's better to recreate the column to ensure compatibility across MySQL versions.
-- A more robust way:
-- ALTER TABLE `users` CHANGE `role` `role_old` ENUM('admin', 'support') NOT NULL;
-- ALTER TABLE `users` ADD `role` ENUM('super_admin', 'company_admin', 'installer', 'support') NOT NULL AFTER `password`;
-- UPDATE `users` SET `role` = 'super_admin' WHERE `role_old` = 'admin';
-- UPDATE `users` SET `role` = 'support' WHERE `role_old` = 'support';
-- ALTER TABLE `users` DROP `role_old`;
-- For simplicity in this context, the MODIFY command is used.

-- به‌روزرسانی جدول FAT ها برای اتصال به شرکت
ALTER TABLE `fats`
ADD COLUMN `company_id` INT NULL DEFAULT NULL AFTER `telecom_center_id`,
ADD CONSTRAINT `fk_fat_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE;

-- کامنت: به‌روزرسانی پایگاه داده به نسخه ۲ با موفقیت انجام شد.
-- Comment: Database update to v2 is complete.
