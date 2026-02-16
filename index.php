<?php
/**
 * Login Page - Enhanced with Security Features
 * 
 * Features:
 * - CSRF Protection
 * - Rate Limiting
 * - Input Validation & Sanitization
 * - Enhanced Session Security
 * - Login Attempt Tracking
 * - Security Headers
 */

require_once 'config/database.php';
require_once 'config/security.php';

// Configure secure session settings BEFORE starting session
configureSecureSession();

session_start();

// Set security headers
setSecurityHeaders();

// Secure session maintenance (regenerate ID if needed)
secureSession();

// Ensure a default admin exists so first login always works
if (!function_exists('ensureDefaultAdmin')) {
    function ensureDefaultAdmin(PDO $pdo): void {
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($count === 0) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', :pwd, 'admin')");
                $stmt->execute(['pwd' => $hash]);
            }
        } catch (PDOException $e) {
            error_log("Error ensuring default admin: " . $e->getMessage());
        }
    }
}
ensureDefaultAdmin($pdo);

// Determine store name for branding
$storeName = 'Shop Billing System';
try {
    $stmt = $pdo->query("SELECT username FROM users WHERE role = 'admin' LIMIT 1");
    $adminRow = $stmt->fetch();
    if ($adminRow && !empty($adminRow['username'])) {
        $storeName = $adminRow['username'];
    }
} catch (PDOException $e) {
    // ignore
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Verify session is still valid by checking user exists
    try {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: cashier/dashboard.php');
            }
            exit();
        } else {
            // User no longer exists, destroy session
            session_destroy();
        }
    } catch (PDOException $e) {
        error_log("Error checking session: " . $e->getMessage());
        session_destroy();
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

$error = '';
$success = '';
$rateLimitInfo = null;

// Show registration success message when redirected from register.php
if (isset($_GET['registered'])) {
    $success = 'Registration successful. You can now log in.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Sanitize input
        $username = sanitizeInput($username);
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } elseif (!validateUsername($username)) {
            $error = 'Invalid username. Please enter your username (1-255 characters).';
        } elseif (!validatePassword($password)) {
            $error = 'Invalid password format. Password must be 6-128 characters.';
        } else {
            // Check rate limiting
            $rateLimitCheck = checkRateLimit($pdo, $username);
            if (!$rateLimitCheck['allowed']) {
                $error = $rateLimitCheck['message'];
                $rateLimitInfo = $rateLimitCheck;
                logLoginAttempt($pdo, $username, false);
            } else {
                // Attempt login
                try {
                    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Login successful
                        clearLoginAttempts($username);
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        logLoginAttempt($pdo, $username, true);
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header('Location: admin/dashboard.php');
                        } else {
                            header('Location: cashier/dashboard.php');
                        }
                        exit();
                    } else {
                        // Login failed
                        recordFailedAttempt($username);
                        logLoginAttempt($pdo, $username, false);
                        $error = 'Invalid username or password';
                        
                        // Check if locked after this attempt
                        $rateLimitCheck = checkRateLimit($pdo, $username);
                        if (!$rateLimitCheck['allowed']) {
                            $error = $rateLimitCheck['message'];
                            $rateLimitInfo = $rateLimitCheck;
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Database error during login: " . $e->getMessage());
                    $error = 'An error occurred. Please try again later.';
                    logLoginAttempt($pdo, $username, false);
                }
            }
        }
    }
    
    // Regenerate CSRF token after POST to prevent token reuse
    $csrfToken = generateCSRFToken();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Billing System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern login styles */
        body.login-page {
            background: linear-gradient(180deg, #f0f4ff 0%, #f7fbff 40%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
        }
        .auth-wrapper {
            width: 980px;
            max-width: 96%;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 24px;
        }
        .brand-panel {
            background: linear-gradient(135deg, rgba(79,70,229,0.06), rgba(20,184,166,0.04));
            border-radius: 14px;
            padding: 28px;
            display:flex;
            flex-direction:column;
            gap: 18px;
            align-items:flex-start;
            justify-content:center;
            box-shadow: 0 12px 40px rgba(15,23,42,0.04);
        }
        .brand-logo { width:86px;height:86px;border-radius:16px;background:#fff;border:1px solid rgba(0,0,0,0.04);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;color:#4f46e5; }
        .brand-title { font-size:1.5rem; font-weight:700; color:#0f172a; margin:0; }
        .brand-sub { color:#475569; margin:0; }
        .benefits { color:#6b7280; font-size:0.95rem; margin-top:8px; }
        .auth-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 18px 40px rgba(2,6,23,0.06); display:flex; flex-direction:column; gap:14px; }
        .auth-card h3 { margin:0; font-size:1.25rem; color:#0f172a; }
        .form-group input[type="text"], .form-group input[type="password"] { padding: 10px 12px; border-radius:8px; border:1px solid #e6eef6; width:100%; }
        .btn-primary { background: linear-gradient(90deg, #6366f1, #14b8a6); border:none; color:#fff; padding:10px 14px; border-radius:10px; font-weight:700; cursor:pointer; box-shadow: 0 8px 20px rgba(99,102,241,0.12); }
        .form-footer { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:6px; }
        .small-link { color:#6b7280; font-size:0.92rem; text-decoration:none; }
        .alert { padding:10px 12px; border-radius:8px; }
        @media (max-width: 900px) { .auth-wrapper { grid-template-columns: 1fr; } .brand-panel { order: 2; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-shopping-cart"></i>
                <h1>Shop Billing System</h1>
                <p>Login to access your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <?php if ($rateLimitInfo && isset($rateLimitInfo['remaining'])): ?>
                        <div style="margin-top: 8px; font-size: 0.9rem;">
                            <i class="fas fa-clock"></i> Time remaining: <span id="countdown"><?php echo ceil($rateLimitInfo['remaining'] / 60); ?></span> minutes
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        title="Enter your username (max 255 characters)"
                        maxlength="255"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            minlength="6"
                            maxlength="128">
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div style="text-align: right; margin-top: 8px;">
                        <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> <span class="btn-text">Login</span>
                    </button>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </div>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2024 Shop Billing System. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const btnText = loginBtn?.querySelector('.btn-text');

            if (togglePassword && passwordInput && eyeIcon) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle eye icon
                    if (type === 'password') {
                        eyeIcon.classList.remove('fa-eye-slash');
                        eyeIcon.classList.add('fa-eye');
                    } else {
                        eyeIcon.classList.remove('fa-eye');
                        eyeIcon.classList.add('fa-eye-slash');
                    }
                });
            }

            // Form validation and loading state
            if (loginForm && loginBtn) {
                loginForm.addEventListener('submit', function(e) {
                    // HTML5 validation
                    if (!loginForm.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                        loginForm.classList.add('was-validated');
                        return false;
                    }

                    // Show loading state
                    loginBtn.disabled = true;
                    if (btnText) {
                        btnText.textContent = 'Logging in...';
                    }
                    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="btn-text">Logging in...</span>';
                    
                    // Prevent double submission
                    return true;
                });
            }

            // Client-side validation feedback
            const inputs = document.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('invalid');
                        this.classList.add('valid');
                    } else {
                        this.classList.remove('valid');
                        this.classList.add('invalid');
                    }
                });
            });

            // Countdown timer for rate limiting
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                let minutes = parseInt(countdownElement.textContent);
                let seconds = 60;
                
                const countdown = setInterval(function() {
                    seconds--;
                    if (seconds < 0) {
                        minutes--;
                        seconds = 59;
                    }
                    
                    if (minutes < 0) {
                        clearInterval(countdown);
                        countdownElement.textContent = '0';
                        // Optionally reload page when countdown reaches 0
                        // location.reload();
                    } else {
                        countdownElement.textContent = minutes + (seconds > 0 ? ':' + (seconds < 10 ? '0' : '') + seconds : '');
                    }
                }, 1000);
            }

            // Focus first input on load
            const firstInput = document.getElementById('username');
            if (firstInput) {
                firstInput.focus();
            }

            // Prevent autofill from showing password
            setTimeout(function() {
                if (passwordInput && passwordInput.type === 'text') {
                    passwordInput.type = 'password';
                }
            }, 100);
        });
    </script>
</body>
</html>
