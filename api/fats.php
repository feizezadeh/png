<?php
// Farsi Comments: API برای مدیریت FAT ها

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// دسترسی: ادمین کل و ادمین شرکت
secure_api_endpoint(['super_admin', 'company_admin']);

$method = $_SERVER['REQUEST_METHOD'];

// A simple router
$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        handle_get_fats($pdo, $user_role, $user_company_id);
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

function handle_get_fats($pdo, $user_role, $user_company_id) {
    try {
        $base_query = "
            SELECT
                f.id, f.fat_number, f.latitude, f.longitude, f.address, f.splitter_type, f.created_at,
                tc.name AS telecom_center_name,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.fat_id = f.id) AS occupied_ports
            FROM fats f
            JOIN telecom_centers tc ON f.telecom_center_id = tc.id
        ";

        $conditions = [];
        $params = [];

        // Data Scoping
        if ($user_role !== 'super_admin') {
            $conditions[] = "f.company_id = ?";
            $params[] = $user_company_id;
        }

        if (isset($_GET['id'])) {
            $conditions[] = "f.id = ?";
            $params[] = $_GET['id'];

            $query = $base_query . " WHERE " . implode(" AND ", $conditions);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $fat = $stmt->fetch();

            if ($fat) {
                echo json_encode(['status' => 'success', 'data' => $fat]);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['status' => 'error', 'message' => 'FAT یافت نشد']);
            }
        } else {
            if (isset($_GET['telecom_center_id'])) {
                $conditions[] = "f.telecom_center_id = ?";
                $params[] = $_GET['telecom_center_id'];
            }

            $query = $base_query;
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            $query .= " ORDER BY f.fat_number";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
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

    // --- Authorization & Scoping ---
    $company_id_to_set = null;
    if ($_SESSION['role'] === 'company_admin') {
        $company_id_to_set = $_SESSION['company_id'];
    } elseif ($_SESSION['role'] === 'super_admin' && !empty($data['company_id'])) {
        $company_id_to_set = $data['company_id'];
    }
    // A super_admin creating a global FAT is a possibility, but let's assume FATs must belong to a company.
    if ($company_id_to_set === null && $_SESSION['role'] !== 'super_admin') {
         header('HTTP/1.1 403 Forbidden');
         echo json_encode(['status' => 'error', 'message' => 'شما باید به یک شرکت منتسب باشید تا بتوانید FAT ایجاد کنید.']);
         return;
    }
    // --- End Authorization ---

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
        $sql = "INSERT INTO fats (fat_number, telecom_center_id, company_id, latitude, longitude, address, splitter_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        // Sanitize inputs
        $fat_number = htmlspecialchars(strip_tags($data['fat_number']));
        $telecom_center_id = filter_var($data['telecom_center_id'], FILTER_VALIDATE_INT);
        $latitude = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
        $address = isset($data['address']) ? htmlspecialchars(strip_tags($data['address'])) : null;
        $splitter_type = $data['splitter_type'];

        $stmt->execute([$fat_number, $telecom_center_id, $company_id_to_set, $latitude, $longitude, $address, $splitter_type]);

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

    // --- Authorization ---
    if ($_SESSION['role'] === 'company_admin') {
        $stmt = $pdo->prepare("SELECT company_id FROM fats WHERE id = ?");
        $stmt->execute([$data['id']]);
        $fat = $stmt->fetch();
        if (!$fat || $fat['company_id'] != $_SESSION['company_id']) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'شما اجازه ویرایش این FAT را ندارید.']);
            return;
        }
    }
    // --- End Authorization ---

    try {
        // A company admin cannot change the company of a FAT. Only super_admin can.
        $company_sql = ($_SESSION['role'] === 'super_admin' && isset($data['company_id'])) ? "company_id = ?, " : "";

        $sql = "
            UPDATE fats SET
                fat_number = ?,
                telecom_center_id = ?,
                {$company_sql}
                latitude = ?,
                longitude = ?,
                address = ?,
                splitter_type = ?
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($sql);

        $params = [
            htmlspecialchars(strip_tags($data['fat_number'])),
            filter_var($data['telecom_center_id'], FILTER_VALIDATE_INT)
        ];
        if ($_SESSION['role'] === 'super_admin' && isset($data['company_id'])) {
            $params[] = $data['company_id'] ?: null;
        }
        $params[] = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
        $params[] = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
        $params[] = htmlspecialchars(strip_tags($data['address']));
        $params[] = $data['splitter_type'];
        $params[] = $data['id'];

        $stmt->execute($params);

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
