<?php
// Start session
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once '../app/config/database.php';
$db = Database::connect();

// Handle signup form submission
$error = '';
$success = '';
$showPopup = false;
$popupMessage = '';
$popupType = ''; // 'error' or 'success'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
        $showPopup = true;
        $popupMessage = 'Please fill in all fields';
        $popupType = 'error';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
        $showPopup = true;
        $popupMessage = 'Password must be at least 8 characters long';
        $popupType = 'error';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter';
        $showPopup = true;
        $popupMessage = 'Password must contain at least one uppercase letter';
        $popupType = 'error';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number';
        $showPopup = true;
        $popupMessage = 'Password must contain at least one number';
        $popupType = 'error';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one special character';
        $showPopup = true;
        $popupMessage = 'Password must contain at least one special character';
        $popupType = 'error';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
        $showPopup = true;
        $popupMessage = 'Passwords do not match';
        $popupType = 'error';
    } else {
        try {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE name = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists';
                $showPopup = true;
                $popupMessage = 'Username already exists';
                $popupType = 'error';
            } else {
                // Insert new user (using plain password as per your existing system)
                // Note: In production, you should hash passwords! This follows your current pattern
                $stmt = $db->prepare("INSERT INTO users (name, pass) VALUES (?, ?)");
                if ($stmt->execute([$username, $password])) {
                    $success = 'Account created successfully! You can now log in.';
                    $showPopup = true;
                    $popupMessage = 'Account created successfully! You can now log in.';
                    $popupType = 'success';
                    // Clear form
                    $_POST = array();
                } else {
                    $error = 'Failed to create account. Please try again.';
                    $showPopup = true;
                    $popupMessage = 'Failed to create account. Please try again.';
                    $popupType = 'error';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            $showPopup = true;
            $popupMessage = 'Database error: ' . $e->getMessage();
            $popupType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - OCSR Inventory System</title>
    <link rel="icon" type="image/png" href="../img/sunset2.png">
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
            padding: 3rem 4rem;
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

        .signup-card {
            background: #2a4b5e;
            width: 100%;
            height: 115%;
            max-width: 480px;
            padding: 0.5rem 2.5rem;
            border-radius: 32px;
            box-shadow: 0 25px 40px -14px rgba(0, 0, 0, 0.5);
            border: 1px solid #3f6b7e;
            position: relative;
            z-index: 1;
            color: white;
            transition: transform 0.25s ease;
        }

        .signup-card:hover {
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
            padding: 3px;
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
            margin-bottom: 0.3rem;
            font-weight: 700;
            text-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        .input-group {
            margin-bottom: 0.75rem;
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
            padding: 0.7rem 1.2rem;
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

        .password-requirements {
            background: #1e3a47;
            border-radius: 12px;
            padding: 0.2rem;
            margin: 0.5rem 0 1.5rem 0;
            border-left: 4px solid #ffb347;
        }

        .requirements-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffb347;
            margin-bottom: 0rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .requirement-item {
            font-size: 0.85rem;
            color: #c0dae7;
            margin-bottom: 0rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirement-item::before {
            content: "•";
            color: #ffb347;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .signup-btn {
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

        .signup-btn:hover {
            background: #ffa55c;
            box-shadow: 0 16px 30px -8px #aa5500;
            transform: translateY(-3px);
        }

        .signup-btn:active {
            background: #e6732e;
            transform: translateY(2px);
            box-shadow: 0 5px 16px -4px #662200;
        }

        .login-link {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #ffb347;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Popup styles */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: #2a4b5e;
            padding: 2rem;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            border: 2px solid;
            animation: popupSlide 0.3s ease;
            position: relative;
        }

        .popup-content.error {
            border-color: #ff4444;
            box-shadow: 0 10px 30px rgba(255, 68, 68, 0.3);
        }

        .popup-content.success {
            border-color: #00C851;
            box-shadow: 0 10px 30px rgba(0, 200, 81, 0.3);
        }

        @keyframes popupSlide {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .popup-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .popup-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }

        .popup-message {
            color: #e0eef5;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .popup-close {
            background: #ff8c42;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .popup-close:hover {
            background: #ffa55c;
            transform: translateY(-2px);
        }

        .popup-close:active {
            transform: translateY(0);
        }

        .popup-close.error {
            background: #ff4444;
        }

        .popup-close.error:hover {
            background: #ff6666;
        }

        .popup-close.success {
            background: #00C851;
        }

        .popup-close.success:hover {
            background: #00e676;
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
            .signup-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-message">
        <h1>WELCOME TO<br>OCSR INVENTORY</h1>
        <p>create your account · get started</p>
    </div>

    <div class="signup-card" role="main">
        <div class="logo-container">
            <img src="/inventory-system-main/img/sunset.png" alt="OCSR Sunset Logo" 
                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%232a4b5e\' stroke=\'%23ffb347\' stroke-width=\'4\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'40\' text-anchor=\'middle\' fill=\'%23ffb347\'%3E🌅%3C/text%3E%3C/svg%3E';">
        </div>

        <h2>Create Account</h2>

        <form method="post" action="">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" class="input-field" id="username" name="username" 
                       placeholder="Choose a username" autocomplete="username" 
                       required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" class="input-field" id="password" name="password" 
                           placeholder="Create a password" autocomplete="new-password" 
                           required>
                    <i class="fas fa-eye toggle-password" id="togglePassword" onclick="togglePasswordVisibility('password', this)"></i>
                </div>
            </div>

            <div class="password-requirements">
                <div class="requirements-title">Password Requirements:</div>
                <div class="requirement-item">Minimum 8 characters long</div>
                <div class="requirement-item">At least one uppercase letter (A-Z)</div>
                <div class="requirement-item">At least one number (0-9)</div>
                <div class="requirement-item">At least one special character (!@#$%^&*)</div>
            </div>

            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-container">
                    <input type="password" class="input-field" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm your password" autocomplete="new-password" 
                           required>
                    <i class="fas fa-eye toggle-password" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                </div>
            </div>

            <button type="submit" class="signup-btn">SIGN UP</button>
            
            <div class="login-link">
                Already have an account? <a href="log_in.php">Log In</a>
            </div>
        </form>
    </div>

    <!-- Popup Modal -->
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-content <?= $popupType ?>" id="popupContent">
            <div class="popup-icon" id="popupIcon">
                <?php if ($popupType === 'error'): ?>
                    ❌
                <?php elseif ($popupType === 'success'): ?>
                    ✅
                <?php endif; ?>
            </div>
            <div class="popup-title" id="popupTitle">
                <?= $popupType === 'error' ? 'Error!' : 'Success!' ?>
            </div>
            <div class="popup-message" id="popupMessage">
                <?= htmlspecialchars($popupMessage) ?>
            </div>
            <button class="popup-close <?= $popupType ?>" onclick="closePopup()">OK</button>
        </div>
    </div>

    <script>
        // Toggle password visibility function
        function togglePasswordVisibility(inputId, iconElement) {
            const passwordInput = document.getElementById(inputId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

        // Show popup if there's a message
        <?php if ($showPopup): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showPopup();
        });
        <?php endif; ?>

        function showPopup() {
            document.getElementById('popupOverlay').style.display = 'flex';
        }

        function closePopup() {
            document.getElementById('popupOverlay').style.display = 'none';
            
            // Redirect to login page after successful signup
            <?php if ($popupType === 'success'): ?>
            window.location.href = 'log_in.php';
            <?php endif; ?>
        }

        // Close popup when clicking outside
        window.onclick = function(event) {
            var popupOverlay = document.getElementById('popupOverlay');
            if (event.target == popupOverlay) {
                closePopup();
            }
        }

        // Close popup with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePopup();
            }
        });
    </script>
</body>
</html>