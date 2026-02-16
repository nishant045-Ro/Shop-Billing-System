<?php
session_start();
// Use same DB connection as rest of app
require_once "config/database.php";

// Ensure a default admin exists so first login always works
if (!function_exists('ensureDefaultAdmin')) {
    function ensureDefaultAdmin(PDO $pdo): void {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', :pwd, 'admin')");
            $stmt->execute(['pwd' => $hash]);
        }
    }
}
ensureDefaultAdmin($pdo);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get and clean input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Debug: Log what we received (remove in production)
    error_log("Login attempt - Username: " . $username . ", Password length: " . strlen($password));

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password!";
    } else {
        try {
            // Check user in database (case-insensitive username check)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    if (isset($user['email_verified']) && !$user['email_verified']) {
                        $message = 'Please verify your email before logging in. Check your inbox for the verification link.';
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];

                    error_log("Login successful - User: " . $user['username'] . ", Role: " . $user['role']);

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } elseif ($user['role'] === 'cashier') {
                        header("Location: cashier/dashboard.php");
                    } else {
                        $message = "Unknown user role: " . htmlspecialchars($user['role']) . ". Please contact administrator.";
                    }
                    exit;
                } else {
                    $message = "Invalid password! Please check your password and try again.";
                    error_log("Login failed - Invalid password for user: " . $username);
                }
            } else {
                $message = "Invalid username! User '" . htmlspecialchars($username) . "' not found. Please check your username or register a new account.";
                error_log("Login failed - User not found: " . $username);
            }
        } catch (PDOException $e) {
            $message = "Database error. Please try again later.";
            error_log("Login database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Billing System - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        /* Remove red border from invalid inputs */
        .form-control:invalid {
            border-color: #ced4da !important;
            box-shadow: none !important;
        }
        
        .form-control:focus:invalid {
            border-color: #80bdff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        }
        
        /* Ensure form can submit */
        #loginForm {
            margin: 0;
        }
        
        .text-danger.small {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-lg p-4" style="width: 400px; border-radius: 12px;">
        <h3 class="text-center mb-3">Login</h3>

        <?php if ($message): ?>
            <div class="alert alert-danger text-center"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" placeholder="Enter username" autocomplete="username">
                <?php if (isset($message) && strpos($message, 'username') !== false): ?>
                    <div class="text-danger small mt-1"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" autocomplete="current-password">
                <?php if (isset($message) && strpos($message, 'password') !== false): ?>
                    <div class="text-danger small mt-1"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <script>
            // Prevent HTML5 validation from blocking form submission
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                var username = document.getElementById('username').value.trim();
                var password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password');
                    return false;
                }
                
                // Remove any validation styling
                document.getElementById('username').classList.remove('is-invalid');
                document.getElementById('password').classList.remove('is-invalid');
                
                return true;
            });
        </script>
        
        <div class="text-center mt-3">
            <a href="register.php" class="text-decoration-none">Don't have an account? Register here</a>
        </div>
    </div>
</div>

</body>
</html>


<!-- #Username:admin -->
 <!-- password:admin123 -->
