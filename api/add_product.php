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

$name = $_POST['name'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$unit = $_POST['unit'] ?? '';
$min_stock = $_POST['min_stock'] ?? '';

if (!$name || !$category_id || !$unit || !$min_stock) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if (!is_numeric($category_id) || !is_numeric($min_stock)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid numeric values']);
    exit;
}

try {
    // Check if product already exists
    $checkStmt = $db->prepare("SELECT id FROM products WHERE name = ? AND category_id = ? AND is_active = 1");
    $checkStmt->execute([$name, $category_id]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Product already exists in this category']);
        exit;
    }
    
    $sql = "INSERT INTO products (name, category_id, unit, min_stock, is_active) VALUES (?, ?, ?, ?, 1)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$name, $category_id, $unit, $min_stock]);
    
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Product added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add product']);
    }
} catch (PDOException $e) {
    error_log("Database error in add_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}