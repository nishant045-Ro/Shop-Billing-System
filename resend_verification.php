<?php
session_start();
require_once 'config/database.php';
require_once 'config/mailer.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email_verified FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'No account found for that email address.';
            } elseif (!empty($user['email_verified'])) {
                $message = 'Your email is already verified. You can <a href="index.php">login</a> now.';
            } else {
                // Generate new token and expiry
                $token = bin2hex(random_bytes(16));
                $expires = date('Y-m-d H:i:s', time() + 86400);
                $stmt = $pdo->prepare('UPDATE users SET verify_token = ?, verify_expires = ? WHERE id = ?');
                $stmt->execute([$token, $expires, $user['id']]);

                $verify_link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/final/verify.php?token=' . $token;
                $subject = 'Resend: Verify your account';
                $body = "Hi " . htmlspecialchars($user['username']) . ",<br>Please verify your account by clicking the following link: <a href='" . $verify_link . "'>Verify Email</a><br>This link expires in 24 hours.";

                $sent = sendVerificationEmail($email, $user['username'], $token, $subject, $body);
                if ($sent) {
                    $message = 'Verification email resent. Please check your inbox (or email log).';
                } else {
                    $error = 'Failed to send verification email. Check email configuration or use the email log.';
                }
            }
        } catch (PDOException $e) {
            error_log('Resend verification error: ' . $e->getMessage());
            $error = 'Internal error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resend Verification</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Resend Verification Email</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button class="btn btn-primary" type="submit">Resend Verification</button>
            </form>
            <div style="margin-top: 1rem;">
                <a href="index.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>