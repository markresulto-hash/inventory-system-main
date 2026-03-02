<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: log_in.php");
    exit();
}

// Add cache busting headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

/* ================================
   FILTERS + PAGINATION
================================ */

$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
        ? (int)$_GET['page']
        : 1;

$limit = 15; // Show 15 products per page (5x3 grid)
$offset = ($page - 1) * $limit;

// Build WHERE clause for SQL
$whereConditions = [];
$params = [];

// Always include active products
$whereConditions[] = "p.is_active = 1";

/* SEARCH FILTER */
if ($search !== '') {
    $whereConditions[] = "p.name LIKE :search";
    $params[':search'] = "%{$search}%";
}

/* CATEGORY FILTER */
if ($categoryFilter !== '' && is_numeric($categoryFilter)) {
    $whereConditions[] = "p.category_id = :category";
    $params[':category'] = (int)$categoryFilter;
}

/* STOCK FILTER */
if ($stockFilter === 'low') {
    $whereConditions[] = "(
        SELECT COALESCE(SUM(
            CASE
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
                ELSE 0
            END
        ), 0)
        FROM stock_movements
        WHERE product_id = p.id
    ) > 0 AND (
        SELECT COALESCE(SUM(
            CASE
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
                ELSE 0
            END
        ), 0)
        FROM stock_movements
        WHERE product_id = p.id
    ) <= p.min_stock";
} elseif ($stockFilter === 'out') {
    $whereConditions[] = "(
        SELECT COALESCE(SUM(
            CASE
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
                ELSE 0
            END
        ), 0)
        FROM stock_movements
        WHERE product_id = p.id
    ) <= 0";
}

// Create the WHERE clause string
$whereSQL = "WHERE " . implode(" AND ", $whereConditions);

/* ================================
   TOTAL COUNT
================================ */

$countSql = "SELECT COUNT(*) FROM products p $whereSQL";
$countStmt = $db->prepare($countSql);

foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}

$countStmt->execute();
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $limit));

/* ================================
   FETCH PRODUCTS
================================ */
$sql = "
SELECT 
    p.id,
    p.name,
    p.unit,
    p.min_stock,
    p.category_id,
    p.has_expiry,
    p.image_path,  -- Added image_path field
    c.name AS category_name,
    COALESCE((
        SELECT SUM(
            CASE
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
                ELSE 0
            END
        )
        FROM stock_movements
        WHERE product_id = p.id
    ), 0) as total_stock
FROM products p
INNER JOIN categories c ON p.category_id = c.id
$whereSQL
ORDER BY p.name ASC
LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process batch info for each product
$processedProducts = [];

foreach ($products as $product) {
    $productId = $product['id'];
    
    // Get all batches with their stock levels
    $batchSql = "
        SELECT 
            expiry_date,
            SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                    ELSE 0
                END
            ) as batch_stock
        FROM stock_movements
        WHERE product_id = :product_id
        GROUP BY expiry_date
        HAVING batch_stock > 0
        ORDER BY 
            CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,
            expiry_date ASC
    ";
    
    $batchStmt = $db->prepare($batchSql);
    $batchStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $batchStmt->execute();
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $product['batch_count'] = count($batches);
    
    // Find nearest expiry and count expired
    $nearestExpiry = null;
    $expiredCount = 0;
    $today = date('Y-m-d');
    
    foreach ($batches as $batch) {
        if ($batch['expiry_date'] !== null) {
            if ($batch['expiry_date'] < $today) {
                $expiredCount++;
            } else {
                if ($nearestExpiry === null || $batch['expiry_date'] < $nearestExpiry) {
                    $nearestExpiry = $batch['expiry_date'];
                }
            }
        }
    }
    
    $product['nearest_expiry'] = $nearestExpiry;
    $product['expired_count'] = $expiredCount;
    
    // Set default image if none exists - UPDATED PATH
    if (empty($product['image_path'])) {
        $product['image_path'] = '/inventory-system-main/img/default-product.png';
    } else {
        // Add full path to existing images
        $product['image_path'] = '/inventory-system-main/' . $product['image_path'];
    }
    
    $processedProducts[] = $product;
}

$products = $processedProducts;

/* ================================
   SUMMARY
================================ */

$summaryQuery = "
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        SUM(CASE 
            WHEN COALESCE(sm.total, 0) > 0 AND COALESCE(sm.total, 0) <= p.min_stock THEN 1 
            ELSE 0 
        END) as low_stock,
        SUM(CASE 
            WHEN COALESCE(sm.total, 0) <= 0 THEN 1 
            ELSE 0 
        END) as out_stock,
        COALESCE(SUM(sm.total), 0) as total_items
    FROM products p
    LEFT JOIN (
        SELECT 
            product_id,
            SUM(CASE 
                WHEN type='IN' THEN quantity 
                WHEN type='OUT' THEN -quantity 
                ELSE 0 
            END) as total
        FROM stock_movements
        GROUP BY product_id
    ) sm ON p.id = sm.product_id
    WHERE p.is_active = 1
";

$summary = $db->query($summaryQuery)->fetch(PDO::FETCH_ASSOC);

if (!$summary) {
    $summary = [
        'total_products' => 0,
        'low_stock' => 0,
        'out_stock' => 0,
        'total_items' => 0
    ];
}

/* ================================
   CATEGORIES
================================ */

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

// Get current GET parameters for pagination links
$queryParams = [
    'search' => $search,
    'category' => $categoryFilter,
    'stock' => $stockFilter
];
$queryParams = array_filter($queryParams, function($value) {
    return $value !== '';
});
?>

<!DOCTYPE html>
<html>
<head>
<title>Products - Inventory System</title>
<link rel="icon" type="image/png" href="../img/sunset2.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #e63946;
    --gray: #6c757d;
    --light: #f8f9fa;
    --dark: #212529;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f4f6f9;
}

.container {
    display: flex;
    min-height: 100vh;
}

/* Main Content */
.main {
    flex: 1;
    padding: 25px 30px;
    overflow-y: auto;
    background: #f4f6f9;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    font-size: 28px;
    color: var(--dark);
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid transparent;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.summary-card.total { border-left-color: var(--primary); }
.summary-card.low { border-left-color: var(--warning); }
.summary-card.out { border-left-color: var(--danger); }
.summary-card.items { border-left-color: var(--success); }

.summary-card h3 {
    font-size: 14px;
    color: var(--gray);
    margin-bottom: 8px;
    font-weight: 500;
}

.summary-card .value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
}

/* Filter Section */
.filter-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--primary);
    color: white;
    text-decoration: none;
    display: inline-block;
}

.btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.btn-secondary {
    background: var(--gray);
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-warning {
    background: var(--warning);
    color: var(--dark);
}

.btn-warning:hover {
    background: #faa307;
}

.btn-danger {
    background: var(--danger);
}

.btn-danger:hover {
    background: #c82333;
}

/* Active Filters */
.active-filters {
    background: #e3f2fd;
    padding: 12px 15px;
    border-radius: 8px;
    margin: 15px 0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-tag {
    background: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filter-tag .remove {
    color: var(--gray);
    text-decoration: none;
    font-weight: bold;
}

.filter-tag .remove:hover {
    color: var(--danger);
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    display: flex;
    flex-direction: column;
    border: 1px solid #edf2f7;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.product-image {
    width: 100%;
    height: 140px;
    object-fit: cover;
    background: #f8f9fa;
    border-bottom: 1px solid #edf2f7;
}

.product-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    z-index: 1;
}

.badge-out { background: var(--danger); color: white; }
.badge-low { background: var(--warning); color: var(--dark); }
.badge-good { background: var(--success); color: white; }
.badge-expiry { background: var(--primary); color: white; }

.product-info {
    padding: 15px;
    flex: 1;
}

.product-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-category {
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 10px;
}

.product-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 13px;
}

.stock-value {
    font-weight: 700;
    font-size: 18px;
}

.stock-unit {
    color: var(--gray);
    font-size: 11px;
}

.min-stock {
    font-size: 11px;
    color: var(--gray);
}

.expiry-info {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 5px;
}

.expiry-warning { background: #fff3cd; color: #856404; }
.expiry-danger { background: #f8d7da; color: #721c24; }
.expiry-good { background: #d4edda; color: #155724; }

.product-actions {
    display: flex;
    border-top: 1px solid #edf2f7;
    background: #f8f9fa;
}

.action-btn {
    flex: 1;
    padding: 10px;
    text-align: center;
    text-decoration: none;
    color: var(--gray);
    font-size: 14px;
    transition: all 0.2s;
    border-right: 1px solid #edf2f7;
}

.action-btn:last-child {
    border-right: none;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
}

.action-btn.danger:hover {
    background: var(--danger);
}

/* Batch Info */
.batch-info {
    font-size: 11px;
    color: var(--gray);
    margin-top: 5px;
    padding-top: 5px;
    border-top: 1px dashed #dee2e6;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 8px 14px;
    border-radius: 8px;
    background: white;
    color: var(--dark);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
    border: 1px solid #dee2e6;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    margin-left: 10px;
    padding: 8px 14px;
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 25px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    position: relative;
}

.modal-content.large {
    max-width: 700px;
}

.modal-close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 24px;
    color: var(--gray);
    cursor: pointer;
}

.modal-close:hover {
    color: var(--danger);
}

.modal h2 {
    margin-bottom: 20px;
    color: var(--dark);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark);
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
}

.form-group input[type="file"] {
    padding: 8px;
    border: 1px dashed #dee2e6;
    background: #f8f9fa;
}

.image-preview {
    width: 100px;
    height: 100px;
    border-radius: 8px;
    object-fit: cover;
    margin-top: 10px;
    border: 1px solid #dee2e6;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

.btn-block {
    width: 100%;
    margin-top: 20px;
}

/* Toast Notifications */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    z-index: 10000;
    animation: slideIn 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.toast.success { background: var(--success); }
.toast.error { background: var(--danger); }
.toast.warning { background: var(--warning); color: var(--dark); }
.toast.info { background: var(--primary); }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Responsive */
@media (max-width: 1400px) {
    .products-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1100px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 800px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .btn {
        flex: 1;
        text-align: center;
    }
}

@media (max-width: 500px) {
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-actions .btn {
        flex: 1;
    }
    
    .modal-content {
        margin: 10% auto;
        padding: 20px;
    }
}

.menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1000;
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }
    
    .main {
        padding: 70px 15px 20px;
    }
}
</style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i> Menu
            </button>

            <div class="page-header">
                <h1>
                    Products
                    <?php if ($stockFilter === 'low'): ?>
                        <span class="filter-tag">Low Stock Only</span>
                    <?php elseif ($stockFilter === 'out'): ?>
                        <span class="filter-tag">Out of Stock Only</span>
                    <?php endif; ?>
                </h1>
                <div class="header-actions">
                    <button class="btn" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <a href="products.php" class="summary-card total">
                    <h3>Total Products</h3>
                    <div class="value"><?= number_format($summary['total_products'] ?? 0) ?></div>
                </a>
                <a href="products.php?stock=low" class="summary-card low">
                    <h3>Low Stock</h3>
                    <div class="value"><?= number_format($summary['low_stock'] ?? 0) ?></div>
                </a>
                <a href="products.php?stock=out" class="summary-card out">
                    <h3>Out of Stock</h3>
                    <div class="value"><?= number_format($summary['out_stock'] ?? 0) ?></div>
                </a>
                <div class="summary-card items">
                    <h3>Total Items</h3>
                    <div class="value"><?= number_format($summary['total_items'] ?? 0) ?></div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form" id="filterForm">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?= htmlspecialchars($search) ?>" id="searchInput">
                    </div>
                    
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" 
                                    <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Stock Status</label>
                        <select name="stock">
                            <option value="">All Stock</option>
                            <option value="low" <?= $stockFilter == 'low' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out" <?= $stockFilter == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>

                <!-- Active Filters Display -->
                <?php if ($search || $categoryFilter || $stockFilter): ?>
                <div class="active-filters">
                    <strong>Active Filters:</strong>
                    <?php if ($search): ?>
                        <span class="filter-tag">
                            Search: "<?= htmlspecialchars($search) ?>"
                            <a href="?<?= http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])) ?>" class="remove">✕</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($categoryFilter): 
                        $catName = '';
                        foreach($categories as $cat) {
                            if ($cat['id'] == $categoryFilter) {
                                $catName = $cat['name'];
                                break;
                            }
                        }
                    ?>
                        <span class="filter-tag">
                            Category: <?= htmlspecialchars($catName) ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])) ?>" class="remove">✕</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($stockFilter): ?>
                        <span class="filter-tag">
                            Stock: <?= $stockFilter == 'low' ? 'Low Stock' : 'Out of Stock' ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['stock' => '', 'page' => 1])) ?>" class="remove">✕</a>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Products Grid -->
            <?php if(count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach($products as $product): 
                    $stock = (int)$product['total_stock'];
                    $min = (int)$product['min_stock'];
                    $hasExpiry = (bool)$product['has_expiry'];
                    $batchCount = isset($product['batch_count']) ? (int)$product['batch_count'] : 0;
                    
                    // Determine stock status
                    if ($stock <= 0) {
                        $stockStatus = 'out';
                        $statusText = 'OUT OF STOCK';
                        $statusClass = 'badge-out';
                    } elseif ($stock <= $min) {
                        $stockStatus = 'low';
                        $statusText = 'LOW STOCK';
                        $statusClass = 'badge-low';
                    } else {
                        $stockStatus = 'good';
                        $statusText = 'IN STOCK';
                        $statusClass = 'badge-good';
                    }
                    
                    // Expiry info
                    $expiryInfo = '';
                    $expiryClass = '';
                    if ($hasExpiry && isset($product['nearest_expiry'])) {
                        $today = new DateTime();
                        $expDate = new DateTime($product['nearest_expiry']);
                        $daysLeft = $today->diff($expDate)->days;
                        
                        if ($expDate < $today) {
                            $expiryInfo = 'Expired';
                            $expiryClass = 'expiry-danger';
                        } elseif ($daysLeft <= 30) {
                            $expiryInfo = "{$daysLeft} days left";
                            $expiryClass = 'expiry-danger';
                        } elseif ($daysLeft <= 60) {
                            $expiryInfo = "{$daysLeft} days left";
                            $expiryClass = 'expiry-warning';
                        } else {
                            $expiryInfo = $expDate->format('M d, Y');
                            $expiryClass = 'expiry-good';
                        }
                    }
                ?>
                <div class="product-card">
                    <span class="product-badge <?= $statusClass ?>"><?= $statusText ?></span>
                    
                    <img src="<?= htmlspecialchars($product['image_path']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-image"
                         onerror="this.src='/inventory-system-main/img/default-product.png'">
                    
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        <div class="product-category">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($product['category_name']) ?>
                        </div>
                        
                        <div class="product-stats">
                            <span class="stock-value"><?= number_format($stock) ?></span>
                            <span class="stock-unit"><?= htmlspecialchars($product['unit']) ?></span>
                        </div>
                        
                        <div class="min-stock">
                            Min: <?= $product['min_stock'] ?> <?= htmlspecialchars($product['unit']) ?>
                        </div>
                        
                        <?php if ($hasExpiry && $expiryInfo): ?>
                        <div class="expiry-info <?= $expiryClass ?>">
                            <i class="fas fa-calendar-alt"></i> <?= $expiryInfo ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($batchCount > 0): ?>
                        <div class="batch-info">
                            <i class="fas fa-layer-group"></i> <?= $batchCount ?> batch(es)
                            <?php if ($product['expired_count'] > 0): ?>
                                <span style="color: var(--danger);">(<?= $product['expired_count'] ?> expired)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-actions">
                        <a href="#" class="action-btn" title="Stock In" 
                           onclick="openStockInModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>', null, <?= $hasExpiry ? 'true' : 'false' ?>); return false;">
                            <i class="fas fa-plus-circle"></i>
                        </a>
                        <a href="#" class="action-btn" title="View Batches" 
                           onclick="viewBatches(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>'); return false;">
                            <i class="fas fa-layer-group"></i>
                        </a>
                        <a href="#" class="action-btn" title="Edit" 
                           onclick="openEditModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>', <?= $product['category_id'] ?>, '<?= htmlspecialchars(addslashes($product['unit'])) ?>', <?= $product['min_stock'] ?>, <?= $product['has_expiry'] ?>, '<?= htmlspecialchars(addslashes($product['image_path'])) ?>'); return false;">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="action-btn danger" title="Delete" 
                           onclick="confirmDelete(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>'); return false;">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 12px;">
                <i class="fas fa-box-open" style="font-size: 60px; color: var(--gray); margin-bottom: 20px;"></i>
                <h3 style="color: var(--gray);">No products found</h3>
                <p style="color: var(--gray); margin-top: 10px;">Try adjusting your filters or add a new product</p>
                <button class="btn" onclick="openModal('add')" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => 1])) ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $page-1])) ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $page+1])) ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $totalPages])) ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
                
                <span class="page-info">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal" id="addProductModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('add')">&times;</span>
            <h2><i class="fas fa-plus-circle"></i> Add Product</h2>
            
            <form id="addProductForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" name="unit" placeholder="e.g., kg, pcs, box" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Stock</label>
                        <input type="number" name="min_stock" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Has Expiry?</label>
                        <select name="has_expiry" required>
                            <option value="1">Yes - Track Expiry Dates</option>
                            <option value="0" selected>No - Single Batch</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'addPreview')">
                    <img id="addPreview" class="image-preview" src="/inventory-system-main/img/default-product.png" alt="Preview">
                </div>
                
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-save"></i> Save Product
                </button>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div class="modal" id="editProductModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('edit')">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Product</h2>
            
            <form id="editProductForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="edit_category" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" name="unit" id="edit_unit" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Stock</label>
                        <input type="number" name="min_stock" id="edit_min_stock" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Has Expiry?</label>
                        <select name="has_expiry" id="edit_has_expiry" required>
                            <option value="1">Yes - Track Expiry Dates</option>
                            <option value="0">No - Single Batch</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'editPreview')">
                    <input type="hidden" name="current_image" id="edit_current_image">
                    <img id="editPreview" class="image-preview" src="/inventory-system-main/img/default-product.png" alt="Preview">
                </div>
                
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </form>
        </div>
    </div>

    <!-- STOCK IN MODAL -->
    <div class="modal" id="stockInModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('stockIn')">&times;</span>
            <h2><i class="fas fa-plus-circle"></i> Stock In</h2>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong id="stockInProductName"></strong><br>
                <span id="stockInBatchInfo" style="color: var(--gray);"></span>
            </div>
            
            <form id="stockInForm">
                <input type="hidden" name="product_id" id="stockInProductId">
                <input type="hidden" name="expiry_date" id="stockInExpiryDate">
                
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" required>
                </div>
                
                <div id="expiryFieldContainer" style="display:none;">
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="new_expiry_date" id="stockInNewExpiryDate">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g., Donation, Purchase, etc."></textarea>
                </div>
                
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-plus"></i> Add Stock
                </button>
            </form>
        </div>
    </div>

    <!-- STOCK OUT MODAL -->
    <div class="modal" id="stockOutModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('stockOut')">&times;</span>
            <h2><i class="fas fa-minus-circle"></i> Stock Out</h2>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong id="stockOutProductName"></strong><br>
                <span id="stockOutBatchInfo"></span><br>
                <span id="stockOutCurrentStock" style="color: var(--gray);"></span>
            </div>
            
            <form id="stockOutForm">
                <input type="hidden" name="product_id" id="stockOutProductId">
                <input type="hidden" name="expiry_date" id="stockOutExpiryDate">
                
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" id="stockOutQuantity" required>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g., Sold, Used, Expired, etc."></textarea>
                </div>
                
                <button type="submit" class="btn btn-block btn-danger">
                    <i class="fas fa-minus"></i> Remove Stock
                </button>
            </form>
        </div>
    </div>

    <!-- BATCHES MODAL -->
    <div class="modal" id="batchesModal">
        <div class="modal-content large">
            <span class="modal-close" onclick="closeModal('batches')">&times;</span>
            <h2 id="batchesProductName">Product Batches</h2>
            
            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse: collapse; margin-top:15px;">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batchesTableBody"></tbody>
                </table>
            </div>
            
            <div style="text-align:right; margin-top:20px;">
                <button class="btn btn-secondary" onclick="closeModal('batches')">Close</button>
            </div>
        </div>
    </div>

    <!-- CONFIRM MODAL -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="modal-close" onclick="closeModal('confirm')">&times;</span>
            <div style="text-align: center;">
                <i id="confirmIcon" style="font-size: 48px; margin-bottom: 15px;">⚠️</i>
                <h3 id="confirmTitle" style="margin-bottom: 10px;">Confirm Action</h3>
                <p id="confirmMessage" style="color: var(--gray); margin-bottom: 20px;"></p>
                
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button class="btn btn-secondary" onclick="closeModal('confirm')">Cancel</button>
                    <button class="btn btn-danger" id="confirmOkBtn">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="toast" class="toast" style="display: none;"></div>

    <script>
    // ================= MODAL FUNCTIONS =================
    function openModal(type, productId = null) {
        if (type === 'add') {
            document.getElementById('addProductModal').style.display = 'block';
        } else if (type === 'edit') {
            document.getElementById('editProductModal').style.display = 'block';
        } else if (type === 'stockIn') {
            document.getElementById('stockInModal').style.display = 'block';
        } else if (type === 'stockOut') {
            document.getElementById('stockOutModal').style.display = 'block';
        } else if (type === 'batches') {
            document.getElementById('batchesModal').style.display = 'block';
        } else if (type === 'confirm') {
            document.getElementById('confirmModal').style.display = 'block';
        }
    }

    function closeModal(type) {
        if (type === 'add') {
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('addProductForm').reset();
            document.getElementById('addPreview').src = '/inventory-system-main/img/default-product.png';
        } else if (type === 'edit') {
            document.getElementById('editProductModal').style.display = 'none';
            document.getElementById('editProductForm').reset();
            document.getElementById('editPreview').src = '/inventory-system-main/img/default-product.png';
        } else if (type === 'stockIn') {
            document.getElementById('stockInModal').style.display = 'none';
            document.getElementById('stockInForm').reset();
            document.getElementById('stockInNewExpiryDate').disabled = false;
        } else if (type === 'stockOut') {
            document.getElementById('stockOutModal').style.display = 'none';
            document.getElementById('stockOutForm').reset();
        } else if (type === 'batches') {
            document.getElementById('batchesModal').style.display = 'none';
        } else if (type === 'confirm') {
            document.getElementById('confirmModal').style.display = 'none';
        }
    }

    // Image preview
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ================= TOAST NOTIFICATION =================
    function showToast(message, type = 'info', duration = 3000) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type}`;
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, duration);
    }

    function showLoading(msg = 'Processing...') {
        showToast(msg, 'info', 0);
    }

    function hideLoading() {
        document.getElementById('toast').style.display = 'none';
    }

    // ================= CONFIRMATION =================
    let confirmCallback = null;

    function showConfirm(options) {
        document.getElementById('confirmIcon').innerHTML = options.icon || '⚠️';
        document.getElementById('confirmTitle').textContent = options.title || 'Confirm Action';
        document.getElementById('confirmMessage').textContent = options.message || 'Are you sure?';
        
        const okBtn = document.getElementById('confirmOkBtn');
        okBtn.textContent = options.okButtonText || 'OK';
        if (options.okButtonClass) {
            okBtn.className = `btn ${options.okButtonClass}`;
        }
        
        confirmCallback = options.onOk;
        openModal('confirm');
    }

    document.getElementById('confirmOkBtn').addEventListener('click', function() {
        if (confirmCallback) confirmCallback();
        closeModal('confirm');
    });

    // ================= ADD PRODUCT =================
    let isSubmitting = false;

    document.getElementById('addProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        isSubmitting = true;
        const formData = new FormData(this);
        
        showLoading('Adding product...');
        
        fetch('../api/add_product.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Product added successfully!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error adding product', 'error', 4000);
                isSubmitting = false;
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
            isSubmitting = false;
        });
    });

    // ================= EDIT PRODUCT =================
    function openEditModal(id, name, category, unit, min_stock, has_expiry, image_path) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_category').value = category;
        document.getElementById('edit_unit').value = unit;
        document.getElementById('edit_min_stock').value = min_stock;
        document.getElementById('edit_has_expiry').value = has_expiry ? '1' : '0';
        document.getElementById('edit_current_image').value = image_path;
        document.getElementById('editPreview').src = image_path || '/inventory-system-main/img/default-product.png';
        
        openModal('edit');
    }

    let isEditSubmitting = false;

    document.getElementById('editProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (isEditSubmitting) return;
        
        isEditSubmitting = true;
        const formData = new FormData(this);
        
        showLoading('Updating product...');
        
        fetch('../api/update_product.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Product updated successfully!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error updating product', 'error', 4000);
                isEditSubmitting = false;
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
            isEditSubmitting = false;
        });
    });

    // ================= DELETE PRODUCT =================
    function confirmDelete(id, name) {
        showConfirm({
            icon: '⚠️',
            title: 'Delete Product',
            message: `Are you sure you want to delete "${name}"?`,
            okButtonText: 'Delete',
            okButtonClass: 'btn-danger',
            onOk: function() {
                showLoading('Deleting...');
                fetch('../api/delete_product.php?id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Product deleted!', 'success', 2000);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        hideLoading();
                        showToast(data.message || 'Error deleting product', 'error', 4000);
                    }
                });
            }
        });
    }

    // ================= BATCHES =================
    function viewBatches(productId, productName) {
        document.getElementById('batchesProductName').textContent = productName + ' - Batches';
        openModal('batches');
        showLoading('Loading batches...');
        
        fetch('../api/get_batches.php?product_id=' + productId)
        .then(res => res.json())
        .then(data => {
            hideLoading();
            const tbody = document.getElementById('batchesTableBody');
            tbody.innerHTML = '';
            
            if (data.batches && data.batches.length > 0) {
                const hasExpiry = data.batches.some(batch => batch.expiry_date);
                
                data.batches.sort((a, b) => {
                    if (!a.expiry_date) return 1;
                    if (!b.expiry_date) return -1;
                    return new Date(a.expiry_date) - new Date(b.expiry_date);
                }).forEach(batch => {
                    const stock = parseInt(batch.current_stock);
                    const batchHasExpiry = !!batch.expiry_date;
                    let status = '', statusClass = '';
                    let batchDisplay = batch.expiry_date || 'Main Stock';
                    
                    if (batchHasExpiry) {
                        const days = Math.ceil((new Date(batch.expiry_date) - new Date()) / (1000*60*60*24));
                        if (days < 0) { 
                            status = 'EXPIRED'; 
                            statusClass = 'expiry-danger'; 
                        } else if (days <= 30) { 
                            status = days + ' days left (Urgent)'; 
                            statusClass = 'expiry-danger'; 
                        } else if (days <= 60) { 
                            status = days + ' days left'; 
                            statusClass = 'expiry-warning'; 
                        } else { 
                            status = 'Good'; 
                            statusClass = 'expiry-good'; 
                        }
                    } else {
                        status = 'No expiry';
                        statusClass = 'expiry-good';
                    }
                    
                    tbody.innerHTML += `<tr>
                        <td><strong>${batchDisplay}</strong></td>
                        <td><strong>${stock}</strong></td>
                        <td><span class="${statusClass}" style="padding:3px 8px; border-radius:4px;">${status}</span></td>
                        <td>
                            <a href="#" onclick="openStockInModal(${productId}, '${productName}', '${batch.expiry_date || ''}', ${hasExpiry}); closeModal('batches'); return false;">➕ Stock In</a> |
                            <a href="#" onclick="openStockOutModal(${productId}, '${productName}', '${batch.expiry_date || ''}', ${stock}, ${hasExpiry}); closeModal('batches'); return false;">➖ Stock Out</a>
                        </td>
                    </tr>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No batches found.</td></tr>';
            }
        });
    }

    // ================= STOCK IN =================
    function openStockInModal(productId, productName, specificBatch = null, hasExpiry = true) {
        document.getElementById('stockInProductId').value = productId;
        document.getElementById('stockInProductName').textContent = productName;
        document.getElementById('stockInExpiryDate').value = specificBatch || '';
        
        const container = document.getElementById('expiryFieldContainer');
        const expiryInput = document.getElementById('stockInNewExpiryDate');
        
        if (!hasExpiry) {
            container.style.display = 'none';
            expiryInput.value = '';
            document.getElementById('stockInBatchInfo').textContent = 'Add to main stock (no expiry)';
        } else {
            container.style.display = 'block';
            
            if (specificBatch) {
                document.getElementById('stockInBatchInfo').textContent = 'Adding to batch: ' + specificBatch;
                expiryInput.disabled = true;
                expiryInput.style.opacity = '0.5';
            } else {
                document.getElementById('stockInBatchInfo').textContent = 'Create new batch';
                expiryInput.disabled = false;
                expiryInput.style.opacity = '1';
                
                const d = new Date();
                d.setFullYear(d.getFullYear() + 1);
                expiryInput.value = d.toISOString().split('T')[0];
            }
        }
        
        openModal('stockIn');
    }

    document.getElementById('stockInForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('type', 'IN');
        
        const container = document.getElementById('expiryFieldContainer');
        const hasExpiry = container.style.display !== 'none';
        
        if (hasExpiry) {
            const expiryDate = document.getElementById('stockInExpiryDate').value;
            const newExpiryDate = document.getElementById('stockInNewExpiryDate').value;
            
            if (!expiryDate && newExpiryDate) {
                formData.set('expiry_date', newExpiryDate);
            }
        } else {
            formData.set('expiry_date', '');
        }
        
        showLoading('Adding stock...');
        fetch('../api/stock_movement.php', { 
            method: 'POST', 
            body: formData 
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Stock added successfully!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error adding stock', 'error', 4000);
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
        });
    });

    // ================= STOCK OUT =================
    function openStockOutModal(productId, productName, specificBatch, batchStock, hasExpiry = true) {
        document.getElementById('stockOutProductId').value = productId;
        document.getElementById('stockOutProductName').textContent = productName;
        document.getElementById('stockOutExpiryDate').value = specificBatch || '';
        
        const batchInfo = document.getElementById('stockOutBatchInfo');
        const stockDisplay = document.getElementById('stockOutCurrentStock');
        const quantityField = document.getElementById('stockOutQuantity');
        
        if (!hasExpiry) {
            batchInfo.textContent = 'Remove from main stock';
            stockDisplay.textContent = 'Available stock: ' + batchStock;
            quantityField.max = batchStock;
            quantityField.value = Math.min(1, batchStock);
        } else {
            batchInfo.textContent = 'Batch: ' + (specificBatch || 'Unknown');
            stockDisplay.textContent = 'Available in this batch: ' + batchStock;
            quantityField.max = batchStock;
            quantityField.value = Math.min(1, batchStock);
        }
        
        openModal('stockOut');
    }

    document.getElementById('stockOutForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('type', 'OUT');
        
        const batchInfo = document.getElementById('stockOutBatchInfo').textContent;
        const hasExpiry = !batchInfo.includes('main stock');
        
        if (hasExpiry && !formData.get('expiry_date')) {
            showToast('Please select a batch first', 'warning', 3000);
            return;
        }
        
        showLoading('Removing stock...');
        fetch('../api/stock_movement.php', { 
            method: 'POST', 
            body: formData 
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Stock removed successfully!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error removing stock', 'error', 4000);
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
        });
    });

    // ================= SEARCH & FILTER =================
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    let searchTimer;

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                filterForm.submit();
            }, 500);
        });
    }

    // Auto-submit on select changes
    const autoSubmitInputs = document.querySelectorAll('select[name="category"], select[name="stock"]');
    autoSubmitInputs.forEach(input => {
        input.addEventListener('change', () => {
            filterForm.submit();
        });
    });

    // ================= SIDEBAR =================
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (sidebar && menuToggle) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // ================= SCROLL POSITION =================
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem('scrollPos', window.scrollY);
        });
    });

    window.addEventListener('load', function() {
        const pos = sessionStorage.getItem('scrollPos');
        if (pos) {
            window.scrollTo(0, parseInt(pos));
            sessionStorage.removeItem('scrollPos');
        }
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    </script>
</body>
</html>