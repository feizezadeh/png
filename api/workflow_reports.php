<?php
// Farsi Comments: API برای ثبت گزارش‌های گردش‌کار (نصب و پشتیبانی)

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

switch ($method) {
    case 'POST':
        handle_post_report($pdo, $user_role, $user_id);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_post_report($pdo, $user_role, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['type']) || empty($data['target_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات گزارش ناقص است.']);
        return;
    }

    try {
        if ($data['type'] === 'installation' && $user_role === 'installer') {
            // --- Authorization Check ---
            // Verify this subscription is assigned to this installer
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE id = ? AND assigned_installer_id = ?");
            $stmt->execute([$data['target_id'], $user_id]);
            if (!$stmt->fetch()) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['status' => 'error', 'message' => 'این نصب به شما ارجاع داده نشده است.']);
                return;
            }

            // --- Insert Report ---
            $sql = "
                INSERT INTO installation_reports
                (subscription_id, installer_id, materials_used, cable_length, cable_type, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $materials_used = isset($data['materials_used']) ? json_encode($data['materials_used']) : null;
            $stmt->execute([
                $data['target_id'],
                $user_id,
                $materials_used,
                $data['cable_length'] ?? null,
                $data['cable_type'] ?? null,
                $data['notes'] ?? null
            ]);

            // --- Update Subscription Status and Activate ---
            $stmt = $pdo->prepare("UPDATE subscriptions SET installation_status = 'completed', is_active = 1 WHERE id = ?");
            $stmt->execute([$data['target_id']]);

            header('HTTP/1.1 201 Created');
            echo json_encode(['status' => 'success', 'message' => 'گزارش نصب با موفقیت ثبت شد.']);

        } elseif ($data['type'] === 'support' && $user_role === 'support') {
            // --- Validation ---
            if (empty($data['status']) || empty($data['notes'])) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['status' => 'error', 'message' => 'وضعیت جدید و توضیحات گزارش الزامی است.']);
                return;
            }
            $allowed_statuses = ['resolved', 'needs_investigation', 'needs_recabling'];
            if (!in_array($data['status'], $allowed_statuses)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['status' => 'error', 'message' => 'وضعیت انتخاب شده نامعتبر است.']);
                return;
            }

            // --- Authorization Check ---
            $stmt = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND assigned_support_id = ?");
            $stmt->execute([$data['target_id'], $user_id]);
            if (!$stmt->fetch()) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['status' => 'error', 'message' => 'این تیکت به شما ارجاع داده نشده است.']);
                return;
            }

            // --- Insert Report ---
            $sql = "
                INSERT INTO support_reports (ticket_id, support_id, notes, materials_used)
                VALUES (?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $materials_used = isset($data['materials_used']) ? json_encode($data['materials_used']) : null;
            $stmt->execute([
                $data['target_id'],
                $user_id,
                htmlspecialchars(strip_tags($data['notes'])),
                $materials_used
            ]);

            // --- Update Ticket Status ---
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $data['target_id']]);

            header('HTTP/1.1 201 Created');
            echo json_encode(['status' => 'success', 'message' => 'گزارش پشتیبانی با موفقیت ثبت شد.']);

        } else {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'شما اجازه ثبت این نوع گزارش را ندارید.']);
        }

    } catch (PDOException $e) {
        error_log("POST workflow_reports Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در فرآیند ثبت گزارش']);
    }
}
?>
