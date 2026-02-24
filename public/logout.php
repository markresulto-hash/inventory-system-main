<?php
session_start();

require_once '../app/config/database.php';
$db = Database::connect();

// If user is logged in, clear their remember token from database
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Log error but continue with logout
        error_log("Logout error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear remember me cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_user', '', time() - 3600, '/');

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: log_in.php");
exit();
?>