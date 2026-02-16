<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($username) || empty($password)) {
                    $error = 'Please fill all required fields';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long';
                } else {
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $error = 'Username already exists';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'cashier')");
                        if ($stmt->execute([$username, $hashed_password])) {
                            $message = 'Cashier account created successfully';
                        } else {
                            $error = 'Failed to create cashier account';
                        }
                    }
                }
                break;
                
            case 'update':
                $id = $_POST['id'];
                $username = trim($_POST['username']);
                $new_password = $_POST['new_password'];
                
                if (empty($username)) {
                    $error = 'Username is required';
                } else {
                    // Check if username already exists for other users
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Username already exists';
                    } else {
                        if (!empty($new_password)) {
                            if (strlen($new_password) < 6) {
                                $error = 'Password must be at least 6 characters long';
                            } else {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                                if ($stmt->execute([$username, $hashed_password, $id])) {
                                    $message = 'User updated successfully';
                                } else {
                                    $error = 'Failed to update user';
                                }
                            }
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                            if ($stmt->execute([$username, $id])) {
                                $message = 'User updated successfully';
                            } else {
                                $error = 'Failed to update user';
                            }
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // Prevent admin from deleting themselves
                if ($id == $_SESSION['user_id']) {
                    $error = 'You cannot delete your own account';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'cashier'");
                    if ($stmt->execute([$id])) {
                        $message = 'User deleted successfully';
                    } else {
                        $error = 'Failed to delete user';
                    }
                }
                break;
                
            case 'reset_password':
                $id = $_POST['id'];
                $new_password = 'cashier123'; // Default password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'cashier'");
                if ($stmt->execute([$hashed_password, $id])) {
                    $message = 'Password reset successfully. New password: ' . $new_password;
                } else {
                    $error = 'Failed to reset password';
                }
                break;
        }
    }
}

// Get all cashier users (including optional shop location)
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'cashier' ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get user for editing
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'cashier'");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-tools"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="products.php" class="nav-item">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="categories.php" class="nav-item">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i class="fas fa-truck"></i> Suppliers
                </a>
                <a href="users.php" class="nav-item active">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <a href="backup.php" class="nav-item">
                    <i class="fas fa-database"></i> Backup
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Team Control</p>
                    <h1>Users Management</h1>
                    <p class="subhead">Create, edit, and secure cashier accounts with a cleaner, modern view.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Cashiers</div>
                    <div class="metric-value"><?php echo count($users); ?></div>
                    <div class="metric-sub">Active on your system</div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add/Edit User Form -->
            <div class="card elevated">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-user-plus"></i> 
                        <?php echo $editUser ? 'Edit Cashier Account' : 'Create New Cashier Account'; ?>
                    </h3>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'add'; ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" 
                               required>
                    </div>
                    
                    <?php if (!$editUser): ?>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="new_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="new_password" name="new_password" 
                                   minlength="6">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $editUser ? 'Update User' : 'Create User'; ?>
                        </button>
                        
                        <?php if ($editUser): ?>
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> All Cashier Users</h3>
                    </div>
                    <div class="table-container">
                        <table class="table modern">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Store Image</th>
                                    <th>Shop Location</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #666;">
                                            No cashier users found. Create the first one above.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                    <?php $storeAvatar = $user['store_image'] ? '../' . htmlspecialchars($user['store_image']) : '../assets/img/store-placeholder.svg'; ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="customer-cell">
                                                <span class="avatar" style="background-image:url('<?php echo $storeAvatar; ?>');"></span>
                                                <div class="customer-meta">
                                                    <div class="customer-name"><?php echo $user['store_image'] ? 'Uploaded' : 'Not set'; ?></div>
                                                    <div class="customer-sub">Store image</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['shop_lat']) && !empty($user['shop_lng'])): ?>
                                                <?php
                                                    $lat = (float)$user['shop_lat'];
                                                    $lng = (float)$user['shop_lng'];
                                                    $mapsBase = 'https://www.google.com/maps';
                                                    $mapsUrl = $mapsBase . '?q=' . $lat . ',' . $lng;
                                                ?>
                                                <div class="customer-meta">
                                                    <div class="customer-name">
                                                        <?php echo number_format($lat, 5); ?>,
                                                        <?php echo number_format($lng, 5); ?>
                                                    </div>
                                                    <div class="customer-sub">
                                                        <a href="<?php echo htmlspecialchars($mapsUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                            Open in Google Maps
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#9ca3af;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Reset password to default (cashier123)?')">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-info btn-sm">
                                                        <i class="fas fa-key"></i> Reset Password
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- Information Card -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Important Information</h3>
                </div>
                <div class="info-content">
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-shield-alt" style="color: #27ae60; margin-right: 10px;"></i>
                            <strong>Default Password:</strong> When you reset a password, it will be set to "cashier123"
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-exclamation-triangle" style="color: #f39c12; margin-right: 10px;"></i>
                            <strong>Security:</strong> Users should change their password after first login
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-user-lock" style="color: #e74c3c; margin-right: 10px;"></i>
                            <strong>Role:</strong> All users created here will have "cashier" role by default
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
