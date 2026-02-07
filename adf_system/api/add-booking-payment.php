<?php
/**
 * API: Add Booking Payment
 * Insert payment record and update booking payment status
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

define('APP_ACCESS', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

ob_clean();
header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$auth->hasPermission('frontdesk')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

try {
    $bookingId = $_POST['booking_id'] ?? null;
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? 'cash';

    if (!$bookingId) {
        throw new Exception('Booking ID is required');
    }
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }

    $validMethods = ['cash', 'card', 'transfer', 'qris', 'ota'];
    if (!in_array($paymentMethod, $validMethods, true)) {
        $paymentMethod = 'cash';
    }

    $booking = $db->fetchOne("SELECT id, final_price, paid_amount FROM bookings WHERE id = ?", [$bookingId]);
    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $db->beginTransaction();

    $db->query("INSERT INTO booking_payments (booking_id, amount, payment_method, processed_by, payment_date, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())", [
        $bookingId,
        $amount,
        $paymentMethod,
        $currentUser['id']
    ]);

    $payment = $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as paid FROM booking_payments WHERE booking_id = ?", [$bookingId]);
    $totalPaid = max((float)$payment['paid'], (float)$booking['paid_amount']);
    $remaining = max(0, (float)$booking['final_price'] - $totalPaid);

    if ($totalPaid <= 0) {
        $paymentStatus = 'unpaid';
    } elseif ($remaining <= 0) {
        $paymentStatus = 'paid';
    } else {
        $paymentStatus = 'partial';
    }

    $db->query("UPDATE bookings SET paid_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ?", [
        $totalPaid,
        $paymentStatus,
        $bookingId
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment saved',
        'total_paid' => $totalPaid,
        'remaining' => $remaining,
        'payment_status' => $paymentStatus
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
