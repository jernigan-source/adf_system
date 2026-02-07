<?php
/**
 * NARAYANA HOTEL MANAGEMENT SYSTEM
 * Live Chart Data API - Daily Transactions (30 Days)
 */

define('APP_ACCESS', true);
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$db = Database::getInstance();

// Get selected month from parameter, default to current month
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

// Get first and last day of selected month
$firstDay = $selectedMonth . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));
$daysInMonth = date('t', strtotime($firstDay));

// Generate all dates in the month
$dates = [];
for ($i = 1; $i <= $daysInMonth; $i++) {
    $dates[] = $selectedMonth . '-' . sprintf('%02d', $i);
}

// Get actual transaction data for the month
$transData = $db->fetchAll(
    "SELECT 
        DATE(transaction_date) as date,
        SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
    FROM cash_book
    WHERE DATE_FORMAT(transaction_date, '%Y-%m') = :month
    GROUP BY DATE(transaction_date)
    ORDER BY date ASC",
    ['month' => $selectedMonth]
);

// Map transaction data by date
$transMap = [];
foreach ($transData as $data) {
    $transMap[$data['date']] = [
        'income' => (float)$data['income'],
        'expense' => (float)$data['expense']
    ];
}

// Format data for chart (fill all days in month)
$labels = [];
$incomeData = [];
$expenseData = [];

foreach ($dates as $date) {
    $day = (int)date('d', strtotime($date));
    $labels[] = $day; // Just show day number (1-31)
    $incomeData[] = isset($transMap[$date]) ? $transMap[$date]['income'] : 0;
    $expenseData[] = isset($transMap[$date]) ? $transMap[$date]['expense'] : 0;
}

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'income' => $incomeData,
    'expense' => $expenseData,
    'month' => $selectedMonth,
    'days_in_month' => $daysInMonth,
    'timestamp' => date('Y-m-d H:i:s')
]);
