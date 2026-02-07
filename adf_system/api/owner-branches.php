<?php
/**
 * API: Owner Branches
 * Get list of branches/businesses from database
 * Filter by user's business_access
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

try {
    // FORCE connection to adf_narayana (businesses table is there, not in business-specific DBs)
    $db = Database::switchDatabase('adf_narayana');
    
    // DEBUG: Check which database we're connected to
    $currentDb = $db->fetchOne("SELECT DATABASE() as db_name");
    error_log("Owner Branches - Connected to: " . $currentDb['db_name']);
    
    // Get user's business_access
    $businessAccess = $currentUser['business_access'] ?? null;
    
    if (!$businessAccess || $businessAccess === 'null') {
        // Jika tidak ada business_access, ambil dari database
        $user = $db->fetchOne(
            "SELECT business_access FROM users WHERE id = ?",
            [$currentUser['id']]
        );
        $businessAccess = $user['business_access'] ?? '[]';
    }
    
    // Decode JSON
    $accessibleBusinessIds = json_decode($businessAccess, true);
    
    if (!is_array($accessibleBusinessIds)) {
        $accessibleBusinessIds = [];
    }
    
    // Get all businesses from database
    $allBusinesses = $db->fetchAll("SELECT id, business_name, address, phone FROM businesses ORDER BY id");
    error_log("Owner Branches - Businesses found: " . count($allBusinesses));
    error_log("Owner Branches - Query result: " . json_encode($allBusinesses));
    
    $branches = [];
    
    foreach ($allBusinesses as $business) {
        // Filter: hanya tampilkan bisnis yang user punya akses
        if (in_array($business['id'], $accessibleBusinessIds)) {
            $branches[] = [
                'id' => $business['id'],
                'branch_name' => $business['business_name'],
                'city' => $business['address'] ?? '-',
                'phone' => $business['phone'] ?? '-'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'branches' => $branches,
        'count' => count($branches),
        'user_info' => [
            'username' => $currentUser['username'],
            'role' => $currentUser['role'],
            'total_businesses' => count($allBusinesses),
            'accessible_businesses' => count($branches)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
