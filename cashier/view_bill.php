<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$bill_id = $_GET['id'];

// Get bill details
$stmt = $pdo->prepare("
    SELECT b.*, u.username as cashier_name 
    FROM bills b 
    JOIN users u ON b.cashier_id = u.id 
    WHERE b.bill_id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

if (!$bill) {
    header('Location: dashboard.php');
    exit();
}

// Fetch cashier/store info for header branding
try {
    $cashierStmt = $pdo->prepare("SELECT username, store_image FROM users WHERE id = ? LIMIT 1");
    $cashierStmt->execute([$bill['cashier_id']]);
    $store = $cashierStmt->fetch();
} catch (PDOException $e) {
    $store = null;
}

$storeName = $store['username'] ?? 'Shop Billing System';
$storeImage = $store['store_image'] ?? null; 

// Get bill items
$stmt = $pdo->prepare("
    SELECT bi.*, p.name as product_name, c.name as category_name
    FROM bill_items bi 
    JOIN products p ON bi.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE bi.bill_id = ?
    ORDER BY bi.id
");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo $bill_id; ?> - Cashier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bill-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 30px;
            padding: 20px;
            border-bottom: 1px solid #e6eef6;
            background: linear-gradient(90deg, #ffffff, #fbfdff);
            border-radius: 8px;
        }
        .bill-header .store { display:flex; align-items:center; gap:16px; }
        .store-logo { width:80px;height:80px;border-radius:12px;overflow:hidden;border:1px solid #eef4fb; background:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; color:#111827; }
        .store-logo img { width:100%; height:100%; object-fit:cover; }
        .store-meta h2 { margin:0; font-size:1.25rem; color:#0f172a; }
        .store-meta p { margin:0; color:#6b7280; font-size:0.95rem; }
        .bill-meta { text-align:right; }
        .bill-meta .id { font-weight:700; color:#111827; }
        .bill-meta .date { color:#6b7280; font-size:0.95rem; }

        .bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .customer-avatar-row { display:flex; gap:16px; align-items:center; }
        .customer-avatar-lg { width:96px; height:96px; border-radius:12px; overflow:hidden; border:1px solid #eef4fb; }
        .customer-avatar-lg img { width:100%; height:100%; object-fit:cover; }

        .bill-items-table table { width:100%; border-collapse:collapse; }
        .bill-items-table th, .bill-items-table td { padding:12px; border-bottom:1px solid #eef2f7; }
        .bill-items-table th { background:#fbfdff; text-align:left; color:#0f172a; }

        .bill-totals { max-width:420px; margin-left:auto; }
        .total-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eef2f7; }
        .total-row:last-child { font-weight:800; font-size:1.15rem; color:#111827; border-bottom:none; }

        @media (max-width: 800px) {
            .bill-header { flex-direction: column; align-items: flex-start; }
            .bill-meta { text-align:left; margin-top:12px; }
            .bill-details { grid-template-columns: 1fr; }
            .bill-totals { width:100%; margin-left:0; }
        }
        
        .bill-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .bill-items-table {
            margin-bottom: 30px;
        }
        
        .bill-totals {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .total-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: #2c3e50;
        }
        
        .print-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }

        /* Payment badge styling */
        .pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
            text-transform: capitalize;
        }
        .pay-badge.cash { background: rgba(34, 197, 94, 0.14); color: #166534; }
        .pay-badge.card { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; }
        .pay-badge.upi { background: rgba(14, 165, 233, 0.14); color: #0ea5e9; }
        .pay-badge.wallet { background: rgba(244, 63, 94, 0.14); color: #be123c; }
        .pay-badge.esewa { background: rgba(34, 197, 94, 0.18); color: #166534; }
        .pay-badge.bpay { background: rgba(249, 115, 22, 0.18); color: #9a3412; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar no-print">
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
            <div class="header no-print">
                <h1>Bill #<?php echo htmlspecialchars($bill_id); ?></h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Bill Content -->
            <div class="card">
                <!-- Bill Header -->
                <div class="bill-header">
                    <div class="store">
                        <?php if ($storeImage): ?>
                            <div class="store-logo"><img src="../<?php echo htmlspecialchars($storeImage); ?>" alt="Store logo"></div>
                        <?php else: ?>
                            <div class="store-logo"><?php echo substr(htmlspecialchars($storeName), 0, 2); ?></div>
                        <?php endif; ?>
                        <div class="store-meta">
                            <h2><?php echo htmlspecialchars($storeName); ?></h2>
                            <p>Your Trusted Shopping Partner</p>
                        </div>
                    </div>

                    <div class="bill-meta">
                        <div class="id">Bill #: <strong><?php echo htmlspecialchars($bill_id); ?></strong></div>
                        <div class="date"><?php echo date('d M Y, h:i A', strtotime($bill['date_time'])); ?></div>
                        <div style="margin-top:8px;">
                            <img src="https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=<?php echo urlencode('bill:'.$bill_id); ?>" alt="QR" style="width:60px;height:60px;border:0">
                        </div>
                    </div>
                </div>

                <!-- Bill Details -->
                <div class="bill-details">
                    <div class="bill-info">
                        <h4><i class="fas fa-info-circle"></i> Bill Information</h4>
                        <p><strong>Bill ID:</strong> <?php echo htmlspecialchars($bill_id); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($bill['date_time'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($bill['date_time'])); ?></p>
                        <p><strong>Cashier:</strong> <?php echo htmlspecialchars($bill['cashier_name']); ?></p>
                    </div>
                    
                    <div class="bill-info">
                        <h4><i class="fas fa-user"></i> Customer Information</h4>
                        <div class="customer-avatar-row">
                            <div class="customer-avatar-lg">
                                <img src="../<?php echo $bill['customer_image'] ? htmlspecialchars($bill['customer_image']) : 'assets/img/customer-placeholder.svg'; ?>" alt="Customer photo">
                            </div>
                            <div>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($bill['customer_name']); ?></p>
                                <?php 
                                    $pm = strtolower($bill['payment_method']);
                                    $pmIcon = 'fa-credit-card';
                                    if ($pm === 'cash') $pmIcon = 'fa-money-bill-wave';
                                    elseif ($pm === 'upi') $pmIcon = 'fa-mobile-alt';
                                    elseif ($pm === 'wallet') $pmIcon = 'fa-wallet';
                                    elseif ($pm === 'esewa') $pmIcon = 'fa-leaf';
                                    elseif ($pm === 'bpay') $pmIcon = 'fa-bolt';
                                ?>
                                <p><strong>Payment Method:</strong> 
                                    <span class="pay-badge <?php echo $pm; ?>">
                                        <i class="fas <?php echo $pmIcon; ?>"></i> <?php echo ucfirst($bill['payment_method']); ?>
                                    </span>
                                </p>
                                <p><strong>Bill Status:</strong> <span style="color: #27ae60; font-weight: bold;">Completed</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bill Items -->
                <div class="bill-items-table">
                    <h4><i class="fas fa-list"></i> Items Purchased</h4>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price (Rs.)</th>
                                    <th>Qty</th>
                                    <th>Subtotal (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bill_items as $index => $item): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bill Totals -->
                <div class="bill-totals">
                    <h4><i class="fas fa-calculator"></i> Bill Summary</h4>
                    
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>RsRs.<?php echo number_format($bill['total_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span>Tax (<?php echo $bill['tax_amount'] > 0 ? round(($bill['tax_amount'] / $bill['total_amount']) * 100, 2) : 0; ?>%):</span>
                        <span>Rs.<?php echo number_format($bill['tax_amount'], 2); ?></span>
                    </div>
                    
                    <?php if ($bill['discount_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>-Rs.<?php echo number_format($bill['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row">
                        <span>Final Amount:</span>
                        <span>Rs.<?php echo number_format($bill['final_amount'], 2); ?></span>
                    </div>
                    
                    <div class="total-row" style="border-bottom:none; padding-top:12px;">
                        <span>Payment Method:</span>
                        <span class="pay-badge <?php echo $pm; ?>">
                            <i class="fas <?php echo $pmIcon; ?>"></i> <?php echo ucfirst($bill['payment_method']); ?>
                        </span>
                    </div>
                </div>

                <!-- Thank You Message -->
                <div style="text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #27ae60; margin-bottom: 10px;">
                        <i class="fas fa-heart"></i> Thank You for Shopping!
                    </h4>
                    <p style="color: #7f8c8d; margin-bottom: 5px;">
                        We appreciate your business and hope to see you again soon.
                    </p>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">
                        For any queries, please contact us at support@shopbilling.com
                    </p>
                </div>

                <!-- Print Actions -->
                <div class="print-actions no-print">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Bill
                    </button>
                    
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <a href="billing.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create New Bill
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print functionality (optional)
        // window.onload = function() {
        //     // Uncomment the line below to auto-print when page loads
        //     // window.print();
        // };
    </script>
</body>
</html>
