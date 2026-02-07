<?php
/**
 * API: Check-in Guest
 * Update booking status to 'checked_in' and record check-in time
 */

// Suppress all errors and warnings to prevent non-JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

define('APP_ACCESS', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

// Clean any output that might have been generated
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
    // Get booking ID from request
    $bookingId = $_POST['booking_id'] ?? null;
    
    if (!$bookingId) {
        throw new Exception('Booking ID is required');
    }
    
    $createInvoice = ($_POST['create_invoice'] ?? '0') == '1';

    // Get booking details
    $booking = $db->fetchOne("
        SELECT b.*, g.guest_name, g.phone as guest_phone, g.email as guest_email, r.room_number 
        FROM bookings b
        LEFT JOIN guests g ON b.guest_id = g.id
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE b.id = ?
    ", [$bookingId]);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Check if already checked in
    if ($booking['status'] === 'checked_in') {
        throw new Exception('Guest sudah check-in sebelumnya');
    }
    
    // Check if booking is confirmed or pending
    if (!in_array($booking['status'], ['confirmed', 'pending'])) {
        throw new Exception('Booking status tidak valid untuk check-in');
    }
    
    // Calculate remaining payment
    $payment = $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as paid FROM booking_payments WHERE booking_id = ?", [$bookingId]);
    $totalPaid = max((float)$payment['paid'], (float)$booking['paid_amount']);
    $remaining = max(0, (float)$booking['final_price'] - $totalPaid);

    if ($remaining > 0 && !$createInvoice) {
        throw new Exception('Pembayaran belum lunas. Silakan bayar atau buat invoice sisa.');
    }

    // Start transaction
    $db->beginTransaction();

    // Create invoice for remaining balance if required
    $invoiceNumber = null;
    if ($remaining > 0 && $createInvoice) {
        $division = $db->fetchOne("SELECT id FROM divisions WHERE is_active = 1 AND division_name = 'Room Sell' LIMIT 1");
        if (!$division) {
            $division = $db->fetchOne("SELECT id FROM divisions WHERE is_active = 1 AND (division_name LIKE '%Front Desk%' OR division_name LIKE '%Frontdesk%' OR division_code LIKE '%-FD') ORDER BY id ASC LIMIT 1");
        }
        if (!$division) {
            $division = $db->fetchOne("SELECT id FROM divisions WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        }
        if (!$division) {
            throw new Exception('Divisi tidak ditemukan untuk invoice');
        }

        $prefix = 'INV-' . date('Ym') . '-';
        $lastInvoice = $db->fetchOne("SELECT invoice_number FROM sales_invoices_header WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1", [$prefix . '%']);
        $newNumber = 1;
        if ($lastInvoice) {
            $lastNumber = (int)substr($lastInvoice['invoice_number'], -4);
            $newNumber = $lastNumber + 1;
        }
        $invoiceNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        $invoiceId = $db->insert('sales_invoices_header', [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => date('Y-m-d'),
            'customer_name' => $booking['guest_name'],
            'customer_phone' => $booking['guest_phone'] ?? null,
            'customer_email' => $booking['guest_email'] ?? null,
            'customer_address' => null,
            'division_id' => $division['id'],
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'subtotal' => $remaining,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $remaining,
            'paid_amount' => 0,
            'notes' => 'Auto invoice from check-in. Booking #' . $booking['booking_code'],
            'created_by' => $currentUser['id']
        ]);

        $db->insert('sales_invoices_detail', [
            'invoice_header_id' => $invoiceId,
            'item_name' => 'Room Revenue - ' . $booking['booking_code'],
            'item_description' => 'Room ' . $booking['room_number'] . ' ' . $booking['check_in_date'] . ' - ' . $booking['check_out_date'],
            'category' => 'Room Revenue',
            'quantity' => 1,
            'unit_price' => $remaining,
            'total_price' => $remaining,
            'notes' => null
        ]);
    }
    
    // Update booking status to checked_in
    $db->query("
        UPDATE bookings 
        SET status = 'checked_in',
            actual_checkin_time = NOW(),
            checked_in_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$currentUser['id'], $bookingId]);
    
    // Update room status to occupied
    $db->query("
        UPDATE rooms 
        SET status = 'occupied',
            current_guest_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [$booking['guest_id'], $booking['room_id']]);
    
    // Log activity
    $db->query("
        INSERT INTO activity_logs (user_id, action, description, created_at)
        VALUES (?, ?, ?, NOW())
    ", [
        $currentUser['id'],
        'check_in',
        "Check-in guest: {$booking['guest_name']} - Room {$booking['room_number']} - Booking #{$booking['booking_code']}"
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Check-in berhasil! {$booking['guest_name']} - Room {$booking['room_number']}",
        'booking_id' => $bookingId,
        'guest_name' => $booking['guest_name'],
        'room_number' => $booking['room_number'],
        'invoice_number' => $invoiceNumber
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Check-in Error: " . $e->getMessage());
    
    // Clean output buffer before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();
