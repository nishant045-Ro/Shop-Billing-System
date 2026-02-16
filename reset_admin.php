<?php
// Temporary helper to regain access. Delete this file after use.
require_once 'config/database.php';

$username = $_GET['user'] ?? 'admin';
$newPassword = $_GET['pass'] ?? 'admin123';

$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Create admin if missing, otherwise reset password
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE username = :u LIMIT 1");
$stmt->execute(['u' => $username]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:u, :p, 'admin')");
    $stmt->execute(['u' => $username, 'p' => $hash]);
    $msg = "Created user '$username' with password '$newPassword'.";
} else {
    $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE username = :u");
    $stmt->execute(['p' => $hash, 'u' => $username]);
    $msg = "Reset password for '$username' to '$newPassword'.";
}

echo htmlspecialchars($msg);
echo "<br>Please delete 'reset_admin.php' after logging in.";

