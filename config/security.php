<?php
/**
 * Security Configuration
 * 
 * Centralized security settings and utility functions
 */

// CSRF Token Management
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

function checkRateLimit(PDO $pdo, string $identifier): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'login_attempts_' . md5($ip . $identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'last_attempt' => time(),
            'locked_until' => 0
        ];
    }
    
    $attempts = &$_SESSION[$key];
    
    // Check if still locked
    if ($attempts['locked_until'] > time()) {
        $remaining = $attempts['locked_until'] - time();
        return [
            'allowed' => false,
            'message' => 'Too many login attempts. Please try again in ' . ceil($remaining / 60) . ' minutes.',
            'remaining' => $remaining
        ];
    }
    
    // Reset if lockout period expired
    if ($attempts['locked_until'] > 0 && $attempts['locked_until'] < time()) {
        $attempts['count'] = 0;
        $attempts['locked_until'] = 0;
    }
    
    // Check if exceeded max attempts
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        $attempts['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
        return [
            'allowed' => false,
            'message' => 'Too many failed login attempts. Account temporarily locked for 15 minutes.',
            'remaining' => LOGIN_LOCKOUT_TIME
        ];
    }
    
    return ['allowed' => true];
}

function recordFailedAttempt(string $identifier): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'login_attempts_' . md5($ip . $identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'last_attempt' => time(),
            'locked_until' => 0
        ];
    }
    
    $_SESSION[$key]['count']++;
    $_SESSION[$key]['last_attempt'] = time();
}

function clearLoginAttempts(string $identifier): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'login_attempts_' . md5($ip . $identifier);
    unset($_SESSION[$key]);
}

// Input Sanitization
function sanitizeInput(string $input): string {
    // Trim and remove slashes only; do NOT HTML-escape here so we store raw username as provided.
    // Always escape when rendering output with htmlspecialchars to prevent XSS.
    $input = trim($input);
    $input = stripslashes($input);
    return $input;
}

function validateUsername(string $username): bool {
    // Allow any non-empty username up to 255 characters to let users choose freely.
    $len = strlen($username);
    return ($len > 0 && $len <= 255);
} 

function validatePassword(string $password): bool {
    // Password: at least 6 characters
    return strlen($password) >= 6 && strlen($password) <= 128;
}

// Session Security Configuration (call BEFORE session_start)
function configureSecureSession(): void {
    // Only set ini settings if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
    }
}

// Session Security Maintenance (call AFTER session_start)
function secureSession(): void {
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Security Headers
function setSecurityHeaders(): void {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Activity Logging
function logLoginAttempt(PDO $pdo, string $username, bool $success, ?string $ip = null): void {
    try {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Note: You may want to create a login_logs table for production
        // For now, we'll just log to session or error log
        error_log(sprintf(
            "Login attempt: username=%s, success=%s, ip=%s, time=%s",
            $username,
            $success ? 'yes' : 'no',
            $ip,
            date('Y-m-d H:i:s')
        ));
    } catch (Exception $e) {
        // Silently fail logging to not expose errors
    }
}
?>
