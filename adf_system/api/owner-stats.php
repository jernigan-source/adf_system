<?php
/**
 * API: Owner Statistics
 * Get today and monthly statistics from accessible businesses
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

$currentUser = $auth->getCurrentUser();

// Check if user is owner or admin
if ($currentUser['role'] !== 'owner' && $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get specific business ID if provided (for single business view)
$specificBusinessId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

try {
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));
    
    // Use single database instance (adf_narayana)
    $db = Database::getInstance();
    
    // Get user's business_access
    $businessAccess = $currentUser['business_access'] ?? null;
    
    if (!$businessAccess || $businessAccess === 'null') {
        $user = $db->fetchOne(
            "SELECT business_access FROM users WHERE id = ?",
            [$currentUser['id']]
        );
        $businessAccess = $user['business_access'] ?? '[]';
    }
    
    $accessibleBusinessIds = json_decode($businessAccess, true);
    if (!is_array($accessibleBusinessIds) || empty($accessibleBusinessIds)) {
        echo json_encode([
            'success' => true,
            'today' => ['income' => 0, 'expense' => 0, 'income_count' => 0, 'expense_count' => 0, 'net' => 0],
            'month' => ['income' => 0, 'expense' => 0, 'net' => 0, 'income_change' => 0, 'expense_change' => 0],
            'message' => 'No businesses accessible'
        ]);
        exit;
    }
    
    // Build WHERE clause for business filter
    if ($specificBusinessId && in_array($specificBusinessId, $accessibleBusinessIds)) {
        $whereToday = "transaction_date = ? AND branch_id = ?";
        $whereMonth = "DATE_FORMAT(transaction_date, '%Y-%m') = ? AND branch_id = ?";
        $paramsToday = [$today, $specificBusinessId];
        $paramsMonth = [$thisMonth, $specificBusinessId];
        $paramsLastMonth = [$lastMonth, $specificBusinessId];
    } else {
        $placeholders = implode(',', array_fill(0, count($accessibleBusinessIds), '?'));
        $whereToday = "transaction_date = ? AND branch_id IN ($placeholders)";
        $whereMonth = "DATE_FORMAT(transaction_date, '%Y-%m') = ? AND branch_id IN ($placeholders)";
        $paramsToday = array_merge([$today], $accessibleBusinessIds);
        $paramsMonth = array_merge([$thisMonth], $accessibleBusinessIds);
        $paramsLastMonth = array_merge([$lastMonth], $accessibleBusinessIds);
    }
    
    // TODAY STATS
    $todayStats = $db->fetchOne(
        "SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as expense,
            COUNT(CASE WHEN transaction_type = 'income' THEN 1 END) as income_count,
            COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_count
         FROM cash_book 
         WHERE $whereToday",
        $paramsToday
    );
    
    if ($todayStats) {
        $todayIncome = (float)$todayStats['income'];
        $todayExpense = (float)$todayStats['expense'];
        $todayIncomeCount = (int)$todayStats['income_count'];
        $todayExpenseCount = (int)$todayStats['expense_count'];
    }
    
    // THIS MONTH STATS
    $monthStats = $db->fetchOne(
        "SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as expense
         FROM cash_book 
         WHERE $whereMonth",
        $paramsMonth
    );
    
    if ($monthStats) {
        $monthIncome = (float)$monthStats['income'];
        $monthExpense = (float)$monthStats['expense'];
    }
    
    // LAST MONTH STATS
    $lastMonthStats = $db->fetchOne(
        "SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as expense
         FROM cash_book 
         WHERE $whereMonth",
        $paramsLastMonth
    );
    
    $lastMonthIncome = 0;
    $lastMonthExpense = 0;
    if ($lastMonthStats) {
        $lastMonthIncome = (float)$lastMonthStats['income'];
        $lastMonthExpense = (float)$lastMonthStats['expense'];
    }
    
    // Calculate change percentages
    $incomeChange = 0;
    $expenseChange = 0;
    
    if ($lastMonthIncome > 0) {
        $incomeChange = (($monthIncome - $lastMonthIncome) / $lastMonthIncome) * 100;
    }
    
    if ($lastMonthExpense > 0) {
        $expenseChange = (($monthExpense - $lastMonthExpense) / $lastMonthExpense) * 100;
    }
    
    echo json_encode([
        'success' => true,
        'today' => [
            'income' => $todayIncome,
            'expense' => $todayExpense,
            'income_count' => $todayIncomeCount,
            'expense_count' => $todayExpenseCount,
            'net' => $todayIncome - $todayExpense
        ],
        'month' => [
            'income' => $monthIncome,
            'expense' => $monthExpense,
            'net' => $monthIncome - $monthExpense,
            'income_change' => round($incomeChange, 1),
            'expense_change' => round($expenseChange, 1)
        ],
        'businesses_count' => count($accessibleBusinessIds),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
