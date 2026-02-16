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

// Get search parameters
$search_type = $_GET['search_type'] ?? 'all'; // all, bill_id, customer_name, date_range, amount_range, payment
$bill_id_search = trim($_GET['bill_id'] ?? '');
$customer_name_search = trim($_GET['customer_name'] ?? '');
$search_date_from = $_GET['date_from'] ?? '';
$search_date_to = $_GET['date_to'] ?? '';
$amount_from = $_GET['amount_from'] ?? '';
$amount_to = $_GET['amount_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';

// Build query
$where = ["cashier_id = ?"];
$params = [$cashier_id];

// Add filters based on search type
if ($search_type === 'bill_id' && !empty($bill_id_search)) {
    $where[] = "bill_id LIKE ?";
    $params[] = '%' . $bill_id_search . '%';
} elseif ($search_type === 'customer_name' && !empty($customer_name_search)) {
    $where[] = "customer_name LIKE ?";
    $params[] = '%' . $customer_name_search . '%';
} elseif ($search_type === 'date_range') {
    if (!empty($search_date_from)) {
        $where[] = "DATE(date_time) >= ?";
        $params[] = $search_date_from;
    }
    if (!empty($search_date_to)) {
        $where[] = "DATE(date_time) <= ?";
        $params[] = $search_date_to;
    }
} elseif ($search_type === 'amount_range') {
    if (!empty($amount_from)) {
        $where[] = "final_amount >= ?";
        $params[] = (float)$amount_from;
    }
    if (!empty($amount_to)) {
        $where[] = "final_amount <= ?";
        $params[] = (float)$amount_to;
    }
} elseif ($search_type === 'all') {
    // Search in all fields
    if (!empty($bill_id_search)) {
        $where[] = "(bill_id LIKE ? OR customer_name LIKE ?)";
        $params[] = '%' . $bill_id_search . '%';
        $params[] = '%' . $bill_id_search . '%';
    }
    if (!empty($search_date_from)) {
        $where[] = "DATE(date_time) >= ?";
        $params[] = $search_date_from;
    }
    if (!empty($search_date_to)) {
        $where[] = "DATE(date_time) <= ?";
        $params[] = $search_date_to;
    }
    if (!empty($amount_from)) {
        $where[] = "final_amount >= ?";
        $params[] = (float)$amount_from;
    }
    if (!empty($amount_to)) {
        $where[] = "final_amount <= ?";
        $params[] = (float)$amount_to;
    }
    if (!empty($payment_method)) {
        $where[] = "payment_method = ?";
        $params[] = $payment_method;
    }
}

// Common filters apply to all
if (!empty($payment_method) && $search_type !== 'all') {
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

// Fetch bills with search results
$stmt = $pdo->prepare("
    SELECT bill_id, customer_name, payment_method, final_amount, date_time, customer_image
    FROM bills
    WHERE $whereSql
    ORDER BY date_time DESC
    LIMIT 500
");
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Calculate summaries
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
    <title>Advanced Search Bills - Cashier Dashboard</title>
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
        body.cashier-search {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 28%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.08), transparent 28%),
                        #f3f4f6;
        }
        .cashier-search .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }
        .cashier-search .hero-header {
            background: linear-gradient(135deg, #4f46e5, #14b8a6);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }
        .cashier-search .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .cashier-search .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-search .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.05rem;
        }
        .mini-icon.search { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.avg { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }
        .mini-icon.payment { background: linear-gradient(135deg, #f97316, #f59e0b); }

        .cashier-search .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 12px 0 18px;
        }
        .cashier-search .summary-card {
            background: white;
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-search .summary-icon {
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

        .cashier-search .search-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .search-tabs .tab-button {
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            background: white;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .search-tabs .tab-button:hover {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .search-tabs .tab-button.active {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-color: #4f46e5;
            color: white;
        }

        .cashier-search .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .cashier-search .search-form input,
        .cashier-search .search-form select {
            height: 42px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0 12px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .cashier-search .search-form input:focus,
        .cashier-search .search-form select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }

        .cashier-search .table.modern th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .cashier-search .table.modern tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(20, 184, 166, 0.05));
        }

        .cashier-search .pay-badge {
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
        .cashier-search .pay-badge.cash { background: rgba(34, 197, 94, 0.12); color: #15803d; }
        .cashier-search .pay-badge.card { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
        .cashier-search .pay-badge.upi { background: rgba(14, 165, 233, 0.12); color: #0ea5e9; }
        .cashier-search .pay-badge.esewa { background: rgba(34, 197, 94, 0.18); color: #166534; }
        .cashier-search .pay-badge.bpay { background: rgba(249, 115, 22, 0.16); color: #9a3412; }

        .cashier-search .subline {
            display: block;
            font-size: 0.82rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        .empty-message i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 16px;
            display: block;
        }
    </style>
</head>
<body class="cashier-search">
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
                <a href="bills.php" class="nav-item">
                    <i class="fas fa-list"></i> All Bills
                </a>
                <a href="search.php" class="nav-item active">
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
                    <p class="eyebrow">Advanced Search</p>
                    <h1>Search Bills</h1>
                    <p class="subhead">Find bills by ID, customer, date, amount, or payment method.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub"><?php echo number_format($totalBills); ?> bills all-time</div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon search"><i class="fas fa-search"></i></span>
                    <div>
                        <div class="summary-label">Search Results</div>
                        <div class="summary-value"><?php echo number_format($filteredCount); ?></div>
                        <span class="subline">Bills found</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon revenue"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="summary-label">Result Revenue</div>
                        <div class="summary-value">Rs.<?php echo number_format($filteredRevenue, 2); ?></div>
                        <span class="subline">From results</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon avg"><i class="fas fa-equals"></i></span>
                    <div>
                        <div class="summary-label">Average Bill</div>
                        <div class="summary-value">Rs.<?php echo number_format($filteredAvg, 2); ?></div>
                        <span class="subline">Result average</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon payment"><i class="fas fa-credit-card"></i></span>
                    <div>
                        <div class="summary-label">Top Payment</div>
                        <div class="summary-value"><?php echo htmlspecialchars($filteredTopPayment); ?></div>
                        <span class="subline">In results</span>
                    </div>
                </div>
            </div>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Search Filters</h3>
                </div>

                <!-- Search Type Tabs -->
                <div class="search-tabs">
                    <button type="button" class="tab-button <?php echo $search_type === 'all' ? 'active' : ''; ?>" onclick="switchTab('all')">
                        <i class="fas fa-asterisk"></i> All Fields
                    </button>
                    <button type="button" class="tab-button <?php echo $search_type === 'bill_id' ? 'active' : ''; ?>" onclick="switchTab('bill_id')">
                        <i class="fas fa-barcode"></i> Bill ID
                    </button>
                    <button type="button" class="tab-button <?php echo $search_type === 'customer_name' ? 'active' : ''; ?>" onclick="switchTab('customer_name')">
                        <i class="fas fa-user"></i> Customer
                    </button>
                    <button type="button" class="tab-button <?php echo $search_type === 'date_range' ? 'active' : ''; ?>" onclick="switchTab('date_range')">
                        <i class="fas fa-calendar"></i> Date Range
                    </button>
                    <button type="button" class="tab-button <?php echo $search_type === 'amount_range' ? 'active' : ''; ?>" onclick="switchTab('amount_range')">
                        <i class="fas fa-coins"></i> Amount Range
                    </button>
                </div>

                <!-- Search Forms -->
                <form method="GET" id="searchForm">
                    <input type="hidden" name="search_type" id="searchType" value="<?php echo htmlspecialchars($search_type); ?>">

                    <!-- All Fields Tab -->
                    <div id="all-fields" class="search-tab-content" style="<?php echo $search_type !== 'all' ? 'display:none;' : ''; ?>">
                        <div class="search-form">
                            <div>
                                <label for="all_search">Bill ID or Customer Name</label>
                                <input type="text" id="all_search" name="bill_id" placeholder="Search..." value="<?php echo htmlspecialchars($bill_id_search); ?>">
                            </div>
                            <div>
                                <label for="all_date_from">From Date</label>
                                <input type="date" id="all_date_from" name="date_from" value="<?php echo htmlspecialchars($search_date_from); ?>">
                            </div>
                            <div>
                                <label for="all_date_to">To Date</label>
                                <input type="date" id="all_date_to" name="date_to" value="<?php echo htmlspecialchars($search_date_to); ?>">
                            </div>
                            <div>
                                <label for="all_amount_from">Min Amount (Rs.)</label>
                                <input type="number" id="all_amount_from" name="amount_from" step="0.01" placeholder="Min" value="<?php echo htmlspecialchars($amount_from); ?>">
                            </div>
                            <div>
                                <label for="all_amount_to">Max Amount (Rs.)</label>
                                <input type="number" id="all_amount_to" name="amount_to" step="0.01" placeholder="Max" value="<?php echo htmlspecialchars($amount_to); ?>">
                            </div>
                            <div>
                                <label for="all_payment">Payment Method</label>
                                <select id="all_payment" name="payment_method">
                                    <option value="">All</option>
                                    <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="upi" <?php echo $payment_method === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                    <option value="esewa" <?php echo $payment_method === 'esewa' ? 'selected' : ''; ?>>Esewa</option>
                                    <option value="bpay" <?php echo $payment_method === 'bpay' ? 'selected' : ''; ?>>B-Pay</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Bill ID Tab -->
                    <div id="bill_id-fields" class="search-tab-content" style="<?php echo $search_type !== 'bill_id' ? 'display:none;' : ''; ?>">
                        <div class="search-form">
                            <div style="grid-column: 1 / -1;">
                                <label for="bill_search">Enter Bill ID</label>
                                <input type="text" id="bill_search" name="bill_id" placeholder="e.g., BIL-001, BIL-002..." value="<?php echo htmlspecialchars($bill_id_search); ?>">
                                <small style="color:#6b7280;">Partial matches are supported</small>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Name Tab -->
                    <div id="customer_name-fields" class="search-tab-content" style="<?php echo $search_type !== 'customer_name' ? 'display:none;' : ''; ?>">
                        <div class="search-form">
                            <div style="grid-column: 1 / -1;">
                                <label for="customer_search">Customer Name</label>
                                <input type="text" id="customer_search" name="customer_name" placeholder="e.g., John Doe, Ramesh..." value="<?php echo htmlspecialchars($customer_name_search); ?>">
                                <small style="color:#6b7280;">Search by customer name</small>
                            </div>
                        </div>
                    </div>

                    <!-- Date Range Tab -->
                    <div id="date_range-fields" class="search-tab-content" style="<?php echo $search_type !== 'date_range' ? 'display:none;' : ''; ?>">
                        <div class="search-form">
                            <div>
                                <label for="date_from">From Date</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($search_date_from); ?>">
                            </div>
                            <div>
                                <label for="date_to">To Date</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($search_date_to); ?>">
                            </div>
                            <div>
                                <label for="date_payment">Payment Method</label>
                                <select id="date_payment" name="payment_method">
                                    <option value="">All</option>
                                    <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="upi" <?php echo $payment_method === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                    <option value="esewa" <?php echo $payment_method === 'esewa' ? 'selected' : ''; ?>>Esewa</option>
                                    <option value="bpay" <?php echo $payment_method === 'bpay' ? 'selected' : ''; ?>>B-Pay</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Range Tab -->
                    <div id="amount_range-fields" class="search-tab-content" style="<?php echo $search_type !== 'amount_range' ? 'display:none;' : ''; ?>">
                        <div class="search-form">
                            <div>
                                <label for="amount_from">Minimum Amount (Rs.)</label>
                                <input type="number" id="amount_from" name="amount_from" step="0.01" placeholder="0" value="<?php echo htmlspecialchars($amount_from); ?>">
                            </div>
                            <div>
                                <label for="amount_to">Maximum Amount (Rs.)</label>
                                <input type="number" id="amount_to" name="amount_to" step="0.01" placeholder="10000" value="<?php echo htmlspecialchars($amount_to); ?>">
                            </div>
                            <div>
                                <label for="amount_payment">Payment Method</label>
                                <select id="amount_payment" name="payment_method">
                                    <option value="">All</option>
                                    <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="upi" <?php echo $payment_method === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                    <option value="esewa" <?php echo $payment_method === 'esewa' ? 'selected' : ''; ?>>Esewa</option>
                                    <option value="bpay" <?php echo $payment_method === 'bpay' ? 'selected' : ''; ?>>B-Pay</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; margin-top:16px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <a href="search.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear All</a>
                    </div>
                </form>
            </div>

            <!-- Results -->
            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Search Results</h3>
                </div>

                <?php if ($filteredCount > 0): ?>
                <div class="summary-row">
                    <div class="summary-card">
                        <span class="summary-icon count"><i class="fas fa-file-invoice"></i></span>
                        <div>
                            <div class="summary-label">Total Bills Found</div>
                            <div class="summary-value"><?php echo $filteredCount; ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon revenue"><i class="fas fa-coins"></i></span>
                        <div>
                            <div class="summary-label">Total Revenue</div>
                            <div class="summary-value">Rs.<?php echo number_format($filteredRevenue, 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon avg"><i class="fas fa-equals"></i></span>
                        <div>
                            <div class="summary-label">Average Amount</div>
                            <div class="summary-value">Rs.<?php echo number_format($filteredAvg, 2); ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <span class="summary-icon pay"><i class="fas fa-credit-card"></i></span>
                        <div>
                            <div class="summary-label">Top Payment Method</div>
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
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bills)): ?>
                                <tr><td colspan="6"><div class="empty-message"><i class="fas fa-search"></i><p>No bills match your search criteria.</p></div></td></tr>
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
                                                elseif ($pm === 'card') $pmIcon = 'fa-credit-card';
                                                elseif ($pm === 'esewa') $pmIcon = 'fa-leaf';
                                                elseif ($pm === 'bpay') $pmIcon = 'fa-bolt';
                                            ?>
                                            <span class="pay-badge <?php echo $pm; ?>">
                                                <i class="fas <?php echo $pmIcon; ?>"></i> <?php echo htmlspecialchars($bill['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><strong>Rs.<?php echo number_format($bill['final_amount'], 2); ?></strong></td>
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

    <script>
        function switchTab(tab) {
            // Hide all content
            document.querySelectorAll('.search-tab-content').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show selected content
            document.getElementById(tab + '-fields').style.display = 'block';
            
            // Update active button
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update hidden input
            document.getElementById('searchType').value = tab;
        }
    </script>
</body>
</html>
