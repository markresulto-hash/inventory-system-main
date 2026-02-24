<?php
require_once '../app/config/database.php';
$db = Database::connect();

/* ================= METHOD CHECK ================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/stock_in.php");
    exit;
}

/* ================= GET FORM DATA ================= */

$product_id  = $_POST['product_id'] ?? null;
$quantity    = $_POST['quantity'] ?? null;
$note        = $_POST['note'] ?? null;
$expiry      = $_POST['expiry_date'] ?? null;

/* Optional future fields */
$staff_id    = null;
$supplier_id = null;
$reason      = null;

/* ================= BASIC VALIDATION ================= */

if (!$product_id || !$quantity) {
    header("Location: ../public/stock_in.php?error=missing");
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    header("Location: ../public/stock_in.php?error=invalid_quantity");
    exit;
}

/* ================= PRODUCT ACTIVE CHECK ================= */

$check = $db->prepare("
    SELECT id FROM products
    WHERE id = :id AND is_active = 1
");

$check->execute([':id' => $product_id]);

if (!$check->fetch()) {
    die("Product is archived or invalid.");
}

/* ================= CLEAN EXPIRY ================= */

if (empty($expiry)) {
    $expiry = null;
}

/* ================= INSERT STOCK ================= */

$stmt = $db->prepare("
    INSERT INTO stock_movements 
    (product_id, staff_id, supplier_id, type, quantity, note, reason, expiry_date, created_at)
    VALUES (?, ?, ?, 'IN', ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $product_id,
    $staff_id,
    $supplier_id,
    $quantity,
    $note,
    $reason,
    $expiry
]);

/* ================= REDIRECT ================= */

header("Location: ../public/stock_in.php?success=1&product_id=" . $product_id);
exit;
