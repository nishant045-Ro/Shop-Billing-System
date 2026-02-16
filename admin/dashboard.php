<?php
/**
 * Admin Dashboard
 * 
 * Main dashboard page for administrators to view system statistics,
 * recent activity, and manage operations.
 * 
 * Features:
 * - Real-time statistics and metrics
 * - 7-day sales trend visualization
 * - Recent bills overview
 * - Low stock alerts
 * - User information and logout
 */

session_start();
require_once '../config/database.php';

// ============================================================================
// SECURITY CHECK
// ============================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$error = null;

// ============================================================================
// FETCH ADMIN USER INFORMATION
// ============================================================================
try {
    $stmt = $pdo->prepare("SELECT username, created_at FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
} catch (PDOException $e) {
    $error = "Unable to load user information. Please try again.";
    $admin = ['username' => 'Admin', 'created_at' => date('Y-m-d')];
}

// ============================================================================
// DASHBOARD STATISTICS - OPTIMIZED QUERIES
// ============================================================================
try {
    // Combined query for main statistics (more efficient)
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM products) as total_products,
            (SELECT COUNT(*) FROM users WHERE role = 'cashier') as total_users,
            (SELECT COUNT(*) FROM bills) as total_bills,
            (SELECT COALESCE(SUM(final_amount), 0) FROM bills) as total_revenue,
            (SELECT COUNT(*) FROM bills WHERE DATE(date_time) = CURDATE()) as today_bills,
            (SELECT COALESCE(SUM(final_amount), 0) FROM bills WHERE DATE(date_time) = CURDATE()) as today_revenue
    ");
    $stats = $stmt->fetch();
    
    $totalProducts = intval($stats['total_products'] ?? 0);
    $totalUsers = intval($stats['total_users'] ?? 0);
    $totalBills = intval($stats['total_bills'] ?? 0);
    $totalRevenue = floatval($stats['total_revenue'] ?? 0);
    $todayBills = intval($stats['today_bills'] ?? 0);
    $todayRevenue = floatval($stats['today_revenue'] ?? 0);
    
} catch (PDOException $e) {
    $error = "Unable to load dashboard statistics.";
    $totalProducts = $totalUsers = $totalBills = $todayBills = 0;
    $totalRevenue = $todayRevenue = 0;
}

// ============================================================================
// CHART DATA - 7 DAY SALES TREND
// ============================================================================
$chartLabels = [];
$chartRevenue = [];
$chartBills = [];

try {
    $stmt = $pdo->query("
        SELECT 
            DATE(date_time) as sale_date, 
            COALESCE(SUM(final_amount), 0) as daily_revenue,
            COUNT(*) as daily_bills
        FROM bills 
        WHERE date_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(date_time)
        ORDER BY sale_date ASC
    ");
    $dailyData = $stmt->fetchAll();
    
    // Fill in missing days with zero values
    $dateRange = [];
    for ($i = 6; $i >= 0; $i--) {
        $dateRange[] = date('Y-m-d', strtotime("-$i days"));
    }
    
    $dataMap = [];
    foreach ($dailyData as $day) {
        $dataMap[$day['sale_date']] = $day;
    }
    
    foreach ($dateRange as $date) {
        $chartLabels[] = date('M d', strtotime($date));
        if (isset($dataMap[$date])) {
            $chartRevenue[] = floatval($dataMap[$date]['daily_revenue']);
            $chartBills[] = intval($dataMap[$date]['daily_bills']);
        } else {
            $chartRevenue[] = 0;
            $chartBills[] = 0;
        }
    }
} catch (PDOException $e) {
    // Use empty data if query fails
    for ($i = 6; $i >= 0; $i--) {
        $chartLabels[] = date('M d', strtotime("-$i days"));
        $chartRevenue[] = 0;
        $chartBills[] = 0;
    }
}

// Prepare last 7 days breakdown (label, bills, revenue) for display
$last7Days = [];
$len = max(count($chartLabels), count($chartBills), count($chartRevenue));
for ($i = 0; $i < $len; $i++) {
    $last7Days[] = [
        'label' => $chartLabels[$i] ?? date('M d', strtotime("-".(6-$i)." days")),
        'bills' => intval($chartBills[$i] ?? 0),
        'revenue' => floatval($chartRevenue[$i] ?? 0)
    ];
}

// For display, present newest first (today at top)
$last7Days = array_reverse($last7Days);

// ============================================================================
// RECENT BILLS
// ============================================================================
$recentBills = [];
$recentSummary = [
    'count' => 0,
    'revenue' => 0,
    'average' => 0,
    'top_payment' => 'N/A'
];
try {
    $stmt = $pdo->query("
        SELECT 
            b.*, 
            u.username as cashier_name 
        FROM bills b 
        JOIN users u ON b.cashier_id = u.id 
        ORDER BY b.date_time DESC 
        LIMIT 5
    ");
    $recentBills = $stmt->fetchAll();
    
    // Quick aggregates for the recent list
    if ($recentBills) {
        $recentSummary['count'] = count($recentBills);
        $paymentCounts = [];
        foreach ($recentBills as $rb) {
            $amount = floatval($rb['final_amount'] ?? 0);
            $recentSummary['revenue'] += $amount;
            $method = strtolower($rb['payment_method'] ?? 'unknown');
            $paymentCounts[$method] = ($paymentCounts[$method] ?? 0) + 1;
        }
        $recentSummary['average'] = $recentSummary['count'] > 0 
            ? $recentSummary['revenue'] / $recentSummary['count'] 
            : 0;
        arsort($paymentCounts);
        $recentSummary['top_payment'] = $paymentCounts ? ucfirst(array_key_first($paymentCounts)) : 'N/A';
    }
} catch (PDOException $e) {
    // Continue with empty array if query fails
}

// ============================================================================
// LOW STOCK PRODUCTS ALERT
// ============================================================================
$lowStockProducts = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.*, 
            c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.stock < 10 
        ORDER BY p.stock ASC 
        LIMIT 5
    ");
    $lowStockProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Continue with empty array if query fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Shop Billing System">
    <title>Admin Dashboard - Shop Billing System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Theme palette */
        :root {
            --indigo-500:rgb(152, 147, 226);
            --indigo-400:rgb(159, 160, 246);
            --purple-500: #7c3aed;
            --teal-400:rgb(127, 222, 211);
            --teal-500:rgb(104, 184, 222);
            --amber-400:rgb(238, 192, 112);
            --rose-500:rgb(239, 128, 147);
            --slate-900:rgb(123, 155, 232);
        }

        /* Page backdrop */
        body.admin-dashboard {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(14, 184, 166, 0.08), transparent 25%),
                        #f2f4f8;
        }

        /* Subtle glass look for main content */
        .admin-dashboard .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(248, 249, 252, 0.95));
            backdrop-filter: blur(6px);
        }

        /* Stat card gradients (new combinations) */
        .admin-dashboard .stat-card.primary {
            background: linear-gradient(135deg, var(--indigo-500), var(--purple-500));
        }
        .admin-dashboard .stat-card.success {
            background: linear-gradient(135deg, var(--teal-500), var(--teal-400));
        }
        .admin-dashboard .stat-card.warning {
            background: linear-gradient(135deg, #f97316, var(--amber-400));
        }
        .admin-dashboard .stat-card.danger {
            background: linear-gradient(135deg, var(--rose-500), #fb7185);
        }

        /* Section headers accent */
        .admin-dashboard .card-header h3 {
            position: relative;
            padding-left: 10px;
        }
        .admin-dashboard .card-header h3::before {
            content: "";
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 4px;
            border-radius: 6px;
            background: linear-gradient(180deg, var(--indigo-400), var(--teal-400));
        }

        /* Pills for payment type / meta */
        .admin-dashboard .customer-sub {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(20, 184, 166, 0.1);
            color: #0f766e;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* Table accents */
        .admin-dashboard .table th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .admin-dashboard .table tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.08), rgba(244, 63, 94, 0.06));
        }

        /* Recent bills summary pills */
        .admin-dashboard .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 12px 0 18px;
        }
        .admin-dashboard .summary-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-dashboard .summary-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        .admin-dashboard .summary-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .admin-dashboard .summary-icon.avg { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }
        .admin-dashboard .summary-icon.count { background: linear-gradient(135deg, #a855f7, #7c3aed); }
        .admin-dashboard .summary-icon.pay { background: linear-gradient(135deg, #f97316, #f59e0b); }
        .admin-dashboard .summary-label { font-size: 0.85rem; color: #6b7280; }
        .admin-dashboard .summary-value { font-weight: 700; color: #0f172a; }

        /* Payment method badge */
        .admin-dashboard .pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
            text-transform: capitalize;
        }
        .admin-dashboard .pay-badge i { font-size: 0.9rem; }
        .admin-dashboard .pay-badge.cash { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .admin-dashboard .pay-badge.card { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
        .admin-dashboard .pay-badge.upi { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
        .admin-dashboard .pay-badge.wallet { background: rgba(244, 63, 94, 0.12); color: #be123c; }

        /* Subtext in tables */
        .admin-dashboard .subline {
            display: block;
            font-size: 0.82rem;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Chart container glow */
        .admin-dashboard .chart-container {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(14, 165, 233, 0.06));
            border-radius: 14px;
            padding: 12px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        /* Hero metric accent */
        .admin-dashboard .hero-metric {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), rgba(20, 184, 166, 0.24));
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Buttons tweak */
        .admin-dashboard .btn.btn-secondary.ghost {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Additional Dashboard Enhancements */
        .user-info {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .user-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: #666;
        }
        
        .logout-btn {
            margin-left: 8px;
            padding: 6px 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c0392b;
            transform: scale(1.05);
        }
        
        .error-banner {
            background: #fee;
            color: #c53030;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c53030;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
            display: block;
        }
        
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
        }
        
        .card {
            transition: transform 0.3s ease;
            margin-bottom: 25px;
            display: block !important;
            visibility: visible !important;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .table-container {
            display: block !important;
            visibility: visible !important;
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }
        
        /* Ensure Recent Bills section is visible */
        .card.elevated {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 1;
            background: white;
            padding: 0;
        }
        
        /* Recent Bills specific styling */
        .card.elevated .card-header {
            padding: 20px 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card.elevated .table-container {
            padding: 20px 25px;
            min-height: 200px;
        }
        
        .table.modern {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table.modern thead th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table.modern tbody td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table.modern tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Ensure main-content doesn't hide content */
        .main-content {
            overflow: visible !important;
            min-height: 100vh;
        }
        
        /* Make Recent Bills section stand out */
        .recent-bills-section {
            border: 2px solid #667eea !important;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2) !important;
            background: white !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 10 !important;
        }
        
        .recent-bills-section .card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
            border-bottom: 2px solid #667eea !important;
        }
        
        @media (max-width: 768px) {
            .user-info {
                position: relative;
                top: 0;
                right: 0;
                margin-bottom: 20px;
                width: 100%;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
        }
        
        .refresh-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 999;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="dashboard-container">
        <!-- User Info & Logout -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($admin['username']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
            <form method="POST" action="../logout.php" style="margin: 0;">
                <button type="submit" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-tools"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="products.php" class="nav-item">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="categories.php" class="nav-item">
                    <i class="fas fa-tags"></i> Categories
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
            <!-- Error Banner -->
            <?php if ($error): ?>
                <div class="error-banner">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Hero Header -->
            <div class="header hero-header">
                <div>
                    <p class="eyebrow"></p>
                    <h1>Operations Overview</h1>
                    <p class="subhead">Monitor performance, track inventory, and keep teams aligned.</p>
                    <div class="header-actions">
                        <!-- <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-box"></i> Manage Products
                        </a> -->
                        <!-- <a href="reports.php" class="btn btn-secondary ghost">
                            <i class="fas fa-chart-line"></i> View Reports
                        </a> -->
                    </div>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub"><?php echo number_format($totalBills); ?> bills processed</div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary elevated animated-stat" data-value="<?php echo $totalProducts; ?>">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">Products</p>
                            <h3 class="counter">0</h3>
                        </div>
                        <span class="stat-icon"><i class="fas fa-box"></i></span>
                    </div>
                    <p class="stat-foot">Inventory breadth.</p>
                </div>
                
                <div class="stat-card success elevated animated-stat" data-value="<?php echo $totalUsers; ?>">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">Cashier Users</p>
                            <h3 class="counter">0</h3>
                        </div>
                        <span class="stat-icon"><i class="fas fa-users"></i></span>
                    </div>
                    <p class="stat-foot">Active team members.</p>
                </div>
                
                <div class="stat-card warning elevated animated-stat" data-value="<?php echo $totalBills; ?>">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">Total Bills</p>
                            <h3 class="counter">0</h3>
                        </div>
                        <span class="stat-icon"><i class="fas fa-receipt"></i></span>
                    </div>
                    <p class="stat-foot">All transactions.</p>
                </div>
                
                <div class="stat-card danger elevated animated-stat" data-value="<?php echo $totalRevenue; ?>" data-is-money="true">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">Revenue</p>
                            <h3 class="counter">Rs.0.00</h3>
                        </div>
                        <span class="stat-icon"><i class="fas fa-money-bill-wave"></i></span>
                    </div>
                    <p class="stat-foot">Total earnings.</p>
                </div>
            </div>

            <!-- Today's Performance & Chart -->
            <div class="grid-2">
                <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-day"></i> Today's Performance</h3>
                    </div>
                    <!-- <div class="today-stats">
                        <div class="today-stat-item">
                            <div class="today-icon primary">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="today-info">
                                <p class="today-label">Bills Today</p>
                                <h3 class="today-value animated-stat" data-value="<?php echo $todayBills; ?>">0</h3>
                            </div>
                        </div>
                        <div class="today-stat-item">
                            <div class="today-icon success">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="today-info">
                                <p class="today-label">Revenue Today</p>
                                <h3 class="today-value animated-stat" data-value="<?php echo $todayRevenue; ?>" data-is-money="true">Rs.0.00</h3>
                            </div>
                        </div>
                    </div> -->

                    <!-- Last 7 days breakdown (new) -->
                    <div class="today-breakdown" style="margin-top:12px;">
                        <h4 style="margin:0 0 8px 0; font-size:0.95rem; color:#374151;">Last 7 days</h4>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; gap:8px; flex-wrap:wrap;">
                            <?php foreach ($last7Days as $day): ?>
                                <li style="background:#fff; border:1px solid #e6eef6; padding:8px 12px; border-radius:8px; min-width:120px;">
                                    <div style="font-size:0.85rem; color:#6b7280;"><?php echo htmlspecialchars($day['label']); ?></div>
                                    <div style="font-weight:700; font-size:1.05rem;"><?php echo number_format($day['bills']); ?> bills</div>
                                    <div style="color:#6b7280; font-size:0.9rem;">Rs.<?php echo number_format($day['revenue'], 2); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> 7-Day Sales Trend</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="card elevated recent-bills-section" style="margin-top: 30px; clear: both; border: 2px solid #667eea; box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2) !important;">
                <div class="card-header" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-clock" style="color: #667eea;"></i> 
                        <span>Recent Bills</span>
                    </h3>
                   
                </div>
                <?php if ($recentSummary['count'] > 0): ?>
                <div class="summary-row">
                    <div class="summary-card">
                        <span class="summary-icon revenue"><i class="fas fa-coins"></i></span>
                        <div>
                            <div class="summary-label">Revenue (last <?php echo $recentSummary['count']; ?>)</div>
                            <div class="summary-value">Rs.<?php echo number_format($recentSummary['revenue'], 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon avg"><i class="fas fa-equals"></i></span>
                        <div>
                            <div class="summary-label">Avg Bill</div>
                            <div class="summary-value">Rs.<?php echo number_format($recentSummary['average'], 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon count"><i class="fas fa-file-invoice"></i></span>
                        <div>
                            <div class="summary-label">Bills Listed</div>
                            <div class="summary-value"><?php echo $recentSummary['count']; ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon pay"><i class="fas fa-credit-card"></i></span>
                        <div>
                            <div class="summary-label">Top Payment</div>
                            <div class="summary-value"><?php echo htmlspecialchars($recentSummary['top_payment']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="table-container">
                    <?php if (count($recentBills) > 0): ?>
                        <table class="table modern interactive-table">
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Customer</th>
                                    <th>Cashier</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBills as $bill): ?>
                                <tr class="table-row-interactive">
                                    <td><strong><?php echo htmlspecialchars($bill['bill_id']); ?></strong></td>
                                    <td>
                                        <?php 
                                        $customerAvatar = $bill['customer_image'] 
                                            ? '../' . htmlspecialchars($bill['customer_image']) 
                                            : '../assets/img/customer-placeholder.svg'; 
                                        ?>
                                        <div class="customer-cell">
                                            <span class="avatar" style="background-image: url('<?php echo $customerAvatar; ?>');"></span>
                                            <div class="customer-meta">
                                                <div class="customer-name"><?php echo htmlspecialchars($bill['customer_name']); ?></div>
                                                <div class="customer-sub"><?php echo ucfirst($bill['payment_method']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($bill['cashier_name']); ?></span>
                                    </td>
                                <td><strong class="text-success">Rs.<?php echo number_format($bill['final_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                        $payMethod = strtolower($bill['payment_method']); 
                                        $payIcon = 'fa-credit-card';
                                        if ($payMethod === 'cash') $payIcon = 'fa-money-bill-wave';
                                        elseif ($payMethod === 'upi') $payIcon = 'fa-mobile-alt';
                                        elseif ($payMethod === 'wallet') $payIcon = 'fa-wallet';
                                        elseif ($payMethod === 'card') $payIcon = 'fa-credit-card';
                                    ?>
                                    <span class="pay-badge <?php echo $payMethod; ?>">
                                        <i class="fas <?php echo $payIcon; ?>"></i> <?php echo htmlspecialchars($bill['payment_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $billDate = strtotime($bill['date_time']);
                                        $timeAgoSeconds = time() - $billDate;
                                        if ($timeAgoSeconds < 60) {
                                            $timeAgo = $timeAgoSeconds . 's ago';
                                        } elseif ($timeAgoSeconds < 3600) {
                                            $timeAgo = floor($timeAgoSeconds / 60) . 'm ago';
                                        } elseif ($timeAgoSeconds < 86400) {
                                            $timeAgo = floor($timeAgoSeconds / 3600) . 'h ago';
                                        } else {
                                            $days = floor($timeAgoSeconds / 86400);
                                            $timeAgo = $days . 'd ago';
                                        }
                                    ?>
                                    <div><?php echo date('M d, Y H:i', $billDate); ?></div>
                                    <span class="subline"><?php echo $timeAgo; ?></span>
                                </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No bills found. Start processing transactions to see them here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="card elevated alert-card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                    <span class="alert-count"><?php echo count($lowStockProducts); ?> items</span>
                </div>
                <div class="table-container">
                    <?php if (count($lowStockProducts) > 0): ?>
                        <table class="table modern interactive-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr class="table-row-interactive warning-row">
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning stock-badge" data-stock="<?php echo $product['stock']; ?>">
                                            <i class="fas fa-exclamation-circle"></i> <?php echo $product['stock']; ?>
                                        </span>
                                    </td>
                                    <td><strong>Rs.<?php echo number_format($product['price'], 2); ?></strong></td>
                                    <td>
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" 
                                           class="btn btn-warning btn-sm pulse-btn">
                                            <i class="fas fa-edit"></i> Update Stock
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            <p>All products are well stocked! Great job managing inventory.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * Dashboard JavaScript
         * Handles animations, charts, and interactions
         */
        document.addEventListener('DOMContentLoaded', function() {
            // ====================================================================
            // ANIMATED COUNTERS
            // ====================================================================
            function animateCounter(element, target, isMoney = false) {
                const duration = 2000;
                const start = 0;
                const increment = target / (duration / 16);
                let current = start;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    if (isMoney) {
                        element.textContent = 'Rs.' + current.toLocaleString('en-IN', {
                            minimumFractionDigits: 2, 
                            maximumFractionDigits: 2
                        });
                    } else {
                        element.textContent = Math.floor(current).toLocaleString('en-IN');
                    }
                }, 16);
            }

            // Initialize all counters
            document.querySelectorAll('.animated-stat').forEach(stat => {
                const value = parseFloat(stat.getAttribute('data-value')) || 0;
                const isMoney = stat.getAttribute('data-is-money') === 'true';
                const counterElement = stat.querySelector('.counter, .today-value');
                
                if (counterElement) {
                    // Delay animation slightly for visual effect
                    setTimeout(() => {
                        animateCounter(counterElement, value, isMoney);
                    }, 100);
                }
            });

            // ====================================================================
            // CHART.JS - SALES TREND CHART
            // ====================================================================
            const ctx = document.getElementById('salesChart');
            if (ctx) {
                const chartData = {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Revenue (Rs.)',
                        data: <?php echo json_encode($chartRevenue); ?>,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y',
                        borderWidth: 2
                    }, {
                        label: 'Number of Bills',
                        data: <?php echo json_encode($chartBills); ?>,
                        borderColor: 'rgb(255, 159, 64)',
                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1',
                        type: 'bar',
                        borderRadius: 4
                    }]
                };

                new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: { size: 14 },
                                bodyFont: { size: 13 },
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (Rs.)',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return 'Rs.' + value.toLocaleString('en-IN');
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Number of Bills',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }

            // ====================================================================
            // TABLE ROW INTERACTIONS
            // ====================================================================
            document.querySelectorAll('.table-row-interactive').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // ====================================================================
            // LOW STOCK PULSE ANIMATION
            // ====================================================================
            document.querySelectorAll('.stock-badge').forEach(badge => {
                const stock = parseInt(badge.getAttribute('data-stock'));
                if (stock < 5) {
                    badge.classList.add('pulse');
                }
            });

            // ====================================================================
            // AUTO-REFRESH DASHBOARD (Optional - every 60 seconds)
            // ====================================================================
            // Uncomment below to enable auto-refresh
            /*
            let refreshInterval = setInterval(function() {
                const refreshIndicator = document.createElement('div');
                refreshIndicator.className = 'refresh-indicator';
                refreshIndicator.innerHTML = '<i class="fas fa-sync fa-spin"></i> Refreshing...';
                document.body.appendChild(refreshIndicator);
                
                setTimeout(() => {
                    location.reload();
                }, 500);
            }, 60000);
            */

            // ====================================================================
            // SMOOTH SCROLL BEHAVIOR
            // ====================================================================
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // ====================================================================
            // MOBILE MENU TOGGLE (if needed)
            // ====================================================================
            if (window.innerWidth <= 768) {
                // Add mobile menu toggle functionality if needed
                console.log('Mobile view detected');
            }
        });
    </script>
</body>
</html>
