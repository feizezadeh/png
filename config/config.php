<?php
// Farsi Comments: فایل تنظیمات پایگاه داده

// اطلاعات اتصال به پایگاه داده
// Database connection details
define('DB_HOST', 'localhost');
define('DB_NAME', 'ftth_db');
define('DB_USER', 'root');
define('DB_PASS', ''); //
define('DB_CHARSET', 'utf8mb4');

// تنظیمات PDO برای اتصال
// PDO settings for connection
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ایجاد اتصال PDO
// Create PDO connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // در صورت بروز خطا، آن را لاگ کرده و اسکریپت را متوقف کنید
    // In case of error, log it and stop the script
    error_log("Database Connection Error: " . $e->getMessage());
    // برای کاربر یک پیام عمومی نمایش دهید
    // Show a generic message to the user
    die("خطا در اتصال به پایگاه داده. لطفاً بعداً تلاش کنید.");
}

// تنظیمات کلی برنامه
// General application settings
define('SITE_URL', 'http://localhost/ftth-management'); // آدرس سایت
define('REPORTS_DIR', __DIR__ . '/../reports'); // مسیر ذخیره گزارشات

// اطمینان از وجود پوشه گزارشات
// Ensure the reports directory exists
if (!is_dir(REPORTS_DIR)) {
    mkdir(REPORTS_DIR, 0755, true);
}

// شروع جلسه برای مدیریت لاگین کاربر
// Start session for user login management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
