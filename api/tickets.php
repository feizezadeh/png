<?php
// Farsi Comments: API برای مدیریت تیکت‌های پشتیبانی

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// Users must be logged in to access this API
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'لطفا ابتدا وارد شوید.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$user_company_id = $_SESSION['company_id'] ?? null;

// Only company_admin, support, and super_admin roles can access this API
secure_api_endpoint(['company_admin', 'support', 'super_admin']);

switch ($method) {
    case 'GET':
        handle_get_tickets($pdo, $user_role, $user_company_id);
        break;
    case 'POST':
        // Only company_admin can create tickets
        if ($user_role !== 'company_admin') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'شما اجازه ساخت تیکت جدید را ندارید.']);
            exit;
        }
        handle_post_ticket($pdo, $user_id, $user_company_id);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get_tickets($pdo, $user_role, $user_company_id) {
    try {
        $query = "
            SELECT
                st.id,
                st.subscription_id,
                st.issue_description AS title,
                st.status,
                st.created_at,
                s.virtual_subscriber_number,
                sub.full_name AS subscriber_name,
                u_creator.username AS created_by,
                u_assignee.username AS assigned_to
            FROM support_tickets st
            JOIN subscriptions s ON st.subscription_id = s.id
            JOIN subscribers sub ON s.subscriber_id = sub.id
            JOIN users u_creator ON st.created_by_user_id = u_creator.id
            LEFT JOIN users u_assignee ON st.assigned_support_id = u_assignee.id
        ";

        $params = [];
        $where_clauses = [];

        // Data Scoping
        if ($user_role === 'company_admin' || $user_role === 'support') {
            $where_clauses[] = "st.company_id = ?";
            $params[] = $user_company_id;
        }

        if (isset($_GET['id'])) {
            $where_clauses[] = "st.id = ?";
            $params[] = $_GET['id'];
        }

        if (isset($_GET['status'])) {
            $where_clauses[] = "st.status = ?";
            $params[] = $_GET['status'];
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " ORDER BY st.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        if (isset($_GET['id'])) {
            $result = $stmt->fetch();
             if (!$result) {
                 header('HTTP/1.1 404 Not Found');
                 echo json_encode(['status' => 'error', 'message' => 'تیکت یافت نشد']);
                 return;
            }
        } else {
            $result = $stmt->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $result]);

    } catch (PDOException $e) {
        error_log("GET tickets Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در بازیابی اطلاعات تیکت‌ها']);
    }
}

function handle_post_ticket($pdo, $user_id, $user_company_id) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['subscription_id']) || empty($data['title']) || empty($data['description'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات تیکت ناقص است (اشتراک، عنوان، و توضیحات الزامی است).']);
        return;
    }

    try {
        // --- Security Check: Ensure the subscription belongs to the admin's company ---
        $stmt = $pdo->prepare("SELECT f.company_id FROM subscriptions sub JOIN fats f ON sub.fat_id = f.id WHERE sub.id = ?");
        $stmt->execute([$data['subscription_id']]);
        $subscription_owner = $stmt->fetch();

        if (!$subscription_owner || $subscription_owner['company_id'] != $user_company_id) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'اشتراک انتخاب شده متعلق به شرکت شما نیست.']);
            return;
        }

        $sql = "
            INSERT INTO support_tickets
            (subscription_id, company_id, title, issue_description, created_by_user_id, status)
            VALUES (?, ?, ?, ?, ?, 'open')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['subscription_id'],
            $user_company_id,
            htmlspecialchars(strip_tags($data['title'])),
            htmlspecialchars(strip_tags($data['description'])),
            $user_id
        ]);

        $new_id = $pdo->lastInsertId();
        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'تیکت پشتیبانی با موفقیت ایجاد شد.', 'data' => ['id' => $new_id]]);

    } catch (PDOException $e) {
        error_log("POST ticket Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در ایجاد تیکت پشتیبانی']);
    }
}
?>
