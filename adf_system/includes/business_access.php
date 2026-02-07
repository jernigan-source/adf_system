<?php
/**
 * Business Access Control Middleware
 * Check if user has access to selected business
 */

if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Check if current user has access to active business
 * @return bool
 */
function checkBusinessAccess() {
    global $auth;
    
    if (!isset($auth) || !$auth->isLoggedIn()) {
        return false;
    }
    
    $currentUser = $auth->getCurrentUser();
    
    // Owner and admin have access to all businesses
    if ($currentUser['role'] === 'owner' || $currentUser['role'] === 'admin') {
        return true;
    }
    
    // Check if user has specific business access
    $businessAccess = json_decode($currentUser['business_access'] ?? '[]', true);
    
    if (empty($businessAccess)) {
        // If no business_access set, deny access (secure by default)
        return false;
    }
    
    // Check if user has access to current business
    return in_array(ACTIVE_BUSINESS_ID, $businessAccess);
}

/**
 * Require business access or redirect
 */
function requireBusinessAccess() {
    if (!checkBusinessAccess()) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke bisnis ini. Silakan hubungi administrator.';
        header('Location: ' . BASE_URL . '/logout.php');
        exit;
    }
}

/**
 * Get available businesses for current user from master database
 * @return array Filtered list of businesses user can access
 */
function getUserAvailableBusinesses() {
    global $auth;
    
    if (!isset($auth) || !$auth->isLoggedIn()) {
        return [];
    }
    
    $username = $_SESSION['username'] ?? null;
    if (!$username) {
        return [];
    }
    
    // Developer role has access to all businesses
    $userRole = $_SESSION['role'] ?? 'staff';
    if ($userRole === 'developer') {
        return getAvailableBusinesses();
    }
    
    try {
        // Connect to master database
        $masterPdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=adf_system;charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get user ID from master
        $userStmt = $masterPdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $userStmt->execute([$username]);
        $masterUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$masterUser) {
            return [];
        }
        
        $masterId = $masterUser['id'];
        
        // Get businesses user has permissions for
        $bizStmt = $masterPdo->prepare("
            SELECT DISTINCT b.id, b.business_code, b.business_name
            FROM businesses b
            JOIN user_menu_permissions p ON b.id = p.business_id
            WHERE p.user_id = ?
            ORDER BY b.business_name
        ");
        $bizStmt->execute([$masterId]);
        $userBusinesses = $bizStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($userBusinesses)) {
            return [];
        }
        
        // Map database results to business config format
        $codeToIdMap = [
            'BENSCAFE' => 'bens-cafe',
            'NARAYANAHOTEL' => 'narayana-hotel'
        ];
        
        $allBusinesses = getAvailableBusinesses();
        $filtered = [];
        
        foreach ($userBusinesses as $biz) {
            $businessId = $codeToIdMap[$biz['business_code']] ?? strtolower($biz['business_code']);
            if (isset($allBusinesses[$businessId])) {
                $filtered[$businessId] = $allBusinesses[$businessId];
            }
        }
        
        return $filtered;
        
    } catch (Exception $e) {
        error_log('getUserAvailableBusinesses error: ' . $e->getMessage());
        return [];
    }
}
