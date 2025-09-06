<?php
// Farsi Comments: API برای دریافت لیست یکپارچه وظایف (نصب و پشتیبانی)

header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/check_permission.php';

// This endpoint is for admins to see assignable tasks.
secure_api_endpoint(['super_admin', 'company_admin']);

$user_role = $_SESSION['role'];
$user_company_id = $_SESSION['company_id'] ?? null;

try {
    $tasks = [];

    // 1. Get pending installations
    $inst_query = "
        SELECT
            sub.id,
            s.full_name as subject,
            sub.address,
            'installation' as type,
            'ارجاع نصب' as title,
            sub.created_at
        FROM subscriptions sub
        JOIN subscribers s ON sub.subscriber_id = s.id
        JOIN fats f ON sub.fat_id = f.id
        WHERE sub.installation_status = 'pending'
    ";
    $inst_params = [];
    if ($user_role === 'company_admin') {
        $inst_query .= " AND f.company_id = ?";
        $inst_params[] = $user_company_id;
    }
    $stmt_inst = $pdo->prepare($inst_query);
    $stmt_inst->execute($inst_params);
    $installation_tasks = $stmt_inst->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get open support tickets
    $support_query = "
        SELECT
            st.id,
            s.full_name as subject,
            st.issue_description as address,
            'support' as type,
            st.title,
            st.created_at
        FROM support_tickets st
        JOIN subscriptions sub ON st.subscription_id = sub.id
        JOIN subscribers s ON sub.subscriber_id = s.id
        WHERE st.status = 'open'
    ";
    $support_params = [];
    if ($user_role === 'company_admin') {
        $support_query .= " AND st.company_id = ?";
        $support_params[] = $user_company_id;
    }
    $stmt_support = $pdo->prepare($support_query);
    $stmt_support->execute($support_params);
    $support_tasks = $stmt_support->fetchAll(PDO::FETCH_ASSOC);

    // 3. Merge and sort tasks
    $tasks = array_merge($installation_tasks, $support_tasks);

    // Sort by creation date descending
    usort($tasks, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 4. Return JSON
    echo json_encode(['status' => 'success', 'data' => $tasks]);

} catch (PDOException $e) {
    error_log("GET tasks Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', 'message' => 'خطا در بازیابی لیست وظایف']);
}
?>
