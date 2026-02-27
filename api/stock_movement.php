<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Enable error reporting for debugging
error_log("Stock movement request received: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$productId = $_POST['product_id'] ?? '';
$quantity = $_POST['quantity'] ?? '';
$type = $_POST['type'] ?? '';
$expiryDate = $_POST['expiry_date'] ?? null;
$notes = $_POST['notes'] ?? '';
$staff_id = $_SESSION['user_id']; // Get logged-in user ID

// Validation
if (!$productId || !$quantity || !$type) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($type, ['IN', 'OUT'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Quantity must be a positive number']);
    exit;
}

// For stock out, check if there's enough stock for the specific batch
if ($type === 'OUT') {
    if (!$expiryDate) {
        echo json_encode(['status' => 'error', 'message' => 'Expiry date is required for stock out']);
        exit;
    }
    
    $checkSql = "
        SELECT SUM(
            CASE
                WHEN type='IN' THEN quantity
                WHEN type='OUT' THEN -quantity
                ELSE 0
            END
        ) as current_stock
        FROM stock_movements
        WHERE product_id = :product_id AND expiry_date = :expiry_date
    ";
    
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $checkStmt->bindValue(':expiry_date', $expiryDate);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $currentStock = $result ? (int)$result['current_stock'] : 0;
    
    if ($currentStock < $quantity) {
        echo json_encode(['status' => 'error', 'message' => "Insufficient stock. Available: $currentStock"]);
        exit;
    }
}

try {
    $db->beginTransaction();
    
    // Insert stock movement with staff_id
    $sql = "
        INSERT INTO stock_movements 
        (product_id, type, quantity, expiry_date, note, staff_id, created_at) 
        VALUES 
        (:product_id, :type, :quantity, :expiry_date, :note, :staff_id, NOW())
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':type', $type);
    $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindValue(':expiry_date', $expiryDate ?: null);
    $stmt->bindValue(':note', $notes ?: null);
    $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Stock movement recorded successfully']);
    } else {
        $db->rollBack();
        $error = $stmt->errorInfo();
        error_log("Database error: " . print_r($error, true));
        echo json_encode(['status' => 'error', 'message' => 'Failed to record stock movement']);
    }
} catch (PDOException $e) {
    $db->rollBack();
    error_log("PDO Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}