<?php
/**
 * Debug script to check users in database
 * DELETE THIS FILE AFTER DEBUGGING FOR SECURITY
 */
require_once 'config/database.php';

echo "<h2>Users in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th></tr>";

try {
    $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<tr><td colspan='4'>No users found in database!</td></tr>";
    } else {
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
    }
} catch (PDOException $e) {
    echo "<tr><td colspan='4'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
}

echo "</table>";

echo "<br><br>";
echo "<strong>Default Admin Credentials:</strong><br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";

echo "<br><br>";
echo "<a href='login.php'>Go to Login</a> | <a href='register.php'>Register New User</a>";
?>

