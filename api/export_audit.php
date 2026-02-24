<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORRECT PATH to database.php
require_once __DIR__ . '/../app/config/database.php';

// Create database connection by calling the static method
$db = Database::connect();

// Get parameters
$export_type = $_GET['export_type'] ?? 'filtered';
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$export_limit = isset($_GET['export_limit']) ? (int)$_GET['export_limit'] : null;
$export_offset = isset($_GET['export_offset']) ? (int)$_GET['export_offset'] : null;
$page = $_GET['page'] ?? 1;

// Build WHERE clause based on export type
$where = [];
$params = [];

if ($export_type !== 'complete') {
    // Apply filters for non-complete exports
    if ($search !== '') {
        $where[] = "(p.name LIKE :search OR sm.note LIKE :search OR sm.reason LIKE :search OR sm.id LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($typeFilter !== '' && in_array($typeFilter, ['IN', 'OUT'])) {
        $where[] = "sm.type = :type";
        $params[':type'] = $typeFilter;
    }

    if ($dateFrom !== '') {
        $where[] = "DATE(sm.created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where[] = "DATE(sm.created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Build LIMIT clause for page export
$limitSQL = "";
if ($export_type === 'page' && $export_limit !== null && $export_offset !== null) {
    $limitSQL = " LIMIT :limit OFFSET :offset";
}

try {
    // Get total count for filename
    $countSql = "SELECT COUNT(*) FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id $whereSQL";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $recordCount = $countStmt->fetchColumn();

    // Fetch data
    $sql = "
        SELECT 
            sm.id,
            sm.product_id,
            p.name as product_name,
            p.unit,
            c.name as category_name,
            sm.type,
            sm.quantity,
            sm.note,
            sm.reason,
            sm.expiry_date,
            sm.created_at
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        $whereSQL
        ORDER BY sm.created_at DESC
        $limitSQL
    ";

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if ($export_type === 'page' && $export_limit !== null && $export_offset !== null) {
        $stmt->bindValue(':limit', $export_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $export_offset, PDO::PARAM_INT);
    }

    $stmt->execute();

    // Generate filename based on export type
    $filename = 'audit_';
    switch($export_type) {
        case 'page':
            $filename .= 'page_' . $page . '_';
            break;
        case 'complete':
            $filename .= 'full_system_';
            break;
        default:
            $filename .= 'filtered_';
    }
    $filename .= date('Y-m-d') . '_' . $recordCount . 'records.csv';

    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Date',
        'Time',
        'Product Name',
        'Category',
        'Unit',
        'Type',
        'Quantity',
        'Batch/Expiry',
        'Notes/Reason',
        'Product ID'
    ]);

    // Add data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            date('Y-m-d', strtotime($row['created_at'])),
            date('H:i:s', strtotime($row['created_at'])),
            $row['product_name'] ?? 'Deleted Product',
            $row['category_name'] ?? 'N/A',
            $row['unit'] ?? 'N/A',
            $row['type'],
            ($row['type'] == 'IN' ? '+' : '-') . $row['quantity'],
            $row['expiry_date'] ?? 'No batch',
            $row['note'] ?? $row['reason'] ?? '',
            $row['product_id'] ?? 'N/A'
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}