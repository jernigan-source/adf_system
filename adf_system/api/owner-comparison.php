<?php
/**
 * API: Owner Comparison
 * Get comparison data between all accessible businesses
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

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

$period = isset($_GET['period']) ? $_GET['period'] : 'today'; // today, this_month, this_year

try {
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
            'businesses' => [],
            'period' => $period
        ]);
        exit;
    }
    
    // Get businesses info
    $placeholders = implode(',', array_fill(0, count($accessibleBusinessIds), '?'));
    $businesses = $db->fetchAll(
        "SELECT id, business_name FROM businesses WHERE id IN ($placeholders)",
        $accessibleBusinessIds
    );
    
    // Build date filter based on period
    $dateFilter = "";
    switch ($period) {
        case 'today':
            $dateFilter = "transaction_date = CURDATE()";
            break;
        case 'this_month':
            $dateFilter = "DATE_FORMAT(transaction_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            break;
        case 'this_year':
            $dateFilter = "YEAR(transaction_date) = YEAR(CURDATE())";
            break;
        default:
            $dateFilter = "transaction_date = CURDATE()";
    }
    
    // Get stats for each business
    $businessStats = [];
    $hasBranchId = false;
    
    // Check once if branch_id column exists
    try {
        $columns = $db->getConnection()->query("SHOW COLUMNS FROM cash_book LIKE 'branch_id'")->fetchAll();
        $hasBranchId = count($columns) > 0;
    } catch (Exception $e) {
        $hasBranchId = false;
    }
    
    foreach ($businesses as $business) {
        if ($hasBranchId) {
            // Use branch_id if exists
            $stats = $db->fetchOne(
                "SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as expense,
                    COUNT(CASE WHEN transaction_type = 'income' THEN 1 END) as income_count,
                    COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_count
                 FROM cash_book 
                 WHERE branch_id = ? AND $dateFilter",
                [$business['id']]
            );
        } else {
            // Fallback: get all data (same for all businesses if branch_id doesn't exist)
            // This will show same data for all until migration is run
            $stats = $db->fetchOne(
                "SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as expense,
                    COUNT(CASE WHEN transaction_type = 'income' THEN 1 END) as income_count,
                    COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_count
                 FROM cash_book 
                 WHERE $dateFilter",
                []
            );
        }
        
        $businessStats[] = [
            'id' => $business['id'],
            'name' => $business['business_name'],
            'income' => (float)($stats['income'] ?? 0),
            'expense' => (float)($stats['expense'] ?? 0),
            'net' => (float)($stats['income'] ?? 0) - (float)($stats['expense'] ?? 0),
            'income_count' => (int)($stats['income_count'] ?? 0),
            'expense_count' => (int)($stats['expense_count'] ?? 0)
        ];
    }
    
    // Calculate totals
    $totalIncome = array_sum(array_column($businessStats, 'income'));
    $totalExpense = array_sum(array_column($businessStats, 'expense'));
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'businesses' => $businessStats,
        'totals' => [
            'income' => $totalIncome,
            'expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense
        ],
        'has_branch_id' => $hasBranchId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
