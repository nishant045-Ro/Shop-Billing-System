<?php
// Email configuration - fill these values with your SMTP provider (Mailtrap, SendGrid, Gmail, etc.)
// For local testing consider Mailtrap (https://mailtrap.io) or a local SMTP catcher like MailHog.

// From address and name
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', 'no-reply@yourshop.local');
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', 'Your Shop');

// SMTP settings (example: Mailtrap credentials)
if (!defined('EMAIL_SMTP_HOST')) define('EMAIL_SMTP_HOST', 'smtp.mailtrap.io');
if (!defined('EMAIL_SMTP_USER')) define('EMAIL_SMTP_USER', 'your_mailtrap_user');
if (!defined('EMAIL_SMTP_PASS')) define('EMAIL_SMTP_PASS', 'your_mailtrap_pass');
if (!defined('EMAIL_SMTP_PORT')) define('EMAIL_SMTP_PORT', 2525);
if (!defined('EMAIL_SMTP_SECURE')) define('EMAIL_SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Development helper: when SMTP is not configured or when running locally, enable logging of outgoing emails
if (!defined('ENABLE_EMAIL_LOGGING')) define('ENABLE_EMAIL_LOGGING', true);
if (!defined('EMAIL_LOG_PATH')) define('EMAIL_LOG_PATH', __DIR__ . '/../logs/emails.log');

// Note: For production, store secrets in environment variables or a secured config file and do not commit them to version control.
?>