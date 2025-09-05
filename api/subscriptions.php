<?php
// Farsi Comments: API برای مدیریت اشتراک ها

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// دسترسی: ادمین و پشتیبانی
secure_api_endpoint(['admin', 'support']);

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        handle_get_subscriptions($pdo, $user_role, $user_company_id);
        break;
    case 'POST':
        handle_post_subscriptions($pdo);
        break;
    case 'PUT':
        handle_put_subscriptions($pdo);
        break;
    case 'DELETE':
        handle_delete_subscriptions($pdo);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد درخواستی مجاز نیست']);
        break;
}

function handle_get_subscriptions($pdo, $user_role, $user_company_id) {
    try {
        $query = "
            SELECT
                sub.id, sub.port_number, sub.virtual_subscriber_number, sub.is_active, sub.created_at,
                s.full_name AS subscriber_name, s.mobile_number,
                f.fat_number,
                tc.name AS telecom_center_name
            FROM subscriptions sub
            JOIN subscribers s ON sub.subscriber_id = s.id
            JOIN fats f ON sub.fat_id = f.id
            JOIN telecom_centers tc ON f.telecom_center_id = tc.id
        ";

        $params = [];
        $where_clauses = [];

        // Data Scoping
        if ($user_role !== 'super_admin') {
            $where_clauses[] = "f.company_id = ?";
            $params[] = $user_company_id;
        }

        if (isset($_GET['id'])) {
            $where_clauses[] = "sub.id = ?";
            $params[] = $_GET['id'];
        }
        if (isset($_GET['fat_id'])) {
            $where_clauses[] = "sub.fat_id = ?";
            $params[] = $_GET['fat_id'];
        }
        if (isset($_GET['is_active'])) {
            $where_clauses[] = "sub.is_active = ?";
            $params[] = $_GET['is_active'];
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " ORDER BY sub.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        if (isset($_GET['id'])) {
            $result = $stmt->fetch();
            if (!$result) {
                 header('HTTP/1.1 404 Not Found');
                 echo json_encode(['status' => 'error', 'message' => 'اشتراک یافت نشد']);
                 return;
            }
        } else {
            $result = $stmt->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $result]);

    } catch (PDOException $e) {
        error_log("GET subscriptions Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در بازیابی اطلاعات اشتراک‌ها']);
    }
}

function handle_post_subscriptions($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    $required_fields = ['subscriber_id', 'fat_id', 'port_number', 'virtual_subscriber_number'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['status' => 'error', 'message' => "فیلد '$field' الزامی است"]);
            return;
        }
    }

    try {
        // Check if port is already taken for this FAT
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE fat_id = ? AND port_number = ?");
        $stmt->execute([$data['fat_id'], $data['port_number']]);
        if ($stmt->fetch()) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'این پورت در این FAT قبلاً اشغال شده است']);
            return;
        }

        $sql = "INSERT INTO subscriptions (subscriber_id, fat_id, port_number, virtual_subscriber_number, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $is_active = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;

        $stmt->execute([
            filter_var($data['subscriber_id'], FILTER_VALIDATE_INT),
            filter_var($data['fat_id'], FILTER_VALIDATE_INT),
            filter_var($data['port_number'], FILTER_VALIDATE_INT),
            htmlspecialchars(strip_tags($data['virtual_subscriber_number'])),
            $is_active
        ]);

        $new_id = $pdo->lastInsertId();
        // Fetch the newly created subscription to return
        // (Similar to GET logic for a single ID)

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'اشتراک با موفقیت ثبت شد', 'data' => ['id' => $new_id]]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
             header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'شماره اشتراک مجازی تکراری است']);
        } else {
            error_log("POST subscriptions Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در ثبت اشتراک']);
        }
    }
}

function handle_put_subscriptions($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه اشتراک الزامی است']);
        return;
    }

    try {
        // For updates, you might only update certain fields, e.g., is_active
        if (isset($data['is_active'])) {
            $sql = "UPDATE subscriptions SET is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN), $data['id']]);
        } else {
            // Or update more fields if needed
            $sql = "UPDATE subscriptions SET port_number = ?, virtual_subscriber_number = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['port_number'], $data['virtual_subscriber_number'], $data['id']]);
        }

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'اشتراک با موفقیت بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'اشتراک یافت نشد یا تغییری ایجاد نشد']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'مقدار تکراری (مثلاً پورت یا شماره اشتراک)']);
        } else {
            error_log("PUT subscriptions Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی اشتراک']);
        }
    }
}

function handle_delete_subscriptions($pdo) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه اشتراک الزامی است']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'اشتراک با موفقیت حذف شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'اشتراک یافت نشد']);
        }
    } catch (PDOException $e) {
        error_log("DELETE subscriptions Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف اشتراک']);
    }
}
?>
