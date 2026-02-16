<?php
session_start();
require_once 'config/database.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cashier/dashboard.php');
    }
    exit();
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Username not found.';
        } else {
            // Reset password
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            if ($stmt->execute([$hash, $username])) {
                $message = 'Password reset successfully! You can now login with your new password.';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Shop Billing System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .password-input-wrapper {
            position: relative;
            width: 100%;
        }
        .password-input-wrapper input {
            padding-right: 45px;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            padding: 5px;
            font-size: 1rem;
            transition: color 0.3s ease;
        }
        .toggle-password:hover {
            color: #764ba2;
        }
        .toggle-password:focus {
            outline: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-key"></i>
                <h1>Reset Password</h1>
                <p>Enter your username and set a new password</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                        <button type="button" class="toggle-password" id="togglePassword1" aria-label="Show password">
                            <i class="fas fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                        <button type="button" class="toggle-password" id="togglePassword2" aria-label="Show password">
                            <i class="fas fa-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>

            <div class="login-footer">
                <p>&copy; 2024 Shop Billing System. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Show/Hide Password Toggle for both password fields
        document.addEventListener('DOMContentLoaded', function() {
            function setupToggle(toggleId, inputId, iconId) {
                const toggle = document.getElementById(toggleId);
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);

                if (toggle && input && icon) {
                    toggle.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        if (type === 'password') {
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                        } else {
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                        }
                    });
                }
            }

            setupToggle('togglePassword1', 'new_password', 'eyeIcon1');
            setupToggle('togglePassword2', 'confirm_password', 'eyeIcon2');
        });
    </script>
</body>
</html>

