<?php
/**
 * CANCEL BOOKING API
 * Change booking status to 'cancelled'
 */

define('APP_ACCESS', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('frontdesk')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$currentUser = $auth->getCurrentUser();

$input = json_decode(file_get_contents('php://input'), true);
$bookingId = $input['booking_id'] ?? null;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit;
}

try {
    // Get booking info first
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    // Cannot cancel if already checked in or checked out
    if (in_array($booking['status'], ['checked_in', 'checked_out'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel booking that is already checked in or checked out']);
        exit;
    }
    
    // Update booking status to cancelled
    $stmt = $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute(['cancelled', $bookingId]);
    
    // Log activity
    $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, module, description, created_at) 
                              VALUES (?, ?, ?, ?, NOW())");
    $logStmt->execute([
        $currentUser['id'],
        'CANCEL_BOOKING',
        'frontdesk',
        "Cancelled booking {$booking['booking_code']}"
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    
} catch (Exception $e) {
    error_log("Cancel Booking Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
