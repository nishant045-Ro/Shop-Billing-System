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

// Defaults for list view
$searchQuery = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 12;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $supplier_name = trim($_POST['supplier_name']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                $address = trim($_POST['address']);
                
                if (empty($supplier_name)) {
                    $error = 'Supplier name is required';
                } else {
                    // Check if supplier already exists
                    $stmt = $pdo->prepare("SELECT supplier_id FROM Suppliers WHERE supplier_name = ?");
                    $stmt->execute([$supplier_name]);
                    if ($stmt->fetch()) {
                        $error = 'Supplier already exists';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO Suppliers (supplier_name, phone, email, address) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$supplier_name, $phone, $email, $address])) {
                            $message = 'Supplier added successfully';
                        } else {
                            $error = 'Failed to add supplier';
                        }
                    }
                }
                break;
                
            case 'update':
                $supplier_id = $_POST['supplier_id'];
                $supplier_name = trim($_POST['supplier_name']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                $address = trim($_POST['address']);
                
                if (empty($supplier_name)) {
                    $error = 'Supplier name is required';
                } else {
                    // Check if supplier name already exists for other suppliers
                    $stmt = $pdo->prepare("SELECT supplier_id FROM Suppliers WHERE supplier_name = ? AND supplier_id != ?");
                    $stmt->execute([$supplier_name, $supplier_id]);
                    if ($stmt->fetch()) {
                        $error = 'Supplier name already exists';
                    } else {
                        $stmt = $pdo->prepare("UPDATE Suppliers SET supplier_name = ?, phone = ?, email = ?, address = ? WHERE supplier_id = ?");
                        if ($stmt->execute([$supplier_name, $phone, $email, $address, $supplier_id])) {
                            $message = 'Supplier updated successfully';
                        } else {
                            $error = 'Failed to update supplier';
                        }
                    }
                }
                break;
                
            case 'delete':
                $supplier_id = $_POST['supplier_id'];
                $stmt = $pdo->prepare("DELETE FROM Suppliers WHERE supplier_id = ?");
                if ($stmt->execute([$supplier_id])) {
                    $message = 'Supplier deleted successfully';
                } else {
                    $error = 'Failed to delete supplier';
                }
                break;
        }
    }
}

// Get all suppliers
try {
    $stmt = $pdo->query("SELECT * FROM Suppliers ORDER BY supplier_name");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Suppliers (
                supplier_id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                email VARCHAR(100),
                address VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $suppliers = [];
    } catch (PDOException $ex) {
        $error = 'Unable to load suppliers. Please check database connection.';
        $suppliers = [];
    }
}

// Get supplier for editing
$editSupplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Suppliers WHERE supplier_id = ?");
        $stmt->execute([$_GET['edit']]);
        $editSupplier = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Unable to load supplier for editing.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .supplier-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .supplier-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .supplier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .supplier-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .supplier-name i {
            color: #3498db;
        }
        
        .supplier-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
        }
        
        .info-item i {
            color: #7f8c8d;
            width: 20px;
        }
        
        .info-item strong {
            color: #2c3e50;
            margin-right: 5px;
        }
        
        .empty-suppliers {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-suppliers i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
            display: block;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-box.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-box.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-box.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
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
                <a href="suppliers.php" class="nav-item active">
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
                <h1><i class="fas fa-truck"></i> Suppliers Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-label">Total Suppliers</div>
                    <div class="stat-value"><?php echo count($suppliers); ?></div>
                </div>
                <div class="stat-box success">
                    <div class="stat-label">Active Suppliers</div>
                    <div class="stat-value"><?php echo count($suppliers); ?></div>
                </div>
            </div>

            <!-- Add/Edit Supplier Form -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus"></i> 
                        <?php echo $editSupplier ? 'Edit Supplier' : 'Add New Supplier'; ?>
                    </h3>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="<?php echo $editSupplier ? 'update' : 'add'; ?>">
                    <?php if ($editSupplier): ?>
                        <input type="hidden" name="supplier_id" value="<?php echo $editSupplier['supplier_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="supplier_name">Supplier Name *</label>
                        <input type="text" id="supplier_name" name="supplier_name" 
                               value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['supplier_name']) : ''; ?>" 
                               required placeholder="Enter supplier name">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['phone'] ?? '') : ''; ?>" 
                               placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['email'] ?? '') : ''; ?>" 
                               placeholder="Enter email address">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" 
                                  placeholder="Enter supplier address"><?php echo $editSupplier ? htmlspecialchars($editSupplier['address'] ?? '') : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $editSupplier ? 'Update Supplier' : 'Add Supplier'; ?>
                        </button>
                        
                        <?php if ($editSupplier): ?>
                            <a href="suppliers.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Suppliers List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Suppliers</h3>
                </div>
                <div class="table-container">
                    <?php if (empty($suppliers)): ?>
                        <div class="empty-suppliers">
                            <i class="fas fa-truck"></i>
                            <h3>No Suppliers Found</h3>
                            <p>Start by adding your first supplier above.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; gap: 15px;">
                            <?php foreach ($suppliers as $supplier): ?>
                            <div class="supplier-card">
                                <div class="supplier-header">
                                    <div class="supplier-name">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </div>
                                    <div class="btn-group">
                                        <a href="?edit=<?php echo $supplier['supplier_id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="supplier-info">
                                    <?php if (!empty($supplier['phone'])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-phone"></i>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($supplier['phone']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($supplier['email'])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-envelope"></i>
                                        <strong>Email:</strong> 
                                        <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                            <?php echo htmlspecialchars($supplier['email']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($supplier['address'])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <strong>Address:</strong> <?php echo htmlspecialchars($supplier['address']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-item">
                                        <i class="fas fa-calendar"></i>
                                        <strong>Added:</strong> <?php echo date('M d, Y', strtotime($supplier['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Supplier Management Tips</h3>
                </div>
                <div class="info-content">
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-lightbulb" style="color: #f39c12; margin-right: 10px;"></i>
                            <strong>Organization:</strong> Keep track of all your suppliers for better inventory management
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-phone" style="color: #3498db; margin-right: 10px;"></i>
                            <strong>Contact Info:</strong> Maintain accurate contact information for quick communication
                        </li>
                        <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <i class="fas fa-map-marker-alt" style="color: #e74c3c; margin-right: 10px;"></i>
                            <strong>Address:</strong> Store supplier addresses for delivery and logistics planning
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

