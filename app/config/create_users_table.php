<?php
// create_users_table.php
require_once 'app/config/database.php';

echo "<h1>Creating Users Table</h1>";

try {
    $db = Database::connect();
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        pass VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "<p style='color: green;'>✅ Users table created successfully!</p>";
    
    // Insert default users
    $users = [
        ['admin', 'admin123'],
        ['staff', 'staff123'],
        ['manager', 'manager123']
    ];
    
    $stmt = $db->prepare("INSERT INTO users (name, pass) VALUES (?, ?)");
    
    foreach ($users as $user) {
        $stmt->execute($user);
        echo "<p>✓ Added user: <strong>" . $user[0] . "</strong> (password: " . $user[1] . ")</p>";
    }
    
    echo "<p style='color: green; margin-top: 20px;'>✅ Login credentials created:</p>";
    echo "<ul>";
    echo "<li><strong>admin</strong> / admin123</li>";
    echo "<li><strong>staff</strong> / staff123</li>";
    echo "<li><strong>manager</strong> / manager123</li>";
    echo "</ul>";
    
    echo "<p><a href='public/log_in.php' style='display: inline-block; padding: 10px 20px; background: #ff8c42; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>