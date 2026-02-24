<?php
require_once __DIR__ . '/../app/config/database.php';

$db = Database::connect();

$search = trim($_GET['search'] ?? '');

$sql = "
SELECT 
    p.*,
    c.name AS category_name,

    COALESCE(SUM(
        CASE 
            WHEN sm.type='IN' THEN sm.quantity
            WHEN sm.type='OUT' THEN -sm.quantity
            ELSE 0
        END
    ),0) AS current_stock,

    MIN(
        CASE
            WHEN sm.type='IN'
                 AND sm.expiry_date IS NOT NULL
                 AND sm.expiry_date >= CURDATE()
            THEN sm.expiry_date
        END
    ) AS nearest_expiry

FROM products p
JOIN categories c ON p.category_id = c.id
LEFT JOIN stock_movements sm ON p.id = sm.product_id
WHERE p.name LIKE :search
GROUP BY p.id
ORDER BY p.created_at DESC
LIMIT 10
";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':search' => "%$search%"
]);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ================= RETURN TABLE ROWS ================= */

if(count($products) === 0){
    echo "<tr><td colspan='7'>No products found.</td></tr>";
    exit;
}

foreach ($products as $product):

$stock = (int)$product['current_stock'];
$min   = (int)$product['min_stock'];
?>

<tr>

<td><?= htmlspecialchars($product['name']) ?></td>

<td><?= htmlspecialchars($product['category_name']) ?></td>

<td><?= htmlspecialchars($product['unit']) ?></td>

<td><?= $product['min_stock'] ?></td>

<!-- STOCK COLUMN -->
<td>
<?php if ($stock <= 0): ?>
    <span class="out-badge">0 Out of Stock</span>

<?php elseif ($stock <= $min): ?>
    <span class="low-badge"><?= $stock ?> Low</span>

<?php else: ?>
    <?= $stock ?>
<?php endif; ?>
</td>


<!-- EXPIRY COLUMN -->
<td>
<?php
$expiry = $product['nearest_expiry'];

if (!$expiry) {
    echo "-";
} else {

    $today   = new DateTime();
    $expDate = new DateTime($expiry);
    $daysLeft = $today->diff($expDate)->days;

    if ($expDate < $today) {
        echo "<span class='expired-badge'>Expired</span>";
    }
    elseif ($daysLeft <= 30) {
        echo "<span class='warning-badge'>{$expiry} ({$daysLeft}d)</span>";
    }
    else {
        echo "<span class='safe-badge'>{$expiry}</span>";
    }
}
?>
</td>


<!-- ACTION COLUMN (FIXED ✅) -->
<td>

<!-- STOCK IN -->
<a href="stock_in.php?product_id=<?= $product['id'] ?>" title="Stock In">➕</a>

<!-- STOCK OUT -->
<a href="stock_out.php?product_id=<?= $product['id'] ?>" title="Stock Out">➖</a>

<!-- EDIT -->
<a href="#"
title="Edit"
onclick="openEditModal(
<?= $product['id'] ?>,
`<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>`,
<?= $product['category_id'] ?>,
`<?= htmlspecialchars($product['unit'], ENT_QUOTES) ?>`,
<?= $product['min_stock'] ?>
)">✏️</a>

<!-- DELETE -->
<a href="../api/delete_product.php?id=<?= $product['id'] ?>"
title="Delete"
onclick="return confirm('Delete this product?')">🗑</a>

</td>

</tr>

<?php endforeach; ?>
