<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../index.php');
    exit();
}

$cashier_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Filters
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';

$where = ["cashier_id = ?"];
$params = [$cashier_id];

if ($search !== '') {
    $where[] = "(bill_id LIKE ? OR customer_name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($date_from !== '') {
    $where[] = "DATE(date_time) >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = "DATE(date_time) <= ?";
    $params[] = $date_to;
}

if ($payment_method !== '') {
    $where[] = "payment_method = ?";
    $params[] = $payment_method;
}

$whereSql = implode(" AND ", $where);

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_bills, SUM(final_amount) as total_revenue FROM bills WHERE cashier_id = ?");
$stmt->execute([$cashier_id]);
$stats = $stmt->fetch();
$totalBills = $stats['total_bills'] ?? 0;
$totalRevenue = $stats['total_revenue'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as today_bills, SUM(final_amount) as today_revenue FROM bills WHERE cashier_id = ? AND DATE(date_time) = CURDATE()");
$stmt->execute([$cashier_id]);
$todayStats = $stmt->fetch();
$todayBills = $todayStats['today_bills'] ?? 0;
$todayRevenue = $todayStats['today_revenue'] ?? 0;

// Fetch bills
$stmt = $pdo->prepare("
    SELECT bill_id, customer_name, payment_method, final_amount, date_time, customer_image
    FROM bills
    WHERE $whereSql
    ORDER BY date_time DESC
    LIMIT 100
");
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Filtered summaries
$filteredCount = count($bills);
$filteredRevenue = 0;
$paymentCounts = [];
foreach ($bills as $b) {
    $amt = floatval($b['final_amount'] ?? 0);
    $filteredRevenue += $amt;
    $pm = strtolower($b['payment_method'] ?? 'unknown');
    $paymentCounts[$pm] = ($paymentCounts[$pm] ?? 0) + 1;
}
$filteredAvg = $filteredCount > 0 ? $filteredRevenue / $filteredCount : 0;
arsort($paymentCounts);
$filteredTopPayment = $paymentCounts ? ucfirst(array_key_first($paymentCounts)) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bills - Cashier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --indigo-500: #4f46e5;
            --indigo-400: #6366f1;
            --teal-400: #14b8a6;
            --amber-400: #f59e0b;
            --rose-500: #f43f5e;
            --slate-900: #0f172a;
        }
        body.cashier-bills {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 28%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.08), transparent 28%),
                        #f3f4f6;
        }
        .cashier-bills .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }
        .cashier-bills .hero-header {
            background: linear-gradient(135deg, #4f46e5, #14b8a6);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }
        .cashier-bills .hero-metric {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .cashier-bills .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .cashier-bills .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-bills .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.05rem;
        }
        .mini-icon.total { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.today { background: linear-gradient(135deg, #ec4899, #f43f5e); }
        .mini-icon.top { background: linear-gradient(135deg, #f97316, #f59e0b); }

        .cashier-bills .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 12px 0 18px;
        }
        .cashier-bills .summary-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-bills .summary-icon {
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

        .cashier-bills .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .cashier-bills .filters-row input,
        .cashier-bills .filters-row select {
            height: 42px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0 12px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .cashier-bills .filters-row input:focus,
        .cashier-bills .filters-row select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }

        .cashier-bills .table.modern th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .cashier-bills .table.modern tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(20, 184, 166, 0.05));
        }

        .cashier-bills .pay-badge {
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
        .cashier-bills .pay-badge.cash { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .cashier-bills .pay-badge.card { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
        .cashier-bills .pay-badge.upi { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
        .cashier-bills .pay-badge.wallet { background: rgba(244, 63, 94, 0.12); color: #be123c; }
        .cashier-bills .pay-badge.esewa { background: rgba(34, 197, 94, 0.18); color: #166534; }
        .cashier-bills .pay-badge.bpay { background: rgba(249, 115, 22, 0.16); color: #9a3412; }

        .cashier-bills .subline {
            display: block;
            font-size: 0.82rem;
            color: #6b7280;
            margin-top: 2px;
        }
    </style>
</head>
<body class="cashier-bills">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cash-register"></i> Cashier Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="fas fa-receipt"></i> New Bill
                </a>
                <a href="bills.php" class="nav-item active">
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
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Billing History</p>
                    <h1>All Bills</h1>
                    <p class="subhead">Review, filter, and manage every bill you have processed.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub"><?php echo number_format($totalBills); ?> bills • Today Rs.<?php echo number_format($todayRevenue, 2); ?></div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon total"><i class="fas fa-receipt"></i></span>
                    <div>
                        <div class="summary-label">All Bills</div>
                        <div class="summary-value"><?php echo number_format($totalBills); ?></div>
                        <span class="subline">Complete history</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon revenue"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="summary-label">Total Revenue</div>
                        <div class="summary-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                        <span class="subline">All time</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon today"><i class="fas fa-calendar-day"></i></span>
                    <div>
                        <div class="summary-label">Today</div>
                        <div class="summary-value"><?php echo number_format($todayBills); ?> bills</div>
                        <span class="subline">Rs.<?php echo number_format($todayRevenue, 2); ?></span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon top"><i class="fas fa-credit-card"></i></span>
                    <div>
                        <div class="summary-label">Top Payment</div>
                        <div class="summary-value"><?php echo htmlspecialchars($filteredTopPayment); ?></div>
                        <span class="subline">Based on results</span>
                    </div>
                </div>
            </div>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filters</h3>
                </div>
                <form method="GET" class="filters-row">
                    <div>
                        <label for="search">Bill ID / Customer</label>
                        <input type="text" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div>
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div>
                        <label for="payment_method">Payment</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">All</option>
                            <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="esewa" <?php echo $payment_method === 'esewa' ? 'selected' : ''; ?>>Esewa</option>
                            <option value="bpay" <?php echo $payment_method === 'bpay' ? 'selected' : ''; ?>>B-Pay</option>
                        </select>
                    </div>
                    <div style="display:flex; align-items:flex-end; gap:8px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                        <a href="bills.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </div>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Bills</h3>
                </div>

                <?php if ($filteredCount > 0): ?>
                <div class="summary-row">
                    <div class="summary-card">
                        <span class="summary-icon revenue"><i class="fas fa-coins"></i></span>
                        <div>
                            <div class="summary-label">Revenue (results)</div>
                            <div class="summary-value">Rs.<?php echo number_format($filteredRevenue, 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon avg"><i class="fas fa-equals"></i></span>
                        <div>
                            <div class="summary-label">Average Bill</div>
                            <div class="summary-value">Rs.<?php echo number_format($filteredAvg, 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon count"><i class="fas fa-file-invoice"></i></span>
                        <div>
                            <div class="summary-label">Bills Listed</div>
                            <div class="summary-value"><?php echo $filteredCount; ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon pay"><i class="fas fa-credit-card"></i></span>
                        <div>
                            <div class="summary-label">Top Payment</div>
                            <div class="summary-value"><?php echo htmlspecialchars($filteredTopPayment); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-container">
                    <table class="table modern interactive-table">
                        <thead>
                            <tr>
                                <th>Bill ID</th>
                                <th>Customer</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bills)): ?>
                                <tr><td colspan="6" style="text-align:center; color:#666;">No bills found for your filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bills as $bill): ?>
                                    <tr class="table-row-interactive">
                                        <td><strong><?php echo htmlspecialchars($bill['bill_id']); ?></strong></td>
                                        <td>
                                            <?php $avatar = $bill['customer_image'] ? '../' . htmlspecialchars($bill['customer_image']) : '../assets/img/customer-placeholder.svg'; ?>
                                            <div class="customer-cell">
                                                <span class="avatar" style="background-image:url('<?php echo $avatar; ?>');"></span>
                                                <div class="customer-meta">
                                                    <div class="customer-name"><?php echo htmlspecialchars($bill['customer_name']); ?></div>
                                                    <div class="customer-sub"><?php echo ucfirst($bill['payment_method']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                                $pm = strtolower($bill['payment_method']);
                                                $pmIcon = 'fa-credit-card';
                                                if ($pm === 'cash') $pmIcon = 'fa-money-bill-wave';
                                                elseif ($pm === 'upi') $pmIcon = 'fa-mobile-alt';
                                                elseif ($pm === 'wallet') $pmIcon = 'fa-wallet';
                                                elseif ($pm === 'esewa') $pmIcon = 'fa-leaf';
                                                elseif ($pm === 'bpay') $pmIcon = 'fa-bolt';
                                            ?>
                                            <span class="pay-badge <?php echo $pm; ?>">
                                                <i class="fas <?php echo $pmIcon; ?>"></i> <?php echo htmlspecialchars($bill['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>Rs.<?php echo number_format($bill['final_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                                $billDate = strtotime($bill['date_time']);
                                                $timeAgoSeconds = time() - $billDate;
                                                if ($timeAgoSeconds < 60) $timeAgo = $timeAgoSeconds . 's ago';
                                                elseif ($timeAgoSeconds < 3600) $timeAgo = floor($timeAgoSeconds / 60) . 'm ago';
                                                elseif ($timeAgoSeconds < 86400) $timeAgo = floor($timeAgoSeconds / 3600) . 'h ago';
                                                else { $days = floor($timeAgoSeconds / 86400); $timeAgo = $days . 'd ago'; }
                                            ?>
                                            <div><?php echo date('M d, Y H:i', $billDate); ?></div>
                                            <span class="subline"><?php echo $timeAgo; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
                                                <a href="print_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-success btn-sm" target="_blank"><i class="fas fa-print"></i> Print</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

