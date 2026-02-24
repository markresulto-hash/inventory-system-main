<?php
// create_login_table.php
require_once 'app/config/database.php';

try {
    $db = Database::connect();
    
    // Create a simple login table
    $sql = "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        pass VARCHAR(255) NOT NULL
    )";
    
    $db->exec($sql);
    echo "✓ Admin table created successfully<br>";
    
    // Add a test user (password: admin123)
    $stmt = $db->prepare("INSERT INTO admin (name, pass) VALUES (?, ?)");
    $stmt->execute(['admin', 'admin123']);
    
    echo "✓ Test user added: admin / admin123<br>";
    echo "<br><a href='public/log_in.php'>Go to Login Page</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>