<?php
/**
 * API: Owner Chart Data (Multi-Business Support)
 * Get chart data aggregated from accessible businesses
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/business_access.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is owner or admin
if (!$auth->hasRole('owner') && !$auth->hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$specificBusinessId = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
$period = isset($_GET['period']) ? $_GET['period'] : '7days'; // 7days, 30days, 12months

// Validate period
$validPeriods = ['7days', '30days', '12months'];
if (!in_array($period, $validPeriods)) {
    $period = '7days';
}

try {
    // Get accessible businesses
    $accessibleBusinesses = getUserAvailableBusinesses($currentUser['id']);
    
    if (empty($accessibleBusinesses)) {
        echo json_encode([
            'success' => true,
            'period' => $period,
            'data' => ['labels' => [], 'income' => [], 'expense' => []]
        ]);
        exit;
    }
    
    // Filter to specific business if requested
    if ($specificBusinessId) {
        $accessibleBusinesses = array_filter($accessibleBusinesses, function($b) use ($specificBusinessId) {
            return $b['id'] == $specificBusinessId;
        });
    }
    
    // Initialize data arrays based on period
    $aggregatedData = [];
    
    if ($period === '7days') {
        // Prepare 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $aggregatedData[$date] = ['income' => 0, 'expense' => 0];
        }
        
        // Query each business
        foreach ($accessibleBusinesses as $business) {
            $dbName = $business['database'];
            $businessDb = new Database($dbName);
            
            $results = $businessDb->fetchAll(
                "SELECT 
                    DATE(transaction_date) as date,
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
                 FROM cash_book
                 WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(transaction_date)",
                []
            );
            
            // Aggregate results
            foreach ($results as $row) {
                if (isset($aggregatedData[$row['date']])) {
                    $aggregatedData[$row['date']]['income'] += floatval($row['income']);
                    $aggregatedData[$row['date']]['expense'] += floatval($row['expense']);
                }
            }
        }
        
        // Format output
        $labels = [];
        $incomeData = [];
        $expenseData = [];
        
        foreach ($aggregatedData as $date => $values) {
            $labels[] = date('D, M j', strtotime($date));
            $incomeData[] = $values['income'];
            $expenseData[] = $values['expense'];
        }
        
    } elseif ($period === '30days') {
        // Prepare current month days
        $daysInMonth = date('t');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = date('Y-m-') . sprintf('%02d', $day);
            $aggregatedData[$date] = ['income' => 0, 'expense' => 0];
        }
        
        $firstDay = date('Y-m-01');
        $lastDay = date('Y-m-t');
        
        // Query each business
        foreach ($accessibleBusinesses as $business) {
            $dbName = $business['database'];
            $businessDb = new Database($dbName);
            
            $results = $businessDb->fetchAll(
                "SELECT 
                    DATE(transaction_date) as date,
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
                 FROM cash_book
                 WHERE transaction_date >= ? AND transaction_date <= ?
                 GROUP BY DATE(transaction_date)",
                [$firstDay, $lastDay]
            );
            
            // Aggregate results
            foreach ($results as $row) {
                if (isset($aggregatedData[$row['date']])) {
                    $aggregatedData[$row['date']]['income'] += floatval($row['income']);
                    $aggregatedData[$row['date']]['expense'] += floatval($row['expense']);
                }
            }
        }
        
        // Format output
        $labels = [];
        $incomeData = [];
        $expenseData = [];
        
        foreach ($aggregatedData as $date => $values) {
            $labels[] = date('M j', strtotime($date));
            $incomeData[] = $values['income'];
            $expenseData[] = $values['expense'];
        }
        
    } elseif ($period === '12months') {
        // Prepare 12 months
        $currentYear = date('Y');
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = $currentYear . '-' . sprintf('%02d', $month);
            $aggregatedData[$monthStr] = ['income' => 0, 'expense' => 0];
        }
        
        $firstDay = $currentYear . '-01-01';
        $lastDay = $currentYear . '-12-31';
        
        // Query each business
        foreach ($accessibleBusinesses as $business) {
            $dbName = $business['database'];
            $businessDb = new Database($dbName);
            
            $results = $businessDb->fetchAll(
                "SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as month,
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
                 FROM cash_book
                 WHERE transaction_date >= ? AND transaction_date <= ?
                 GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')",
                [$firstDay, $lastDay]
            );
            
            // Aggregate results
            foreach ($results as $row) {
                if (isset($aggregatedData[$row['month']])) {
                    $aggregatedData[$row['month']]['income'] += floatval($row['income']);
                    $aggregatedData[$row['month']]['expense'] += floatval($row['expense']);
                }
            }
        }
        
        // Format output
        $labels = [];
        $incomeData = [];
        $expenseData = [];
        
        foreach ($aggregatedData as $month => $values) {
            $labels[] = date('M Y', strtotime($month . '-01'));
            $incomeData[] = $values['income'];
            $expenseData[] = $values['expense'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'data' => [
            'labels' => $labels,
            'income' => $incomeData,
            'expense' => $expenseData
        ],
        'businesses_count' => count($accessibleBusinesses)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'period' => $period ?? '7days'
    ]);
}
