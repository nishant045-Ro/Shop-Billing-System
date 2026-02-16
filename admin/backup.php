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
$downloadLink = '';

function quoteValue($value, PDO $pdo): string {
    if ($value === null) {
        return 'NULL';
    }
    return $pdo->quote($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    try {
        $tablesStmt = $pdo->query("SHOW TABLES");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            throw new RuntimeException('No tables found to backup.');
        }

        $sqlDump = "-- Backup generated on " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Drop & create
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= $createRow['Create Table'] . ";\n\n";

            // Data
            $dataStmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $columns = array_keys($rows[0]);
                $colList = '`' . implode('`, `', $columns) . '`';
                $sqlDump .= "INSERT INTO `$table` ($colList) VALUES\n";
                $valuesArr = [];
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($columns as $col) {
                        $vals[] = quoteValue($row[$col], $pdo);
                    }
                    $valuesArr[] = '(' . implode(', ', $vals) . ')';
                }
                $sqlDump .= implode(",\n", $valuesArr) . ";\n\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup-' . date('Ymd-His') . '.sql';
        $filepath = $backupDir . '/' . $filename;
        file_put_contents($filepath, $sqlDump);

        $message = 'Backup created successfully.';
        $downloadLink = '../backups/' . $filename;
    } catch (Throwable $e) {
        $error = 'Backup failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <a href="backup.php" class="nav-item active">
                    <i class="fas fa-database"></i> Backup
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header hero-header">
                <div>
                    <p class="eyebrow">Reliability</p>
                    <h1>Database Backup</h1>
                    <p class="subhead">Generate a downloadable SQL backup of your current data.</p>
                </div>
                <div class="hero-metric">
                    <div class="metric-label">Status</div>
                    <div class="metric-value"><?php echo $message ? 'Ready' : 'Idle'; ?></div>
                    <div class="metric-sub">Click Backup to export</div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-database"></i> Create Backup</h3>
                </div>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="backup">
                    <div class="form-group">
                        <p class="text-muted">This will export all tables and data into an SQL file you can download.</p>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-download-alt"></i> Backup Now</button>
                        <?php if ($downloadLink): ?>
                            <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-success" download>
                                <i class="fas fa-file-download"></i> Download Latest
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php
            // List existing backups if directory exists
            $backups = [];
            $backupDir = __DIR__ . '/../backups';
            if (is_dir($backupDir)) {
                $files = array_diff(scandir($backupDir, SCANDIR_SORT_DESCENDING), ['.', '..']);
                foreach ($files as $file) {
                    if (str_ends_with($file, '.sql')) {
                        $backups[] = $file;
                    }
                }
            }
            ?>

            <div class="card elevated">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Backups</h3>
                </div>
                <div class="table-container">
                    <table class="table modern">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backups)): ?>
                                <tr><td colspan="4" style="text-align:center; color:#666;">No backups yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($backups as $file): 
                                    $path = $backupDir . '/' . $file;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($file); ?></td>
                                        <td><?php echo number_format(filesize($path) / 1024, 2); ?> KB</td>
                                        <td><?php echo date('M d, Y H:i', filemtime($path)); ?></td>
                                        <td><a class="btn btn-info btn-sm" href="<?php echo '../backups/' . htmlspecialchars($file); ?>" download><i class="fas fa-download"></i> Download</a></td>
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

