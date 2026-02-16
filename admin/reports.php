<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$category_id = $_GET['category_id'] ?? '';
$cashier_id = $_GET['cashier_id'] ?? '';

// Build WHERE clause for filters
$where_conditions = ["DATE(b.date_time) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($cashier_id) {
    $where_conditions[] = "b.cashier_id = ?";
    $params[] = $cashier_id;
}

$where_clause = implode(" AND ", $where_conditions);

// Get sales summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT b.bill_id) as total_bills,
        SUM(b.final_amount) as total_revenue,
        SUM(b.tax_amount) as total_tax,
        SUM(b.discount_amount) as total_discount,
        AVG(b.final_amount) as avg_bill_amount
    FROM bills b
    WHERE {$where_clause}
");
$stmt->execute($params);
$sales_summary = $stmt->fetch();

// Get top selling products
$stmt = $pdo->prepare("
    SELECT 
        p.name as product_name,
        c.name as category_name,
        SUM(bi.quantity) as total_quantity,
        SUM(bi.subtotal) as total_revenue
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.bill_id
    JOIN products p ON bi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$where_clause}
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->execute($params);
$top_products = $stmt->fetchAll();

// Get sales by category
$stmt = $pdo->prepare("
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT b.bill_id) as total_bills,
        SUM(bi.subtotal) as total_revenue,
        SUM(bi.quantity) as total_quantity
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.bill_id
    JOIN products p ON bi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$where_clause}
    GROUP BY c.id
    ORDER BY total_revenue DESC
");
$stmt->execute($params);
$sales_by_category = $stmt->fetchAll();

// Get sales by cashier
$stmt = $pdo->prepare("
    SELECT 
        u.username as cashier_name,
        COUNT(b.bill_id) as total_bills,
        SUM(b.final_amount) as total_revenue
    FROM bills b
    JOIN users u ON b.cashier_id = u.id
    WHERE {$where_clause}
    GROUP BY u.id
    ORDER BY total_revenue DESC
");
$stmt->execute($params);
$sales_by_cashier = $stmt->fetchAll();

// Get daily sales data for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE(b.date_time) as sale_date,
        COUNT(b.bill_id) as total_bills,
        SUM(b.final_amount) as daily_revenue
    FROM bills b
    WHERE {$where_clause}
    GROUP BY DATE(b.date_time)
    ORDER BY sale_date
");
$stmt->execute($params);
$daily_sales = $stmt->fetchAll();

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get cashiers for filter
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'cashier' ORDER BY username");
$cashiers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="reports.php" class="nav-item active">
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
                    <p class="eyebrow">Advanced Insights</p>
                    <h1>Sales Reports & Analytics</h1>
                    <p class="subhead">Track revenue, taxes, discounts, and your top performers in one place.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($sales_summary['total_revenue'], 2); ?></div>
                    <div class="metric-sub">Across <?php echo number_format($sales_summary['total_bills']); ?> bills</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Report Filters</h3>
                </div>
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cashier_id">Cashier</label>
                        <select id="cashier_id" name="cashier_id">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $cashier): ?>
                                <option value="<?php echo $cashier['id']; ?>" 
                                        <?php echo $cashier_id == $cashier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cashier['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Sales Summary -->
            <div class="stats-grid">
                <div class="stat-card primary elevated">
                    <i class="fas fa-receipt"></i>
                    <h3><?php echo number_format($sales_summary['total_bills']); ?></h3>
                    <p>Total Bills</p>
                </div>
                <div class="stat-card success elevated">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Rs.<?php echo number_format($sales_summary['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
                <div class="stat-card warning elevated">
                    <i class="fas fa-chart-line"></i>
                    <h3>Rs.<?php echo number_format($sales_summary['avg_bill_amount'], 2); ?></h3>
                    <p>Average Bill</p>
                </div>
                <div class="stat-card danger elevated">
                    <i class="fas fa-percentage"></i>
                    <h3>Rs.<?php echo number_format($sales_summary['total_tax'], 2); ?></h3>
                    <p>Total Tax</p>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Daily Sales Trend</h3>
                </div>
                <canvas id="salesChart" width="400" height="200"></canvas>
            </div>

            <!-- Top Products -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-star"></i> Top Selling Products</h3>
                </div>
                <div class="table-container">
                    <table class="table modern">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo number_format($product['total_quantity']); ?></td>
                                <td>Rs.<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales by Category -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> Sales by Category</h3>
                </div>
                <div class="table-container">
                    <table class="table modern">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Bills</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_by_category as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo number_format($category['total_bills']); ?></td>
                                <td><?php echo number_format($category['total_quantity']); ?></td>
                                <td>Rs.<?php echo number_format($category['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales by Cashier -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Sales by Cashier</h3>
                </div>
                <div class="table-container">
                    <table class="table modern">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th>Bills</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_by_cashier as $cashier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cashier['cashier_name']); ?></td>
                                <td><?php echo number_format($cashier['total_bills']); ?></td>
                                <td>Rs.<?php echo number_format($cashier['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode($daily_sales); ?>;
        
        const labels = salesData.map(item => item.sale_date);
        const revenueData = salesData.map(item => parseFloat(item.daily_revenue));
        const billsData = salesData.map(item => parseInt(item.total_bills));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Revenue (₹)',
                    data: revenueData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'Daily Bills',
                    data: billsData,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Bills'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>
