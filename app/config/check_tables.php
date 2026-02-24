<?php
// check_my_tables.php
require_once 'app/config/database.php';

echo "<h1>Database Table Check</h1>";

try {
    $db = Database::connect();
    
    // Get all tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p style='color: red;'>No tables found in the database!</p>";
        echo "<p>Your database 'inventory_system' is empty.</p>";
    } else {
        echo "<p>Tables found in your database:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><strong>" . htmlspecialchars($table) . "</strong>";
            
            // Show columns for this table
            $columns = $db->query("DESCRIBE " . $table);
            $cols = $columns->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<ul>";
            foreach ($cols as $col) {
                echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
            }
            echo "</ul>";
            echo "</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>