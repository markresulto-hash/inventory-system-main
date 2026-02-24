<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

$id = (int)$_GET['id'];

try {
    // Check if product exists and is active
    $checkStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1");
    $checkStmt->execute([$id]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found or already deleted']);
        exit;
    }
    
    // Soft delete - set is_active to 0 instead of actually deleting
    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Product "' . $product['name'] . '" has been archived successfully'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in delete_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}