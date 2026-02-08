<?php
/**
 * API: CREATE RESERVATION
 * Handles reservation creation from calendar
 */

// Start output buffering FIRST before any includes
ob_start();

// Suppress all errors/warnings
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('APP_ACCESS', true);

// Capture output from includes
ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
ob_end_clean();

// Clear ALL buffered output before sending JSON
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header AFTER clearing buffers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

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

try {
    // Validate required fields
    $required = ['guest_name', 'check_in_date', 'check_out_date', 'room_id', 'room_price', 'booking_source'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required");
        }
    }
    
    // Get form data
    $guestName = trim($_POST['guest_name']);
    $guestPhone = trim($_POST['guest_phone'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestIdNumber = trim($_POST['guest_id_number'] ?? '');
    
    $checkInDate = $_POST['check_in_date'];
    $checkOutDate = $_POST['check_out_date'];
    $roomId = (int)($_POST['room_id'] ?? 0);
    $roomPrice = (float)($_POST['room_price'] ?? 0);
    $totalNights = (int)($_POST['total_nights'] ?? 0);
    $adultCount = (int)($_POST['adult_count'] ?? 1);
    $childrenCount = (int)($_POST['children_count'] ?? 0);
    $bookingSource = $_POST['booking_source'];
    $discount = (float)($_POST['discount'] ?? 0);
    $totalPrice = (float)($_POST['total_price'] ?? 0);
    $finalPrice = (float)($_POST['final_price'] ?? 0);
    $specialRequest = trim($_POST['special_request'] ?? '');
    $paymentStatus = $_POST['payment_status'] ?? 'unpaid';
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $paymentMethodRaw = $_POST['payment_method'] ?? 'cash';
    $paymentMethod = strtolower(trim($paymentMethodRaw));
    if ($paymentMethod === 'qr') {
        $paymentMethod = 'qris';
    }
    $allowedMethods = ['cash', 'card', 'transfer', 'qris', 'ota', 'bank_transfer', 'other'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = 'cash';
    }
    
    // Map booking source to database enum values
    $sourceMap = [
        'walk_in' => 'walk_in',
        'phone' => 'phone',
        'online' => 'online',
        'agoda' => 'ota',
        'booking' => 'ota',
        'tiket' => 'ota',
        'airbnb' => 'ota',
        'ota' => 'ota'
    ];
    $bookingSource = $sourceMap[$bookingSource] ?? 'walk_in';
    
    // Validate dates
    $checkIn = new DateTime($checkInDate);
    $checkOut = new DateTime($checkOutDate);
    
    if ($checkOut <= $checkIn) {
        throw new Exception("Check-out date must be after check-in date");
    }
    
    // Calculate nights if not provided
    if ($totalNights == 0) {
        $interval = $checkIn->diff($checkOut);
        $totalNights = $interval->days;
    }
    
    // Check room availability
    $conflicts = $db->fetchAll("
        SELECT id FROM bookings 
        WHERE room_id = ? 
        AND status != 'cancelled'
        AND (
            (check_in_date < ? AND check_out_date > ?)
            OR (check_in_date >= ? AND check_in_date < ?)
        )
    ", [$roomId, $checkOutDate, $checkInDate, $checkInDate, $checkOutDate]);
    
    if (!empty($conflicts)) {
        throw new Exception("Room is not available for selected dates");
    }
    
    $db->beginTransaction();
    
    // ALWAYS CREATE NEW GUEST for each reservation
    // This prevents name changes when same phone/email is used
    $idCardNumber = !empty($guestIdNumber) ? $guestIdNumber : 'TEMP-' . date('YmdHis') . '-' . rand(1000, 9999);
    $db->query("
        INSERT INTO guests (guest_name, phone, email, id_card_number, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ", [$guestName, $guestPhone, $guestEmail, $idCardNumber]);
    $guestId = $db->getConnection()->lastInsertId();
    
    // Generate booking code
    $bookingCode = 'BK-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if booking code exists
    $exists = $db->fetchOne("SELECT id FROM bookings WHERE booking_code = ?", [$bookingCode]);
    if ($exists) {
        $bookingCode = 'BK-' . date('YmdHis') . '-' . rand(100, 999);
    }
    
    // Ensure payment status matches paid amount
    if ($paidAmount <= 0) {
        $paymentStatus = 'unpaid';
    } elseif ($paidAmount >= $finalPrice) {
        $paymentStatus = 'paid';
    } else {
        $paymentStatus = 'partial';
    }

    // Create booking (remove guest_name from INSERT as it doesn't exist in table)
    $bookingStmt = $db->query("
        INSERT INTO bookings (
            booking_code, guest_id, room_id, 
            check_in_date, check_out_date, total_nights,
            adults, children,
            room_price, total_price, discount, final_price,
            booking_source, status, payment_status, paid_amount,
            special_request, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, NOW())
    ", [
        $bookingCode, $guestId, $roomId,
        $checkInDate, $checkOutDate, $totalNights,
        $adultCount, $childrenCount,
        $roomPrice, $totalPrice, $discount, $finalPrice,
        $bookingSource, $paymentStatus, $paidAmount,
        $specialRequest
    ]);

    if (!$bookingStmt) {
        throw new Exception('Failed to create booking');
    }
    
    $bookingId = $db->getConnection()->lastInsertId();
    
    // Create initial payment record if paid amount exists
    if ($paidAmount > 0) {
        $columnInfo = $db->fetchOne("SHOW COLUMNS FROM booking_payments LIKE 'payment_method'");
        if (!empty($columnInfo['Type']) && preg_match("/^enum\((.*)\)$/i", $columnInfo['Type'], $matches)) {
            $enumValues = array_map(function ($value) {
                return trim($value, "'\"");
            }, explode(',', $matches[1]));
            if (!in_array($paymentMethod, $enumValues, true)) {
                $paymentMethod = $enumValues[0] ?? 'cash';
            }
        }

        $paymentStmt = $db->query("
            INSERT INTO booking_payments (booking_id, amount, payment_method)
            VALUES (?, ?, ?)
        ", [$bookingId, $paidAmount, $paymentMethod]);

        if (!$paymentStmt) {
            throw new Exception('Failed to create payment record');
        }
    }

    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reservation created successfully',
        'booking_id' => $bookingId,
        'booking_code' => $bookingCode
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Create Reservation Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
