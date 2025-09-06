<?php
// Farsi Comments: API برای تولید گزارش‌های پیشرفته

require_once '../config/config.php';
require_once '../includes/check_permission.php';

// این صفحه فقط برای کاربران با نقش ادمین قابل دسترسی است
secure_api_endpoint(['super_admin', 'company_admin']);

// دریافت پارامترهای فیلتر از درخواست GET
$fat_id = filter_input(INPUT_GET, 'fat_id', FILTER_VALIDATE_INT);
$is_active = filter_input(INPUT_GET, 'is_active', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);
$format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING) ?? 'json'; // json, csv, pdf

// ساخت کوئری پایه
$query = "
    SELECT
        s.full_name AS subscriber_name,
        CONCAT(f.fat_number, ' - Port ', sub.port_number) AS fat_port,
        sub.virtual_subscriber_number,
        sub.is_active,
        sub.created_at,
        (SELECT COUNT(*) FROM subscriptions WHERE fat_id = f.id) AS occupied_capacity,
        f.splitter_type
    FROM subscriptions sub
    JOIN subscribers s ON sub.subscriber_id = s.id
    JOIN fats f ON sub.fat_id = f.id
";

$conditions = [];
$params = [];

// افزودن فیلترها به کوئری
if ($fat_id) {
    $conditions[] = "sub.fat_id = ?";
    $params[] = $fat_id;
}
if ($is_active !== null) {
    $conditions[] = "sub.is_active = ?";
    $params[] = $is_active;
}
if ($start_date) {
    $conditions[] = "sub.created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
}
if ($end_date) {
    $conditions[] = "sub.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY sub.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Report Generation Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', 'message' => 'خطا در تولید گزارش']);
    exit;
}

// تولید خروجی بر اساس فرمت درخواستی
switch ($format) {
    case 'csv':
        generate_csv($report_data);
        break;
    case 'pdf':
        generate_pdf($report_data);
        break;
    case 'json':
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $report_data]);
        break;
}

function generate_csv($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ftth_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // هدر فایل CSV
    fputcsv($output, ["ردیف", "نام مشترک", "FAT و پورت", "شماره اشتراک", "وضعیت", "ظرفیت اشغالی"]);

    foreach ($data as $index => $row) {
        fputcsv($output, [
            $index + 1,
            $row['subscriber_name'],
            $row['fat_port'],
            $row['virtual_subscriber_number'],
            $row['is_active'] ? 'فعال' : 'غیرفعال',
            $row['occupied_capacity'] . ' / ' . explode(':', $row['splitter_type'])[1]
        ]);
    }

    fclose($output);
}

function generate_pdf($data) {
    // مسیر کتابخانه TCPDF
    $tcpdf_path = '../libs/tcpdf/tcpdf.php';

    if (!file_exists($tcpdf_path)) {
        header('HTTP/1.1 501 Not Implemented');
        echo json_encode(['status' => 'error', 'message' => 'کتابخانه PDF نصب نشده است.']);
        exit;
    }
    require_once $tcpdf_path;

    // ایجاد یک سند PDF جدید
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('FTTH Management System');
    $pdf->SetTitle('گزارش اشتراک‌ها');

    // تنظیم فونت فارسی
    $pdf->SetFont('dejavusans', '', 10);

    $pdf->AddPage();

    // ایجاد جدول HTML
    $html = '<h1 style="text-align:center;">گزارش اشتراک‌های FTTH</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="direction:rtl; text-align:right;">';
    $html .= '<thead><tr style="background-color:#eee;">
                <th>ردیف</th>
                <th>نام مشترک</th>
                <th>FAT و پورت</th>
                <th>شماره اشتراک</th>
                <th>وضعیت</th>
                <th>ظرفیت اشغالی</th>
              </tr></thead>';
    $html .= '<tbody>';
    foreach ($data as $index => $row) {
         $html .= '<tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($row['subscriber_name']) . '</td>
                    <td>' . htmlspecialchars($row['fat_port']) . '</td>
                    <td>' . htmlspecialchars($row['virtual_subscriber_number']) . '</td>
                    <td>' . ($row['is_active'] ? 'فعال' : 'غیرفعال') . '</td>
                    <td>' . $row['occupied_capacity'] . ' / ' . explode(':', $row['splitter_type'])[1] . '</td>
                   </tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // خروجی PDF
    $pdf->Output('ftth_report_' . date('Y-m-d') . '.pdf', 'I');
}
?>
