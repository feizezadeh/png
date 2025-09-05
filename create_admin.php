<?php
// Farsi Comments: اسکریپت برای ساخت کاربر ادمین اولیه

// این اسکریپت را فقط یک بار پس از ساخت پایگاه داده اجرا کنید
// Run this script only once after setting up the database

require_once 'config/config.php';

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><title>ساخت ادمین</title><meta charset='UTF-8'></head><body>";
echo "<h1>ساخت کاربر ادمین اولیه</h1>";

// --- تنظیمات کاربر ادمین ---
$admin_username = 'admin';
$admin_password = 'password123'; //
$admin_role = 'admin';
$admin_permissions = json_encode(['*']); // All permissions

// --- هش کردن رمز عبور ---
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// --- بررسی وجود کاربر ادمین ---
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$admin_username]);
    if ($stmt->fetch()) {
        echo "<p style='color:orange;'>کاربر ادمین ('{$admin_username}') قبلاً ایجاد شده است.</p>";
        echo "</body></html>";
        exit;
    }
} catch (PDOException $e) {
    die("<p style='color:red;'>خطا در بررسی کاربر: " . $e->getMessage() . "</p>");
}

// --- افزودن کاربر ادمین به پایگاه داده ---
try {
    $sql = "INSERT INTO users (username, password, role, permissions) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_username, $hashed_password, $admin_role, $admin_permissions]);

    if ($stmt->rowCount()) {
        echo "<p style='color:green;'>کاربر ادمین با موفقیت ایجاد شد!</p>";
        echo "<ul>";
        echo "<li><strong>نام کاربری:</strong> {$admin_username}</li>";
        echo "<li><strong>رمز عبور:</strong> {$admin_password}</li>";
        echo "</ul>";
        echo "<p style='color:red;'><strong>مهم:</strong> لطفاً پس از اولین ورود، رمز عبور خود را تغییر دهید و این فایل (create_admin.php) را حذف کنید.</p>";
    } else {
        echo "<p style='color:red;'>خطا در ایجاد کاربر ادمین.</p>";
    }

} catch (PDOException $e) {
    die("<p style='color:red;'>خطا در افزودن کاربر به پایگاه داده: " . $e->getMessage() . "</p>");
}

echo "</body></html>";
?>
