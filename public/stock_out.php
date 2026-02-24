<?php
require_once '../app/config/database.php';
$db = Database::connect();

/* ================= AUTO SELECT PRODUCT ================= */

$selectedProduct = $_GET['product_id'] ?? '';
if ($selectedProduct) {

    $check = $db->prepare("
        SELECT id FROM products
        WHERE id = :id AND is_active = 1
    ");

    $check->execute([':id' => $selectedProduct]);

    if (!$check->fetch()) {
        $selectedProduct = '';
    }
}


// Fetch products for dropdown
$stmt = $db->query("SELECT id, name FROM products ORDER BY name ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent stock OUT history
$stmt2 = $db->query("
    SELECT sm.*, p.name AS product_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE sm.type = 'OUT'
    ORDER BY sm.created_at DESC
    LIMIT 5
");
$recent = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Stock Out - Inventory System</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">

<style>
body {
    margin: 0;
    font-family: Arial;
    background: #f4f6f9;
}

.container {
    display: flex;
    min-height: 100vh;
}

/* MAIN */
.main {
    flex: 1;
    padding: 20px;
    background: #f4f6f9;
}

/* CARD (✅ SAME SLIM STYLE AS STOCK IN) */
.card {
    background: white;
    padding: 25px;
    border-radius: 6px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);

    max-width: 370px;
}

/* FORM */
input, select {
    display: block;
    width: 100%;
    padding: 8px;
    margin-bottom: 12px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

input:focus, select:focus {
    outline: none;
    border-color: #e74c3c;
}

/* ✅ QUANTITY WIDTH */
.quantity-input {
    width: 120px;
}

/* ✅ REASON SAME STYLE */
.reason-input {
    width: 200px;
}

/* BUTTON */
button {
    padding: 8px 12px;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background: #c0392b;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

th {
    background: #ecf0f1;
}

tr:hover {
    background: #f9f9f9;
}
</style>
</head>

<body>

<div class="overlay" onclick="toggleSidebar()"></div>

<div class="container">

<?php include 'includes/sidebar.php'; ?>

<div class="main">

<?php if(isset($_GET['success'])): ?>
<div style="background:#d4edda;padding:10px;margin-bottom:15px;color:#155724;border-radius:4px;">
Stock removed successfully!
</div>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
<div style="background:#f8d7da;padding:10px;margin-bottom:15px;color:#721c24;border-radius:4px;">
Stock out should not exceed the stock count!
</div>
<?php endif; ?>

<button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>

<h1>📤 Stock Out</h1>

<div class="card">
<form action="../api/remove_stock.php" method="POST">

<label>Product</label>
<select name="product_id" required>
<option value="">Select Product</option>
<?php foreach($products as $p): ?>
<option value="<?= $p['id'] ?>"
<?= $selectedProduct == $p['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($p['name']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Quantity</label>

<!-- ✅ UPDATED INPUT -->
<input
    type="number"
    name="quantity"
    class="quantity-input"
    required
    min="1"
    max="999"
    inputmode="numeric"
    pattern="[0-9]*"
>

<label>Reason (Optional)</label>

<input
    type="text"
    name="reason"
    class="reason-input"
    placeholder="Sold / Damaged / Expired"
>

<button type="submit" style="display:block;margin-top:10px;">
Remove Stock
</button>

</form>
</div>

<h2>Recent Stock Out</h2>

<table>
<thead>
<tr>
<th>Product</th>
<th>Quantity</th>
<th>Reason</th>
<th>Date</th>
</tr>
</thead>
<tbody>

<?php if(count($recent) > 0): ?>
<?php foreach($recent as $r): ?>
<tr>
<td><?= htmlspecialchars($r['product_name']) ?></td>
<td><?= $r['quantity'] ?></td>
<td><?= htmlspecialchars($r['reason'] ?? '') ?></td>
<td><?= $r['created_at'] ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="4">No stock-out records yet.</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>
</div>

<!-- ✅ NUMBER ONLY + 3 DIGIT LIMIT -->
<script>
const qtyInput = document.querySelector('input[name="quantity"]');

qtyInput.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, '');

    if (this.value.length > 3) {
        this.value = this.value.slice(0,3);
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const productSelect = document.querySelector('select[name="product_id"]');
    const quantityInput = document.querySelector('input[name="quantity"]');

    if (productSelect.value !== "") {
        quantityInput.focus();
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
