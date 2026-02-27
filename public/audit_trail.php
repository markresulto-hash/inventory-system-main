<?php
session_start(); // Add this at the very top
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

/* ================================
   FILTERS + PAGINATION
================================ */

$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$staffFilter = $_GET['staff'] ?? ''; // Using staff_id filter
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
        ? (int)$_GET['page']
        : 1;

// Entries per page selector
$per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 20, 50, 100]) 
            ? (int)$_GET['per_page'] 
            : 20;

$offset = ($page - 1) * $per_page;

// Use UNION to combine stock movements AND product audit
$unionQueries = [];
$allParams = [];

/* ================================
   STOCK MOVEMENTS QUERY
================================ */
$stockWhere = [];
$stockParams = [];

/* SEARCH FILTER */
if ($search !== '') {
    $stockWhere[] = "(p.name LIKE :stock_search OR sm.note LIKE :stock_search OR sm.reason LIKE :stock_search OR sm.id LIKE :stock_search OR staff.name LIKE :stock_search)";
    $stockParams[':stock_search'] = "%{$search}%";
}

/* TYPE FILTER - Only include stock movements if type matches IN/OUT or if no type filter or if type filter is for product actions */
if ($typeFilter === '' || $typeFilter == 'IN' || $typeFilter == 'OUT') {
    // This query will be included
    if ($typeFilter == 'IN' || $typeFilter == 'OUT') {
        $stockWhere[] = "sm.type = :stock_type";
        $stockParams[':stock_type'] = $typeFilter;
    }
} else {
    // If filtering by ADD/EDIT/DELETE, exclude stock movements by adding impossible condition
    $stockWhere[] = "1=0";
}

/* STAFF FILTER */
if ($staffFilter !== '' && is_numeric($staffFilter)) {
    $stockWhere[] = "sm.staff_id = :stock_staff_id";
    $stockParams[':stock_staff_id'] = $staffFilter;
}

/* DATE RANGE FILTER */
if ($dateFrom !== '') {
    $stockWhere[] = "DATE(sm.created_at) >= :stock_date_from";
    $stockParams[':stock_date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $stockWhere[] = "DATE(sm.created_at) <= :stock_date_to";
    $stockParams[':stock_date_to'] = $dateTo;
}

$stockWhereSQL = !empty($stockWhere) ? "WHERE " . implode(" AND ", $stockWhere) : "";

$unionQueries[] = "
    SELECT 
        CONCAT('SM-', sm.id) as id,
        sm.product_id,
        p.name as product_name,
        p.unit,
        c.name as category_name,
        sm.type,
        sm.quantity,
        sm.note,
        sm.reason,
        sm.expiry_date,
        sm.created_at,
        sm.staff_id,
        staff.name as staff_name,
        CASE 
            WHEN sm.type = 'IN' THEN '+' 
            ELSE '-' 
        END as direction,
        'stock' as source
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users staff ON sm.staff_id = staff.id
    $stockWhereSQL
";

// Merge stock parameters
foreach ($stockParams as $key => $value) {
    $allParams[$key] = $value;
}

/* ================================
   PRODUCT AUDIT QUERY (ADD/EDIT/DELETE)
   FIXED: Properly extract from nested JSON structure
================================ */
$productWhere = [];
$productParams = [];

/* SEARCH FILTER */
if ($search !== '') {
    $productWhere[] = "(pa.old_data LIKE :product_search OR staff.name LIKE :product_search)";
    $productParams[':product_search'] = "%{$search}%";
}

/* TYPE FILTER - Only include product audit if type matches ADD/EDIT/DELETE or if no type filter or if type filter is for stock actions */
if ($typeFilter === '' || $typeFilter == 'ADD' || $typeFilter == 'EDIT' || $typeFilter == 'DELETE') {
    // This query will be included
    if ($typeFilter == 'ADD' || $typeFilter == 'EDIT' || $typeFilter == 'DELETE') {
        $productWhere[] = "pa.action = :product_action";
        $productParams[':product_action'] = $typeFilter;
    }
} else {
    // If filtering by IN/OUT, exclude product audits by adding impossible condition
    $productWhere[] = "1=0";
}

/* STAFF FILTER */
if ($staffFilter !== '' && is_numeric($staffFilter)) {
    $productWhere[] = "pa.staff_id = :product_staff_id";
    $productParams[':product_staff_id'] = $staffFilter;
}

/* DATE RANGE FILTER */
if ($dateFrom !== '') {
    $productWhere[] = "DATE(pa.created_at) >= :product_date_from";
    $productParams[':product_date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $productWhere[] = "DATE(pa.created_at) <= :product_date_to";
    $productParams[':product_date_to'] = $dateTo;
}

$productWhereSQL = !empty($productWhere) ? "WHERE " . implode(" AND ", $productWhere) : "";

// FIXED: Properly extract from nested JSON structure
$unionQueries[] = "
    SELECT 
        CONCAT('PA-', pa.id) as id,
        pa.product_id,
        COALESCE(
            -- For ADD actions, get from new data in the nested structure
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.name')),
            -- For EDIT actions, get from new data in the nested structure
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.name')),
            -- For DELETE actions, get from old data in the nested structure
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.old.name')),
            -- Fallback
            'Unknown Product'
        ) as product_name,
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.unit')),
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.old.unit')),
            NULL
        ) as unit,
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.category_name')),
            JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.old.category_name')),
            NULL
        ) as category_name,
        pa.action as type,
        NULL as quantity,
        CASE 
            WHEN pa.action = 'ADD' THEN 
                CONCAT('Added product: ', JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.name')))
            WHEN pa.action = 'EDIT' THEN 
                CONCAT('Edited product: ', JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.new.name')), 
                       ' (was: ', JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.old.name')), ')')
            WHEN pa.action = 'DELETE' THEN 
                CONCAT('Deleted product: ', JSON_UNQUOTE(JSON_EXTRACT(pa.old_data, '$.old.name')))
            ELSE pa.action
        END as note,
        NULL as reason,
        NULL as expiry_date,
        pa.created_at,
        pa.staff_id,
        staff.name as staff_name,
        CASE 
            WHEN pa.action = 'ADD' THEN '+'
            WHEN pa.action = 'EDIT' THEN '✎'
            WHEN pa.action = 'DELETE' THEN '✕'
            ELSE ''
        END as direction,
        'product' as source
    FROM product_audit pa
    LEFT JOIN users staff ON pa.staff_id = staff.id
    $productWhereSQL
";

// Merge product parameters
foreach ($productParams as $key => $value) {
    $allParams[$key] = $value;
}

/* ================================
   COMBINED QUERY WITH PAGINATION
================================ */

// Combine all queries with UNION
$combinedSQL = implode(" UNION ALL ", $unionQueries) . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// Count query - wrap in subquery for total
$countSQL = "SELECT COUNT(*) as total FROM (" . implode(" UNION ALL ", $unionQueries) . ") as combined";

// Prepare and execute count
$countStmt = $db->prepare($countSQL);

// Bind parameters for count
foreach ($allParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}

$countStmt->execute();
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $per_page));

// Ensure current page doesn't exceed total pages
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $per_page;
}

// Prepare and execute main query
$stmt = $db->prepare($combinedSQL);

// Bind parameters
foreach ($allParams as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$auditTrail = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================
   SUMMARY STATISTICS
================================ */

// Stock movements summary
$stockSummary = $db->query("
    SELECT 
        COUNT(*) as total_movements,
        SUM(CASE WHEN type = 'IN' THEN 1 ELSE 0 END) as total_in,
        SUM(CASE WHEN type = 'OUT' THEN 1 ELSE 0 END) as total_out,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_movements
    FROM stock_movements
")->fetch(PDO::FETCH_ASSOC);

// Product audit summary
$productSummary = $db->query("
    SELECT 
        COUNT(*) as total_product_actions,
        SUM(CASE WHEN action = 'ADD' THEN 1 ELSE 0 END) as total_adds,
        SUM(CASE WHEN action = 'EDIT' THEN 1 ELSE 0 END) as total_edits,
        SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) as total_deletes
    FROM product_audit
")->fetch(PDO::FETCH_ASSOC);

// Combined summary - but we'll only show stock movements in the cards as requested
$summary = [
    'total_movements' => $stockSummary['total_movements'] ?? 0,
    'total_in' => $stockSummary['total_in'] ?? 0,
    'total_out' => $stockSummary['total_out'] ?? 0,
    'today_movements' => $stockSummary['today_movements'] ?? 0
];

/* Get staff for filter dropdown - from users table */
$staff = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get total records for complete export - including both tables
$totalStockRecords = $db->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$totalProductRecords = $db->query("SELECT COUNT(*) FROM product_audit")->fetchColumn();
$totalAllRecords = $totalStockRecords + $totalProductRecords;

// Function to generate pagination HTML
function renderPagination($page, $totalPages, $params, $position = 'bottom') {
    if ($totalPages <= 1) return '';
    
    $html = '<div class="pagination ' . $position . '-pagination">';
    
    // First page
    if ($page > 1) {
        $html .= '<a href="?' . http_build_query(array_merge($params, ['page' => 1])) . '#table-top" class="pagination-link first" title="First Page">⏮</a>';
        $html .= '<a href="?' . http_build_query(array_merge($params, ['page' => $page-1])) . '#table-top" class="pagination-link prev" title="Previous Page">◀</a>';
    }
    
    // Page numbers
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    
    if ($start > 1) {
        $html .= '<span class="pagination-ellipsis">...</span>';
    }
    
    for($i = $start; $i <= $end; $i++) {
        $activeClass = $i == $page ? 'active' : '';
        $html .= '<a href="?' . http_build_query(array_merge($params, ['page' => $i])) . '#table-top" class="pagination-link ' . $activeClass . '">' . $i . '</a>';
    }
    
    if ($end < $totalPages) {
        $html .= '<span class="pagination-ellipsis">...</span>';
    }
    
    // Next and Last
    if ($page < $totalPages) {
        $html .= '<a href="?' . http_build_query(array_merge($params, ['page' => $page+1])) . '#table-top" class="pagination-link next" title="Next Page">▶</a>';
        $html .= '<a href="?' . http_build_query(array_merge($params, ['page' => $totalPages])) . '#table-top" class="pagination-link last" title="Last Page">⏭</a>';
    }
    
    // Page info
    $html .= '<span class="page-info">Page ' . $page . ' of ' . $totalPages . '</span>';
    $html .= '</div>';
    
    return $html;
}

// Get current GET parameters for pagination links
$paginationParams = $_GET;
unset($paginationParams['page']); // Remove page to add it dynamically

// Add filter container ID for scroll preservation
$filterContainerId = 'filter-section';
?>

<!DOCTYPE html>
<html>
<head>
<title>Audit Trail - Inventory System</title>
<link rel="icon" type="image/png" href="../img/sunset2.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* Staff badge styles */
.staff-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background-color: #e9ecef;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    color: #495057;
    font-weight: 500;
}

.staff-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    margin-right: 5px;
}

/* Filter tag for staff */
.filter-tag.staff {
    background-color: #cce5ff;
    border-color: #b8daff;
    color: #004085;
}

/* Quick filter button */
.btn-quick {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    font-size: 13px;
}

.btn-quick:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
}

/* New badge styles for product events */
.badge-add {
    background-color: #cce5ff;
    color: #004085;
    border: 1px solid #b8daff;
}

.badge-edit {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.badge-delete {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Source indicator */
.source-badge {
    font-size: 10px;
    padding: 2px 4px;
    border-radius: 3px;
    margin-left: 4px;
    background-color: #f8f9fa;
    color: #6c757d;
    display: inline-block;
}

/* Rest of your existing styles remain exactly the same */
.filter-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    scroll-margin-top: 20px; /* Helps with scroll positioning */
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group input,
.filter-group select {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-clear {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-clear:hover {
    background: #7f8c8d;
    transform: translateY(-2px);
    color: white;
}

.per-page {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.per-page select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    cursor: pointer;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.summary-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-card .value {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
}

.summary-card.in { border-left: 4px solid #27ae60; }
.summary-card.out { border-left: 4px solid #e74c3c; }
.summary-card.total { border-left: 4px solid #3498db; }
.summary-card.today { border-left: 4px solid #f39c12; }

/* Export Panel */
.export-panel {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.export-panel h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.export-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.export-option {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 20px;
    border-radius: 10px;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.export-option:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,0.15);
    border-color: white;
}

.export-option h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.export-option p {
    margin: 0 0 15px 0;
    font-size: 13px;
    opacity: 0.9;
    line-height: 1.5;
}

.export-option .btn-export {
    background: white;
    color: #667eea;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    width: 100%;
    text-align: center;
    box-sizing: border-box;
}

.export-option .btn-export:hover {
    transform: scale(1.02);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.record-count {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    margin-top: 10px;
}

/* Pagination */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    color: #495057;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.pagination-link:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.pagination-link.active {
    background: #3498db;
    border-color: #3498db;
    color: white;
    font-weight: 600;
}

.page-info {
    margin-left: 15px;
    padding: 8px 15px;
    background: #f8f9fa;
    border-radius: 20px;
    color: #495057;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #dee2e6;
}

/* Table */
.table-container {
    background: white;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin: 20px 0;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.audit-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.audit-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.audit-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.badge-in {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge-out {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.quantity-in {
    color: #27ae60;
    font-weight: 700;
}

.quantity-out {
    color: #e74c3c;
    font-weight: 700;
}

.id-badge {
    background-color: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.date-cell {
    font-size: 13px;
    color: #666;
}

.date-cell small {
    display: block;
    color: #999;
    font-size: 11px;
}

.notes-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #666;
}

.table-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 0 5px;
}

.showing-info {
    color: #666;
    font-size: 14px;
}

.showing-info strong {
    color: #2c3e50;
}

.active-filters {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 8px;
    margin-top: 15px;
    font-size: 13px;
    color: #1976d2;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-tag {
    background: white;
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-tag .remove-filter {
    color: #999;
    text-decoration: none;
    font-weight: bold;
    margin-left: 5px;
}

.filter-tag .remove-filter:hover {
    color: #e74c3c;
}

.empty-state {
    text-align: center;
    padding: 50px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions button,
    .filter-actions a {
        width: 100%;
        text-align: center;
    }
    
    .per-page {
        margin-left: 0;
        width: 100%;
        justify-content: flex-start;
    }
    
    .pagination {
        gap: 4px;
    }
    
    .pagination-link {
        min-width: 35px;
        height: 35px;
        font-size: 12px;
    }
}
</style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>

            <div class="audit-container">
                <h1>📋 Audit Trail</h1>

                <!-- Summary Cards - Unchanged, only showing stock movements -->
                <div class="summary-cards">
                    <div class="summary-card total">
                        <h3>Total Movements</h3>
                        <div class="value"><?= number_format($summary['total_movements'] ?? 0) ?></div>
                    </div>
                    <div class="summary-card in">
                        <h3>Stock In</h3>
                        <div class="value"><?= number_format($summary['total_in'] ?? 0) ?></div>
                    </div>
                    <div class="summary-card out">
                        <h3>Stock Out</h3>
                        <div class="value"><?= number_format($summary['total_out'] ?? 0) ?></div>
                    </div>
                    <div class="summary-card today">
                        <h3>Today's Movements</h3>
                        <div class="value"><?= number_format($summary['today_movements'] ?? 0) ?></div>
                    </div>
                </div>

                <!-- Export Panel - Unchanged -->
                <div class="export-panel">
                    <h3>📤 Export Options</h3>
                    <div class="export-options">
                        <!-- Option 1: Current Page -->
                        <div class="export-option">
                            <h4>👁️ Current Page</h4>
                            <p>Export exactly what you see on this page<br>
                            <strong><?= count($auditTrail) ?> records</strong> with current filters</p>
                            <?php
                            $pageExportParams = array_merge($_GET, [
                                'export_type' => 'page', 
                                'export_limit' => $per_page, 
                                'export_offset' => $offset
                            ]);
                            ?>
                            <a href="/inventory-system-main/api/export_audit.php?<?= http_build_query($pageExportParams) ?>" 
                               class="btn-export" 
                               onclick="return confirm('Export this page (<?= count($auditTrail) ?> records) to CSV?')">
                                📥 Export Current Page
                            </a>
                        </div>
                        
                        <!-- Option 2: All Filtered Results -->
                        <div class="export-option">
                            <h4>🔍 Filtered Results</h4>
                            <p>Export all records matching your current filters<br>
                            <strong><?= number_format($totalRecords) ?> total records</strong></p>
                            <?php
                            $filteredExportParams = array_merge($_GET, [
                                'export_type' => 'filtered', 
                                'export_all' => 1
                            ]);
                            ?>
                            <a href="/inventory-system-main/api/export_audit.php?<?= http_build_query($filteredExportParams) ?>" 
                               class="btn-export" 
                               onclick="return confirm('Export all <?= number_format($totalRecords) ?> filtered records? This may take a moment.')">
                                📥 Export All Filtered
                            </a>
                            <?php if($totalRecords > 1000): ?>
                                <div class="record-count">⚠️ Large dataset</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Option 3: Complete System Export -->
                        <div class="export-option">
                            <h4>📚 Complete Audit</h4>
                            <p>Export EVERYTHING - no filters applied<br>
                            <strong><?= number_format($totalAllRecords) ?> total records</strong> (Stock + Product Events)</p>
                            <a href="/inventory-system-main/api/export_audit.php?export_type=complete" 
                               class="btn-export" 
                               onclick="return confirm('Export ALL <?= number_format($totalAllRecords) ?> records? This is the entire system history and may take a moment.')">
                                📥 Full System Export
                            </a>
                            <?php if($totalAllRecords > 5000): ?>
                                <div class="record-count">⚠️ Large file - may take time</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Filter Section - Added ID for scroll preservation -->
                <div class="filter-section" id="filter-section">
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Search</label>
                                <input type="text" name="search" placeholder="Product, notes, ID, staff..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="filter-group">
                                <label>Action Type</label>
                                <select name="type">
                                    <option value="">All Actions</option>
                                    <optgroup label="📦 Stock Movements">
                                        <option value="IN" <?= $typeFilter == 'IN' ? 'selected' : '' ?>>Stock In</option>
                                        <option value="OUT" <?= $typeFilter == 'OUT' ? 'selected' : '' ?>>Stock Out</option>
                                    </optgroup>
                                    <optgroup label="📝 Product Changes">
                                        <option value="ADD" <?= $typeFilter == 'ADD' ? 'selected' : '' ?>>Product Added</option>
                                        <option value="EDIT" <?= $typeFilter == 'EDIT' ? 'selected' : '' ?>>Product Edited</option>
                                        <option value="DELETE" <?= $typeFilter == 'DELETE' ? 'selected' : '' ?>>Product Deleted</option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- Staff Filter -->
                            <div class="filter-group">
                                <label>Staff</label>
                                <select name="staff">
                                    <option value="">All Staff</option>
                                    <?php foreach($staff as $s): ?>
                                        <option value="<?= $s['id'] ?>" 
                                            <?= $staffFilter == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>From Date</label>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>

                            <div class="filter-group">
                                <label>To Date</label>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>

                            <div class="filter-actions">
                                <!-- Removed Apply Filters button -->
                                <a href="audit_trail.php" class="btn-clear">Clear Filters</a>
                                
                                <!-- My Activities Quick Filter -->
                                <?php if(isset($_SESSION['user_id'])): ?>
                                    <a href="?staff=<?= $_SESSION['user_id'] ?>" class="btn-quick">
                                        👤 My Activities
                                    </a>
                                <?php endif; ?>
                                
                                <div class="per-page">
                                    <label>Show:</label>
                                    <select name="per_page" onchange="this.form.submit()">
                                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                    <span>entries</span>
                                </div>
                            </div>
                        </div>

                        <!-- Active Filters Display with Remove Links -->
                        <?php if($search || $typeFilter || $staffFilter || $dateFrom || $dateTo): ?>
                        <div class="active-filters">
                            <strong>Active Filters:</strong>
                            <?php if($search): ?>
                                <span class="filter-tag">
                                    Search: "<?= htmlspecialchars($search) ?>"
                                    <a href="?<?= http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])) ?>#filter-section" class="remove-filter" title="Remove">✕</a>
                                </span>
                            <?php endif; ?>
                            <?php if($typeFilter): ?>
                                <span class="filter-tag">
                                    Action: <?= $typeFilter ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['type' => '', 'page' => 1])) ?>#filter-section" class="remove-filter" title="Remove">✕</a>
                                </span>
                            <?php endif; ?>
                            <?php if($staffFilter): 
                                $staffName = '';
                                foreach($staff as $s) {
                                    if($s['id'] == $staffFilter) {
                                        $staffName = $s['name'];
                                        break;
                                    }
                                }
                            ?>
                                <span class="filter-tag staff">
                                    Staff: <?= htmlspecialchars($staffName) ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['staff' => '', 'page' => 1])) ?>#filter-section" class="remove-filter" title="Remove">✕</a>
                                </span>
                            <?php endif; ?>
                            <?php if($dateFrom): ?>
                                <span class="filter-tag">
                                    From: <?= $dateFrom ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['date_from' => '', 'page' => 1])) ?>#filter-section" class="remove-filter" title="Remove">✕</a>
                                </span>
                            <?php endif; ?>
                            <?php if($dateTo): ?>
                                <span class="filter-tag">
                                    To: <?= $dateTo ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['date_to' => '', 'page' => 1])) ?>#filter-section" class="remove-filter" title="Remove">✕</a>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table Info Bar -->
                <div class="table-info">
                    <div class="showing-info">
                        Showing <strong><?= count($auditTrail) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> entries
                        <?php if($search || $typeFilter || $staffFilter || $dateFrom || $dateTo): ?>
                            <span style="color:#666;">(filtered)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Pagination -->
                <?= renderPagination($page, $totalPages, $paginationParams, 'top') ?>

                <!-- Table with Staff Column - Updated to show product events -->
                <div id="table-top" class="table-container">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Batch/Expiry</th>
                                <th>Staff</th>
                                <th>Notes/Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($auditTrail) > 0): ?>
                                <?php foreach($auditTrail as $entry): 
                                    // Get staff initials for avatar
                                    $staffName = $entry['staff_name'] ?? 'System';
                                    $initials = '';
                                    if ($staffName !== 'System' && $staffName !== null) {
                                        $nameParts = explode(' ', $staffName);
                                        foreach($nameParts as $part) {
                                            if(!empty($part)) {
                                                $initials .= strtoupper(substr($part, 0, 1));
                                            }
                                        }
                                        $initials = substr($initials, 0, 2); // Max 2 letters
                                    }
                                    
                                    // Determine if this is the current user
                                    $isCurrentUser = (isset($_SESSION['user_id']) && $entry['staff_id'] == $_SESSION['user_id']);
                                    
                                    // Determine badge class based on type
                                    $badgeClass = 'badge-out';
                                    if ($entry['type'] == 'IN') {
                                        $badgeClass = 'badge-in';
                                    } elseif ($entry['type'] == 'ADD') {
                                        $badgeClass = 'badge-add';
                                    } elseif ($entry['type'] == 'EDIT') {
                                        $badgeClass = 'badge-edit';
                                    } elseif ($entry['type'] == 'DELETE') {
                                        $badgeClass = 'badge-delete';
                                    }
                                    
                                    // Determine source
                                    $source = (strpos($entry['id'], 'SM-') === 0) ? 'stock' : 'product';
                                ?>
                                <tr>
                                    <td>
                                        <span class="id-badge"><?= htmlspecialchars($entry['id']) ?></span>
                                        <span class="source-badge"><?= $source ?></span>
                                    </td>
                                    <td class="date-cell">
                                        <?= date('M d, Y', strtotime($entry['created_at'])) ?>
                                        <small><?= date('h:i A', strtotime($entry['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($entry['product_name'] ?? 'Deleted Product') ?></strong>
                                        <?php if($entry['unit']): ?>
                                            <small style="display:block; color:#999;">Unit: <?= $entry['unit'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($entry['category_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= $entry['type'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($entry['quantity'] !== null): ?>
                                            <span class="<?= $entry['type'] == 'IN' ? 'quantity-in' : 'quantity-out' ?>">
                                                <?= $entry['direction'] ?><?= number_format($entry['quantity']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($entry['expiry_date']): ?>
                                            <span style="font-size:13px;">
                                                📅 <?= date('M d, Y', strtotime($entry['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#999; font-size:12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($entry['staff_name']): ?>
                                            <div class="staff-badge" title="Staff ID: <?= $entry['staff_id'] ?><?= $isCurrentUser ? ' (You)' : '' ?>">
                                                <?php if(!empty($initials)): ?>
                                                    <span class="staff-avatar"><?= $initials ?></span>
                                                <?php else: ?>
                                                    <span class="staff-avatar">👤</span>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($entry['staff_name']) ?>
                                                <?php if($isCurrentUser): ?>
                                                    <span style="color: #3498db; font-size: 11px; margin-left: 3px;">(you)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="staff-badge" style="background-color: #f8f9fa; color: #6c757d;">
                                                <span class="staff-avatar" style="background-color: #6c757d;">🤖</span>
                                                System
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="notes-cell" title="<?= htmlspecialchars($entry['note'] ?? $entry['reason'] ?? '') ?>">
                                        <?php 
                                        $note = $entry['note'] ?? $entry['reason'] ?? '';
                                        if($note) {
                                            echo '<span style="display: flex; align-items: center; gap: 5px;">';
                                            echo '📝 ' . htmlspecialchars(substr($note, 0, 50)) . (strlen($note) > 50 ? '...' : '');
                                            echo '</span>';
                                        } else {
                                            echo '<span style="color:#999;">—</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                                            <h3 style="margin-bottom: 10px;">No events found</h3>
                                            <p style="color: #999;">Try adjusting your filters or add some stock movements or products</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Pagination -->
                <?= renderPagination($page, $totalPages, $paginationParams, 'bottom') ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
    const searchInput = document.querySelector('input[name="search"]');
    const filterForm = document.getElementById('filterForm');
    let searchTimer;
    const filterSection = document.getElementById('filter-section');

    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimer);
            // Store scroll position before submit
            const scrollPos = window.scrollY;
            sessionStorage.setItem('scrollPos', scrollPos);
            searchTimer = setTimeout(() => {
                filterForm.submit();
            }, 500);
        });
    }

    // Auto-submit on select changes with scroll preservation
    const autoSubmitInputs = document.querySelectorAll('select[name="type"], select[name="staff"], input[type="date"]');
    autoSubmitInputs.forEach(input => {
        input.addEventListener('change', () => {
            // Store scroll position before submit
            const scrollPos = window.scrollY;
            sessionStorage.setItem('scrollPos', scrollPos);
            filterForm.submit();
        });
    });

    // Handle remove filter links to preserve scroll
    document.querySelectorAll('.remove-filter').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const scrollPos = window.scrollY;
            sessionStorage.setItem('scrollPos', scrollPos);
            window.location.href = this.href;
        });
    });

    // Handle clear filters button
    document.querySelector('.btn-clear')?.addEventListener('click', function(e) {
        e.preventDefault();
        const scrollPos = window.scrollY;
        sessionStorage.setItem('scrollPos', scrollPos);
        window.location.href = this.href;
    });

    // Handle My Activities quick filter
    document.querySelector('.btn-quick')?.addEventListener('click', function(e) {
        e.preventDefault();
        const scrollPos = window.scrollY;
        sessionStorage.setItem('scrollPos', scrollPos);
        window.location.href = this.href;
    });

    // Handle pagination links to preserve scroll to filter section
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const scrollPos = filterSection.offsetTop - 20; // Scroll to filter section with offset
            sessionStorage.setItem('scrollPos', scrollPos);
            window.location.href = this.href;
        });
    });

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    document.addEventListener("click", function (event) {
        const sidebar = document.querySelector(".sidebar");
        const toggleBtn = document.querySelector(".menu-toggle");

        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove("active");
        }
    });

    // Restore scroll position on page load
    window.addEventListener('load', function() {
        const savedScrollPos = sessionStorage.getItem('scrollPos');
        if (savedScrollPos) {
            setTimeout(() => {
                window.scrollTo({
                    top: parseInt(savedScrollPos),
                    behavior: 'smooth'
                });
                sessionStorage.removeItem('scrollPos');
            }, 100);
        } else if (window.location.hash === '#filter-section') {
            filterSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });

    // Tooltip for truncated notes
    const notesCells = document.querySelectorAll('.notes-cell');
    notesCells.forEach(cell => {
        if(cell.scrollWidth > cell.clientWidth) {
            cell.style.cursor = 'help';
        }
    });
    </script>
</body>
</html>