<?php
session_start();
require_once 'config/database.php';

$message = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    $message = 'Invalid verification link.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT id, username, verify_expires, email_verified FROM users WHERE verify_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = 'Invalid or already used verification link.';
        } else {
            if (!empty($user['email_verified'])) {
                $message = 'Your email is already verified. You can <a href="index.php">login</a>.';
            } elseif (!empty($user['verify_expires']) && strtotime($user['verify_expires']) < time()) {
                $message = 'Verification link expired. Please register again or contact support to resend a verification email.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verify_token = NULL, verify_expires = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                $message = 'Email verified successfully. You can now <a href="index.php">login</a>.';
            }
        }
    } catch (PDOException $e) {
        error_log("Verify error: " . $e->getMessage());
        $message = 'An internal error occurred. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-envelope-open-text"></i>
                <h1>Email Verification</h1>
            </div>

            <div class="alert alert-info" style="margin: 1rem;">
                <?php echo $message; ?>
            </div>

            <div style="text-align: center; margin-top: 1rem;">
                <a href="index.php" class="btn btn-primary">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>