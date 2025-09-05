<?php
// Farsi Comments: API برای مدیریت FAT ها

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// دسترسی: ادمین و پشتیبانی
secure_api_endpoint(['admin', 'support']);

$method = $_SERVER['REQUEST_METHOD'];

// A simple router
switch ($method) {
    case 'GET':
        handle_get_fats($pdo);
        break;
    case 'POST':
        handle_post_fats($pdo);
        break;
    case 'PUT':
        handle_put_fats($pdo);
        break;
    case 'DELETE':
        handle_delete_fats($pdo);
        break;
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد درخواستی مجاز نیست']);
        break;
}

function handle_get_fats($pdo) {
    try {
        $query = "
            SELECT
                f.id, f.fat_number, f.latitude, f.longitude, f.address, f.splitter_type, f.created_at,
                tc.name AS telecom_center_name,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.fat_id = f.id) AS occupied_ports
            FROM fats f
            JOIN telecom_centers tc ON f.telecom_center_id = tc.id
        ";

        if (isset($_GET['id'])) {
            // Get a single FAT
            $query .= " WHERE f.id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_GET['id']]);
            $fat = $stmt->fetch();
            if ($fat) {
                echo json_encode(['status' => 'success', 'data' => $fat]);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['status' => 'error', 'message' => 'FAT یافت نشد']);
            }
        } elseif (isset($_GET['telecom_center_id'])) {
            // Get FATs for a specific telecom center
            $query .= " WHERE f.telecom_center_id = ? ORDER BY f.fat_number";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_GET['telecom_center_id']]);
            $fats = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $fats]);
        } else {
            // Get all FATs
            $query .= " ORDER BY f.fat_number";
            $stmt = $pdo->query($query);
            $fats = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $fats]);
        }
    } catch (PDOException $e) {
        error_log("GET fats Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در بازیابی اطلاعات FAT']);
    }
}

function handle_post_fats($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    // Input validation
    $required_fields = ['fat_number', 'telecom_center_id', 'latitude', 'longitude', 'splitter_type'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['status' => 'error', 'message' => "فیلد '$field' الزامی است"]);
            return;
        }
    }

    $splitter_types = ['1:2', '1:4', '1:8', '1:16', '1:32'];
    if (!in_array($data['splitter_type'], $splitter_types)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نوع اسپلیتر نامعتبر است']);
        return;
    }

    try {
        $sql = "INSERT INTO fats (fat_number, telecom_center_id, latitude, longitude, address, splitter_type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        // Sanitize inputs
        $fat_number = htmlspecialchars(strip_tags($data['fat_number']));
        $telecom_center_id = filter_var($data['telecom_center_id'], FILTER_VALIDATE_INT);
        $latitude = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
        $address = isset($data['address']) ? htmlspecialchars(strip_tags($data['address'])) : null;
        $splitter_type = $data['splitter_type'];

        $stmt->execute([$fat_number, $telecom_center_id, $latitude, $longitude, $address, $splitter_type]);

        $new_id = $pdo->lastInsertId();

        // Fetch the newly created FAT to return to the client
        $stmt = $pdo->prepare("
            SELECT f.*, tc.name AS telecom_center_name
            FROM fats f
            JOIN telecom_centers tc ON f.telecom_center_id = tc.id
            WHERE f.id = ?
        ");
        $stmt->execute([$new_id]);
        $new_fat = $stmt->fetch();

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'FAT با موفقیت اضافه شد', 'data' => $new_fat]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Unique constraint violation
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'شماره FAT تکراری است']);
        } else {
            error_log("POST fats Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در افزودن FAT']);
        }
    }
}

function handle_put_fats($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه FAT الزامی است']);
        return;
    }
    // Further validation for other fields can be added here

    try {
        $sql = "
            UPDATE fats SET
                fat_number = ?,
                telecom_center_id = ?,
                latitude = ?,
                longitude = ?,
                address = ?,
                splitter_type = ?
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            htmlspecialchars(strip_tags($data['fat_number'])),
            filter_var($data['telecom_center_id'], FILTER_VALIDATE_INT),
            filter_var($data['latitude'], FILTER_VALIDATE_FLOAT),
            filter_var($data['longitude'], FILTER_VALIDATE_FLOAT),
            htmlspecialchars(strip_tags($data['address'])),
            $data['splitter_type'],
            $data['id']
        ]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'FAT با موفقیت بروزرسانی شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'FAT یافت نشد یا تغییری ایجاد نشد']);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'شماره FAT تکراری است']);
        } else {
            error_log("PUT fats Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در بروزرسانی FAT']);
        }
    }
}

function handle_delete_fats($pdo) {
    if (!isset($_GET['id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'شناسه FAT الزامی است']);
        return;
    }

    try {
        // ON DELETE CASCADE will handle related subscriptions
        $stmt = $pdo->prepare("DELETE FROM fats WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        if ($stmt->rowCount()) {
            echo json_encode(['status' => 'success', 'message' => 'FAT و اشتراک‌های مرتبط با آن با موفقیت حذف شد']);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['status' => 'error', 'message' => 'FAT یافت نشد']);
        }
    } catch (PDOException $e) {
        error_log("DELETE fats Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف FAT']);
    }
}
?>
