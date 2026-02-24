<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

/* ================================
   FILTERS + PAGINATION (FIXED CLEAN)
================================ */

$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
        ? (int)$_GET['page']
        : 1;

$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

/* SEARCH FILTER */
if ($search !== '') {
    $where[] = "p.name LIKE :search";
    $params[':search'] = "%{$search}%";
}

/* CATEGORY FILTER */
if ($categoryFilter !== '' && is_numeric($categoryFilter)) {
    $where[] = "p.category_id = :category";
    $params[':category'] = (int)$categoryFilter;
}

$where[] = "p.is_active = 1";
$whereSQL = "WHERE " . implode(" AND ", $where);


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
   FETCH PRODUCTS (GROUPED BY PRODUCT - NO DUPLICATES)
================================ */
$sql = "
SELECT 
    p.id,
    p.name,
    p.unit,
    p.min_stock,
    p.category_id,
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
    ), 0) as total_stock,
    
    -- Count how many active batches
    (
        SELECT COUNT(DISTINCT expiry_date)
        FROM stock_movements
        WHERE product_id = p.id
        AND expiry_date IS NOT NULL
        AND (
            SELECT SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                    ELSE 0
                END
            )
            FROM stock_movements sm2
            WHERE sm2.product_id = p.id
            AND sm2.expiry_date = stock_movements.expiry_date
        ) > 0
    ) as batch_count,
    
    -- Get the SOONEST expiry date (for warnings) - THIS IS WHAT SHOWS IN EXPIRY COLUMN
    (
        SELECT MIN(expiry_date)
        FROM stock_movements
        WHERE product_id = p.id
        AND expiry_date IS NOT NULL
        AND (
            SELECT SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                    ELSE 0
                END
            )
            FROM stock_movements sm2
            WHERE sm2.product_id = p.id
            AND sm2.expiry_date = stock_movements.expiry_date
        ) > 0
    ) as nearest_expiry,
    
    -- Check if there are any expired batches
    (
        SELECT COUNT(*)
        FROM stock_movements
        WHERE product_id = p.id
        AND expiry_date IS NOT NULL
        AND expiry_date < CURDATE()
        AND (
            SELECT SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                    ELSE 0
                END
            )
            FROM stock_movements sm2
            WHERE sm2.product_id = p.id
            AND sm2.expiry_date = stock_movements.expiry_date
        ) > 0
    ) as expired_count

FROM products p
JOIN categories c ON p.category_id = c.id

WHERE p.is_active = 1

ORDER BY p.name ASC

LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);

/* Bind filters */
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

/* Bind pagination */
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================
   SUMMARY
================================ */

$summary = $db->query("
    SELECT 
        COUNT(*) as total_products,

        SUM(
            CASE 
                WHEN stock > 0 AND stock <= min_stock THEN 1 
                ELSE 0 
            END
        ) as low_stock,

        SUM(
            CASE 
                WHEN stock <= 0 THEN 1 
                ELSE 0 
            END
        ) as out_stock,

        SUM(
            CASE 
                WHEN stock > 0 THEN stock 
                ELSE 0 
            END
        ) as total_items

    FROM (
        SELECT 
            p.id,
            p.min_stock,
            COALESCE(SUM(
                CASE 
                    WHEN sm.type = 'IN' THEN sm.quantity
                    WHEN sm.type = 'OUT' THEN -sm.quantity
                    ELSE 0
                END
            ),0) as stock
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        GROUP BY p.id
    ) as inventory
")->fetch(PDO::FETCH_ASSOC);


/* ================================
   CATEGORIES
================================ */

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Products - Inventory System</title>
<link rel="stylesheet" href="assets/css/products.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* Custom Confirmation Modal Styles */
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

.batch-badge {
    display: inline-block;
    background-color: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 5px;
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

/* Expiry badges - updated to 60 days */
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
</style>
</head>

<body>
    
    <div class="container">

<?php include 'includes/sidebar.php'; ?>

<div class="main">
<button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>


<h1>Products</h1>

<div class="card-container">
    <div class="card">Total Products<br><b><?= $summary['total_products'] ?? 0 ?></b></div>
    <div class="card">Low Stock<br><b><?= $summary['low_stock'] ?? 0 ?></b></div>
    <div class="card">Out of Stock<br><b><?= $summary['out_stock'] ?? 0 ?></b></div>
    <div class="card">Total Items<br><b><?= $summary['total_items'] ?? 0 ?></b></div>
</div>

<button class="btn" onclick="openModal()">+ Add Product</button>

<br><br>

<form id="filterForm" method="GET" style="margin-bottom:15px;">

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
</form>

<table>
<thead>
<tr>
    <th>Name</th>
    <th>Category</th>
    <th>Unit</th>
    <th>Min</th>
    <th>Stock</th>
    <th>Expiry (Soonest)</th>
    <th>Action</th>
</tr>
</thead>
<tbody id="productTable">

<?php if(count($products) > 0): ?>
<?php foreach($products as $product): ?>
<tr>
<td>
    <strong><?= htmlspecialchars($product['name']) ?></strong>
    
    <?php if($product['batch_count'] > 0): ?>
        <div class="batch-info">
            📦 <?= $product['batch_count'] ?> batch(es) 
            <?php if($product['expired_count'] > 0): ?>
                | <span style="color:#dc3545;">⚠️ <?= $product['expired_count'] ?> expired</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</td>

<td><?= htmlspecialchars($product['category_name']) ?></td>
<td><?= htmlspecialchars($product['unit']) ?></td>
<td><?= $product['min_stock'] ?></td>

<td>
<?php
$stock = (int)$product['total_stock'];
$min = (int)$product['min_stock'];

if ($stock <= 0):
?>
    <span class="out-badge">0 Out of Stock</span>

<?php elseif ($stock <= $min): ?>
    <span class="low-badge"><?= $stock ?> Low</span>

<?php else: ?>
    <span style="font-weight:bold;"><?= $stock ?></span>

<?php endif; ?>

</td>

<td>
<?php
if ($product['nearest_expiry']) {
    $today = new DateTime();
    $expDate = new DateTime($product['nearest_expiry']);
    $daysLeft = $today->diff($expDate)->days;
    
    // Check if the expDate is in the past
    if ($expDate < $today) {
        echo "<span class='expired-badge'>Expired " . $expDate->format('M d, Y') . "</span>";
    }
    // 60-day warning notice
    elseif ($daysLeft <= 60) {
        echo "<span class='warning-badge'>{$daysLeft} days left (" . $expDate->format('M d, Y') . ")</span>";
    }
    else {
        echo "<span class='safe-badge'>" . $expDate->format('M d, Y') . "</span>";
    }
} else {
    echo "<span class='safe-badge'>No Expiry</span>";
}
?>
</td>

<td>
    <!-- STOCK IN BUTTON (Creates new batch) -->
    <a href="#" 
       title="Stock In (New Batch)" 
       onclick="openStockInModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', null); return false;">➕</a>

    <!-- VIEW BATCHES BUTTON -->
    <a href="#" 
       title="View Batches" 
       onclick="viewBatches(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>'); return false;">📋</a>

    <!-- EDIT -->
    <a href="#"
       title="Edit"
       onclick="openEditModal(
           <?= $product['id'] ?>,
           `<?= htmlspecialchars($product['name']) ?>`,
           <?= $product['category_id'] ?>,
           `<?= htmlspecialchars($product['unit']) ?>`,
           <?= $product['min_stock'] ?>
       )">✏️</a>

    <!-- DELETE -->
    <a href="#" 
       title="Archive Product"
       onclick="confirmDelete(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>'); return false;">🗑</a>
</td>

</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center; padding:20px;">No products found.</td></tr>
<?php endif; ?>

</tbody>
</table>

<div id="pagination" class="pagination" style="margin-top:15px;">


<?php if($page > 1): ?>
    <!-- First -->
    <a href="?<?= http_build_query([
        'search'=>$search,
        'category'=>$categoryFilter,
        'page'=>1
    ]) ?>#pagination">⏮</a>

    <!-- Previous -->
    <a href="?<?= http_build_query([
        'search'=>$search,
        'category'=>$categoryFilter,
        'page'=>$page-1
    ]) ?>#pagination">◀</a>
<?php endif; ?>


<?php
// Show limited page numbers (clean look)
$start = max(1, $page - 2);
$end = min($totalPages, $page + 2);

for($i = $start; $i <= $end; $i++):
?>
    <a href="?<?= http_build_query([
        'search'=>$search,
        'category'=>$categoryFilter,
        'page'=>$i
    ]) ?>"
       style="<?= $i == $page ? 'font-weight:bold; text-decoration:underline;' : '' ?>">
       <?= $i ?>
    </a>
<?php endfor; ?>

<?php if($page < $totalPages): ?>
    <!-- Next -->
    <a href="?<?= http_build_query([
        'search'=>$search,
        'category'=>$categoryFilter,
        'page'=>$page+1
    ]) ?>">▶</a>

    <!-- Last -->
    <a href="?<?= http_build_query([
        'search'=>$search,
        'category'=>$categoryFilter,
        'page'=>$totalPages
    ]) ?>">⏭</a>
<?php endif; ?>

</div>

</div>
</div>

<!-- ================= CONFIRMATION MODAL ================= -->
<div class="confirm-modal" id="confirmModal">
    <div class="confirm-content">
        <div class="confirm-header" id="confirmHeader">
            <i id="confirmIcon">⚠️</i>
            <h3 id="confirmTitle">Confirm Action</h3>
        </div>
        <div class="confirm-message" id="confirmMessage">
            Are you sure you want to proceed?
        </div>
        <div class="confirm-buttons">
            <button class="confirm-btn cancel" id="confirmCancelBtn">Cancel</button>
            <button class="confirm-btn confirm" id="confirmOkBtn">OK</button>
        </div>
    </div>
</div>

<!-- ================= TOAST NOTIFICATION ================= -->
<div id="toastNotification" style="display: none;"></div>

<!-- ================= ADD PRODUCT MODAL ================= -->
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
<option value="<?= $category['id'] ?>">
<?= htmlspecialchars($category['name']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Unit</label>
<input type="text" name="unit" required>

<label>Minimum Stock</label>
<input type="number" name="min_stock" required>

<br><br>
<button type="submit" class="btn">Save</button>

</form>
</div>
</div>

<!-- ================= EDIT PRODUCT MODAL ================= -->
<div class="modal" id="editProductModal" style="display:none;">
<div class="modal-content">

<span class="modal-close" onclick="closeEditModal()">&times;</span>
<h2>Edit Product</h2>

<form id="editProductForm">

<input type="hidden" name="id" id="edit_id">

<label>Product Name</label>
<input type="text" name="name" id="edit_name" required>

<label>Category</label>
<select name="category_id" id="edit_category" required>
<option value="">Select</option>
<?php foreach($categories as $category): ?>
<option value="<?= $category['id'] ?>">
<?= htmlspecialchars($category['name']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Unit</label>
<input type="text" name="unit" id="edit_unit" required>

<label>Minimum Stock</label>
<input type="number" name="min_stock" id="edit_min_stock" required>

<br><br>
<button type="submit" class="btn btn-primary">Update</button>

</form>
</div>
</div>

<!-- ================= STOCK IN MODAL ================= -->
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

<label>Expiry Date</label>
<input type="date" name="new_expiry_date" id="stockInNewExpiryDate">

<label>Notes</label>
<textarea name="notes" rows="2" placeholder="e.g., Donation, Purchase, etc."></textarea>

<br><br>
<button type="submit" class="btn">Add Stock</button>

</form>
</div>
</div>

<!-- ================= STOCK OUT MODAL ================= -->
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

<!-- ================= BATCHES MODAL (SORTED BY SOONEST EXPIRY) ================= -->
<div class="modal" id="batchesModal" style="display:none;">
<div class="modal-content" style="width:700px; max-width:95%;">

<span class="modal-close" onclick="closeBatchesModal()">&times;</span>
<h2 id="batchesProductName">Product Batches</h2>

<table class="batch-table" style="width:100%; margin-top:15px;">
<thead>
    <tr>
        <th>Batch (Expiry Date)</th>
        <th>Stock</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody id="batchesTableBody">
    <!-- Batches will be loaded here - SORTED BY SOONEST EXPIRY -->
</tbody>
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

    timer = setTimeout(() => {
        filterForm.submit();
    }, 500); // auto filter after typing
});
</script>

<script>
// ================= CUSTOM CONFIRMATION SYSTEM =================
let confirmCallback = null;
let confirmCancelCallback = null;

function showConfirm(options) {
    const modal = document.getElementById('confirmModal');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const message = document.getElementById('confirmMessage');
    const okBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    // Set content
    icon.innerHTML = options.icon || '⚠️';
    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    
    // Set button styles
    okBtn.className = 'confirm-btn ' + (options.okButtonClass || 'confirm');
    okBtn.textContent = options.okButtonText || 'OK';
    cancelBtn.textContent = options.cancelButtonText || 'Cancel';
    
    // Set callbacks
    confirmCallback = options.onOk;
    confirmCancelCallback = options.onCancel;
    
    // Show modal
    modal.style.display = 'block';
    
    // Focus OK button
    okBtn.focus();
}

function closeConfirm() {
    document.getElementById('confirmModal').style.display = 'none';
    confirmCallback = null;
    confirmCancelCallback = null;
}

document.getElementById('confirmOkBtn').addEventListener('click', function() {
    if (confirmCallback) {
        confirmCallback();
    }
    closeConfirm();
});

document.getElementById('confirmCancelBtn').addEventListener('click', function() {
    if (confirmCancelCallback) {
        confirmCancelCallback();
    }
    closeConfirm();
});

// Close on click outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('confirmModal');
    if (event.target === modal) {
        if (confirmCancelCallback) {
            confirmCancelCallback();
        }
        closeConfirm();
    }
});

// ================= TOAST NOTIFICATION SYSTEM =================
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('toastNotification');
    
    // Clear any existing timeout
    if (window.toastTimeout) {
        clearTimeout(window.toastTimeout);
    }
    if (window.toastHideTimeout) {
        clearTimeout(window.toastHideTimeout);
    }
    
    // Set icon based on type
    let icon = 'ℹ️';
    switch(type) {
        case 'success':
            icon = '✅';
            break;
        case 'error':
            icon = '❌';
            break;
        case 'warning':
            icon = '⚠️';
            break;
        case 'info':
            icon = 'ℹ️';
            break;
    }
    
    toast.innerHTML = `
        <span style="margin-right: 10px; font-size: 20px;">${icon}</span>
        <span style="flex-grow: 1;">${message}</span>
        ${type === 'info' && duration === 0 ? '<span class="loading-spinner" style="margin-left: 10px;"></span>' : ''}
    `;
    toast.className = `toast-notification ${type}`;
    toast.style.display = 'flex';
    toast.style.opacity = '1';
    
    // Auto hide after duration (if duration > 0)
    if (duration > 0) {
        window.toastTimeout = setTimeout(() => {
            toast.classList.add('fade-out');
            window.toastHideTimeout = setTimeout(() => {
                toast.style.display = 'none';
                toast.classList.remove('fade-out');
            }, 300);
        }, duration);
    }
}

// ================= SHOW LOADING =================
function showLoading(message = 'Processing...') {
    showToast(message, 'info', 0); // 0 duration means it won't auto hide
}

function hideLoading() {
    const toast = document.getElementById('toastNotification');
    
    // Clear any existing timeouts
    if (window.toastTimeout) {
        clearTimeout(window.toastTimeout);
    }
    if (window.toastHideTimeout) {
        clearTimeout(window.toastHideTimeout);
    }
    
    toast.classList.add('fade-out');
    window.toastHideTimeout = setTimeout(() => {
        toast.style.display = 'none';
        toast.classList.remove('fade-out');
    }, 300);
}

/* ================= ADD PRODUCT ================= */

function openModal() {
    document.getElementById('productModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    document.getElementById('addProductForm').reset();
}

document.getElementById('addProductForm').addEventListener('submit', function(e){
    e.preventDefault();

    const formData = new FormData(this);

    console.log("Submitting product...");
    showLoading('Adding product...');

    fetch('../api/add_product.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log("Response:", data);

        if(data.status === 'success'){
            showToast('Product added successfully!', 'success', 2000);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            hideLoading();
            showToast(data.message || "Error adding product", 'error', 4000);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        hideLoading();
        showToast("Failed to connect to server", 'error', 4000);
    });
});

/* ================= EDIT PRODUCT ================= */

function openEditModal(id, name, category, unit, min_stock) {
    console.log("Opening edit modal with:", {id, name, category, unit, min_stock});
    
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_unit').value = unit;
    document.getElementById('edit_min_stock').value = min_stock;
    document.getElementById('editProductModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editProductModal').style.display = 'none';
}

// Edit form submission
const editForm = document.getElementById('editProductForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        console.log("Edit form submitted");

        const formData = new FormData(this);
        
        showLoading('Updating product...');

        fetch('../api/update_product.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            console.log("Response data:", data);
            
            if(data.status === 'success'){
                showToast('Product updated successfully!', 'success', 2000);
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                hideLoading();
                showToast(data.message || 'Error updating product', 'error', 4000);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            hideLoading();
            showToast('Failed to update product', 'error', 4000);
        });
    });
}

// ================= DELETE PRODUCT =================
function confirmDelete(productId, productName) {
    showConfirm({
        icon: '⚠️',
        title: 'Delete Product',
        message: `Are you sure you want to delete "${productName}"? This action cannot be undone.`,
        okButtonText: 'Delete',
        okButtonClass: 'warning',
        cancelButtonText: 'Cancel',
        onOk: function() {
            showLoading('Deleting product...');
            
            fetch('../api/delete_product.php?id=' + productId)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast('Product deleted successfully!', 'success', 2000);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    hideLoading();
                    showToast(data.message || 'Error deleting product', 'error', 4000);
                }
            })
            .catch(err => {
                console.error(err);
                hideLoading();
                showToast('Failed to delete product', 'error', 4000);
            });
        }
    });
}

/* ================= BATCHES MODAL - SORTED BY SOONEST EXPIRY ================= */
function viewBatches(productId, productName) {
    document.getElementById('batchesProductName').textContent = productName + ' - Batches';
    document.getElementById('batchesModal').style.display = 'block';
    
    showLoading('Loading batches...');
    
    fetch('../api/get_batches.php?product_id=' + productId)
    .then(res => res.json())
    .then(data => {
        hideLoading();
        
        const tbody = document.getElementById('batchesTableBody');
        tbody.innerHTML = '';
        
        if(data.batches && data.batches.length > 0) {
            // Sort batches: expired first, then by expiry date (soonest first), then no expiry at the end
            const sortedBatches = data.batches.sort((a, b) => {
                // Handle null expiry dates
                if (!a.expiry_date && !b.expiry_date) return 0;
                if (!a.expiry_date) return 1; // No expiry goes to bottom
                if (!b.expiry_date) return -1;
                
                const dateA = new Date(a.expiry_date);
                const dateB = new Date(b.expiry_date);
                const today = new Date();
                
                // Check if expired
                const aExpired = dateA < today;
                const bExpired = dateB < today;
                
                if (aExpired && !bExpired) return -1; // Expired first
                if (!aExpired && bExpired) return 1;
                
                // Then sort by date (soonest first)
                return dateA - dateB;
            });
            
            sortedBatches.forEach(batch => {
                let status = '';
                let statusClass = '';
                let stock = parseInt(batch.current_stock);
                
                if(batch.expiry_date) {
                    let today = new Date();
                    let expDate = new Date(batch.expiry_date);
                    let daysLeft = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                    
                    if(expDate < today) {
                        status = 'EXPIRED';
                        statusClass = 'expired-badge';
                    } else if(daysLeft <= 60) {
                        status = daysLeft + ' days left';
                        statusClass = 'warning-badge';
                    } else {
                        status = 'Good';
                        statusClass = 'safe-badge';
                    }
                } else {
                    status = 'No expiry';
                    statusClass = 'safe-badge';
                }
                
                let row = `<tr>
                    <td><strong>${batch.expiry_date || 'No expiry'}</strong></td>
                    <td><strong>${stock}</strong></td>
                    <td><span class="${statusClass}">${status}</span></td>
                    <td>
                        <a href="#" onclick="openStockInModal(${productId}, '${productName}', '${batch.expiry_date}'); closeBatchesModal(); return false;" title="Add to this batch">➕</a>
                        <a href="#" onclick="openStockOutModal(${productId}, '${productName}', '${batch.expiry_date}', ${stock}); closeBatchesModal(); return false;" title="Remove from this batch">➖</a>
                    </td>
                </tr>`;
                tbody.innerHTML += row;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No batches found. Click the + button to create a new batch.</td></tr>';
        }
    })
    .catch(err => {
        hideLoading();
        console.error(err);
        showToast('Failed to load batches', 'error', 4000);
    });
}

function closeBatchesModal() {
    document.getElementById('batchesModal').style.display = 'none';
}

/* ================= STOCK IN MODAL ================= */
function openStockInModal(productId, productName, specificBatch = null) {
    document.getElementById('stockInProductId').value = productId;
    document.getElementById('stockInProductName').textContent = productName;
    document.getElementById('stockInNewExpiryDate').value = '';
    
    if (specificBatch) {
        document.getElementById('stockInBatchInfo').textContent = 'Adding to batch: ' + specificBatch;
        document.getElementById('stockInExpiryDate').value = specificBatch;
        document.getElementById('stockInNewExpiryDate').disabled = true;
        document.getElementById('stockInNewExpiryDate').style.opacity = '0.5';
    } else {
        document.getElementById('stockInBatchInfo').textContent = 'Create new batch';
        document.getElementById('stockInExpiryDate').value = '';
        document.getElementById('stockInNewExpiryDate').disabled = false;
        document.getElementById('stockInNewExpiryDate').style.opacity = '1';
        
        // Set default expiry date to today + 1 year for new batch
        let defaultExpiry = new Date();
        defaultExpiry.setFullYear(defaultExpiry.getFullYear() + 1);
        let year = defaultExpiry.getFullYear();
        let month = String(defaultExpiry.getMonth() + 1).padStart(2, '0');
        let day = String(defaultExpiry.getDate()).padStart(2, '0');
        document.getElementById('stockInNewExpiryDate').value = year + '-' + month + '-' + day;
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
    
    // If this is a new batch, use the new expiry date
    const expiryDate = document.getElementById('stockInExpiryDate').value;
    const newExpiryDate = document.getElementById('stockInNewExpiryDate').value;
    
    if (!expiryDate && newExpiryDate) {
        formData.set('expiry_date', newExpiryDate);
    }
    
    console.log("Sending stock in data:");
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    showLoading('Adding stock...');

    fetch('../api/stock_movement.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log("Response data:", data);
        
        if(data.status === 'success'){
            showToast('Stock added successfully!', 'success', 2000);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            hideLoading();
            showToast(data.message || 'Error adding stock', 'error', 4000);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        hideLoading();
        showToast('Failed to connect to server', 'error', 4000);
    });
});

/* ================= STOCK OUT MODAL ================= */
function openStockOutModal(productId, productName, specificBatch, batchStock) {
    document.getElementById('stockOutProductId').value = productId;
    document.getElementById('stockOutProductName').textContent = productName;
    
    if (specificBatch) {
        document.getElementById('stockOutBatchInfo').textContent = 'Batch: ' + specificBatch;
        document.getElementById('stockOutExpiryDate').value = specificBatch;
    } else {
        document.getElementById('stockOutBatchInfo').textContent = 'Please select a batch from the batches view';
        document.getElementById('stockOutExpiryDate').value = '';
    }
    
    document.getElementById('stockOutCurrentStock').textContent = 'Available in this batch: ' + batchStock;
    document.getElementById('stockOutQuantity').max = batchStock;
    document.getElementById('stockOutQuantity').value = 1;
    
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
    
    // Validate that we have a batch
    const expiryDate = document.getElementById('stockOutExpiryDate').value;
    if (!expiryDate) {
        showToast('Please select a batch from the batches view', 'error', 3000);
        return;
    }
    
    console.log("Sending stock out data:");
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    showLoading('Removing stock...');

    fetch('../api/stock_movement.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log("Response data:", data);
        
        if(data.status === 'success'){
            showToast('Stock removed successfully!', 'success', 2000);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            hideLoading();
            showToast(data.message || 'Error removing stock', 'error', 4000);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        hideLoading();
        showToast('Failed to connect to server', 'error', 4000);
    });
});
</script>

<script>
// Save scroll position before navigating
document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', function() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
    });
});

// Restore scroll position
window.addEventListener('load', function() {
    const scrollPosition = sessionStorage.getItem('scrollPosition');
    if (scrollPosition !== null) {
        window.scrollTo(0, parseInt(scrollPosition));
        sessionStorage.removeItem('scrollPosition');
    }
});
</script>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

document.addEventListener("click", function (event) {
    const sidebar = document.querySelector(".sidebar");
    const toggleBtn = document.querySelector(".menu-toggle");

    if (!sidebar.contains(event.target) &&
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove("active");
    }
});
</script>

</body>
</html>