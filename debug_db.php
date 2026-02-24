<?php
// debug_db.php
require_once 'app/config/database.php';

echo "<h1>Database Debug Information</h1>";

try {
    $db = Database::connect();
    
    // Get database name
    $stmt = $db->query("SELECT DATABASE()");
    $dbname = $stmt->fetchColumn();
    echo "<h2>Connected to Database: " . htmlspecialchars($dbname) . "</h2>";
    
    // Get all tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p style='color: red;'>No tables found in the database!</p>";
    } else {
        echo "<h3>Tables in database:</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><strong>" . htmlspecialchars($table) . "</strong>";
            
            // Show structure of each table
            $columns = $db->query("DESCRIBE " . $table);
            $columnData = $columns->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<ul>";
            foreach ($columnData as $col) {
                echo "<li>" . $col['Field'] . " - " . $col['Type'] . "</li>";
            }
            echo "</ul>";
            echo "</li>";
        }
        echo "</ul>";
    }
    
    // Also check if there's a table with id, name, pass fields
    echo "<h3>Looking for tables with 'name' and 'pass' fields:</h3>";
    foreach ($tables as $table) {
        $columns = $db->query("DESCRIBE " . $table);
        $colNames = $columns->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if (in_array('name', $colNames) && in_array('pass', $colNames)) {
            echo "<p style='color: green;'>✓ Table '" . $table . "' has both 'name' and 'pass' fields - THIS IS LIKELY YOUR LOGIN TABLE!</p>";
            
            // Show sample data
            $data = $db->query("SELECT id, name, pass FROM " . $table . " LIMIT 5");
            $users = $data->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($users)) {
                echo "<p>Sample users in this table:</p>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Name</th><th>Password</th></tr>";
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . $user['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                    echo "<td>" . htmlspecialchars(substr($user['pass'], 0, 20)) . "...</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>