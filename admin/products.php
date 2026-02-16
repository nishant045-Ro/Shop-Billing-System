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
                $category_id = $_POST['category_id'];
                $price = $_POST['price'];
                $stock = $_POST['stock'];
                
                if (empty($name) || empty($price) || empty($stock)) {
                    $error = 'Please fill all required fields';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, stock) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$name, $category_id, $price, $stock])) {
                        $message = 'Product added successfully';
                    } else {
                        $error = 'Failed to add product';
                    }
                }
                break;
                
            case 'update':
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $category_id = $_POST['category_id'];
                $price = $_POST['price'];
                $stock = $_POST['stock'];
                
                if (empty($name) || empty($price) || empty($stock)) {
                    $error = 'Please fill all required fields';
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, stock = ? WHERE id = ?");
                    if ($stmt->execute([$name, $category_id, $price, $stock, $id])) {
                        $message = 'Product updated successfully';
                    } else {
                        $error = 'Failed to update product';
                    }
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Product deleted successfully';
                } else {
                    $error = 'Failed to delete product';
                }
                break;
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get products with category names
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name
");
$products = $stmt->fetchAll();

// Quick stats
$totalProducts = count($products);
$categoryCount = count($categories);
$lowStockProducts = [];
$totalStock = 0;
$sumPrice = 0;

foreach ($products as $p) {
    $totalStock += (int)($p['stock'] ?? 0);
    $sumPrice += (float)($p['price'] ?? 0);
    if ((int)$p['stock'] < 10) {
        $lowStockProducts[] = $p;
    }
}

$avgPrice = $totalProducts > 0 ? $sumPrice / $totalProducts : 0;
$lowStockCount = count($lowStockProducts);

// Get product for editing
$editProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProduct = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --indigo-500: #4f46e5;
            --indigo-400: #6366f1;
            --purple-500: #7c3aed;
            --teal-400: #14b8a6;
            --amber-400: #f59e0b;
            --rose-500: #f43f5e;
            --slate-900: #0f172a;
        }

        body.admin-dashboard {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.06), transparent 30%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.06), transparent 28%),
                        #f2f4f8;
        }

        .admin-dashboard .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }

        .admin-dashboard .hero-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }

        .admin-dashboard .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .admin-dashboard .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.14);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-dashboard .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        .mini-icon.products { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.low { background: linear-gradient(135deg, #f97316, #f59e0b); }
        .mini-icon.stock { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.price { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }

        .admin-dashboard .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .admin-dashboard .filters-row input,
        .admin-dashboard .filters-row select {
            height: 42px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0 12px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .admin-dashboard .filters-row input:focus,
        .admin-dashboard .filters-row select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }

        .admin-dashboard .badge-soft {
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }
        .badge-soft.success { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .badge-soft.warning { background: rgba(234, 179, 8, 0.14); color: #92400e; }
        .badge-soft.danger { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }

        .admin-dashboard .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
            font-weight: 600;
        }

        .admin-dashboard .table.modern th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .admin-dashboard .table.modern tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(20, 184, 166, 0.05));
        }

        .admin-dashboard .helper {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: -4px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body class="admin-dashboard">
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
                <a href="products.php" class="nav-item active">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="categories.php" class="nav-item">
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
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Catalog Control</p>
                    <h1>Products Management</h1>
                    <p class="subhead">Add, edit, and monitor inventory health.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Products</div>
                    <div class="metric-value"><?php echo number_format($totalProducts); ?></div>
                    <div class="metric-sub"><?php echo $categoryCount; ?> categories • <?php echo $totalStock; ?> units</div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon products"><i class="fas fa-box-open"></i></span>
                    <div>
                        <div class="summary-label">Products</div>
                        <div class="summary-value"><?php echo number_format($totalProducts); ?></div>
                        <span class="subline">Across <?php echo $categoryCount; ?> categories</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon low"><i class="fas fa-exclamation-triangle"></i></span>
                    <div>
                        <div class="summary-label">Low Stock</div>
                        <div class="summary-value"><?php echo $lowStockCount; ?></div>
                        <span class="subline"><strong><?php echo $lowStockCount; ?></strong> need restock</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon stock"><i class="fas fa-warehouse"></i></span>
                    <div>
                        <div class="summary-label">Total Units</div>
                        <div class="summary-value"><?php echo number_format($totalStock); ?></div>
                        <span class="subline">On-hand quantity</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon price"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="summary-label">Avg. Price</div>
                        <div class="summary-value">Rs.<?php echo number_format($avgPrice, 2); ?></div>
                        <span class="subline">Avg per item</span>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add/Edit Product Form -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus"></i> 
                        <?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?>
                    </h3>
                    <p class="helper">Tip: keep stock updated to maintain accurate low-stock alerts.</p>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'add'; ?>">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($editProduct && $editProduct['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="helper">Organize products for better reporting.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (Rs.) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" 
                               value="<?php echo $editProduct ? $editProduct['price'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock">Stock Quantity *</label>
                        <input type="text" id="stock" name="stock" min="0" 
                               value="<?php echo $editProduct ? $editProduct['stock'] : ''; ?>" 
                               required>
                        <small class="helper">Mark low stock under 10 units.</small>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $editProduct ? 'Update Product' : 'Add Product'; ?>
                        </button>
                        
                        <?php if ($editProduct): ?>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Products List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Products</h3>
                </div>

                <div class="filters-row">
                    <input type="text" id="productSearch" placeholder="Search by name or category...">
                    <select id="categoryFilter">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo strtolower($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="stockFilter">
                        <option value="">All stock levels</option>
                        <option value="low">Low (&lt; 10)</option>
                        <option value="ok">10 - 50</option>
                        <option value="high">50+</option>
                    </select>
                </div>

                <div class="table-container">
                    <table class="table modern interactive-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <?php 
                                $stock = (int)$product['stock'];
                                if ($stock < 5) { $stockClass = 'danger'; $stockLabel = 'Critical'; }
                                elseif ($stock < 10) { $stockClass = 'warning'; $stockLabel = 'Low'; }
                                elseif ($stock < 50) { $stockClass = 'success'; $stockLabel = 'Healthy'; }
                                else { $stockClass = 'success'; $stockLabel = 'Plentiful'; }
                            ?>
                            <tr data-name="<?php echo strtolower($product['name']); ?>" 
                                data-category="<?php echo strtolower($product['category_name'] ?? 'uncategorized'); ?>"
                                data-stock="<?php echo $stock; ?>">
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>
                                    <span class="category-pill">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td>Rs.<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $stock; ?></td>
                                <td>
                                    <span class="badge-soft <?php echo $stockClass; ?>">
                                        <i class="fas fa-circle"></i> <?php echo $stockLabel; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this product?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-circle"></i> Low Stock Watch</h3>
                    <span class="helper">Items under 10 units are highlighted below.</span>
                </div>
                <div class="table-container">
                    <?php if ($lowStockCount > 0): ?>
                        <table class="table modern interactive-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="badge-soft danger"><i class="fas fa-arrow-down"></i> <?php echo $product['stock']; ?></span>
                                    </td>
                                    <td>Rs.<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Update
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:#16a34a;"></i>
                            <p>All products are healthy. Great job keeping stock updated!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    // Client-side filters
    const searchInput = document.getElementById('productSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const tableRows = Array.from(document.querySelectorAll('#productsTable tbody tr'));

    function applyFilters() {
        const term = (searchInput?.value || '').toLowerCase();
        const cat = (categoryFilter?.value || '').toLowerCase();
        const stockSel = stockFilter?.value || '';

        tableRows.forEach(row => {
            const name = row.dataset.name || '';
            const category = row.dataset.category || '';
            const stock = parseInt(row.dataset.stock || '0', 10);

            const matchTerm = term === '' || name.includes(term) || category.includes(term);
            const matchCat = cat === '' || category === cat;
            let matchStock = true;
            if (stockSel === 'low') matchStock = stock < 10;
            else if (stockSel === 'ok') matchStock = stock >= 10 && stock < 50;
            else if (stockSel === 'high') matchStock = stock >= 50;

            row.style.display = (matchTerm && matchCat && matchStock) ? '' : 'none';
        });
    }

    [searchInput, categoryFilter, stockFilter].forEach(el => {
        if (el) el.addEventListener('input', applyFilters);
        if (el) el.addEventListener('change', applyFilters);
    });
</script>
</html>
