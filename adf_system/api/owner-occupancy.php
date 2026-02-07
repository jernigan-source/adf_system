<?php
/**
 * API: Owner Occupancy
 * Get room occupancy statistics from accessible businesses
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

try {
    $today = date('Y-m-d');
    
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
            'total_rooms' => 0,
            'occupied_rooms' => 0,
            'available_rooms' => 0,
            'maintenance_rooms' => 0,
            'occupancy_rate' => 0,
            'today_checkins' => 0,
            'today_checkouts' => 0
        ]);
        exit;
    }
    
    // Filter to specific business if requested
    if ($specificBusinessId && in_array($specificBusinessId, $accessibleBusinessIds)) {
        $businessFilter = " WHERE branch_id = ?";
        $businessParams = [$specificBusinessId];
    } else {
        $placeholders = implode(',', array_fill(0, count($accessibleBusinessIds), '?'));
        $businessFilter = " WHERE branch_id IN ($placeholders)";
        $businessParams = $accessibleBusinessIds;
    }
    
    // Get room statistics (if frontdesk_rooms table exists)
    $roomStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_rooms,
            COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_rooms,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_rooms,
            COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_rooms
         FROM frontdesk_rooms" . $businessFilter,
        $businessParams
    );
    
    $totalRooms = $roomStats ? (int)$roomStats['total_rooms'] : 0;
    $occupiedRooms = $roomStats ? (int)$roomStats['occupied_rooms'] : 0;
    $availableRooms = $roomStats ? (int)$roomStats['available_rooms'] : 0;
    $maintenanceRooms = $roomStats ? (int)$roomStats['maintenance_rooms'] : 0;
    
    // Get today's check-ins and check-outs (if frontdesk_reservations table exists)
    $todayParams = array_merge([$today, $today], $businessParams);
    $todayActivity = $db->fetchOne(
        "SELECT 
            COUNT(CASE WHEN DATE(check_in_date) = ? THEN 1 END) as today_checkins,
            COUNT(CASE WHEN DATE(check_out_date) = ? THEN 1 END) as today_checkouts
         FROM frontdesk_reservations" . $businessFilter . "
            AND status IN ('checked_in', 'checked_out')",
        $todayParams
    );
    
    $todayCheckins = $todayActivity ? (int)$todayActivity['today_checkins'] : 0;
    $todayCheckouts = $todayActivity ? (int)$todayActivity['today_checkouts'] : 0;
    
    $occupancyRate = $totalRooms > 0 ? ($occupiedRooms / $totalRooms) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'total_rooms' => $totalRooms,
        'occupied_rooms' => $occupiedRooms,
        'available_rooms' => $availableRooms,
        'maintenance_rooms' => $maintenanceRooms,
        'occupancy_rate' => round($occupancyRate, 1),
        'today_checkins' => $todayCheckins,
        'today_checkouts' => $todayCheckouts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
