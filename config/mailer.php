<?php
require_once __DIR__ . '/email.php';

/**
 * sendEmail: attempts to send an email using PHPMailer (if available) or PHP mail()
 * returns true on success, false on failure (but logs when logging enabled)
 */
function sendEmail(string $to, string $subject, string $body): bool {
    $sent = false;

    // Ensure logs directory exists if logging enabled
    if (defined('ENABLE_EMAIL_LOGGING') && ENABLE_EMAIL_LOGGING) {
        $logDir = dirname(EMAIL_LOG_PATH);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    // Try PHPMailer if installed via Composer
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = EMAIL_SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = EMAIL_SMTP_USER;
            $mail->Password = EMAIL_SMTP_PASS;
            $mail->SMTPSecure = EMAIL_SMTP_SECURE;
            $mail->Port = EMAIL_SMTP_PORT;
            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            error_log("PHPMailer send error: " . $e->getMessage());
            $sent = false;
        }
    } else {
        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
        $result = @mail($to, $subject, $body, $headers);
        $sent = (bool)$result;
    }

    // If logging enabled, append email to log for debugging (do not expose in production)
    if (defined('ENABLE_EMAIL_LOGGING') && ENABLE_EMAIL_LOGGING) {
        $logEntry = sprintf("[%s] To: %s\nSubject: %s\nBody:\n%s\n----\n", date('Y-m-d H:i:s'), $to, $subject, $body);
        @file_put_contents(EMAIL_LOG_PATH, $logEntry, FILE_APPEND | LOCK_EX);
    }

    return $sent;
}

/**
 * sendVerificationEmail: convenience wrapper to send verification emails
 */
function sendVerificationEmail(string $email, string $username, string $token, string $subject, string $body): bool {
    // For compatibility we pass $body through as-is
    return sendEmail($email, $subject, $body);
}
?>