<?php

class Database {
    public static function connect() {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=inventory_system;charset=utf8mb4",
                "root",
                ""
            );

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;

        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}
