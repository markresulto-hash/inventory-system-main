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

// Change this from GET to POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = $_POST['id'] ?? ''; // Change from $_GET to $_POST
$name = $_POST['name'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$unit = $_POST['unit'] ?? '';
$min_stock = $_POST['min_stock'] ?? '';
$has_expiry = $_POST['has_expiry'] ?? '';

if (!$id || !is_numeric($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

if (empty($name) || empty($category_id) || empty($unit) || empty($min_stock)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get old data for audit
    $getStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $getStmt->execute([$id]);
    $oldData = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldData) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
    
    // Update product
    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, category_id = ?, unit = ?, min_stock = ?, has_expiry = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$name, $category_id, $unit, $min_stock, $has_expiry, $id]);
    
    if ($result) {
        // Log to product_audit table
        $auditData = [
            'old' => [
                'name' => $oldData['name'],
                'category_id' => $oldData['category_id'],
                'unit' => $oldData['unit'],
                'min_stock' => $oldData['min_stock'],
                'has_expiry' => $oldData['has_expiry']
            ],
            'new' => [
                'name' => $name,
                'category_id' => $category_id,
                'unit' => $unit,
                'min_stock' => $min_stock,
                'has_expiry' => $has_expiry
            ]
        ];
        
        $auditStmt = $db->prepare("
            INSERT INTO product_audit (product_id, action, old_data, staff_id, created_at) 
            VALUES (?, 'UPDATE', ?, ?, NOW())
        ");
        $auditStmt->execute([$id, json_encode($auditData), $_SESSION['user_id']]);
        
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
    } else {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
    }
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Database error in update_product.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
}
?>