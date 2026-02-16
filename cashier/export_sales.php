<?php
session_start();
require_once '../config/database.php';

// Ensure cashier access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../index.php');
    exit();
}

$cashier_id = $_SESSION['user_id'];
$scope = $_GET['scope'] ?? 'today';

try {
    if ($scope === 'today') {
        $stmt = $pdo->prepare("SELECT bill_id, customer_name, final_amount, payment_method, date_time FROM bills WHERE cashier_id = ? AND DATE(date_time) = CURDATE() ORDER BY date_time DESC");
        $stmt->execute([$cashier_id]);
        $rows = $stmt->fetchAll();
        $filename = 'sales_' . date('Ymd') . '.csv';
    } else {
        $stmt = $pdo->prepare("SELECT bill_id, customer_name, final_amount, payment_method, date_time FROM bills WHERE cashier_id = ? ORDER BY date_time DESC LIMIT 1000");
        $stmt->execute([$cashier_id]);
        $rows = $stmt->fetchAll();
        $filename = 'sales_all.csv';
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Bill ID', 'Customer', 'Amount', 'Payment Method', 'Date']);
    foreach ($rows as $r) {
        fputcsv($output, [$r['bill_id'], $r['customer_name'], $r['final_amount'], $r['payment_method'], $r['date_time']]);
    }
    fclose($output);
    exit();
} catch (PDOException $e) {
    error_log('Export sales error: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>