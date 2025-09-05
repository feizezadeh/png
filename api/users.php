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
    // PUT and DELETE will be added in the next phase
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
    }
    // In a later phase, add logic for company_admin creating users
    // elseif ($user_role === 'company_admin' && in_array($data['role'], ['installer', 'support'])) {
    //     $allowed = true;
    // }

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
?>
