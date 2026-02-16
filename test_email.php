<?php
require_once 'config/mailer.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $subject = 'Test email from Shop Billing System';
        $body = 'This is a test email sent at ' . date('Y-m-d H:i:s');
        $sent = sendEmail($to, $subject, $body);
        $message = $sent ? 'Test email sent (check your inbox or email log).' : 'Failed to send test email (check logs and configuration).';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Send Test Email</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>Send Test Email</h1>
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="to">Destination Email</label>
                    <input type="email" id="to" name="to" required>
                </div>
                <button class="btn btn-primary" type="submit">Send Test</button>
            </form>
        </div>
    </div>
</body>
</html>