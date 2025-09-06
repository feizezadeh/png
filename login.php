<?php
// Farsi Comments: صفحه لاگین کاربران

header('Content-Type: application/json; charset=utf-8');
require_once 'config/config.php';

// اطمینان از اینکه درخواست از نوع POST است
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['username']) || empty($data['password'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'نام کاربری و رمز عبور الزامی است']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // بررسی وجود کاربر و صحت رمز عبور
    if ($user && password_verify($password, $user['password'])) {
        // لاگین موفقیت آمیز بود
        // ذخیره اطلاعات کاربر در سشن
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['permissions'] = json_decode($user['permissions'], true) ?? [];
        $_SESSION['logged_in'] = true;

        // بازگرداندن اطلاعات کاربر به جز رمز عبور
        unset($user['password']);
        echo json_encode([
            'status' => 'success',
            'message' => 'ورود با موفقیت انجام شد',
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);

    } else {
        // لاگین ناموفق
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['status' => 'error', 'message' => 'نام کاربری یا رمز عبور اشتباه است']);
    }

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', 'message' => 'خطای داخلی سرور']);
}
?>
