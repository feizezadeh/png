<?php
// Farsi Comments: API برای مدیریت مراکز مخابراتی

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// Access is granted to super_admin and company_admin, but their capabilities differ.
secure_api_endpoint(['super_admin', 'company_admin']);

$method = $_SERVER['REQUEST_METHOD'];
$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

switch ($method) {
    case 'GET':
        handle_get_telecom_centers($pdo, $user_role, $user_company_id);
        break;
    case 'POST':
        handle_post_telecom_centers($pdo, $user_role, $user_company_id);
        break;
    // For simplicity, PUT and DELETE are not implemented for telecom centers in this phase,
    // as they are less critical than for FATs or users.
    default:
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['status' => 'error', 'message' => 'متد غیر مجاز']);
        break;
}

function handle_get_telecom_centers($pdo, $user_role, $user_company_id) {
    try {
        // Super admin can see global centers (company_id IS NULL) and all company-specific centers.
        // Company admin can see global centers and their own company's centers.
        $query = "SELECT * FROM telecom_centers WHERE company_id IS NULL";
        $params = [];
        if ($user_role !== 'super_admin') {
            $query .= " OR company_id = ?";
            $params[] = $user_company_id;
        }
        $query .= " ORDER BY name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $centers = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $centers]);

    } catch (PDOException $e) {
        error_log("GET telecom_centers Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'خطا در دریافت اطلاعات مراکز']);
    }
}

function handle_post_telecom_centers($pdo, $user_role, $user_company_id) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['name'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'نام مرکز مخابراتی الزامی است']);
        return;
    }

    // If a company_admin creates a center, it MUST belong to their company.
    $company_id_to_set = null;
    if ($user_role === 'company_admin') {
        $company_id_to_set = $user_company_id;
    }
    // Super admin can choose to create a global center (null) or assign to a company.
    elseif ($user_role === 'super_admin' && !empty($data['company_id'])) {
        $company_id_to_set = $data['company_id'];
    }

    try {
        $sql = "INSERT INTO telecom_centers (name, company_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([htmlspecialchars(strip_tags($data['name'])), $company_id_to_set]);

        $new_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM telecom_centers WHERE id = ?");
        $stmt->execute([$new_id]);
        $new_center = $stmt->fetch();

        header('HTTP/1.1 201 Created');
        echo json_encode(['status' => 'success', 'message' => 'مرکز مخابراتی با موفقیت اضافه شد', 'data' => $new_center]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('HTTP/1.1 409 Conflict');
            echo json_encode(['status' => 'error', 'message' => 'نام مرکز مخابراتی در این حوزه (سراسری/شرکت) تکراری است']);
        } else {
            error_log("POST telecom_centers Error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['status' => 'error', 'message' => 'خطا در افزودن مرکز مخابراتی']);
        }
    }
}
?>
