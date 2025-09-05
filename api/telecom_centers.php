<?php
// Farsi Comments: API برای مدیریت مراکز مخابراتی

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// بررسی دسترسی - فقط ادمین می‌تواند مدیریت کند
secure_api_endpoint('admin');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($pdo);
        break;
    case 'POST':
        handle_post($pdo);
        break;
    case 'PUT':
        handle_put($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get($pdo) {
    try {
        if (isset($_GET['id'])) {
            // دریافت یک مرکز خاص
            $stmt = $pdo->prepare("SELECT * FROM telecom_centers WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $center = $stmt->fetch();
            if ($center) {
                echo json_encode(['status' => 'success', 'data' => $center]);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['status' => 'error', 'message' => 'مرکز مخابراتی یافت نشد']);
            }
        } else {
            // دریافت تمام مراکز
            $stmt = $pdo->query("SELECT * FROM telecom_centers ORDER BY name");
            $centers = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $centers]);
        }
    } catch (PDOException $e) {
        error_log("GET telecom_centers Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در دریافت اطلاعات']);
    }
}

function handle_post($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['name'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نام مرکز مخابراتی الزامی است']);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO telecom_centers (name) VALUES (?)");
        $stmt->execute([htmlspecialchars(strip_tags($data['name']))]);
        $new_id = $pdo->lastInsertId();

        // دریافت اطلاعات جدید برای بازگشت به کلاینت
        $stmt = $pdo->prepare("SELECT * FROM telecom_centers WHERE id = ?");
        $stmt->execute([$new_id]);
        $new_center = $stmt->fetch();

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'مرکز مخابراتی با موفقیت اضافه شد', 'data' => $new_center]);

    } catch (PDOException $e) {
        // بررسی خطای تکراری بودن نام
        if ($e->getCode() == 23000) { // Integrity constraint violation
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام مرکز مخابراتی تکراری است']);
        } else {
            error_log("POST telecom_centers Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در افزودن مرکز مخابراتی']);
        }
    }
}

function handle_put($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id']) || empty($data['name'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه و نام مرکز مخابراتی الزامی است']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE telecom_centers SET name = ? WHERE id = ?");
        $stmt->execute([htmlspecialchars(strip_tags($data['name'])), $data['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'مرکز مخابراتی با موفقیت بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'مرکز مخابراتی یافت نشد یا تغییری ایجاد نشد']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام مرکز مخابراتی تکراری است']);
        } else {
            error_log("PUT telecom_centers Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی مرکز مخابراتی']);
        }
    }
}

function handle_delete($pdo) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه مرکز مخابراتی الزامی است']);
        return;
    }

    try {
        // ON DELETE CASCADE will handle related FATs and subscriptions
        $stmt = $pdo->prepare("DELETE FROM telecom_centers WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'مرکز مخابراتی و تمام داده‌های مرتبط با موفقیت حذف شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'مرکز مخابراتی یافت نشد']);
        }
    } catch (PDOException $e) {
        error_log("DELETE telecom_centers Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف مرکز مخابراتی']);
    }
}
?>
