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

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/products/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$id = $_POST['id'] ?? ''; // Change from $_GET to $_POST
$name = $_POST['name'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$unit = $_POST['unit'] ?? '';
$min_stock = $_POST['min_stock'] ?? '';
$has_expiry = $_POST['has_expiry'] ?? '';
$current_image = $_POST['current_image'] ?? '';

if (!$id || !is_numeric($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

if (empty($name) || empty($category_id) || empty($unit) || $min_stock === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

// Handle image upload
$imagePath = $current_image; // Keep old image by default

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF and WEBP are allowed.']);
        exit;
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File too large. Maximum size is 2MB.']);
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $imagePath = 'uploads/products/' . $filename;
        
        // Delete old image if it exists and is not the default
        if (!empty($current_image) && $current_image !== 'uploads/products/' && file_exists(__DIR__ . '/../' . $current_image)) {
            unlink(__DIR__ . '/../' . $current_image);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload image.']);
        exit;
    }
}

try {
    $db->beginTransaction();
    
    // Get old data for audit including category name
    $getStmt = $db->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $getStmt->execute([$id]);
    $oldData = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldData) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
    
    // Get new category name for audit
    $catStmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $catStmt->execute([$category_id]);
    $newCategory = $catStmt->fetch(PDO::FETCH_ASSOC);
    $newCategoryName = $newCategory ? $newCategory['name'] : '';
    
    // Update product with image path
    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, category_id = ?, unit = ?, min_stock = ?, has_expiry = ?, image_path = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$name, $category_id, $unit, $min_stock, $has_expiry, $imagePath, $id]);
    
    if ($result) {
        // Prepare audit data with nested old and new structure
        $auditData = [
            'old' => [
                'name' => $oldData['name'],
                'category_id' => (int)$oldData['category_id'],
                'category_name' => $oldData['category_name'],
                'unit' => $oldData['unit'],
                'min_stock' => (int)$oldData['min_stock'],
                'has_expiry' => (int)$oldData['has_expiry'],
                'image_path' => $oldData['image_path']
            ],
            'new' => [
                'name' => $name,
                'category_id' => (int)$category_id,
                'category_name' => $newCategoryName,
                'unit' => $unit,
                'min_stock' => (int)$min_stock,
                'has_expiry' => (int)$has_expiry,
                'image_path' => $imagePath
            ]
        ];
        
        // Log to product_audit table - using the structure that matches your table
        $auditStmt = $db->prepare("
            INSERT INTO product_audit (product_id, action, old_data, staff_id, created_at) 
            VALUES (?, 'UPDATE', ?, ?, NOW())
        ");
        $auditStmt->execute([$id, json_encode($auditData), $_SESSION['user_id']]);
        
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully', 'image_path' => $imagePath]);
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