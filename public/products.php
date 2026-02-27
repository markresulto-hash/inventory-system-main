<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

/* ================================
   FILTERS + PAGINATION
================================ */

$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? ''; // New: low, out, or empty
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
        ? (int)$_GET['page']
        : 1;

$limit = 10;
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

/* STOCK FILTER - New functionality */
if ($stockFilter === 'low') {
    // Products with stock > 0 but <= min_stock
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
    // Products with stock <= 0
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
   FETCH PRODUCTS - FIXED (NO DUPLICATES)
================================ */
$sql = "
SELECT 
    p.id,
    p.name,
    p.unit,
    p.min_stock,
    p.category_id,
    p.has_expiry,
    c.name AS category_name,
    -- Calculate TOTAL stock across all batches
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

/* Bind filter parameters */
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

/* Bind pagination */
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check number of products found
error_log("Found " . count($products) . " products in query");

// If no products found, check if there are products in database
if (empty($products)) {
    $checkProducts = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    error_log("Total active products in database: " . $checkProducts);
}

// Create a new array to avoid reference issues
$processedProducts = [];

// Now calculate batch info for each product separately
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
    
    // Count active batches
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
    
    // Add to processed products array
    $processedProducts[] = $product;
}

// Replace products with processed ones
$products = $processedProducts;

/* ================================
   SUMMARY - FIXED
================================ */

// Get summary data
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

// Debug categories
error_log("Found " . count($categories) . " categories");
?>

<!DOCTYPE html>
<html>
<head>
<title>Products - Inventory System</title>
<link rel="icon" type="image/png" href="../img/sunset2.png">
<link rel="stylesheet" href="assets/css/products.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* Your existing styles plus new filter indicators */
.active-filter {
    background-color: #007bff;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: 10px;
    font-size: 12px;
}

.filter-badge {
    display: inline-block;
    background-color: #f0f0f0;
    border-radius: 4px;
    padding: 5px 10px;
    margin-right: 10px;
    font-size: 13px;
}

.filter-badge .remove {
    margin-left: 5px;
    color: #999;
    text-decoration: none;
    font-weight: bold;
}

.filter-badge .remove:hover {
    color: #ff0000;
}

/* The rest of your existing styles remain exactly the same */
.confirm-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}

.confirm-content {
    background-color: white;
    margin: 15% auto;
    padding: 25px;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: slideIn 0.3s;
    position: relative;
}

.confirm-header {
    text-align: center;
    margin-bottom: 20px;
}

.confirm-header i {
    font-size: 50px;
    margin-bottom: 15px;
}

.confirm-header.warning i {
    color: #f39c12;
}

.confirm-header.success i {
    color: #27ae60;
}

.confirm-header.error i {
    color: #e74c3c;
}

.confirm-header.info i {
    color: #3498db;
}

.confirm-header h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
}

.confirm-message {
    text-align: center;
    color: #666;
    margin-bottom: 25px;
    font-size: 16px;
    line-height: 1.5;
}

.confirm-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.confirm-btn {
    padding: 10px 25px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 100px;
}

.confirm-btn.confirm {
    background-color: #3498db;
    color: white;
}

.confirm-btn.confirm:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
}

.confirm-btn.cancel {
    background-color: #e0e0e0;
    color: #333;
}

.confirm-btn.cancel:hover {
    background-color: #d0d0d0;
    transform: translateY(-2px);
}

.confirm-btn.warning {
    background-color: #e74c3c;
    color: white;
}

.confirm-btn.warning:hover {
    background-color: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 300px;
    max-width: 500px;
    pointer-events: none;
}

.toast-notification.success {
    background-color: #27ae60;
}

.toast-notification.error {
    background-color: #e74c3c;
}

.toast-notification.warning {
    background-color: #f39c12;
}

.toast-notification.info {
    background-color: #3498db;
}

.toast-notification.fade-out {
    animation: fadeOut 0.3s ease forwards;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

.batch-info {
    font-size: 11px;
    color: #666;
    margin-top: 3px;
}

.batch-table {
    width: 100%;
    border-collapse: collapse;
}

.batch-table th {
    background-color: #f8f9fa;
    padding: 10px;
    text-align: left;
    font-size: 13px;
}

.batch-table td {
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
}

.batch-table tr:hover {
    background-color: #f8f9fa;
}

.expired-badge {
    background-color: #dc3545;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.warning-badge {
    background-color: #ffc107;
    color: #333;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.safe-badge {
    background-color: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.out-badge {
    background-color: #6c757d;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.low-badge {
    background-color: #fd7e14;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.no-expiry-badge {
    background-color: #6c757d;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(100%); }
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.action-btn {
    text-decoration: none;
    margin: 0 3px;
    font-size: 16px;
}

.action-btn:hover {
    opacity: 0.7;
}
</style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>

            <h1>Products
                <?php if ($stockFilter === 'low'): ?>
                    <span class="active-filter">Low Stock Only</span>
                <?php elseif ($stockFilter === 'out'): ?>
                    <span class="active-filter">Out of Stock Only</span>
                <?php endif; ?>
            </h1>

            <div class="card-container">
                <a href="products.php" style="text-decoration: none; color: inherit;">
                    <div class="card">Total Products<br><b><?= number_format($summary['total_products'] ?? 0) ?></b></div>
                </a>
                <a href="products.php?stock=low" style="text-decoration: none; color: inherit;">
                    <div class="card">Low Stock<br><b><?= number_format($summary['low_stock'] ?? 0) ?></b></div>
                </a>
                <a href="products.php?stock=out" style="text-decoration: none; color: inherit;">
                    <div class="card">Out of Stock<br><b><?= number_format($summary['out_stock'] ?? 0) ?></b></div>
                </a>
                <div class="card">Total Items<br><b><?= number_format($summary['total_items'] ?? 0) ?></b></div>
            </div>

            <button class="btn" onclick="openModal()">+ Add Product</button>

            <br><br>

            <!-- Active filters display -->
            <?php if ($stockFilter !== '' || $search !== '' || $categoryFilter !== ''): ?>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: bold; margin-right: 10px;">Active Filters:</span>
                <?php if ($stockFilter === 'low'): ?>
                <span class="filter-badge">Low Stock <a href="?<?= http_build_query(array_merge($_GET, ['stock' => '', 'page' => 1])) ?>" class="remove" title="Remove filter">✕</a></span>
                <?php elseif ($stockFilter === 'out'): ?>
                <span class="filter-badge">Out of Stock <a href="?<?= http_build_query(array_merge($_GET, ['stock' => '', 'page' => 1])) ?>" class="remove" title="Remove filter">✕</a></span>
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                <span class="filter-badge">Search: "<?= htmlspecialchars($search) ?>" <a href="?<?= http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])) ?>" class="remove" title="Remove filter">✕</a></span>
                <?php endif; ?>
                <?php if ($categoryFilter !== ''): 
                    $catName = '';
                    foreach($categories as $cat) {
                        if ($cat['id'] == $categoryFilter) {
                            $catName = $cat['name'];
                            break;
                        }
                    }
                ?>
                <span class="filter-badge">Category: <?= htmlspecialchars($catName) ?> <a href="?<?= http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])) ?>" class="remove" title="Remove filter">✕</a></span>
                <?php endif; ?>
                <a href="products.php" style="font-size: 13px; color: #007bff;">Clear all</a>
            </div>
            <?php endif; ?>

            <form id="filterForm" method="GET" style="margin-bottom:15px;">
                <!-- Preserve stock filter in form -->
                <?php if ($stockFilter !== ''): ?>
                <input type="hidden" name="stock" value="<?= htmlspecialchars($stockFilter) ?>">
                <?php endif; ?>
                
                <input type="text" id="searchInput" name="search" placeholder="Search product..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn">Filter</button>
                
                <!-- Quick filter buttons -->
                <a href="?stock=low<?= $search ? '&search='.urlencode($search) : '' ?><?= $categoryFilter ? '&category='.$categoryFilter : '' ?>" class="btn" style="background-color: <?= $stockFilter === 'low' ? '#007bff' : '#6c757d' ?>; color: white; text-decoration: none;">Low Stock</a>
                <a href="?stock=out<?= $search ? '&search='.urlencode($search) : '' ?><?= $categoryFilter ? '&category='.$categoryFilter : '' ?>" class="btn" style="background-color: <?= $stockFilter === 'out' ? '#007bff' : '#6c757d' ?>; color: white; text-decoration: none;">Out of Stock</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Min</th>
                        <th>Stock</th>
                        <th>Expiry</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="productTable">
                    <?php if(count($products) > 0): ?>
                        <?php foreach($products as $product): ?>
                            <?php 
                            $stock = (int)$product['total_stock'];
                            $min = (int)$product['min_stock'];
                            $hasExpiry = (bool)$product['has_expiry'];
                            $batchCount = isset($product['batch_count']) ? (int)$product['batch_count'] : 0;
                            $expiredCount = isset($product['expired_count']) ? (int)$product['expired_count'] : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <?php if($batchCount > 0): ?>
                                        <div class="batch-info">
                                            📦 <?= $batchCount ?> active batch(es)
                                            <?php if(!$hasExpiry): ?>
                                                | <span style="color:#28a745;">🔄 No expiry</span>
                                            <?php endif; ?>
                                            <?php if($expiredCount > 0): ?>
                                                | <span style="color:#dc3545;">⚠️ <?= $expiredCount ?> expired</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td><?= htmlspecialchars($product['unit']) ?></td>
                                <td><?= $product['min_stock'] ?></td>
                                <td>
                                    <?php if ($stock <= 0): ?>
                                        <span class="out-badge">0</span>
                                    <?php elseif ($stock <= $min): ?>
                                        <span class="low-badge"><?= $stock ?></span>
                                    <?php else: ?>
                                        <span style="font-weight:bold;"><?= $stock ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if (!$hasExpiry) {
                                        echo "<span class='no-expiry-badge'>No Expiry</span>";
                                    } elseif (isset($product['nearest_expiry']) && $product['nearest_expiry']) {
                                        $today = new DateTime();
                                        $expDate = new DateTime($product['nearest_expiry']);
                                        $daysLeft = $today->diff($expDate)->days;
                                        
                                        if ($expDate < $today) {
                                            echo "<span class='expired-badge'>Expired</span>";
                                        } elseif ($daysLeft <= 60) {
                                            echo "<span class='warning-badge'>{$daysLeft}d</span>";
                                        } else {
                                            echo "<span class='safe-badge'>" . $expDate->format('M d') . "</span>";
                                        }
                                    } else {
                                        echo "<span class='safe-badge'>No Stock</span>";
                                    }
                                    ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="#" class="action-btn" title="Stock In" 
                                       onclick="openStockInModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>', null, <?= $hasExpiry ? 'true' : 'false' ?>); return false;">➕</a>
                                    <a href="#" class="action-btn" title="View Batches" 
                                       onclick="viewBatches(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>'); return false;">📋</a>
                                    <a href="#" class="action-btn" title="Edit" 
                                       onclick="openEditModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>', <?= $product['category_id'] ?>, '<?= htmlspecialchars(addslashes($product['unit'])) ?>', <?= $product['min_stock'] ?>, <?= $product['has_expiry'] ?>); return false;">✏️</a>
                                    <a href="#" class="action-btn" title="Delete" 
                                       onclick="confirmDelete(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>'); return false;">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px;">No products found matching your filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $queryParams = [
                'search' => $search,
                'category' => $categoryFilter,
                'stock' => $stockFilter
            ];
            // Remove empty values
            $queryParams = array_filter($queryParams, function($value) {
                return $value !== '';
            });
            ?>

            <?php if($totalPages > 1): ?>
            <div id="pagination" class="pagination" style="margin-top:15px;">
                <?php if($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => 1])) ?>#pagination">⏮</a>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $page-1])) ?>#pagination">◀</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>#pagination"
                       style="<?= $i == $page ? 'font-weight:bold; text-decoration:underline;' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $page+1])) ?>#pagination">▶</a>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $totalPages])) ?>#pagination">⏭</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALS -->
    <div class="confirm-modal" id="confirmModal">
        <div class="confirm-content">
            <div class="confirm-header" id="confirmHeader">
                <i id="confirmIcon">⚠️</i>
                <h3 id="confirmTitle">Confirm Action</h3>
            </div>
            <div class="confirm-message" id="confirmMessage"></div>
            <div class="confirm-buttons">
                <button class="confirm-btn cancel" id="confirmCancelBtn">Cancel</button>
                <button class="confirm-btn confirm" id="confirmOkBtn">OK</button>
            </div>
        </div>
    </div>

    <div id="toastNotification" style="display: none;"></div>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal" id="productModal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2>Add Product</h2>
            <form id="addProductForm">
                <label>Product Name</label>
                <input type="text" name="name" required>
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">Select</option>
                    <?php foreach($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Unit</label>
                <input type="text" name="unit" required>
                <label>Minimum Stock</label>
                <input type="number" name="min_stock" required>
                <label>Has Expiry?</label>
                <select name="has_expiry" required>
                    <option value="1">Yes - Track Expiry Dates</option>
                    <option value="0">No - Single Batch</option>
                </select>
                <br><br>
                <button type="submit" class="btn">Save</button>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL - FIXED with method="POST" -->
    <div class="modal" id="editProductModal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Product</h2>
            <form id="editProductForm" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <label>Product Name</label>
                <input type="text" name="name" id="edit_name" required>
                <label>Category</label>
                <select name="category_id" id="edit_category" required>
                    <option value="">Select</option>
                    <?php foreach($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Unit</label>
                <input type="text" name="unit" id="edit_unit" required>
                <label>Minimum Stock</label>
                <input type="number" name="min_stock" id="edit_min_stock" required>
                <label>Has Expiry?</label>
                <select name="has_expiry" id="edit_has_expiry" required>
                    <option value="1">Yes - Track Expiry Dates</option>
                    <option value="0">No - Single Batch</option>
                </select>
                <br><br>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <!-- STOCK IN MODAL -->
    <div class="modal" id="stockInModal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeStockInModal()">&times;</span>
            <h2>Stock In</h2>
            <div id="stockInProductInfo" style="margin-bottom:15px; padding:10px; background:#f5f5f5; border-radius:5px;">
                <strong id="stockInProductName"></strong><br>
                <span id="stockInBatchInfo"></span>
            </div>
            <form id="stockInForm">
                <input type="hidden" name="product_id" id="stockInProductId">
                <input type="hidden" name="expiry_date" id="stockInExpiryDate">
                <label>Quantity</label>
                <input type="number" name="quantity" min="1" required>
                <div id="expiryFieldContainer" style="display:none;">
                    <label>Expiry Date</label>
                    <input type="date" name="new_expiry_date" id="stockInNewExpiryDate">
                </div>
                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g., Donation, Purchase, etc."></textarea>
                <br><br>
                <button type="submit" class="btn">Add Stock</button>
            </form>
        </div>
    </div>

    <!-- STOCK OUT MODAL -->
    <div class="modal" id="stockOutModal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeStockOutModal()">&times;</span>
            <h2>Stock Out</h2>
            <div id="stockOutProductInfo" style="margin-bottom:15px; padding:10px; background:#f5f5f5; border-radius:5px;">
                <strong id="stockOutProductName"></strong><br>
                <span id="stockOutBatchInfo"></span><br>
                <span id="stockOutCurrentStock"></span>
            </div>
            <form id="stockOutForm">
                <input type="hidden" name="product_id" id="stockOutProductId">
                <input type="hidden" name="expiry_date" id="stockOutExpiryDate">
                <label>Quantity</label>
                <input type="number" name="quantity" min="1" id="stockOutQuantity" required>
                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g., Sold, Used, Expired, etc."></textarea>
                <br><br>
                <button type="submit" class="btn">Remove Stock</button>
            </form>
        </div>
    </div>

    <!-- BATCHES MODAL -->
    <div class="modal" id="batchesModal" style="display:none;">
        <div class="modal-content" style="width:700px; max-width:95%;">
            <span class="modal-close" onclick="closeBatchesModal()">&times;</span>
            <h2 id="batchesProductName">Product Batches</h2>
            <table class="batch-table" style="width:100%; margin-top:15px;">
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
            <div style="text-align:right; margin-top:15px;">
                <button class="btn" onclick="closeBatchesModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    let timer;

    searchInput.addEventListener('keyup', function () {
        clearTimeout(timer);
        timer = setTimeout(() => filterForm.submit(), 500);
    });

    // ================= CONFIRMATION SYSTEM =================
    let confirmCallback = null;

    function showConfirm(options) {
        const modal = document.getElementById('confirmModal');
        document.getElementById('confirmIcon').innerHTML = options.icon || '⚠️';
        document.getElementById('confirmTitle').textContent = options.title || 'Confirm Action';
        document.getElementById('confirmMessage').textContent = options.message || 'Are you sure?';
        
        const okBtn = document.getElementById('confirmOkBtn');
        okBtn.className = 'confirm-btn ' + (options.okButtonClass || 'confirm');
        okBtn.textContent = options.okButtonText || 'OK';
        document.getElementById('confirmCancelBtn').textContent = options.cancelButtonText || 'Cancel';
        
        confirmCallback = options.onOk;
        modal.style.display = 'block';
    }

    function closeConfirm() {
        document.getElementById('confirmModal').style.display = 'none';
        confirmCallback = null;
    }

    document.getElementById('confirmOkBtn').addEventListener('click', function() {
        if (confirmCallback) confirmCallback();
        closeConfirm();
    });

    document.getElementById('confirmCancelBtn').addEventListener('click', closeConfirm);

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('confirmModal');
        if (event.target === modal) closeConfirm();
    });

    // ================= TOAST NOTIFICATION =================
    function showToast(message, type = 'info', duration = 3000) {
        const toast = document.getElementById('toastNotification');
        if (window.toastTimeout) clearTimeout(window.toastTimeout);
        
        const icon = {success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️'}[type] || 'ℹ️';
        toast.innerHTML = `<span style="margin-right:10px;">${icon}</span><span>${message}</span>`;
        toast.className = `toast-notification ${type}`;
        toast.style.display = 'flex';
        
        if (duration > 0) {
            window.toastTimeout = setTimeout(() => {
                toast.style.display = 'none';
            }, duration);
        }
    }

    function showLoading(msg = 'Processing...') { showToast(msg, 'info', 0); }
    function hideLoading() { document.getElementById('toastNotification').style.display = 'none'; }

    // ================= ADD PRODUCT =================
    let isSubmitting = false;

    function openModal() {
        document.getElementById('productModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('productModal').style.display = 'none';
        document.getElementById('addProductForm').reset();
        isSubmitting = false;
    }

    document.getElementById('addProductForm').addEventListener('submit', function(e){
        e.preventDefault();
        
        if (isSubmitting) {
            console.log('Already submitting...');
            return;
        }
        
        isSubmitting = true;
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;
        
        showLoading('Adding product...');
        
        fetch('../api/add_product.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success'){
                showToast('Product added!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error', 'error', 4000);
                isSubmitting = false;
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
            isSubmitting = false;
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

    // ================= EDIT PRODUCT - FIXED =================
    let isEditSubmitting = false;

    function openEditModal(id, name, category, unit, min_stock, has_expiry) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_category').value = category;
        document.getElementById('edit_unit').value = unit;
        document.getElementById('edit_min_stock').value = min_stock;
        document.getElementById('edit_has_expiry').value = has_expiry;
        document.getElementById('editProductModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editProductModal').style.display = 'none';
        document.getElementById('editProductForm').reset();
        isEditSubmitting = false;
    }

    document.getElementById('editProductForm').addEventListener('submit', function(e){
        e.preventDefault();
        
        if (isEditSubmitting) {
            console.log('Already submitting edit...');
            return;
        }
        
        isEditSubmitting = true;
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Updating...';
        submitBtn.disabled = true;
        
        showLoading('Updating product...');
        
        const formData = new FormData(this);
        
        fetch('../api/update_product.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success'){
                showToast('Product updated successfully!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error updating product', 'error', 4000);
                isEditSubmitting = false;
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed: ' + err.message, 'error', 4000);
            isEditSubmitting = false;
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

    // ================= DELETE =================
    function confirmDelete(id, name) {
        showConfirm({
            icon: '⚠️',
            title: 'Delete Product',
            message: `Delete "${name}"?`,
            okButtonText: 'Delete',
            okButtonClass: 'warning',
            onOk: function() {
                showLoading('Deleting...');
                fetch('../api/delete_product.php?id=' + id)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success'){
                        showToast('Deleted!', 'success', 2000);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        hideLoading();
                        showToast(data.message || 'Error', 'error', 4000);
                    }
                });
            }
        });
    }

    // ================= BATCHES =================
    function viewBatches(productId, productName) {
        document.getElementById('batchesProductName').textContent = productName + ' - Batches';
        document.getElementById('batchesModal').style.display = 'block';
        showLoading('Loading...');
        
        fetch('../api/get_batches.php?product_id=' + productId)
        .then(res => res.json())
        .then(data => {
            hideLoading();
            const tbody = document.getElementById('batchesTableBody');
            tbody.innerHTML = '';
            
            if(data.batches && data.batches.length > 0) {
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
                        if (days < 0) { status = 'EXPIRED'; statusClass = 'expired-badge'; }
                        else if (days <= 60) { status = days + 'd left'; statusClass = 'warning-badge'; }
                        else { status = 'Good'; statusClass = 'safe-badge'; }
                    } else {
                        status = 'No expiry'; statusClass = 'no-expiry-badge';
                    }
                    
                    tbody.innerHTML += `<tr>
                        <td><strong>${batchDisplay}</strong></td>
                        <td><strong>${stock}</strong></td>
                        <td><span class="${statusClass}">${status}</span></td>
                        <td>
                            <a href="#" onclick="openStockInModal(${productId}, '${productName}', '${batch.expiry_date || ''}', ${hasExpiry}); closeBatchesModal(); return false;">➕</a>
                            <a href="#" onclick="openStockOutModal(${productId}, '${productName}', '${batch.expiry_date || ''}', ${stock}, ${hasExpiry}); closeBatchesModal(); return false;">➖</a>
                        </td>
                    </tr>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No batches found.</td></tr>';
            }
        });
    }

    function closeBatchesModal() {
        document.getElementById('batchesModal').style.display = 'none';
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
        
        document.getElementById('stockInModal').style.display = 'block';
    }

    function closeStockInModal() {
        document.getElementById('stockInModal').style.display = 'none';
        document.getElementById('stockInForm').reset();
        document.getElementById('stockInNewExpiryDate').disabled = false;
    }

    document.getElementById('stockInForm').addEventListener('submit', function(e){
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
        
        showLoading('Adding...');
        fetch('../api/stock_movement.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success'){
                showToast('Stock added!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error', 'error', 4000);
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
        
        document.getElementById('stockOutModal').style.display = 'block';
    }

    function closeStockOutModal() {
        document.getElementById('stockOutModal').style.display = 'none';
        document.getElementById('stockOutForm').reset();
    }

    document.getElementById('stockOutForm').addEventListener('submit', function(e){
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('type', 'OUT');
        
        const batchInfo = document.getElementById('stockOutBatchInfo').textContent;
        const hasExpiry = !batchInfo.includes('main stock');
        
        if (hasExpiry && !formData.get('expiry_date')) {
            showToast('Please select a batch first', 'warning', 3000);
            return;
        }
        
        showLoading('Removing...');
        fetch('../api/stock_movement.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success'){
                showToast('Stock removed!', 'success', 2000);
                setTimeout(() => location.reload(), 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error', 'error', 4000);
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Connection failed', 'error', 4000);
        });
    });

    // ================= SIDEBAR =================
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    document.addEventListener('click', function(e) {
        const s = document.querySelector('.sidebar'), b = document.querySelector('.menu-toggle');
        if (!s.contains(e.target) && !b.contains(e.target)) s.classList.remove('active');
    });

    // Scroll position
    document.querySelectorAll('.pagination a').forEach(l => l.addEventListener('click', function() {
        sessionStorage.setItem('scrollPos', window.scrollY);
    }));

    window.addEventListener('load', function() {
        const pos = sessionStorage.getItem('scrollPos');
        if (pos) { window.scrollTo(0, parseInt(pos)); sessionStorage.removeItem('scrollPos'); }
    });
    </script>
</body>
</html>