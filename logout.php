<?php
// Farsi Comments: صفحه خروج کاربران

header('Content-Type: application/json; charset=utf-8');
require_once 'config/config.php'; // session_start() is called inside

// پاک کردن تمام متغیرهای سشن
$_SESSION = [];

// از بین بردن کوکی سشن
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// در نهایت، از بین بردن سشن
session_destroy();

echo json_encode(['status' => 'success', 'message' => 'خروج با موفقیت انجام شد']);
?>
