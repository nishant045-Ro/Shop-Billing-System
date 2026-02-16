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

// Fetch user info (including optional shop location)
$stmt = $pdo->prepare("SELECT username, role, created_at, store_image, shop_lat, shop_lng FROM users WHERE id = ?");
$stmt->execute([$cashier_id]);
$user = $stmt->fetch();

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_bills, SUM(final_amount) as total_revenue FROM bills WHERE cashier_id = ?");
$stmt->execute([$cashier_id]);
$stats = $stmt->fetch();
$totalBills = $stats['total_bills'] ?? 0;
$totalRevenue = $stats['total_revenue'] ?? 0;

// Today stats
$stmt = $pdo->prepare("SELECT COUNT(*) as today_bills, SUM(final_amount) as today_revenue FROM bills WHERE cashier_id = ? AND DATE(date_time) = CURDATE()");
$stmt->execute([$cashier_id]);
$today = $stmt->fetch();
$todayBills = $today['today_bills'] ?? 0;
$todayRevenue = $today['today_revenue'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$cashier_id]);
            $passwordRow = $stmt->fetch();

            if (!$passwordRow || !password_verify($current_password, $passwordRow['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed, $cashier_id])) {
                    $message = 'Password updated successfully.';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            }
        }
    } elseif ($action === 'update_store_image') {
        if (isset($_FILES['store_image']) && $_FILES['store_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['store_image']['error'] === UPLOAD_ERR_OK) {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($_FILES['store_image']['name'], PATHINFO_EXTENSION));
                $maxSize = 2 * 1024 * 1024; // 2MB

                if (!in_array($extension, $allowedExtensions, true)) {
                    $error = 'Store image must be JPG, PNG, GIF, or WEBP.';
                } elseif ($_FILES['store_image']['size'] > $maxSize) {
                    $error = 'Store image must be smaller than 2MB.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/stores/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Delete old store image if exists
                    if ($user['store_image']) {
                        $oldFile = __DIR__ . '/../' . $user['store_image'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                    // Use user ID in filename to ensure uniqueness per shop
                    $uniqueName = 'store-' . $cashier_id . '-' . date('YmdHis') . '.' . $extension;
                    $destination = $uploadDir . $uniqueName;

                    if (move_uploaded_file($_FILES['store_image']['tmp_name'], $destination)) {
                        $relativePath = 'uploads/stores/' . $uniqueName;
                        $stmt = $pdo->prepare("UPDATE users SET store_image = ? WHERE id = ?");
                        if ($stmt->execute([$relativePath, $cashier_id])) {
                            $message = 'Store image updated successfully.';
                            $user['store_image'] = $relativePath;
                        } else {
                            $error = 'Failed to save store image path.';
                        }
                    } else {
                        $error = 'Could not save the store image. Please try again.';
                    }
                }
            } else {
                $error = 'Error uploading store image. Please try again.';
            }
        } else {
            $error = 'Please choose an image to upload.';
        }
    } elseif ($action === 'update_location') {
        $lat = isset($_POST['shop_lat']) && $_POST['shop_lat'] !== '' ? (float)$_POST['shop_lat'] : null;
        $lng = isset($_POST['shop_lng']) && $_POST['shop_lng'] !== '' ? (float)$_POST['shop_lng'] : null;

        if ($lat === null || $lng === null) {
            $error = 'Please pick a location on the map.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET shop_lat = ?, shop_lng = ? WHERE id = ?");
            if ($stmt->execute([$lat, $lng, $cashier_id])) {
                $message = 'Shop location updated successfully.';
                $user['shop_lat'] = $lat;
                $user['shop_lng'] = $lng;
            } else {
                $error = 'Failed to update shop location. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - Cashier Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Leaflet CSS for world map -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <style>
        :root {
            --indigo-500: #4f46e5;
            --indigo-400: #6366f1;
            --teal-400: #14b8a6;
            --amber-400: #f59e0b;
            --rose-500: #f43f5e;
            --slate-900: #0f172a;
        }
        body.cashier-profile {
            background: radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 28%),
                        radial-gradient(circle at 80% 0%, rgba(20, 184, 166, 0.08), transparent 28%),
                        #f3f4f6;
        }
        .cashier-profile .main-content {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(248, 249, 252, 0.96));
            backdrop-filter: blur(6px);
        }
        .cashier-profile .hero-header {
            background: linear-gradient(135deg, #4f46e5, #14b8a6);
            color: white;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        }
        .cashier-profile .hero-metric {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .cashier-profile .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }
        .cashier-profile .mini-stat {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cashier-profile .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.05rem;
        }
        .mini-icon.bills { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .mini-icon.revenue { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .mini-icon.today { background: linear-gradient(135deg, #ec4899, #f43f5e); }
        .mini-icon.brand { background: linear-gradient(135deg, #f97316, #f59e0b); }

        .cashier-profile .brand-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 900px) {
            .cashier-profile .brand-card {
                grid-template-columns: 1fr;
            }
        }
        .store-avatar-lg {
            width: 160px;
            height: 160px;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid rgba(79, 70, 229, 0.16);
            box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        }
        .store-avatar-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .upload-hint {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }
        .map-card {
            /* Allow the map card to grow so coordinates and button are visible */
            height: auto;
        }
        #shopMap {
            width: 100%;
            height: 220px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        .location-coords {
            font-size: 0.9rem;
            color: #4b5563;
            margin-top: 6px;
        }
    </style>
</head>
<body class="cashier-profile">
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
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i> Search Bills
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user"></i> Profile
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Your Profile</p>
                    <h1></h1>
                    <p class="subhead">Showcase your store, track your impact, and stay secure.</p>
                    <!-- <div class="header-actions">
                        <a href="billing.php" class="btn btn-primary"><i class="fas fa-bolt"></i> New Bill</a>
                        <a href="bills.php" class="btn btn-secondary ghost"><i class="fas fa-list"></i> View Bills</a>
                    </div> -->
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub"><?php echo $totalBills; ?> bills • Today Rs.<?php echo number_format($todayRevenue, 2); ?></div>
                </div>
            </div>

            <!-- <div class="stat-grid">
                <div class="mini-stat">
                    <span class="mini-icon bills"><i class="fas fa-receipt"></i></span>
                    <div>
                        <div class="summary-label">All-time Bills</div>
                        <div class="summary-value"><?php echo number_format($totalBills); ?></div>
                        <span class="subline">Everything you processed</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon revenue"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="summary-label">All-time Revenue</div>
                        <div class="summary-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                        <span class="subline">Your performance</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon today"><i class="fas fa-calendar-day"></i></span>
                    <div>
                        <div class="summary-label">Today</div>
                        <div class="summary-value"><?php echo number_format($todayBills); ?> bills</div>
                        <span class="subline">Rs.<?php echo number_format($todayRevenue, 2); ?> today</span>
                    </div>
                </div>
                <div class="mini-stat">
                    <span class="mini-icon brand"><i class="fas fa-store"></i></span>
                    <div>
                        <div class="summary-label">Store Image</div>
                        <div class="summary-value"><?php echo $user['store_image'] ? 'Uploaded' : 'Not set'; ?></div>
                        <span class="subline">Keep your brand fresh</span>
                    </div>
                </div>
            </div> -->

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="grid-2 profile-grid">
            <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-store"></i> Store Image</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="brand-card">
                        <input type="hidden" name="action" value="update_store_image">
                        <div class="form-group" style="display:flex; flex-direction:column; gap:10px;">
                            <div class="store-avatar-lg">
                                <img id="storePreview" src="../<?php echo $user['store_image'] ? htmlspecialchars($user['store_image']) : 'assets/img/store-placeholder.svg'; ?>" alt="Store image">
                            </div>
                            <span class="upload-hint">Tip: use a square logo or storefront photo (max 2MB).</span>
                        </div>
                        <div class="form-group" style="display:flex; flex-direction:column; gap:10px;">
                            <label for="store_image">Upload Store Image</label>
                            <input type="file" id="store_image" name="store_image" accept="image/*">
                            <small class="text-muted">JPG/PNG/GIF/WEBP, max 2MB</small>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Update Image</button>
                        </div>
                    </form>
                </div>

                <div class="card elevated">
                    <div class="card-header">
                        <h3><i class="fas fa-id-card"></i> Account Details</h3>
                    </div>
                    <div class="info-content info-grid">
                        <div><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></div>
                        <div><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></div>
                        <div><strong>Member since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                        <div><strong>Today:</strong> <?php echo $todayBills; ?> bills • Rs.<?php echo number_format($todayRevenue, 2); ?></div>
                        <div><strong>All time:</strong> <?php echo $totalBills; ?> bills • Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                        <div><strong>Store Image:</strong> <?php echo $user['store_image'] ? 'Available' : 'Not set'; ?></div>
                        <div><strong>Shop Location:</strong>
                            <?php if (!empty($user['shop_lat']) && !empty($user['shop_lng'])): ?>
                                <span><?php echo number_format((float)$user['shop_lat'], 5); ?>,
                                      <?php echo number_format((float)$user['shop_lng'], 5); ?></span>
                            <?php else: ?>
                                <span>Not set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card elevated map-card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Shop Location</h3>
                </div>
                <form method="POST" class="form-row" id="locationForm">
                    <input type="hidden" name="action" value="update_location">
                    <input type="hidden" id="shop_lat" name="shop_lat" value="<?php echo htmlspecialchars((string)($user['shop_lat'] ?? '')); ?>">
                    <input type="hidden" id="shop_lng" name="shop_lng" value="<?php echo htmlspecialchars((string)($user['shop_lng'] ?? '')); ?>">
                    <div style="width:100%;">
                        <div id="shopMap"></div>
                        <div class="location-coords">
                            <span id="coordsText">
                                <?php if (!empty($user['shop_lat']) && !empty($user['shop_lng'])): ?>
                                    Current: <?php echo number_format((float)$user['shop_lat'], 5); ?>,
                                    <?php echo number_format((float)$user['shop_lng'], 5); ?>
                                <?php else: ?>
                                    Click on the map to set your shop location.
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="margin-top:10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Location
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> Change Password</h3>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Leaflet JS for world map -->
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin="">
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const storeInput = document.getElementById('store_image');
            const storePreview = document.getElementById('storePreview');

            if (storeInput && storePreview) {
                storeInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        storePreview.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);
                });
            }

            // Simple map using Leaflet + OpenStreetMap (no API key needed)
            const mapElement = document.getElementById('shopMap');
            const latInput = document.getElementById('shop_lat');
            const lngInput = document.getElementById('shop_lng');
            const coordsText = document.getElementById('coordsText');

            if (mapElement && latInput && lngInput) {
                const hasSaved = !!(latInput.value && lngInput.value);
                const initialLat = hasSaved ? parseFloat(latInput.value) : 20;  // near global center
                const initialLng = hasSaved ? parseFloat(lngInput.value) : 0;

                // If user has a saved location, zoom closer; otherwise show almost whole world
                const initialZoom = hasSaved ? 10 : 2;
                const map = L.map('shopMap').setView([initialLat, initialLng], initialZoom);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                let marker = null;
                if (latInput.value && lngInput.value) {
                    marker = L.marker([initialLat, initialLng]).addTo(map);
                }

                function updateCoords(lat, lng) {
                    latInput.value = lat.toFixed(7);
                    lngInput.value = lng.toFixed(7);
                    if (coordsText) {
                        coordsText.textContent = `Selected: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                    }
                }

                map.on('click', function (e) {
                    const { lat, lng } = e.latlng;
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng]).addTo(map);
                    }
                    updateCoords(lat, lng);
                });
            }
        });
    </script>
</body>
</html>

