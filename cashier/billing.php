<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is cashier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../index.php');
    exit();
}
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                $product_id = $_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity <= 0) {
                    $error = 'Quantity must be greater than 0'; #If the quantity is less than zero(0).
                } else {
                    // Check stock availability
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if ($product && $product['stock'] >= $quantity) {
                        // Add to session cart
                        if (!isset($_SESSION['cart'])) {
                            $_SESSION['cart'] = [];
                        }
                        
                        $item_key = $product_id;
                        if (isset($_SESSION['cart'][$item_key])) {
                            $_SESSION['cart'][$item_key]['quantity'] += $quantity;
                        } else {
                            $_SESSION['cart'][$item_key] = [
                                'id' => $product['id'],
                                'name' => $product['name'],
                                'price' => $product['price'],
                                'quantity' => $quantity,
                                'subtotal' => $product['price'] * $quantity
                            ];
                        }
                        $message = 'Item added to bill';
                    } else {
                        $error = 'Insufficient stock available';  //Stock is not available.
                    }
                }
                break;
                
            case 'remove_item':
                $product_id = $_POST['product_id'];
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]);
                    $message = 'Item removed from bill';
                }
                break;
                
            case 'update_quantity':
                $product_id = $_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$product_id]);
                } else {
                    // Check stock availability
                    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if ($product && $product['stock'] >= $quantity) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        $_SESSION['cart'][$product_id]['subtotal'] = $_SESSION['cart'][$product_id]['price'] * $quantity;
                    } else {
                        $error = 'Insufficient stock available';
                    }
                }
                break;
                
            case 'finalize_bill':
                if (empty($_SESSION['cart'])) {
                    $error = 'Please add items to the bill first';
                } else {
                    $customer_name = trim($_POST['customer_name']) ?: 'Walk-in Customer';
                    $customer_phone = null;
                    if (isset($_POST['customer_phone'])) {
                        $phoneRaw = trim($_POST['customer_phone']);
                        if ($phoneRaw !== '') {
                            if (preg_match('/^[0-9+\-\s()]{3,20}$/', $phoneRaw)) {
                                $customer_phone = $phoneRaw;
                            } else {
                                $error = 'Customer phone contains invalid characters';
                                break;
                            }
                        }
                    }

                    $payment_method = $_POST['payment_method'];
                    $tax_rate = (float)$_POST['tax_rate'];
                    $discount_amount = (float)$_POST['discount_amount'];
                    $customerImagePath = null;

                    // Optional customer image upload
                    if (isset($_FILES['customer_image']) && $_FILES['customer_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($_FILES['customer_image']['error'] === UPLOAD_ERR_OK) {
                            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            $extension = strtolower(pathinfo($_FILES['customer_image']['name'], PATHINFO_EXTENSION));
                            $maxSize = 2 * 1024 * 1024; // 2MB

                            if (!in_array($extension, $allowedExtensions, true)) {
                                $error = 'Customer photo must be an image (JPG, PNG, GIF, WEBP)';
                                break;
                            }

                            if ($_FILES['customer_image']['size'] > $maxSize) {
                                $error = 'Customer photo must be smaller than 2MB';
                                break;
                            }

                            $uploadDir = __DIR__ . '/../uploads/customers/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }

                            $uniqueName = 'cust-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
                            $destination = $uploadDir . $uniqueName;

                            if (move_uploaded_file($_FILES['customer_image']['tmp_name'], $destination)) {
                                $customerImagePath = 'uploads/customers/' . $uniqueName;
                            } else {
                                $error = 'Could not save the customer photo. Please try again.';
                                break;
                            }
                        } else {
                            $error = 'Error uploading customer photo. Please try again.';
                            break;
                        }
                    }
                    
                    // Calculate totals
                    $subtotal = 0;
                    foreach ($_SESSION['cart'] as $item) {
                        $subtotal += $item['subtotal'];
                    }
                    
                    $tax_amount = ($subtotal * $tax_rate) / 100;
                    $total_amount = $subtotal + $tax_amount - $discount_amount;
                    
                    // Generate bill ID
                    $bill_id = 'BILL' . date('YmdHis') . rand(100, 999);
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Insert bill
                        $stmt = $pdo->prepare("
                            INSERT INTO bills (bill_id, cashier_id, customer_name, customer_image, total_amount, tax_amount, discount_amount, final_amount, payment_method) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$bill_id, $_SESSION['user_id'], $customer_name, $customerImagePath, $subtotal, $tax_amount, $discount_amount, $total_amount, $payment_method]);
                        
                        // Insert bill items and update stock
                        foreach ($_SESSION['cart'] as $item) {
                            $stmt = $pdo->prepare("
                                INSERT INTO bill_items (bill_id, product_id, quantity, price, subtotal) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$bill_id, $item['id'], $item['quantity'], $item['price'], $item['subtotal']]);
                            
                            // Update product stock
                            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                            $stmt->execute([$item['quantity'], $item['id']]);
                        }
                        
                        $pdo->commit();
                        
                        // Clear cart
                        unset($_SESSION['cart']);
                        
                        // Redirect to bill view
                        header("Location: view_bill.php?id=" . $bill_id);
                        exit();
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to create bill: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get products for dropdown
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.stock > 0 
    ORDER BY p.name
");
$products = $stmt->fetchAll();

// Calculate cart totals
$cart_subtotal = 0;
$cart_total_items = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_subtotal += $item['subtotal'];
        $cart_total_items += $item['quantity'];
    }
}

// Defaults for summary preview
$defaultTaxRate = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 18;
$defaultDiscount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
$estimatedTax = ($cart_subtotal * $defaultTaxRate) / 100;
$estimatedTotal = max($cart_subtotal + $estimatedTax - $defaultDiscount, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Bill - Cashier Dashboard</title>
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
        body.cashier-billing {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 28%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.08), transparent 28%),
                        #f3f4f6;
        }
        .cashier-billing .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }
        .cashier-billing .hero-header {
            background: linear-gradient(135deg, #4f46e5, #14b8a6);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }
        .cashier-billing .hero-metric {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .cashier-billing .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .cashier-billing .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-billing .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.05rem;
        }
        .mini-icon.cart { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.subtotal { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.tax { background: linear-gradient(135deg, #38bdf8, #0ea5e9); }
        .mini-icon.total { background: linear-gradient(135deg, #f97316, #f59e0b); }

        .cashier-billing .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .cashier-billing .qa-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid rgba(99, 102, 241, 0.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
        }
        .cashier-billing .qa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        }

        .cashier-billing .billing-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }
        @media (max-width: 992px) {
            .cashier-billing .billing-container {
                grid-template-columns: 1fr;
            }
        }

        .cashier-billing .table.modern th {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.12), rgba(20, 184, 166, 0.12));
            border-bottom: none;
        }
        .cashier-billing .table.modern tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(20, 184, 166, 0.05));
        }
        .cashier-billing .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
        }
        .cashier-billing .pill.low {
            background: rgba(244, 63, 94, 0.12);
            color: #be123c;
        }
        .cashier-billing .pill.ok {
            background: rgba(34, 197, 94, 0.14);
            color: #166534;
        }
        .cashier-billing .inline-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cashier-billing .inline-form input[type="number"] {
            width: 90px;
        }
        .cashier-billing .hint {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .cashier-billing .helper-box {
            background: white;
            border: 1px dashed rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.04);
        }
    </style>
</head>
<body class="cashier-billing">
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
                <a href="billing.php" class="nav-item active">
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
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Billing Station</p>
                    <h1>Create New Bill</h1>
                    <p class="subhead">Add items faster, keep totals clear, and close payments confidently.</p>
                    <!-- <div class="quick-actions">
                        <a href="billing.php" class="qa-card">
                            <span class="mini-icon cart"><i class="fas fa-plus"></i></span>
                            <div>
                                <div class="summary-label">New Bill</div>
                                <div class="summary-value">Start fresh</div>
                            </div>
                        </a>
                        <a href="bills.php" class="qa-card">
                            <span class="mini-icon subtotal"><i class="fas fa-list"></i></span>
                            <div>
                                <div class="summary-label">All Bills</div>
                                <div class="summary-value">Review history</div>
                            </div>
                        </a>
                        <a href="search.php" class="qa-card">
                            <span class="mini-icon tax"><i class="fas fa-search"></i></span>
                            <div>
                                <div class="summary-label">Find Bill</div>
                                <div class="summary-value">Search quickly</div>
                            </div>
                        </a>
                    </div> -->
                </div>
                <div class="hero-metric">
                    <div class="metric-label">In Cart</div>
                    <div class="metric-value"><?php echo number_format($cart_total_items); ?> items</div>
                    <div class="metric-sub">Rs.<?php echo number_format($cart_subtotal, 2); ?> subtotal</div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon cart"><i class="fas fa-shopping-cart"></i></span>
                    <div>
                        <div class="summary-label">Items in cart</div>
                        <div class="summary-value"><?php echo number_format($cart_total_items); ?></div>
                        <span class="subline">Ready to bill</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon subtotal"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="summary-label">Subtotal</div>
                        <div class="summary-value">Rs.<?php echo number_format($cart_subtotal, 2); ?></div>
                        <span class="subline">Before tax/discount</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon tax"><i class="fas fa-receipt"></i></span>
                    <div>
                        <div class="summary-label">Est. Tax (<?php echo $defaultTaxRate; ?>%)</div>
                        <div class="summary-value">Rs.<?php echo number_format($estimatedTax, 2); ?></div>
                        <span class="subline">Preview</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon total"><i class="fas fa-check-circle"></i></span>
                    <div>
                        <div class="summary-label">Est. Payable</div>
                        <div class="summary-value">Rs.<?php echo number_format($estimatedTotal, 2); ?></div>
                        <span class="subline">After tax & discount</span>
                    </div>
                </div>
            </div>

            <div class="billing-container">
                <!-- Add Items Section -->
                <div class="bill-items">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-plus"></i> Add Items to Bill</h3>
                        </div>
                        
                        <!-- Add Item Form -->
                        <form method="POST" class="form-row">
                            <input type="hidden" name="action" value="add_item">
                            
                            <div class="form-group">
                                <label for="product_id">Select Product</label>
                                <select id="product_id" name="product_id" required>
                                    <option value="">Choose a product...</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> 
                                            (Stock: <?php echo $product['stock']; ?>) - 
                                            Rs.<?php echo number_format($product['price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="text" id="quantity" name="quantity" min="1" value="1" required>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add to Bill
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Bill Items List -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Bill Items (<?php echo $cart_total_items; ?> items)</h3>
                        </div>
                        
                        <?php if (empty($_SESSION['cart'])): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                No items in bill. Start adding products above.
                            </p>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table modern interactive-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>Rs.<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="action" value="update_quantity">
                                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                           min="1" onchange="this.form.submit()">
                                                </form>
                                            </td>
                                            <td><?php echo number_format($item['subtotal'], 2); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="remove_item">
                                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bill Summary -->
                <div class="bill-summary">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calculator"></i> Bill Summary</h3>
                        </div>
                        
                        <div class="bill-item">
                            <span>Subtotal:</span>
                            <span>Rs.<?php echo number_format($cart_subtotal, 2); ?></span>
                        </div>
                        
                        <div class="bill-item">
                            <span>Total Items:</span>
                            <span><?php echo $cart_total_items; ?></span>
                        </div>
                        <div class="bill-item">
                            <span>Est. Tax (<span id="taxRateLabel"><?php echo $defaultTaxRate; ?></span>%):</span>
                            <span id="estTax">Rs.<?php echo number_format($estimatedTax, 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span>Est. Discount:</span>
                            <span id="estDiscount">Rs.<?php echo number_format($defaultDiscount, 2); ?></span>
                        </div>
                        <div class="bill-item" style="font-weight:700;">
                            <span>Est. Payable:</span>
                            <span id="estTotal">Rs.<?php echo number_format($estimatedTotal, 2); ?></span>
                        </div>
                        
                        <hr style="margin: 15px 0;">
                        
                        <!-- Finalize Bill Form -->
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <form method="POST" enctype="multipart/form-data" class="finalize-bill-form">
                                <input type="hidden" name="action" value="finalize_bill">
                                
                                <div class="form-group">
                                    <label for="customer_name">Customer Name</label>
                                    <input type="text" id="customer_name" name="customer_name" 
                                           placeholder="Walk-in Customer">
                                </div>

                                <div class="form-group">
                                    <label for="customer_image">Customer Photo (optional)</label>
                                    <div class="customer-upload">
                                        <div class="avatar-preview" id="avatarPreview">
                                            <img src="../assets/img/customer-placeholder.svg" alt="Customer avatar preview">
                                        </div>
                                        <input type="file" id="customer_image" name="customer_image" accept="image/*">
                                        <small class="text-muted">JPG/PNG/WEBP, max 2MB</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                     <label for="customer_phone">Customer Phone (optional)</label>
                                     <input type="tel" id="customer_phone" name="customer_phone" 
                                           placeholder="e.g. +9779812345678" value="<?php echo isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : ''; ?>">
                                  </div>

                                  <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="esewa">Esewa</option>
                                        <option value="bpay">B-Pay</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tax_rate">Tax Rate (%)</label>
                                    <input type="number" id="tax_rate" name="tax_rate" 
                                           value="<?php echo $defaultTaxRate; ?>" min="0" max="100" step="0.01">
                                </div>
                                
                                <div class="form-group">
                                    <label for="discount_amount">Discount Amount (Rs.)</label>
                                    <input type="number" id="discount_amount" name="discount_amount" 
                                           value="<?php echo $defaultDiscount; ?>" min="0" step="0.01">
                                </div>
                                
                                <div class="helper-box hint">
                                    Tip: Set tax/discount before finalizing. Totals above update instantly as you change values.
                                </div>

                                <style>
                                    /* QR box styles */
                                    .qr-controls { display:flex; align-items:center; gap:10px; margin-top:6px; }
                                    .qr-box { display:flex; flex-direction:column; align-items:center; gap:8px; margin-top:10px; }
                                    .qr-box img { width:160px; height:160px; border-radius:8px; border:1px solid #eef2f7; background:#fff; }
                                    .btn-qr { padding:6px 10px; border-radius:8px; border:1px solid rgba(99,102,241,0.12); background:#fff; cursor:pointer; }
                                    .btn-qr:active { transform:translateY(1px); }
                                </style>

                                <!-- <div class="form-group">
                                    <label>Payment QR (optional)</label>
                                    <div class="qr-controls">
                                        <button type="button" id="toggleQR" class="btn-qr"><?php echo ($cart_total_items > 0) ? 'Hide QR' : 'Show QR'; ?></button>
                                        <small class="hint">Generate a QR that encodes payment method and amount (useful for eSewa/B-Pay).</small>
                                    </div> -->

                                    <!-- <div id="qrContainer" class="qr-box" style="<?php echo ($cart_total_items > 0) ? 'display:flex;' : 'display:none;'; ?>">
                                        <img id="paymentQR" src="" alt="Payment QR">
                                        <div>
                                            <a id="downloadQR" href="#" download="payment-qr.png" class="btn btn-secondary btn-sm">Download QR</a>
                                            <button type="button" id="refreshQR" class="btn btn-outline btn-sm" style="margin-left:8px;">Refresh</button>
                                        </div>
                                    </div> -->

                                    <input type="hidden" id="computedTotal" name="computed_total" value="<?php echo number_format($estimatedTotal, 2, '.', ''); ?>">
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-check"></i> Finalize Bill
                                </button>
                            </form>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                Add items to see bill summary
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit quantity changes + live preview for totals and avatar
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('input[name="quantity"]');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            const customerImageInput = document.getElementById('customer_image');
            const avatarPreview = document.getElementById('avatarPreview').querySelector('img');

            if (customerImageInput && avatarPreview) {
                customerImageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatarPreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                });
            }

            // Live estimated totals
            const subtotal = parseFloat('<?php echo $cart_subtotal; ?>') || 0;
            const taxInput = document.getElementById('tax_rate');
            const discountInput = document.getElementById('discount_amount');
            const estTax = document.getElementById('estTax');
            const estDiscount = document.getElementById('estDiscount');
            const estTotal = document.getElementById('estTotal');
            const taxLabel = document.getElementById('taxRateLabel');

            function recalc() {
                const taxRate = parseFloat(taxInput?.value || '0') || 0;
                const discount = parseFloat(discountInput?.value || '0') || 0;
                const taxAmount = (subtotal * taxRate) / 100;
                const total = Math.max(subtotal + taxAmount - discount, 0);

                if (taxLabel) taxLabel.textContent = taxRate.toFixed(2).replace(/\.?0+$/, '') || '0';
                if (estTax) estTax.textContent = 'Rs.' + taxAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (estDiscount) estDiscount.textContent = 'Rs.' + discount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (estTotal) estTotal.textContent = 'Rs.' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            if (taxInput) taxInput.addEventListener('input', recalc);
            if (discountInput) discountInput.addEventListener('input', recalc);

            // // Payment QR logic
            // const toggleQRBtn = document.getElementById('toggleQR');
            // const qrContainer = document.getElementById('qrContainer');
            // const paymentQR = document.getElementById('paymentQR');
            // const downloadQR = document.getElementById('downloadQR');
            // const refreshQR = document.getElementById('refreshQR');
            // const paymentMethodSelect = document.getElementById('payment_method');
            // const computedTotalInput = document.getElementById('computedTotal');

            function formatAmountForQR(val) {
                // Keep numeric value with two decimals
                return Number(val).toFixed(2);
            }

            function buildQRUrl(method, amount, reference) {
                const payload = 'pay:' + method + ';amt:' + formatAmountForQR(amount) + ';ref:' + reference;
                return 'https://chart.googleapis.com/chart?chs=240x240&cht=qr&chl=' + encodeURIComponent(payload);
            }

            function updateQR() {
                const totalText = computedTotalInput.value || ( (subtotal + ((parseFloat(taxInput?.value)||0) * subtotal / 100)) - (parseFloat(discountInput?.value)||0) );
                const total = parseFloat(totalText) || 0;
                const method = (paymentMethodSelect?.value) || 'cash';
                const reference = 'TEMP' + Date.now();
                const url = buildQRUrl(method, total, reference);
                if (paymentQR) paymentQR.src = url;
                if (downloadQR) downloadQR.href = url;
            }

            if (toggleQRBtn) {
                toggleQRBtn.addEventListener('click', function() {
                    if (qrContainer.style.display === 'none' || qrContainer.style.display === '') {
                        updateQR();
                        qrContainer.style.display = 'flex';
                        toggleQRBtn.textContent = 'Hide QR';
                    } else {
                        qrContainer.style.display = 'none';
                        toggleQRBtn.textContent = 'Show QR';
                    }
                });
            }

            if (refreshQR) refreshQR.addEventListener('click', function() { updateQR(); });
            if (paymentMethodSelect) paymentMethodSelect.addEventListener('change', function() { updateQR(); });

            // If QR container is visible on load (e.g., cart has items), generate QR immediately
            if (qrContainer && window.getComputedStyle(qrContainer).display !== 'none') {
                updateQR();
                if (toggleQRBtn) toggleQRBtn.textContent = 'Hide QR';
            }

            recalc();
        });
    </script>
</body>
</html>
