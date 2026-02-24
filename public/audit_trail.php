<?php
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
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
        ? (int)$_GET['page']
        : 1;

// Entries per page selector
$per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 20, 50, 100]) 
            ? (int)$_GET['per_page'] 
            : 20;

$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

/* SEARCH FILTER - Search across multiple fields */
if ($search !== '') {
    $where[] = "(p.name LIKE :search OR sm.note LIKE :search OR sm.reason LIKE :search OR sm.id LIKE :search)";
    $params[':search'] = "%{$search}%";
}

/* TYPE FILTER */
if ($typeFilter !== '' && in_array($typeFilter, ['IN', 'OUT'])) {
    $where[] = "sm.type = :type";
    $params[':type'] = $typeFilter;
}

/* DATE RANGE FILTER */
if ($dateFrom !== '') {
    $where[] = "DATE(sm.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(sm.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* ================================
   TOTAL COUNT
================================ */

$countSql = "
    SELECT COUNT(*) 
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    $whereSQL
";

$countStmt = $db->prepare($countSql);

foreach ($params as $key => $value) {
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

/* ================================
   FETCH AUDIT TRAIL DATA
================================ */

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
        sm.created_at,
        CASE 
            WHEN sm.type = 'IN' THEN '+' 
            ELSE '-' 
        END as direction
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSQL
    ORDER BY sm.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);

/* Bind filters */
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

/* Bind pagination */
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$auditTrail = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================
   SUMMARY STATISTICS
================================ */

$summary = $db->query("
    SELECT 
        COUNT(*) as total_movements,
        SUM(CASE WHEN type = 'IN' THEN 1 ELSE 0 END) as total_in,
        SUM(CASE WHEN type = 'OUT' THEN 1 ELSE 0 END) as total_out,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_movements
    FROM stock_movements
")->fetch(PDO::FETCH_ASSOC);

/* Get unique types for filter dropdown */
$types = $db->query("SELECT DISTINCT type FROM stock_movements ORDER BY type")->fetchAll(PDO::FETCH_ASSOC);

// Get total records for complete export
$totalAllRecords = $db->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();

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
?>

<!DOCTYPE html>
<html>
<head>
<title>Audit Trail - Inventory System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ================= AUDIT TRAIL SPECIFIC STYLES ================= */

.audit-container {
    padding: 20px;
}

/* Summary Cards */
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

/* Filter Section */
.filter-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

/* Per Page Selector */
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

.per-page select:focus {
    outline: none;
    border-color: #3498db;
}

/* ================= EXPORT PANEL STYLES ================= */
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

/* ================= PAGINATION STYLES ================= */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.top-pagination {
    margin-bottom: 20px;
}

.bottom-pagination {
    margin-top: 20px;
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

.pagination-link.active:hover {
    background: #2980b9;
}

.pagination-link.first,
.pagination-link.prev,
.pagination-link.next,
.pagination-link.last {
    font-size: 16px;
}

.pagination-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    color: #6c757d;
    font-size: 14px;
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

/* Table anchor for scroll locking */
#table-top {
    scroll-margin-top: 20px;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin: 20px 0;
    scroll-margin-top: 20px;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
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

/* Status Badges */
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

/* Quantity Indicator */
.quantity-in {
    color: #27ae60;
    font-weight: 700;
}

.quantity-out {
    color: #e74c3c;
    font-weight: 700;
}

/* ID Badge */
.id-badge {
    background-color: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

/* Date Format */
.date-cell {
    font-size: 13px;
    color: #666;
}

.date-cell small {
    display: block;
    color: #999;
    font-size: 11px;
}

/* Notes Cell */
.notes-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #666;
}

/* Table Info Bar */
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

/* Mobile Responsive */
@media (max-width: 768px) {
    .main {
        padding: 15px;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
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
    
    .page-info {
        margin-left: 5px;
        padding: 5px 10px;
        font-size: 11px;
    }
    
    .table-info {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .export-options {
        grid-template-columns: 1fr;
    }
}

/* Empty State */
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

/* Active Filters Indicator */
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

.filter-tag .remove {
    color: #999;
    cursor: pointer;
    font-weight: bold;
    margin-left: 5px;
}

.filter-tag .remove:hover {
    color: #e74c3c;
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

                <!-- Summary Cards -->
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

                <!-- ================= EXPORT PANEL ================= -->
                <div class="export-panel">
                    <h3>📤 Export Options</h3>
                    <div class="export-options">
                        
                        <!-- Option 1: Current Page -->
                        <div class="export-option">
                            <h4>👁️ Current Page</h4>
                            <p>Export exactly what you see on this page<br>
                            <strong><?= count($auditTrail) ?> records</strong> with current filters</p>
                            <a href="/inventory-system-main/api/export_audit.php?<?= http_build_query(array_merge($_GET, ['export_type' => 'page', 'export_limit' => $per_page, 'export_offset' => $offset])) ?>" 
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
                            <a href="/inventory-system-main/api/export_audit.php?<?= http_build_query(array_merge($_GET, ['export_type' => 'filtered', 'export_all' => 1])) ?>" 
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
                            <strong><?= number_format($totalAllRecords) ?> total records</strong></p>
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

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Search</label>
                                <input type="text" name="search" placeholder="Product, notes, ID..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="filter-group">
                                <label>Movement Type</label>
                                <select name="type">
                                    <option value="">All Types</option>
                                    <?php foreach($types as $t): ?>
                                        <option value="<?= $t['type'] ?>" 
                                            <?= $typeFilter == $t['type'] ? 'selected' : '' ?>>
                                            <?= $t['type'] ?>
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
                                <button type="submit" class="btn">Apply Filters</button>
                                <a href="audit_trail.php" class="btn-clear">Clear</a>
                                
                                <!-- Per Page Selector -->
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

                        <!-- Active Filters Display -->
                        <?php if($search || $typeFilter || $dateFrom || $dateTo): ?>
                        <div class="active-filters">
                            <strong>Active Filters:</strong>
                            <?php if($search): ?>
                                <span class="filter-tag">Search: "<?= htmlspecialchars($search) ?>"</span>
                            <?php endif; ?>
                            <?php if($typeFilter): ?>
                                <span class="filter-tag">Type: <?= $typeFilter ?></span>
                            <?php endif; ?>
                            <?php if($dateFrom): ?>
                                <span class="filter-tag">From: <?= $dateFrom ?></span>
                            <?php endif; ?>
                            <?php if($dateTo): ?>
                                <span class="filter-tag">To: <?= $dateTo ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table Info Bar -->
                <div class="table-info">
                    <div class="showing-info">
                        Showing <strong><?= count($auditTrail) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> entries
                        <?php if($search || $typeFilter || $dateFrom || $dateTo): ?>
                            <span style="color:#666;">(filtered)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== TOP PAGINATION ===== -->
                <?= renderPagination($page, $totalPages, $paginationParams, 'top') ?>

                <!-- Table with anchor for scroll locking -->
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
                                <th>Notes/Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($auditTrail) > 0): ?>
                                <?php foreach($auditTrail as $entry): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge">#<?= str_pad($entry['id'], 5, '0', STR_PAD_LEFT) ?></span>
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
                                        <span class="badge <?= $entry['type'] == 'IN' ? 'badge-in' : 'badge-out' ?>">
                                            <?= $entry['type'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?= $entry['type'] == 'IN' ? 'quantity-in' : 'quantity-out' ?>">
                                            <?= $entry['direction'] ?><?= number_format($entry['quantity']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($entry['expiry_date']): ?>
                                            <span style="font-size:13px;">
                                                📅 <?= date('M d, Y', strtotime($entry['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#999; font-size:12px;">No batch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="notes-cell" title="<?= htmlspecialchars($entry['note'] ?? $entry['reason'] ?? '') ?>">
                                        <?php 
                                        $note = $entry['note'] ?? $entry['reason'] ?? '';
                                        echo $note ? htmlspecialchars($note) : '<span style="color:#999;">—</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <div>📭</div>
                                            <h3>No movements found</h3>
                                            <p>Try adjusting your filters or add some stock movements</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== BOTTOM PAGINATION ===== -->
                <?= renderPagination($page, $totalPages, $paginationParams, 'bottom') ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
    // Search with debounce
    const searchInput = document.querySelector('input[name="search"]');
    const filterForm = document.getElementById('filterForm');
    let searchTimer;

    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                filterForm.submit();
            }, 500);
        });
    }

    // Auto-submit on select change (except per_page which already has onchange)
    const selectInputs = document.querySelectorAll('select[name="type"], input[type="date"]');
    selectInputs.forEach(input => {
        input.addEventListener('change', () => {
            filterForm.submit();
        });
    });

    // Toggle sidebar
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    // Close sidebar when clicking outside
    document.addEventListener("click", function (event) {
        const sidebar = document.querySelector(".sidebar");
        const toggleBtn = document.querySelector(".menu-toggle");

        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove("active");
        }
    });

    // Smooth scroll to table top when pagination is clicked
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't prevent default - let the link work normally
            // The #table-top hash will handle the scrolling
        });
    });

    // Check if we need to scroll to table on page load
    window.addEventListener('load', function() {
        if (window.location.hash === '#table-top') {
            document.getElementById('table-top').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
    </script>
</body>
</html>