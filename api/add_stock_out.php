<?php
require_once '../app/config/database.php';
$db = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/stock_out.php");
    exit;
}

$product_id = $_POST['product_id'] ?? null;
$quantity   = $_POST['quantity'] ?? null;
$reason     = $_POST['reason'] ?? null;

if (!$product_id || !$quantity) {
    header("Location: ../public/stock_out.php?error=Missing fields");
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    header("Location: ../public/stock_out.php?error=Invalid quantity");
    exit;
}

/* ================= CHECK CURRENT STOCK ================= */

$stmt = $db->prepare("
    SELECT COALESCE(SUM(
        CASE 
            WHEN type = 'IN' THEN quantity
            WHEN type = 'OUT' THEN -quantity
            ELSE 0
        END
    ),0) AS current_stock
    FROM stock_movements
    WHERE product_id = ?
");

$stmt->execute([$product_id]);
$currentStock = $stmt->fetchColumn();

if ($quantity > $currentStock) {
    header("Location: ../public/stock_out.php?error=Not enough stock available");
    exit;
}

/* ================= INSERT STOCK OUT ================= */

$stmt = $db->prepare("
    INSERT INTO stock_movements
    (product_id, type, quantity, reason, created_at)
    VALUES (?, 'OUT', ?, ?, NOW())
");

$stmt->execute([$product_id, $quantity, $reason]);

header("Location: ../public/stock_out.php?success=1");
exit;
