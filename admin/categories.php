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
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (empty($name)) {
                    $error = 'Category name is required';
                } else {
                    // Check if category already exists
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetch()) {
                        $error = 'Category already exists';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                        if ($stmt->execute([$name, $description])) {
                            $message = 'Category added successfully';
                        } else {
                            $error = 'Failed to add category';
                        }
                    }
                }
                break;
                
            case 'update':
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (empty($name)) {
                    $error = 'Category name is required';
                } else {
                    // Check if category name already exists for other categories
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Category name already exists';
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                        if ($stmt->execute([$name, $description, $id])) {
                            $message = 'Category updated successfully';
                        } else {
                            $error = 'Failed to update category';
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // Check if category is being used by products
                $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
                $stmt->execute([$id]);
                $productCount = $stmt->fetch()['product_count'];
                
                if ($productCount > 0) {
                    $error = "Cannot delete category. It is being used by {$productCount} product(s).";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Category deleted successfully';
                    } else {
                        $error = 'Failed to delete category';
                    }
                }
                break;
        }
    }
}

// Get all categories with product counts
$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Get category for editing
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCategory = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - Admin Dashboard</title>
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
                <a href="categories.php" class="nav-item active">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i class="fas fa-truck"></i> Suppliers
                </a>
                <a href="users.php" class="nav-item">
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
            <div class="header">
                <h1>Categories Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add/Edit Category Form -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus"></i> 
                        <?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?>
                    </h3>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'add'; ?>">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" 
                                  placeholder="Optional description for the category"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $editCategory ? 'Update Category' : 'Add Category'; ?>
                        </button>
                        
                        <?php if ($editCategory): ?>
                            <a href="categories.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Categories List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Categories</h3>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Products</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #666;">
                                        No categories found. Create the first one above.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($category['description'] ?: 'No description'); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $category['product_count']; ?> products
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <?php if ($category['product_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this category?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-sm" disabled title="Cannot delete - category has products">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
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
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Category Management Tips</h3>
                </div>
                <div class="info-content">
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-lightbulb" style="color: #f39c12; margin-right: 10px;"></i>
                            <strong>Organization:</strong> Use categories to organize your products logically
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-exclamation-triangle" style="color: #e74c3c; margin-right: 10px;"></i>
                            <strong>Deletion:</strong> Categories with products cannot be deleted. Remove or reassign products first.
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-tags" style="color: #3498db; margin-right: 10px;"></i>
                            <strong>Naming:</strong> Use clear, descriptive names for better product organization
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
