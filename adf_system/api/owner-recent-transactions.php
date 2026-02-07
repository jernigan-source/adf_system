<?php
/**
 * API: Owner Recent Transactions
 * Get recent transactions from accessible businesses
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

$specificBusinessId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // Use single database (adf_narayana)
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
            'transactions' => [],
            'count' => 0
        ]);
        exit;
    }
    
    // Filter to specific business if requested
    if ($specificBusinessId && in_array($specificBusinessId, $accessibleBusinessIds)) {
        $businessFilter = " AND cb.branch_id = ?";
        $businessParams = [$specificBusinessId];
    } else {
        $placeholders = implode(',', array_fill(0, count($accessibleBusinessIds), '?'));
        $businessFilter = " AND cb.branch_id IN ($placeholders)";
        $businessParams = $accessibleBusinessIds;
    }
    
    // Get recent transactions from single database
    $transactions = $db->fetchAll(
        "SELECT 
            cb.*,
            d.division_name,
            c.category_name,
            u.full_name as user_name,
            b.business_name
         FROM cash_book cb
         LEFT JOIN divisions d ON cb.division_id = d.id
         LEFT JOIN categories c ON cb.category_id = c.id
         LEFT JOIN users u ON cb.created_by = u.id
         LEFT JOIN businesses b ON cb.branch_id = b.id
         WHERE DATE(cb.transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" . $businessFilter . "
         ORDER BY cb.transaction_date DESC, cb.transaction_time DESC
         LIMIT ?",
        array_merge($businessParams, [$limit])
    );
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'count' => count($transactions),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
