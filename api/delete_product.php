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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = $_GET['id'] ?? '';

if (!$id || !is_numeric($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get product data before deleting for audit
    $getStmt = $db->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $getStmt->execute([$id]);
    $product = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
    
    // Soft delete - set is_active to 0 instead of actually deleting
    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        // Log to product_audit table
        $auditData = [
            'name' => $product['name'],
            'category_id' => $product['category_id'],
            'category_name' => $product['category_name'],
            'unit' => $product['unit'],
            'min_stock' => $product['min_stock'],
            'has_expiry' => $product['has_expiry']
        ];
        
        $auditStmt = $db->prepare("
            INSERT INTO product_audit (product_id, action, old_data, staff_id, created_at) 
            VALUES (?, 'DELETE', ?, ?, NOW())
        ");
        $auditStmt->execute([$id, json_encode($auditData), $_SESSION['user_id']]);
        
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
    } else {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
    }
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Database error in delete_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}