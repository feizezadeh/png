<?php
// Farsi Comments: API برای مدیریت شرکت‌های پیمانکار

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// این صفحه فقط برای ادمین کل قابل دسترسی است
secure_api_endpoint('super_admin');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_companies($pdo);
        break;
    case 'POST':
        handle_post_companies($pdo);
        break;
    case 'PUT':
        handle_put_companies($pdo);
        break;
    case 'DELETE':
        handle_delete_companies($pdo);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get_companies($pdo) {
    try {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $company = $stmt->fetch();
            echo json_encode(['status' => 'success', 'data' => $company]);
        } else {
            $stmt = $pdo->query("SELECT * FROM companies ORDER BY name");
            $companies = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $companies]);
        }
    } catch (PDOException $e) {
        error_log("GET companies Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در دریافت اطلاعات شرکت‌ها']);
    }
}

function handle_post_companies($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['name'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نام شرکت الزامی است']);
        return;
    }

    try {
        $sql = "INSERT INTO companies (name, expires_at) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);

        $expires_at = !empty($data['expires_at']) ? $data['expires_at'] : null;
        if ($expires_at) {
            $expires_at = str_replace('/', '-', $expires_at);
        }
        $stmt->execute([htmlspecialchars(strip_tags($data['name'])), $expires_at]);

        $new_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$new_id]);
        $new_company = $stmt->fetch();

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'شرکت با موفقیت اضافه شد', 'data' => $new_company]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام شرکت تکراری است']);
        } else {
            error_log("POST companies Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در افزودن شرکت']);
        }
    }
}

function handle_put_companies($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id']) || empty($data['name'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه و نام شرکت الزامی است']);
        return;
    }

    try {
        $sql = "UPDATE companies SET name = ?, expires_at = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        $expires_at = !empty($data['expires_at']) ? $data['expires_at'] : null;
        if ($expires_at) {
            $expires_at = str_replace('/', '-', $expires_at);
        }
        $stmt->execute([htmlspecialchars(strip_tags($data['name'])), $expires_at, $data['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'شرکت با موفقیت بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'شرکت یافت نشد یا تغییری ایجاد نشد']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام شرکت تکراری است']);
        } else {
            error_log("PUT companies Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی شرکت']);
        }
    }
}

function handle_delete_companies($pdo) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه شرکت الزامی است']);
        return;
    }

    try {
        // ON DELETE CASCADE for fats and ON DELETE SET NULL for users will be handled by the DB
        $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'شرکت و تمام داده‌های مرتبط با آن (FATs, users) با موفقیت حذف/بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'شرکت یافت نشد']);
        }
    } catch (PDOException $e) {
        error_log("DELETE companies Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف شرکت']);
    }
}
?>
