<?php
require_once 'config/database.php';

$username = 'test_user_' . rand(1000, 9999);
$password = 'testPass123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'cashier')");
    $stmt->execute([$username, $hash]);
    echo "Inserted user: $username with password: $password\n";
} catch (PDOException $e) {
    echo "Error inserting user: " . htmlspecialchars($e->getMessage());
}
