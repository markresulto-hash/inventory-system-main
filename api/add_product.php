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
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$category_id = $_POST['category_id'] ?? '';
$unit = trim($_POST['unit'] ?? '');
$min_stock = $_POST['min_stock'] ?? '';
$has_expiry = isset($_POST['has_expiry']) ? (int)$_POST['has_expiry'] : 1;
$staff_id = $_SESSION['user_id'];

// Validate required fields
if (empty($name) || empty($category_id) || empty($unit) || $min_stock === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if (!is_numeric($category_id) || !is_numeric($min_stock)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid numeric values']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Check if product already exists (case-insensitive)
    $checkStmt = $db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND category_id = ? AND is_active = 1");
    $checkStmt->execute([$name, $category_id]);
    
    if ($checkStmt->fetch()) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product already exists in this category']);
        exit;
    }
    
    // Insert new product
    $sql = "INSERT INTO products (name, category_id, unit, min_stock, has_expiry, is_active) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$name, $category_id, $unit, $min_stock, $has_expiry]);
    
    if ($result) {
        $newId = $db->lastInsertId();
        
        // Get category name for audit
        $catStmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $catStmt->execute([$category_id]);
        $categoryName = $catStmt->fetchColumn();
        
        // Log to product_audit table
        $auditData = [
            'name' => $name,
            'category_id' => $category_id,
            'category_name' => $categoryName,
            'unit' => $unit,
            'min_stock' => $min_stock,
            'has_expiry' => $has_expiry
        ];
        
        $auditStmt = $db->prepare("
            INSERT INTO product_audit (product_id, action, new_data, staff_id, created_at) 
            VALUES (?, 'ADD', ?, ?, NOW())
        ");
        $auditStmt->execute([$newId, json_encode($auditData), $staff_id]);
        
        $db->commit();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Product added successfully',
            'id' => $newId
        ]);
    } else {
        $db->rollBack();
        $error = $stmt->errorInfo();
        error_log("Database error in add_product.php: " . print_r($error, true));
        echo json_encode(['status' => 'error', 'message' => 'Failed to add product']);
    }
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Database error in add_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}