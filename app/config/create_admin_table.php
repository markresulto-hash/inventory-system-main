<?php
// create_admin_table.php
require_once 'app/config/database.php';

echo "<h1>Creating Admin Table</h1>";

try {
    $db = Database::connect();
    
    // Check if admin table exists
    $stmt = $db->query("SHOW TABLES LIKE 'admin'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create admin table
        $sql = "CREATE TABLE admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            pass VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($sql);
        echo "<p style='color: green;'>✓ Admin table created successfully!</p>";
        
        // Insert a default admin user (password: admin123)
        $stmt = $db->prepare("INSERT INTO admin (name, pass) VALUES (?, ?)");
        $stmt->execute(['admin', 'admin123']);
        
        echo "<p style='color: green;'>✓ Default admin user created:</p>";
        echo "<ul>";
        echo "<li>Username: <strong>admin</strong></li>";
        echo "<li>Password: <strong>admin123</strong></li>";
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>Admin table already exists.</p>";
        
        // Show existing users
        $users = $db->query("SELECT id, name, pass FROM admin");
        echo "<p>Current users in admin table:</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Password</th></tr>";
        while ($user = $users->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['pass']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<br><a href='public/log_in.php'>Go to Login Page</a>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>