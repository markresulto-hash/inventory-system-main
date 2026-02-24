<?php
require_once __DIR__ . '/../app/config/database.php';
$db = Database::connect();

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$unit = $_POST['unit'] ?? '';
$min_stock = $_POST['min_stock'] ?? '';

error_log("Update product - ID: $id, Name: $name, Category: $category_id, Unit: $unit, Min Stock: $min_stock");

if (!$id || !$name || !$category_id || !$unit || !$min_stock) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if (!is_numeric($id) || !is_numeric($category_id) || !is_numeric($min_stock)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid numeric values']);
    exit;
}

try {
    $checkStmt = $db->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found or inactive']);
        exit;
    }
    
    $duplicateStmt = $db->prepare("
        SELECT id FROM products 
        WHERE name = ? AND category_id = ? AND id != ? AND is_active = 1
    ");
    $duplicateStmt->execute([$name, $category_id, $id]);
    
    if ($duplicateStmt->fetch()) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'A product with this name already exists in the selected category'
        ]);
        exit;
    }
    
    $sql = "UPDATE products SET name = ?, category_id = ?, unit = ?, min_stock = ? WHERE id = ? AND is_active = 1";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$name, $category_id, $unit, $min_stock, $id]);
    
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}