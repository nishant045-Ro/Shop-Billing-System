<?php
session_start();
require_once 'config/database.php';
require_once 'config/security.php';

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
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Sanitize input (trim and stripslashes only)
    $username = sanitizeInput($username);
    $email = trim($_POST['email'] ?? '');
    $email = sanitizeInput($email);

    // Validate inputs (username can be any non-empty value up to 255 chars)
    if ($username === '' || $password === '' || $confirm === '' || $email === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $error = 'Please enter a valid email address (max 255 characters).';
    } elseif (!validateUsername($username)) {
        $error = 'Invalid username. Please enter a username (1-255 characters).';
    } elseif (!validatePassword($password)) {
        $error = 'Invalid password format. Password must be 6-128 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Case-insensitive username uniqueness check
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username is already taken.';
            } else {
                // Check email uniqueness
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email is already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(16));
                    $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
                    // New members get "cashier" role by default, email not verified yet
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, verify_token, verify_expires) VALUES (?, ?, 'cashier', ?, ?, ?)");
                    $stmt->execute([$username, $hash, $email, $token, $expires]);

                    // Send verification email (centralized helper)
                    require_once 'config/mailer.php';
                    $verify_link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/final/verify.php?token=' . $token;
                    $subject = 'Verify your account';
                    $body = "Hi " . htmlspecialchars($username) . ",<br>Please verify your account by clicking the following link: <a href='" . $verify_link . "'>Verify Email</a><br>This link expires in 24 hours.";

                    $sent = sendVerificationEmail($email, $username, $token, $subject, $body);
                    if ($sent) {
                        $message = 'Registration successful! Please check your email to verify your account before logging in.';
                    } else {
                        $message = 'Registration successful! We could not send a verification email automatically - you can <a href="resend_verification.php">resend verification</a> or contact support.';
                    }
                }
            }
        } catch (PDOException $e) {
            // Log the specific DB error for diagnostics without revealing details to user
            error_log("Registration error for username '{$username}': " . $e->getMessage());
            $error = 'An internal error occurred while creating your account. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Shop Billing System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-user-plus"></i>
                <h1>Create New Account</h1>
                <p>Register as a new member</p>
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
                    <input type="text" id="username" name="username" required maxlength="255" title="Enter any username (max 255 characters)" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required maxlength="255" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required minlength="6" maxlength="128">
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="128">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
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
</body>
</html>


