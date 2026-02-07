<?php
/**
 * DELETE BOOKING API
 * Permanently delete booking from database
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
    // Start transaction
    $pdo->beginTransaction();
    
    // Get booking info first
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    // Cannot delete if checked in
    if ($booking['status'] === 'checked_in') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cannot delete booking that is checked in']);
        exit;
    }
    
    // Delete breakfast orders first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM breakfast_orders WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    
    // Delete booking payments
    $stmt = $pdo->prepare("DELETE FROM booking_payments WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    
    // Delete booking
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete Booking Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
