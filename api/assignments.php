<?php
// Farsi Comments: API برای ارجاع کارها (نصب و پشتیبانی)

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// All users in this workflow must be logged in.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'لطفا ابتدا وارد شوید.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        // For installers/support to get their own tasks
        handle_get_assignments($pdo, $user_role, $user_id);
        break;
    case 'POST':
        // For company_admin to assign tasks
        handle_post_assignments($pdo, $user_role, $user_company_id);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get_assignments($pdo, $user_role, $user_id) {
    try {
        if ($user_role === 'installer') {
            $query = "
                SELECT sub.id, sub.installation_status, s.full_name as subscriber_name, sub.address, f.fat_number
                FROM subscriptions sub
                JOIN subscribers s ON sub.subscriber_id = s.id
                JOIN fats f ON sub.fat_id = f.id
                WHERE sub.assigned_installer_id = ?
                ORDER BY sub.created_at DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            $assignments = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $assignments]);
        } elseif ($user_role === 'support') {
            // Logic for support users to get their tickets
            $query = "
                SELECT
                    st.id, st.title, st.status, st.created_at,
                    s.full_name as subscriber_name,
                    sub.virtual_subscriber_number
                FROM support_tickets st
                JOIN subscriptions sub ON st.subscription_id = sub.id
                JOIN subscribers s ON sub.subscriber_id = s.id
                WHERE st.assigned_support_id = ?
                ORDER BY st.updated_at DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            $assignments = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $assignments]);
        } else {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'نقش شما برای دریافت لیست کارها مجاز نیست.']);
        }
    } catch (PDOException $e) {
        error_log("GET assignments Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در دریافت لیست کارها']);
    }
}

function handle_post_assignments($pdo, $user_role, $user_company_id) {
    if (!in_array($user_role, ['super_admin', 'company_admin'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'شما اجازه ارجاع کار را ندارید.']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['type']) || empty($data['target_id']) || empty($data['user_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ارجاع کار ناقص است.']);
        return;
    }

    try {
        // --- Security Check for company_admin ---
        if ($user_role === 'company_admin') {
            // 1. Check if the user being assigned to belongs to the admin's company
            $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
            $stmt->execute([$data['user_id']]);
            $target_user = $stmt->fetch();
            if (!$target_user || $target_user['company_id'] != $user_company_id) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['status' => 'error', 'message' => 'کاربر انتخاب شده متعلق به شرکت شما نیست.']);
                return;
            }

            // 2. Check if the target resource belongs to the admin's company
            if ($data['type'] === 'installation') {
                $stmt = $pdo->prepare("SELECT f.company_id FROM subscriptions sub JOIN fats f ON sub.fat_id = f.id WHERE sub.id = ?");
                $stmt->execute([$data['target_id']]);
                $subscription_owner = $stmt->fetch();
                if (!$subscription_owner || $subscription_owner['company_id'] != $user_company_id) {
                    header('HTTP/1.1 403 Forbidden');
                    echo json_encode(['status' => 'error', 'message' => 'اشتراک انتخاب شده متعلق به شرکت شما نیست.']);
                    return;
                }
            } elseif ($data['type'] === 'support') {
                $stmt = $pdo->prepare("SELECT company_id FROM support_tickets WHERE id = ?");
                $stmt->execute([$data['target_id']]);
                $ticket_owner = $stmt->fetch();
                if (!$ticket_owner || $ticket_owner['company_id'] != $user_company_id) {
                    header('HTTP/1.1 403 Forbidden');
                    echo json_encode(['status' => 'error', 'message' => 'تیکت انتخاب شده متعلق به شرکت شما نیست.']);
                    return;
                }
            }
        }
        // --- End Security Check ---

        // Assign based on type
        if ($data['type'] === 'installation') {
            $sql = "UPDATE subscriptions SET assigned_installer_id = ?, installation_status = 'assigned' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['user_id'], $data['target_id']]);
            echo json_encode(['status' => 'success', 'message' => 'نصب با موفقیت به نصاب ارجاع داده شد.']);
        } elseif ($data['type'] === 'support') {
            $sql = "UPDATE support_tickets SET assigned_support_id = ?, status = 'assigned' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['user_id'], $data['target_id']]);
            echo json_encode(['status' => 'success', 'message' => 'تیکت با موفقیت به پشتیبان ارجاع داده شد.']);
        } else {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['status' => 'error', 'message' => 'نوع ارجاع نامعتبر است.']);
        }

    } catch (PDOException $e) {
        error_log("POST assignments Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در فرآیند ارجاع کار']);
    }
}
?>
