<?php
// Start session
session_start();

// IMPORTANT: Force destroy any existing session to ensure clean login
// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Start a fresh session
session_start();

// Check for remember me cookie
require_once '../app/config/database.php';
$db = Database::connect();

// Check if user is already logged in via remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
    $token = $_COOKIE['remember_token'];
    $username = $_COOKIE['remember_user'];
    
    try {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE name = ? AND remember_token = ?");
        $stmt->execute([$username, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['name'];
            $_SESSION['logged_in'] = true;
            
            // Redirect to index page
            header("Location: index.php?fresh=" . time());
            exit();
        } else {
            // Invalid token, clear cookies
            setcookie('remember_token', '', time() - 3600, '/');
            setcookie('remember_user', '', time() - 3600, '/');
        }
    } catch (PDOException $e) {
        // Invalid token, clear cookies
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user', '', time() - 3600, '/');
    }
}

// Now redirect if already logged in (this should now be clean)
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle login form submission
$error = '';
$show_popup = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
        $show_popup = true;
    } else {
        try {
            // Using 'users' table
            $stmt = $db->prepare("SELECT id, name, pass FROM users WHERE name = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $password === $user['pass']) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['logged_in'] = true;
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (86400 * 30); // 30 days
                    
                    // Check if remember_token column exists, if not, add it
                    try {
                        // First check if column exists
                        $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
                        if ($checkColumn->rowCount() == 0) {
                            // Add remember_token column if it doesn't exist
                            $db->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL");
                        }
                        
                        // Store token in database
                        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                        $stmt->execute([$token, $user['id']]);
                        
                        // Set cookies - make sure path is correct
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                        setcookie('remember_user', $user['name'], $expiry, '/', '', false, true);
                        
                        // Debug: Check if cookies were set
                        error_log("Remember me cookies set for user: " . $user['name']);
                    } catch (PDOException $e) {
                        // Log error but continue with login
                        error_log("Remember me error: " . $e->getMessage());
                    }
                } else {
                    // Clear any existing remember me cookies if they exist
                    if (isset($_COOKIE['remember_token']) || isset($_COOKIE['remember_user'])) {
                        setcookie('remember_token', '', time() - 3600, '/');
                        setcookie('remember_user', '', time() - 3600, '/');
                        
                        // Also clear token from database
                        try {
                            $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                            $stmt->execute([$user['id']]);
                        } catch (PDOException $e) {
                            // Log error but continue
                            error_log("Clear remember token error: " . $e->getMessage());
                        }
                    }
                }
                
                // Force a completely new session to avoid any stuck states
                session_regenerate_id(true);
                
                // Redirect with timestamp to prevent caching
                header("Location: index.php?fresh=" . time());
                exit();
            } else {
                $error = 'Invalid username or password';
                $show_popup = true;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            $show_popup = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OCSR Inventory System</title>
    <!-- Font Awesome for eye icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem 4rem;
            background-color: #1a2e3b;
        }

        /* Fixed background to show top part */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('/inventory-system-main/img/bg.jpg');
            background-size: cover;
            background-position: center 100%;
            background-repeat: no-repeat;
            filter: blur(3px);
            z-index: -1;
        }

        .welcome-message {
            color: white;
            text-shadow: 0 4px 18px rgba(0, 40, 55, 0.9);
            max-width: 500px;
            margin-right: 2rem;
            position: relative;
            z-index: 1;
        }

        .welcome-message h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            text-transform: uppercase;
            background: linear-gradient(130deg, #ffffff, #d6f0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
        }

        .welcome-message p {
            font-size: 1.4rem;
            border-left: 5px solid #ffffffd0;
            padding-left: 1.5rem;
            text-shadow: 0 2px 10px #022f3a;
        }

        .login-card {
            background: #2a4b5e;
            width: 100%;
            max-width: 480px;
            padding: 2rem 2.5rem;
            border-radius: 32px;
            box-shadow: 0 25px 40px -14px rgba(0, 0, 0, 0.5);
            border: 1px solid #3f6b7e;
            position: relative;
            z-index: 1;
            color: white;
            transition: transform 0.25s ease;
        }

        .login-card:hover {
            transform: scale(1.02);
            border-color: #5f8b9e;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .logo-container img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            background: #ffffff20;
            padding: 5px;
            border: 2px solid #ffb347;
            transition: transform 0.2s;
        }

        .logo-container img:hover {
            transform: scale(1.08);
            border-color: #ffa500;
        }

        h2 {
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        /* Popup Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: #2a4b5e;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 32px;
            max-width: 400px;
            text-align: center;
            border: 2px solid #ff4444;
            box-shadow: 0 25px 40px -14px rgba(0, 0, 0, 0.7);
            animation: slideIn 0.3s ease;
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-icon {
            font-size: 4rem;
            color: #ff4444;
            margin-bottom: 1rem;
        }

        .modal h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: #ff4444;
        }

        .modal p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .modal-button {
            background: #ff8c42;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            outline: none;
        }

        .modal-button:hover {
            background: #ffa55c;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px #7b3f00;
        }

        .modal-button:active {
            transform: translateY(2px);
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #e0eef5;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
        }

        .input-field {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: #ffffff20;
            border: 1px solid #ffffff60;
            border-radius: 40px;
            font-size: 1rem;
            outline: none;
            color: white;
            font-weight: 400;
            backdrop-filter: blur(4px);
        }

        .input-field:focus {
            border-color: #ffb347;
            box-shadow: 0 0 0 4px #ffb34740;
            background: #ffffff30;
        }

        .input-field::placeholder {
            color: #c0dae7;
            font-weight: 300;
        }

        /* Password input container */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container .input-field {
            padding-right: 3rem; /* Make space for the eye icon */
        }

        .toggle-password {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ffffff90;
            font-size: 1.2rem;
            transition: color 0.2s;
            z-index: 2;
        }

        .toggle-password:hover {
            color: #ffb347;
        }

        .pw-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0 2rem 0;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: white;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            accent-color: #ffb347;
            width: 1.1rem;
            height: 1.1rem;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 0.9rem;
            color: #ffd49f;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid #ffb34780;
            padding-bottom: 1px;
        }

        .forgot-link:hover {
            color: #ffb347;
            border-bottom-color: #ffb347;
        }

        .login-btn {
            background: #ff8c42;
            border: none;
            width: 100%;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            letter-spacing: 0.5px;
            cursor: pointer;
            box-shadow: 0 10px 20px -8px #7b3f00;
            transition: all 0.2s;
            text-transform: uppercase;
        }

        .login-btn:hover {
            background: #ffa55c;
            box-shadow: 0 16px 30px -8px #aa5500;
            transform: translateY(-3px);
        }

        .login-btn:active {
            background: #e6732e;
            transform: translateY(2px);
            box-shadow: 0 5px 16px -4px #662200;
        }

        @media (max-width: 860px) {
            body {
                flex-direction: column;
                justify-content: center;
                gap: 1.5rem;
                padding: 1.5rem;
                overflow-y: auto;
            }
            .welcome-message {
                margin-right: 0;
                text-align: center;

            }
            .welcome-message p {
                border-left: none;
                padding-left: 0;
                border-top: 3px solid white;
                padding-top: 0.8rem;
                width: fit-content;
                margin: 0 auto;

            }
            .login-card {
                max-width: 100%;
            }
            
            .modal-content {
                margin: 50% auto;
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <!-- Error Popup Modal -->
    <div id="errorModal" class="modal" style="<?= $show_popup ? 'display:block;' : '' ?>">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3>Error</h3>
            <p><?= htmlspecialchars($error) ?></p>
            <button class="modal-button" onclick="closeModal()">OK</button>
        </div>
    </div>

    <div class="welcome-message" >
        <h1>WELCOME TO<br>OCSR INVENTORY</h1>
        <p>system ready · please log in</p>
    </div>

    <div class="login-card" role="main">
        <div class="logo-container">
            <img src="/inventory-system-main/img/sunset.png" alt="OCSR Sunset Logo" 
                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%232a4b5e\' stroke=\'%23ffb347\' stroke-width=\'4\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'40\' text-anchor=\'middle\' fill=\'%23ffb347\'%3E🌅%3C/text%3E%3C/svg%3E';">
        </div>

        <h2>Log In Into<br>Your Account</h2>

        <form method="post" action="" id="loginForm">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" class="input-field" id="username" name="username" 
                       placeholder="Enter your username" autocomplete="username" 
                       required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" class="input-field" id="password" name="password" 
                           placeholder="Enter your password" autocomplete="current-password" 
                           required>
                    <i class="fas fa-eye toggle-password" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                </div>
            </div>

            <div class="pw-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_user']) ? 'checked' : '' ?>> 
                    <span>Remember me</span>
                </label>
                <a href="#" class="forgot-link" onclick="alert('Please contact admin to reset password'); return false;">Forgot Password?</a>
            </div>
            <!-- Add after the LOGIN button or before closing the form -->
            <div style="text-align: center; margin-top: 0; margin-bottom: 0.5rem;">
                <span style="color: #e0eef5;">Don't have an account?</span> 
                <a href="signup.php" style="color: #ffb347; text-decoration: none; font-weight: 600; border-bottom: 1px solid #ffb34780; padding-bottom: 1px;">Sign Up</a>
            </div>  

            <button type="submit" class="login-btn">LOGIN</button>
        </form>
    </div>

    <script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePassword');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    function closeModal() {
        document.getElementById('errorModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('errorModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Auto-show modal if there's an error (PHP will handle this with inline style)
    <?php if ($show_popup): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('errorModal').style.display = 'block';
    });
    <?php endif; ?>
    </script>
</body>
</html>