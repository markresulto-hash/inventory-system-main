<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

header('Content-Type: application/json');

if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
    exit;
}

$productId = (int)$_GET['product_id'];

try {
    $sql = "
        SELECT 
            expiry_date,
            SUM(
                CASE
                    WHEN type='IN' THEN quantity
                    WHEN type='OUT' THEN -quantity
                    ELSE 0
                END
            ) AS current_stock
        FROM stock_movements
        WHERE product_id = :product_id
        GROUP BY expiry_date
        HAVING current_stock > 0
        ORDER BY 
            CASE 
                WHEN expiry_date IS NULL THEN 1 
                ELSE 0 
            END,
            expiry_date ASC  -- This ensures soonest expiry first
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'batches' => $batches]);
} catch (PDOException $e) {
    error_log("Database error in get_batches.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}