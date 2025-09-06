<?php
// Farsi Comments: API برای مدیریت مشترکین

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// دسترسی: ادمین کل، ادمین شرکت، و پشتیبانی
secure_api_endpoint(['super_admin', 'company_admin', 'support']);

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        handle_get_subscribers($pdo, $user_role, $user_company_id);
        break;
    case 'POST':
        handle_post_subscribers($pdo);
        break;
    case 'PUT':
        handle_put_subscribers($pdo);
        break;
    case 'DELETE':
        handle_delete_subscribers($pdo);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد درخواستی مجاز نیست']);
        break;
}

function handle_get_subscribers($pdo, $user_role, $user_company_id) {
    try {
        $base_query = "SELECT * FROM subscribers";
        $conditions = [];
        $params = [];

        // Data Scoping
        if ($user_role !== 'super_admin') {
            // Use a JOIN for better performance
            $base_query = "
                SELECT DISTINCT s.*
                FROM subscribers s
                JOIN subscriptions sub ON s.id = sub.subscriber_id
                JOIN fats f ON sub.fat_id = f.id
            ";
            $conditions[] = "f.company_id = ?";
            $params[] = $user_company_id;
        }

        if (isset($_GET['id'])) {
            $conditions[] = "id = ?";
            $params[] = $_GET['id'];

            $query = $base_query . " WHERE " . implode(" AND ", $conditions);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $subscriber = $stmt->fetch();

            if ($subscriber) {
                echo json_encode(['status' => 'success', 'data' => $subscriber]);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['status' => 'error', 'message' => 'مشترک یافت نشد']);
            }
        } else {
            $query = $base_query;
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            $query .= " ORDER BY full_name";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $subscribers = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $subscribers]);
        }
    } catch (PDOException $e) {
        error_log("GET subscribers Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در بازیابی اطلاعات مشترکین']);
    }
}

function handle_post_subscribers($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    // Input validation
    if (empty($data['full_name']) || empty($data['mobile_number'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نام کامل و شماره موبایل الزامی است']);
        return;
    }

    // Basic validation for mobile and national ID
    if (!preg_match('/^09[0-9]{9}$/', $data['mobile_number'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'فرمت شماره موبایل نامعتبر است']);
        return;
    }
    if (!empty($data['national_id']) && !preg_match('/^[0-9]{10}$/', $data['national_id'])) {
         header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'فرمت کد ملی نامعتبر است']);
        return;
    }

    try {
        $sql = "INSERT INTO subscribers (full_name, mobile_number, national_id) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $full_name = htmlspecialchars(strip_tags($data['full_name']));
        $mobile_number = htmlspecialchars(strip_tags($data['mobile_number']));
        $national_id = !empty($data['national_id']) ? htmlspecialchars(strip_tags($data['national_id'])) : null;

        $stmt->execute([$full_name, $mobile_number, $national_id]);

        $new_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM subscribers WHERE id = ?");
        $stmt->execute([$new_id]);
        $new_subscriber = $stmt->fetch();

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'مشترک با موفقیت اضافه شد', 'data' => $new_subscriber]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Unique constraint violation
            header('HTTP/1.1 409 Conflict');
            $message = strpos($e->getMessage(), 'mobile_number') !== false
                ? 'شماره موبایل تکراری است'
                : 'کد ملی تکراری است';
            echo json_encode(['status' => 'error', 'message' => $message]);
        } else {
            error_log("POST subscribers Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در افزودن مشترک']);
        }
    }
}

function handle_put_subscribers($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه مشترک الزامی است']);
        return;
    }

    try {
        $sql = "UPDATE subscribers SET full_name = ?, mobile_number = ?, national_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        $full_name = htmlspecialchars(strip_tags($data['full_name']));
        $mobile_number = htmlspecialchars(strip_tags($data['mobile_number']));
        $national_id = !empty($data['national_id']) ? htmlspecialchars(strip_tags($data['national_id'])) : null;

        $stmt->execute([$full_name, $mobile_number, $national_id, $data['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'اطلاعات مشترک با موفقیت بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'مشترک یافت نشد یا تغییری ایجاد نشد']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'شماره موبایل یا کد ملی تکراری است']);
        } else {
            error_log("PUT subscribers Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی اطلاعات مشترک']);
        }
    }
}

function handle_delete_subscribers($pdo) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه مشترک الزامی است']);
        return;
    }

    try {
        // ON DELETE CASCADE will handle related subscriptions
        $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'مشترک و اشتراک‌های مرتبط با آن با موفقیت حذف شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'مشترک یافت نشد']);
        }
    } catch (PDOException $e) {
        error_log("DELETE subscribers Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف مشترک']);
    }
}
?>
