<?php
/**
 * Admin Inventory Management
 * 
 * Comprehensive inventory tracking and management system
 * - View all products and stock levels
 * - Adjust stock quantities
 * - Track inventory movements
 * - Monitor low stock alerts
 * - Generate inventory reports
 */

session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// ============================================================================
// HANDLE FORM SUBMISSIONS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // Adjust stock
            case 'adjust_stock':
                $product_id = (int)$_POST['product_id'];
                $adjustment = (int)$_POST['adjustment'];
                $reason = trim($_POST['reason'] ?? '');
                
                if (empty($adjustment) || empty($reason)) {
                    $error = 'Please provide adjustment quantity and reason';
                } else {
                    try {
                        // Get current stock
                        $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch();
                        
                        if (!$product) {
                            $error = 'Product not found';
                        } else {
                            $current = (int)$product['stock'];
                            $new_stock = $current + $adjustment;
                            
                            if ($new_stock < 0) {
                                $error = 'Adjustment would result in negative stock';
                            } else {
                                // Update product stock
                                $updateStmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
                                if ($updateStmt->execute([$new_stock, $product_id])) {
                                    // Log the movement
                                    $logStmt = $pdo->prepare("
                                        INSERT INTO inventory_movements 
                                        (product_id, adjustment, reason, old_stock, new_stock, created_by) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $logStmt->execute([
                                        $product_id, 
                                        $adjustment, 
                                        $reason, 
                                        $current, 
                                        $new_stock, 
                                        $_SESSION['user_id']
                                    ]);
                                    
                                    $message = "Stock adjusted for {$product['name']} (Old: $current → New: $new_stock)";
                                } else {
                                    $error = 'Failed to adjust stock';
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;

            // Bulk stock update
            case 'bulk_update':
                try {
                    $updates = $_POST['stock_updates'] ?? [];
                    $success_count = 0;
                    
                    foreach ($updates as $product_id => $new_stock) {
                        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
                        if ($stmt->execute([(int)$new_stock, (int)$product_id])) {
                            $success_count++;
                        }
                    }
                    
                    if ($success_count > 0) {
                        $message = "Updated stock for $success_count product(s)";
                    } else {
                        $error = 'No products were updated';
                    }
                } catch (PDOException $e) {
                    $error = 'Bulk update failed: ' . $e->getMessage();
                }
                break;
        }
    }
}

// ============================================================================
// CREATE inventory_movements TABLE IF NOT EXISTS
// ============================================================================
try {
    $checkTable = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'inventory_movements'
    ");
    $checkTable->execute();
    $hasMovementsTable = (int)$checkTable->fetchColumn() === 1;
    
    if (!$hasMovementsTable) {
        $pdo->exec("
            CREATE TABLE inventory_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                adjustment INT NOT NULL,
                reason VARCHAR(255),
                old_stock INT,
                new_stock INT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX (product_id),
                INDEX (created_at)
            )
        ");
    }
} catch (Exception $e) {
    // Table might already exist
}

// ============================================================================
// FETCH INVENTORY DATA
// ============================================================================

// Get all products with category info
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name
");
$products = $stmt->fetchAll();

// Calculate inventory statistics
$totalProducts = count($products);
$lowStockCount = 0;
$criticalStockCount = 0;
$totalValue = 0;
$outOfStockCount = 0;

foreach ($products as $p) {
    $stock = (int)$p['stock'];
    if ($stock === 0) {
        $outOfStockCount++;
    }
    if ($stock < 5) {
        $criticalStockCount++;
    }
    if ($stock < 10) {
        $lowStockCount++;
    }
    $totalValue += (float)($p['price'] * $stock);
}

// Get recent inventory movements
$movementsStmt = $pdo->query("
    SELECT im.*, p.name as product_name, u.username as admin_name
    FROM inventory_movements im
    LEFT JOIN products p ON im.product_id = p.id
    LEFT JOIN users u ON im.created_by = u.id
    ORDER BY im.created_at DESC
    LIMIT 20
");
$movements = $movementsStmt->fetchAll();

// Get stock by category
$categoryStmt = $pdo->query("
    SELECT c.name as category_name, COUNT(p.id) as product_count, 
           SUM(CAST(p.stock AS UNSIGNED)) as total_stock,
           AVG(CAST(p.price AS DECIMAL)) as avg_price
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    GROUP BY p.category_id, c.name
    ORDER BY c.name
");
$stockByCategory = $categoryStmt->fetchAll();

// Get product for editing
$editProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProduct = $stmt->fetch();
}

// Get categories for dropdown
$categoryList = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Admin Dashboard</title>
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

        .mini-icon.stock { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .mini-icon.low { background: linear-gradient(135deg, #f97316, #f59e0b); }
        .mini-icon.value { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

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
        .badge-soft.critical { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }

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

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .filters-row input,
        .filters-row select {
            height: 42px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0 12px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filters-row input:focus,
        .filters-row select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }

        .stock-input {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-align: center;
        }

        .adjustment-row {
            background: rgba(99, 102, 241, 0.05);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .adjustment-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .category-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .category-card {
            background: white;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .category-card-header {
            font-weight: 600;
            color: #4f46e5;
            margin-bottom: 8px;
        }

        .category-stat {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .category-stat strong { color: #1f2937; }
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
                <a href="products.php" class="nav-item">
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
                <a href="inventory.php" class="nav-item active">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <a href="backup.php" class="nav-item">
                    <i class="fas fa-database"></i> Backup
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Hero Header -->
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Stock Control</p>
                    <h1>Inventory Management</h1>
                    <p class="subhead">Track, adjust, and monitor stock levels across all products.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Inventory Value</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalValue, 0); ?></div>
                    <div class="metric-sub"><?php echo $totalProducts; ?> products • <?php echo $lowStockCount; ?> low stock</div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon stock"><i class="fas fa-boxes"></i></span>
                    <div>
                        <div class="summary-label">Total Products</div>
                        <div class="summary-value"><?php echo number_format($totalProducts); ?></div>
                        <span class="subline">In inventory</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon critical"><i class="fas fa-exclamation"></i></span>
                    <div>
                        <div class="summary-label">Critical Stock</div>
                        <div class="summary-value"><?php echo $criticalStockCount; ?></div>
                        <span class="subline">&lt; 5 units remaining</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon low"><i class="fas fa-arrow-down"></i></span>
                    <div>
                        <div class="summary-label">Low Stock Items</div>
                        <div class="summary-value"><?php echo $lowStockCount; ?></div>
                        <span class="subline">&lt; 10 units</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon value"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="summary-label">Total Value</div>
                        <div class="summary-value">Rs.<?php echo number_format($totalValue, 0); ?></div>
                        <span class="subline">Current inventory</span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stock by Category -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-th"></i> Stock by Category</h3>
                    <p class="helper">Overview of inventory across product categories.</p>
                </div>
                <div class="category-summary">
                    <?php foreach ($stockByCategory as $cat): ?>
                    <div class="category-card">
                        <div class="category-card-header">
                            <?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?>
                        </div>
                        <div class="category-stat">
                            <span>Products:</span>
                            <strong><?php echo $cat['product_count']; ?></strong>
                        </div>
                        <div class="category-stat">
                            <span>Total Stock:</span>
                            <strong><?php echo number_format($cat['total_stock'] ?? 0); ?> units</strong>
                        </div>
                        <div class="category-stat">
                            <span>Avg Price:</span>
                            <strong>RsRs.<?php echo number_format($cat['avg_price'] ?? 0, 0); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Stock Adjustment -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sync"></i> Quick Stock Adjustment</h3>
                    <p class="helper">Adjust individual product stock levels and record the reason.</p>
                </div>
                <form method="POST" class="adjustment-row">
                    <input type="hidden" name="action" value="adjust_stock">
                    <div class="form-group">
                        <label for="product_id">Select Product *</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">-- Choose a product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['name']); ?> 
                                    (Current: <?php echo $p['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adjustment">Adjustment (+/-) *</label>
                        <input type="number" id="adjustment" name="adjustment" required placeholder="e.g., +10 or -5">
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason *</label>
                        <select id="reason" name="reason" required>
                            <option value="">-- Select reason --</option>
                            <option value="Stock received">Stock received</option>
                            <option value="Damage">Damage</option>
                            <option value="Loss">Loss</option>
                            <option value="Inventory count">Inventory count</option>
                            <option value="Return">Return</option>
                            <option value="Adjustment">Manual adjustment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Adjustment
                        </button>
                    </div>
                </form>
            </div>

            <!-- Complete Inventory List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Complete Inventory</h3>
                </div>

                <div class="filters-row">
                    <input type="text" id="productSearch" placeholder="Search by product name...">
                    <select id="categoryFilter">
                        <option value="">All categories</option>
                        <?php foreach ($categoryList as $cat): ?>
                            <option value="<?php echo strtolower($cat['name']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="stockFilter">
                        <option value="">All stock levels</option>
                        <option value="critical">Critical (&lt; 5)</option>
                        <option value="low">Low (5-10)</option>
                        <option value="medium">Medium (10-50)</option>
                        <option value="high">High (50+)</option>
                    </select>
                </div>

                <div class="table-container">
                    <table class="table modern interactive-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Unit Price</th>
                                <th>Current Stock</th>
                                <th>Stock Value</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <?php 
                                $stock = (int)$product['stock'];
                                $stock_value = $stock * (float)$product['price'];
                                
                                if ($stock === 0) { 
                                    $stockClass = 'critical'; 
                                    $stockLabel = 'Out of Stock'; 
                                } elseif ($stock < 5) { 
                                    $stockClass = 'critical'; 
                                    $stockLabel = 'Critical'; 
                                } elseif ($stock < 10) { 
                                    $stockClass = 'warning'; 
                                    $stockLabel = 'Low'; 
                                } else { 
                                    $stockClass = 'success'; 
                                    $stockLabel = 'Healthy'; 
                                }
                            ?>
                            <tr data-name="<?php echo strtolower($product['name']); ?>" 
                                data-category="<?php echo strtolower($product['category_name'] ?? 'uncategorized'); ?>"
                                data-stock="<?php echo $stock; ?>">
                                <td><?php echo $product['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td>Rs.<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $stock; ?></td>
                                <td>Rs.<?php echo number_format($stock_value, 0); ?></td>
                                <td>
                                    <span class="badge-soft <?php echo $stockClass; ?>">
                                        <i class="fas fa-circle"></i> <?php echo $stockLabel; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" 
                                           class="btn btn-warning btn-sm" title="Edit product details">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Inventory Movements -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Stock Movements</h3>
                    <p class="helper">Last 20 inventory adjustments and transactions.</p>
                </div>
                <?php if (count($movements) > 0): ?>
                    <div class="table-container">
                        <table class="table modern">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Product</th>
                                    <th>Reason</th>
                                    <th>Adjustment</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $move): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($move['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($move['product_name'] ?? 'Unknown'); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($move['reason'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge-soft <?php echo $move['adjustment'] > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $move['adjustment'] > 0 ? '+' : ''; ?><?php echo $move['adjustment']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $move['old_stock']; ?></td>
                                    <td><?php echo $move['new_stock']; ?></td>
                                    <td><?php echo htmlspecialchars($move['admin_name'] ?? 'System'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox" style="color:#9ca3af;"></i>
                        <p>No inventory movements recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        // Client-side filtering
        const searchInput = document.getElementById('productSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');
        const tableRows = Array.from(document.querySelectorAll('#inventoryTable tbody tr'));

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
                if (stockSel === 'critical') matchStock = stock < 5;
                else if (stockSel === 'low') matchStock = stock >= 5 && stock < 10;
                else if (stockSel === 'medium') matchStock = stock >= 10 && stock < 50;
                else if (stockSel === 'high') matchStock = stock >= 50;

                row.style.display = (matchTerm && matchCat && matchStock) ? '' : 'none';
            });
        }

        [searchInput, categoryFilter, stockFilter].forEach(el => {
            if (el) el.addEventListener('input', applyFilters);
            if (el) el.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
