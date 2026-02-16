<?php
session_start();
require_once '../config/database.php';

// Ensure cashier access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../index.php');
    exit();
}

$cashier_id = $_SESSION['user_id'];
$error = null;

// Fetch cashier profile info for display (username, optional store image)
try {
    $stmt = $pdo->prepare("SELECT username, store_image FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$cashier_id]);
    $cashierUser = $stmt->fetch();
} catch (PDOException $e) {
    $cashierUser = null;
}

$displayName = htmlspecialchars($cashierUser['username'] ?? ($_SESSION['username'] ?? 'Cashier'));
$storeImage = $cashierUser['store_image'] ?? null;

// Primary stats (single query for performance)
try {
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM bills WHERE cashier_id = ?) AS total_bills,
            (SELECT COALESCE(SUM(final_amount), 0) FROM bills WHERE cashier_id = ?) AS total_revenue,
            (SELECT COUNT(*) FROM bills WHERE cashier_id = ? AND DATE(date_time) = CURDATE()) AS today_bills,
            (SELECT COALESCE(SUM(final_amount), 0) FROM bills WHERE cashier_id = ? AND DATE(date_time) = CURDATE()) AS today_revenue
    ");
    $stmt->execute([$cashier_id, $cashier_id, $cashier_id, $cashier_id]);
    $stats = $stmt->fetch();
    $totalBills = intval($stats['total_bills'] ?? 0);
    $totalRevenue = floatval($stats['total_revenue'] ?? 0);
    $todayBills = intval($stats['today_bills'] ?? 0);
    $todayRevenue = floatval($stats['today_revenue'] ?? 0);
} catch (PDOException $e) {
    $error = 'Unable to load statistics right now.';
    $totalBills = $todayBills = 0;
    $totalRevenue = $todayRevenue = 0;
}

// Top payment method
$topPayment = 'N/A';
try {
    $stmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) as cnt 
        FROM bills 
        WHERE cashier_id = ? 
        GROUP BY payment_method 
        ORDER BY cnt DESC 
        LIMIT 1
    ");
    $stmt->execute([$cashier_id]);
    $row = $stmt->fetch();
    if ($row) {
        $topPayment = ucfirst($row['payment_method']);
    }
} catch (PDOException $e) {
    // ignore
}

// Weekly trend (last 7 days)
$chartLabels = [];
$chartRevenue = [];
$chartBills = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE(date_time) as sale_date, 
               COALESCE(SUM(final_amount), 0) as daily_revenue,
               COUNT(*) as daily_bills
        FROM bills 
        WHERE cashier_id = ? 
          AND date_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(date_time)
        ORDER BY sale_date ASC
    ");
    $stmt->execute([$cashier_id]);
    $dailyData = $stmt->fetchAll();

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
    // fallback zeros
    for ($i = 6; $i >= 0; $i--) {
        $chartLabels[] = date('M d', strtotime("-$i days"));
        $chartRevenue[] = 0;
        $chartBills[] = 0;
    }
}

// Recent bills for this cashier
$recentBills = [];
$recentSummary = ['count' => 0, 'revenue' => 0, 'average' => 0, 'top_payment' => 'N/A'];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM bills 
        WHERE cashier_id = ? 
        ORDER BY date_time DESC 
        LIMIT 6
    ");
    $stmt->execute([$cashier_id]);
    $recentBills = $stmt->fetchAll();

    if ($recentBills) {
        $recentSummary['count'] = count($recentBills);
        $paymentCounts = [];
        foreach ($recentBills as $rb) {
            $amt = floatval($rb['final_amount'] ?? 0);
            $recentSummary['revenue'] += $amt;
            $method = strtolower($rb['payment_method'] ?? 'unknown');
            $paymentCounts[$method] = ($paymentCounts[$method] ?? 0) + 1;
        }
        $recentSummary['average'] = $recentSummary['count'] ? $recentSummary['revenue'] / $recentSummary['count'] : 0;
        arsort($paymentCounts);
        $recentSummary['top_payment'] = $paymentCounts ? ucfirst(array_key_first($paymentCounts)) : 'N/A';
    }
} catch (PDOException $e) {
    // ignore
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sapkota Mini Mart - Shop Billing System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --indigo-500: #4f46e5;
            --indigo-400: #6366f1;
            --teal-400: #14b8a6;
            --amber-400: #f59e0b;
            --rose-500: #f43f5e;
            --slate-900: #0f172a;
        }
        body.cashier-dashboard {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 28%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.08), transparent 28%),
                        #f3f4f6;
        }
        .cashier-dashboard .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }
        .cashier-dashboard .hero-header {
            background: linear-gradient(135deg, #4f46e5, #14b8a6);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }
        .cashier-dashboard .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .cashier-dashboard .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-dashboard .mini-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.15rem;
        }
        .mini-icon.bills { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.avg { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }
        .mini-icon.pay { background: linear-gradient(135deg, #f97316, #f59e0b); }
        .mini-icon.today { background: linear-gradient(135deg, #ec4899, #f43f5e); }

        .cashier-dashboard .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .cashier-dashboard .quick-actions .qa-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid rgba(99, 102, 241, 0.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .cashier-dashboard .quick-actions .qa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        }
        .cashier-dashboard .badge-soft {
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }
        .badge-soft.success { background: rgba(34, 197, 94, 0.14); color: #166534; }
        .badge-soft.warning { background: rgba(234, 179, 8, 0.16); color: #92400e; }
        .badge-soft.danger { background: rgba(244, 63, 94, 0.12); color: #be123c; }
        .badge-soft.info { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }

        .cashier-dashboard .table.modern th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .cashier-dashboard .table.modern tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(20, 184, 166, 0.05));
        }
        .cashier-dashboard .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 12px 0 18px;
        }
        .cashier-dashboard .summary-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-dashboard .summary-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        .summary-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .summary-icon.avg { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }
        .summary-icon.count { background: linear-gradient(135deg, #a855f7, #7c3aed); }
        .summary-icon.pay { background: linear-gradient(135deg, #f97316, #f59e0b); }
        .summary-label { font-size: 0.85rem; color: #6b7280; }
        .summary-value { font-weight: 700; color: #0f172a; }

        .cashier-dashboard .pay-badge {
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
        .cashier-dashboard .pay-badge.cash { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .cashier-dashboard .pay-badge.card { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
        .cashier-dashboard .pay-badge.upi { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
        .cashier-dashboard .pay-badge.wallet { background: rgba(244, 63, 94, 0.12); color: #be123c; }

        .cashier-dashboard .chart-container {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(14, 165, 233, 0.06));
            border-radius: 14px;
            padding: 12px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
            min-height: 260px;
        }
        .cashier-dashboard .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cashier-dashboard .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            border: none;
            background: rgba(248, 250, 252, 0.15);
            color: #f9fafb;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
        }
        .cashier-dashboard .btn-logout:hover {
            background: rgba(15, 23, 42, 0.25);
        }
    </style>
</head>
<body class="cashier-dashboard">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cash-register"></i> Cashier Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="fas fa-receipt"></i> New Bill
                </a>
                <a href="bills.php" class="nav-item">
                    <i class="fas fa-list"></i> All Bills
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i> Search Bills
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i> Profile
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="header hero-header">
                <div class="header-actions">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <?php if ($storeImage): ?>
                            <div class="store-avatar" style="width:56px;height:56px;border-radius:10px;overflow:hidden;border:2px solid rgba(255,255,255,0.12);">
                                <img src="../<?php echo htmlspecialchars($storeImage); ?>" alt="Store" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="eyebrow">Welcome, <?php echo $displayName; ?></p>
                            <p class="subhead">Track your performance, bill faster, and keep payments tidy.</p>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                    <!-- <div class="quick-actions">
                        <a href="billing.php" class="qa-card">
                            <span class="mini-icon bills"><i class="fas fa-bolt"></i></span>
                            <div>
                                <div class="summary-label">Create Bill</div>
                                <div class="summary-value">Start a new bill</div>
                            </div>
                        </a>
                        <a href="bills.php" class="qa-card">
                            <span class="mini-icon avg"><i class="fas fa-list"></i></span>
                            <div>
                                <div class="summary-label">All Bills</div>
                                <div class="summary-value">Review history</div>
                            </div>
                        </a>
                        <a href="search.php" class="qa-card">
                            <span class="mini-icon pay"><i class="fas fa-search"></i></span>
                            <div>
                                <div class="summary-label">Search</div>
                                <div class="summary-value">Find any bill</div>
                            </div>
                        </a>
                    </div> -->
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub"><?php echo number_format($totalBills); ?> bills processed</div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon bills"><i class="fas fa-receipt"></i></span>
                    <div>
                        <div class="summary-label">Total Bills</div>
                        <div class="summary-value" id="stat-total-bills"><?php echo number_format($totalBills); ?></div>
                        <span class="subline">All-time processed</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon revenue"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="summary-label">Total Revenue</div>
                        <div class="summary-value" id="stat-total-rev">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                        <span class="subline">Lifetime earnings</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon today"><i class="fas fa-calendar-day"></i></span>
                    <div>
                        <div class="summary-label">Today</div>
                        <div class="summary-value" id="stat-today-bills"><?php echo number_format($todayBills); ?> bills</div>
                        <span class="subline">Rs.<?php echo number_format($todayRevenue, 2); ?></span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon avg"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="summary-label">Avg Bill (recent)</div>
                        <div class="summary-value">Rs.<?php echo number_format($recentSummary['average'], 2); ?></div>
                        <span class="subline"><?php echo $recentSummary['count']; ?> recent bills</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon pay"><i class="fas fa-credit-card"></i></span>
                    <div>
                        <div class="summary-label">Top Payment</div>
                        <div class="summary-value"><?php echo htmlspecialchars($topPayment); ?></div>
                        <span class="subline">Overall preference</span>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> 7-Day Trend</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-list-check"></i> Shift Checklist</h3>
                    </div>
                    <ul style="list-style:none; padding:0; display:grid; gap:10px;">
                        <li class="qa-card" style="padding:12px; background:white;">
                            <span class="mini-icon revenue"><i class="fas fa-plus-circle"></i></span>
                            <div>
                                <div class="summary-label">Start with quick bill</div>
                                <div class="summary-value">Use product search & shortcuts</div>
                            </div>
                        </li>
                        <li class="qa-card" style="padding:12px; background:white;">
                            <span class="mini-icon avg"><i class="fas fa-ticket-alt"></i></span>
                            <div>
                                <div class="summary-label">Apply discounts</div>
                                <div class="summary-value">Confirm before finalizing payment</div>
                            </div>
                        </li>
                        <li class="qa-card" style="padding:12px; background:white;">
                            <span class="mini-icon pay"><i class="fas fa-wallet"></i></span>
                            <div>
                                <div class="summary-label">Verify payment type</div>
                                <div class="summary-value">Cash / Esewa / B-Pay / Wallet</div>
                            </div>
                        </li>
                        <li class="qa-card" style="padding:12px; background:white;">
                            <span class="mini-icon today"><i class="fas fa-print"></i></span>
                            <div>
                                <div class="summary-label">Print & hand off</div>
                                <div class="summary-value">Give receipt and thank customer</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Bills</h3>
                </div>
                <?php if ($recentSummary['count'] > 0): ?>
                <div class="summary-row">
                    <div class="summary-card">
                        <span class="summary-icon revenue"><i class="fas fa-coins"></i></span>
                        <div>
                            <div class="summary-label">Revenue (recent)</div>
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
                    <?php if (empty($recentBills)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No bills found. Start creating bills to see them here.</p>
                        </div>
                    <?php else: ?>
                        <table class="table modern interactive-table">
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBills as $bill): ?>
                                <tr class="table-row-interactive">
                                    <td><strong><?php echo htmlspecialchars($bill['bill_id']); ?></strong></td>
                                    <td>
                                        <?php $customerAvatar = $bill['customer_image'] ? '../' . htmlspecialchars($bill['customer_image']) : '../assets/img/customer-placeholder.svg'; ?>
                                        <div class="customer-cell">
                                            <span class="avatar" style="background-image: url('<?php echo $customerAvatar; ?>');"></span>
                                            <div class="customer-meta">
                                                <div class="customer-name"><?php echo htmlspecialchars($bill['customer_name']); ?></div>
                                                <div class="customer-sub"><?php echo ucfirst($bill['payment_method']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Rs.<?php echo number_format($bill['final_amount'], 2); ?></td>
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
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="print_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animated counters are simple here; already formatted server-side.

            // Chart.js - Sales Trend Chart
            const ctx = document.getElementById('salesChart');
            if (ctx) {
                const chartData = {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Revenue (Rs.)',
                        data: <?php echo json_encode($chartRevenue); ?>,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.12)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y',
                        borderWidth: 2
                    }, {
                        label: 'Bills',
                        data: <?php echo json_encode($chartBills); ?>,
                        borderColor: 'rgb(20, 184, 166)',
                        backgroundColor: 'rgba(20, 184, 166, 0.15)',
                        tension: 0.35,
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
                                    padding: 12,
                                    font: { size: 12 }
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
                                grid: { display: false },
                                ticks: { font: { size: 11 } }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (Rs.)',
                                    font: { size: 12, weight: 'bold' }
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' },
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
                                    text: 'Bills',
                                    font: { size: 12, weight: 'bold' }
                                },
                                grid: { drawOnChartArea: false },
                                ticks: { stepSize: 1 }
                            }
                        },
                        animation: { duration: 2000, easing: 'easeInOutQuart' }
                    }
                });
            }
        });
    </script>
</body>
</html>
