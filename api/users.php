<?php
// Farsi Comments: API برای مدیریت کاربران

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// All logged-in users can potentially access this page, but actions are restricted by role.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'لطفا ابتدا وارد شوید.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        handle_get_users($pdo, $user_role, $user_company_id);
        break;
    case 'POST':
        handle_post_users($pdo, $user_role);
        break;
    case 'PUT':
        handle_put_users($pdo, $user_role, $user_company_id);
        break;
    case 'DELETE':
        handle_delete_users($pdo, $user_role, $user_company_id);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get_users($pdo, $user_role, $user_company_id) {
    // Super admin can see all users. Company admin sees their own users.
    if ($user_role === 'super_admin') {
        $query = "SELECT u.id, u.username, u.role, u.company_id, c.name as company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id ORDER BY u.username";
        $stmt = $pdo->query($query);
        $users = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $users]);
    } elseif ($user_role === 'company_admin') {
        // In a later phase, this will show the company's users
        $query = "SELECT u.id, u.username, u.role FROM users u WHERE u.company_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_company_id]);
        $users = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $users]);
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'دسترسی غیر مجاز']);
    }
}

function handle_post_users($pdo, $user_role) {
    $data = json_decode(file_get_contents('php://input'), true);

    // --- Validation ---
    if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نام کاربری، رمز عبور و نقش الزامی است']);
        return;
    }

    // --- Authorization ---
    $allowed = false;
    if ($user_role === 'super_admin' && $data['role'] === 'company_admin') {
        if (empty($data['company_id'])) {
             header('HTTP/1.1 400 Bad Request');
             echo json_encode(['status' => 'error', 'message' => 'برای ادمین شرکت، انتخاب شرکت الزامی است']);
             return;
        }
        $allowed = true;
    } elseif ($user_role === 'company_admin' && in_array($data['role'], ['installer', 'support'])) {
        $allowed = true;
        // Force the company_id to be the admin's own company
        $data['company_id'] = $_SESSION['company_id'];
    }

    if (!$allowed) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'شما اجازه ساخت کاربر با این نقش را ندارید.']);
        return;
    }

    // --- Execution ---
    try {
        $sql = "INSERT INTO users (username, password, role, company_id) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $company_id = $data['company_id'] ?? null;

        $stmt->execute([
            htmlspecialchars(strip_tags($data['username'])),
            $hashed_password,
            $data['role'],
            $company_id
        ]);

        $new_id = $pdo->lastInsertId();
        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'کاربر با موفقیت ایجاد شد', 'data' => ['id' => $new_id]]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام کاربری تکراری است']);
        } else {
            error_log("POST users Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در ایجاد کاربر']);
        }
    }
}

function handle_put_users($pdo, $user_role, $user_company_id) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه کاربر الزامی است']);
        return;
    }

    // A company_admin can only edit users in their own company.
    if ($user_role === 'company_admin') {
        $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        $target_user = $stmt->fetch();
        if (!$target_user || $target_user['company_id'] != $user_company_id) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'شما اجازه ویرایش این کاربر را ندارید.']);
            return;
        }
    }

    // Build query dynamically based on what's provided
    $fields = [];
    $params = [];
    if (!empty($data['password'])) {
        $fields[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (!empty($data['role'])) {
        $fields[] = "role = ?";
        $params[] = $data['role'];
    }
    // A super_admin can also change a user's company
    if ($user_role === 'super_admin' && isset($data['company_id'])) {
         $fields[] = "company_id = ?";
         $params[] = $data['company_id'] ?: null;
    }

    if (empty($fields)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'هیچ فیلدی برای بروزرسانی مشخص نشده است.']);
        return;
    }

    $params[] = $data['id'];
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'message' => 'کاربر با موفقیت بروزرسانی شد.']);
    } catch (PDOException $e) {
        error_log("PUT users Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی کاربر.']);
    }
}

function handle_delete_users($pdo, $user_role, $user_company_id) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه کاربر الزامی است']);
        return;
    }
    $user_id_to_delete = $_GET['id'];

    // Prevent users from deleting themselves
    if ($user_id_to_delete == $_SESSION['user_id']) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'شما نمی‌توانید خودتان را حذف کنید.']);
        return;
    }

    // A company_admin can only delete users in their own company.
    if ($user_role === 'company_admin') {
        $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_delete]);
        $target_user = $stmt->fetch();
        if (!$target_user || $target_user['company_id'] != $user_company_id) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'شما اجازه حذف این کاربر را ندارید.']);
            return;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_delete]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'کاربر با موفقیت حذف شد.']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'کاربر یافت نشد.']);
        }
    } catch (PDOException $e) {
        error_log("DELETE users Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف کاربر.']);
    }
}
?>
