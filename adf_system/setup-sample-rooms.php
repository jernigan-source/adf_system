<?php
// Setup Sample Data untuk 12 Kamar
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // 1. INSERT ROOM TYPES
    echo "📝 Inserting Room Types...\n";
    
    $roomTypes = [
        ['type_name' => 'King Room', 'base_price' => 500000, 'max_occupancy' => 2, 'amenities' => 'King Bed, AC, WiFi, TV, Bathroom', 'color_code' => '#3b82f6'],
        ['type_name' => 'Twin Room', 'base_price' => 400000, 'max_occupancy' => 2, 'amenities' => 'Twin Beds, AC, WiFi, TV, Bathroom', 'color_code' => '#10b981'],
        ['type_name' => 'Queen Room', 'base_price' => 450000, 'max_occupancy' => 2, 'amenities' => 'Queen Bed, AC, WiFi, TV, Bathroom', 'color_code' => '#8b5cf6']
    ];
    
    // Clear existing room types (optional)
    $pdo->exec("DELETE FROM room_types WHERE type_name IN ('King Room', 'Twin Room', 'Queen Room')");
    
    $roomTypeIds = [];
    foreach ($roomTypes as $type) {
        $stmt = $pdo->prepare("INSERT INTO room_types (type_name, base_price, max_occupancy, amenities, color_code) 
                             VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $type['type_name'],
            $type['base_price'],
            $type['max_occupancy'],
            $type['amenities'],
            $type['color_code']
        ]);
        $roomTypeIds[$type['type_name']] = $pdo->lastInsertId();
        echo "  ✓ " . $type['type_name'] . " (ID: " . $roomTypeIds[$type['type_name']] . ")\n";
    }
    
    // 2. INSERT ROOMS
    echo "\n🚪 Inserting 12 Sample Rooms...\n";
    
    $rooms = [];
    // 6 King Rooms (101-106)
    for ($i = 1; $i <= 6; $i++) {
        $rooms[] = [
            'room_number' => '10' . $i,
            'room_type_id' => $roomTypeIds['King Room'],
            'floor_number' => 1,
            'status' => 'available',
            'position_x' => 100 + ($i * 150),
            'position_y' => 100
        ];
    }
    
    // 3 Twin Rooms (201-203)
    for ($i = 1; $i <= 3; $i++) {
        $rooms[] = [
            'room_number' => '20' . $i,
            'room_type_id' => $roomTypeIds['Twin Room'],
            'floor_number' => 2,
            'status' => 'available',
            'position_x' => 100 + ($i * 150),
            'position_y' => 300
        ];
    }
    
    // 3 Queen Rooms (202-204)
    for ($i = 2; $i <= 4; $i++) {
        $rooms[] = [
            'room_number' => '30' . $i,
            'room_type_id' => $roomTypeIds['Queen Room'],
            'floor_number' => 3,
            'status' => 'available',
            'position_x' => 100 + (($i-1) * 150),
            'position_y' => 500
        ];
    }
    
    // Clear existing rooms
    $pdo->exec("DELETE FROM rooms WHERE room_number IN ('101','102','103','104','105','106','201','202','203','302','303','304')");
    
    $roomIds = [];
    foreach ($rooms as $room) {
        $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_type_id, floor_number, status, position_x, position_y) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $room['room_number'],
            $room['room_type_id'],
            $room['floor_number'],
            $room['status'],
            $room['position_x'],
            $room['position_y']
        ]);
        $roomIds[$room['room_number']] = $pdo->lastInsertId();
        echo "  ✓ Room " . $room['room_number'] . " (ID: " . $roomIds[$room['room_number']] . ")\n";
    }
    
    // 3. INSERT SAMPLE GUESTS
    echo "\n👤 Inserting Sample Guests...\n";
    
    $guests = [
        ['guest_name' => 'John Doe', 'id_card_type' => 'Passport', 'id_card_number' => 'AB123456', 'phone' => '+1234567890', 'email' => 'john@example.com', 'address' => 'New York, USA', 'nationality' => 'American'],
        ['guest_name' => 'Jane Smith', 'id_card_type' => 'Passport', 'id_card_number' => 'CD789012', 'phone' => '+1987654321', 'email' => 'jane@example.com', 'address' => 'London, UK', 'nationality' => 'British'],
        ['guest_name' => 'Ahmad Wijaya', 'id_card_type' => 'KTP', 'id_card_number' => '3507021234567890', 'phone' => '+6281234567890', 'email' => 'ahmad@example.com', 'address' => 'Jakarta, Indonesia', 'nationality' => 'Indonesian'],
        ['guest_name' => 'Maria Garcia', 'id_card_type' => 'Passport', 'id_card_number' => 'EF345678', 'phone' => '+34123456789', 'email' => 'maria@example.com', 'address' => 'Madrid, Spain', 'nationality' => 'Spanish'],
        ['guest_name' => 'Siti Nurhayati', 'id_card_type' => 'KTP', 'id_card_number' => '1201011234567890', 'phone' => '+6287654321098', 'email' => 'siti@example.com', 'address' => 'Bandung, Indonesia', 'nationality' => 'Indonesian'],
        ['guest_name' => 'Michel Dupont', 'id_card_type' => 'Passport', 'id_card_number' => 'GH901234', 'phone' => '+33123456789', 'email' => 'michel@example.com', 'address' => 'Paris, France', 'nationality' => 'French'],
        ['guest_name' => 'Budi Santoso', 'id_card_type' => 'KTP', 'id_card_number' => '3201021234567890', 'phone' => '+6282345678901', 'email' => 'budi@example.com', 'address' => 'Surabaya, Indonesia', 'nationality' => 'Indonesian'],
        ['guest_name' => 'Lisa Wong', 'id_card_type' => 'Passport', 'id_card_number' => 'IJ567890', 'phone' => '+6581234567', 'email' => 'lisa@example.com', 'address' => 'Singapore', 'nationality' => 'Singaporean']
    ];
    
    // Clear existing guests
    $pdo->exec("DELETE FROM guests WHERE guest_name IN ('John Doe','Jane Smith','Ahmad Wijaya','Maria Garcia','Siti Nurhayati','Michel Dupont','Budi Santoso','Lisa Wong')");
    
    $guestIds = [];
    foreach ($guests as $guest) {
        $stmt = $pdo->prepare("INSERT INTO guests (guest_name, id_card_type, id_card_number, phone, email, address, nationality) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $guest['guest_name'],
            $guest['id_card_type'],
            $guest['id_card_number'],
            $guest['phone'],
            $guest['email'],
            $guest['address'],
            $guest['nationality']
        ]);
        $guestIds[$guest['guest_name']] = $pdo->lastInsertId();
        echo "  ✓ " . $guest['guest_name'] . " (ID: " . $guestIds[$guest['guest_name']] . ")\n";
    }
    
    // 4. INSERT SAMPLE BOOKINGS (dengan berbagai tanggal dan OTA source)
    echo "\n📅 Inserting Sample Bookings...\n";
    
    $otaProviders = ['Direct', 'Agoda', 'Booking.com', 'Tiket.com', 'Airbnb', 'Phone Booking'];
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $in3days = date('Y-m-d', strtotime('+3 days'));
    $in7days = date('Y-m-d', strtotime('+7 days'));
    $in10days = date('Y-m-d', strtotime('+10 days'));
    $in15days = date('Y-m-d', strtotime('+15 days'));
    
    $bookings = [
        // Current (today - 2 nights, checked in)
        [
            'booking_code' => 'BK001',
            'guest_id' => $guestIds['John Doe'],
            'room_id' => $roomIds['101'],
            'check_in_date' => date('Y-m-d', strtotime('-2 days')),
            'check_out_date' => $today,
            'status' => 'checked_out',
            'payment_status' => 'paid',
            'booking_source' => 'Direct',
            'room_price' => 500000,
            'total_price' => 1000000,
            'final_price' => 1000000,
            'discount' => 0,
            'paid_amount' => 1000000
        ],
        // In-house checked in (start 1 day ago, checkout in 2 days)
        [
            'booking_code' => 'BK002',
            'guest_id' => $guestIds['Jane Smith'],
            'room_id' => $roomIds['102'],
            'check_in_date' => date('Y-m-d', strtotime('-1 days')),
            'check_out_date' => date('Y-m-d', strtotime('+2 days')),
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'booking_source' => 'Agoda',
            'room_price' => 500000,
            'total_price' => 1500000,
            'final_price' => 1275000,
            'discount' => 0,
            'paid_amount' => 1275000
        ],
        // In-house checked in (start today, checkout 3 days)
        [
            'booking_code' => 'BK003',
            'guest_id' => $guestIds['Ahmad Wijaya'],
            'room_id' => $roomIds['103'],
            'check_in_date' => $today,
            'check_out_date' => $in3days,
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'booking_source' => 'Booking.com',
            'room_price' => 500000,
            'total_price' => 1500000,
            'final_price' => 1320000,
            'discount' => 0,
            'paid_amount' => 1320000
        ],
        // Confirmed pending check-in (tomorrow)
        [
            'booking_code' => 'BK004',
            'guest_id' => $guestIds['Maria Garcia'],
            'room_id' => $roomIds['104'],
            'check_in_date' => $tomorrow,
            'check_out_date' => $in3days,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'booking_source' => 'Tiket.com',
            'room_price' => 500000,
            'total_price' => 1000000,
            'final_price' => 900000,
            'discount' => 0,
            'paid_amount' => 0
        ],
        // Confirmed (in 7 days)
        [
            'booking_code' => 'BK005',
            'guest_id' => $guestIds['Siti Nurhayati'],
            'room_id' => $roomIds['105'],
            'check_in_date' => $in7days,
            'check_out_date' => date('Y-m-d', strtotime('+9 days')),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'booking_source' => 'Airbnb',
            'room_price' => 500000,
            'total_price' => 1000000,
            'final_price' => 970000,
            'discount' => 0,
            'paid_amount' => 1000000
        ],
        // Confirmed (in 10 days - Twin Room)
        [
            'booking_code' => 'BK006',
            'guest_id' => $guestIds['Michel Dupont'],
            'room_id' => $roomIds['201'],
            'check_in_date' => $in10days,
            'check_out_date' => date('Y-m-d', strtotime('+12 days')),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'booking_source' => 'Direct',
            'room_price' => 400000,
            'total_price' => 800000,
            'final_price' => 800000,
            'discount' => 0,
            'paid_amount' => 0
        ],
        // Confirmed (in 15 days - Queen Room)
        [
            'booking_code' => 'BK007',
            'guest_id' => $guestIds['Budi Santoso'],
            'room_id' => $roomIds['302'],
            'check_in_date' => $in15days,
            'check_out_date' => date('Y-m-d', strtotime('+17 days')),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'booking_source' => 'Phone Booking',
            'room_price' => 450000,
            'total_price' => 900000,
            'final_price' => 900000,
            'discount' => 0,
            'paid_amount' => 900000
        ],
        // Pending (in 5 days)
        [
            'booking_code' => 'BK008',
            'guest_id' => $guestIds['Lisa Wong'],
            'room_id' => $roomIds['106'],
            'check_in_date' => date('Y-m-d', strtotime('+5 days')),
            'check_out_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 'pending',
            'payment_status' => 'pending',
            'booking_source' => 'Booking.com',
            'room_price' => 500000,
            'total_price' => 1000000,
            'final_price' => 880000,
            'discount' => 0,
            'paid_amount' => 0
        ]
    ];
    
    // Clear existing bookings
    $pdo->exec("DELETE FROM bookings WHERE booking_code LIKE 'BK%'");
    
    $bookingIds = [];
    foreach ($bookings as $booking) {
        $stmt = $pdo->prepare("INSERT INTO bookings 
                             (booking_code, guest_id, room_id, check_in_date, check_out_date, status, payment_status, 
                              booking_source, room_price, total_price, final_price, discount, paid_amount) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $booking['booking_code'],
            $booking['guest_id'],
            $booking['room_id'],
            $booking['check_in_date'],
            $booking['check_out_date'],
            $booking['status'],
            $booking['payment_status'],
            $booking['booking_source'],
            $booking['room_price'],
            $booking['total_price'],
            $booking['final_price'],
            $booking['discount'],
            $booking['paid_amount']
        ]);
        $bookingIds[$booking['booking_code']] = $pdo->lastInsertId();
        echo "  ✓ " . $booking['booking_code'] . " | " . $booking['guest_id'] . " → Room " . $booking['room_id'] . 
             " | " . $booking['booking_source'] . " | Status: " . $booking['status'] . "\n";
    }
    
    // 5. INSERT SAMPLE PAYMENTS
    echo "\n💰 Inserting Sample Payments...\n";
    
    foreach ($bookingIds as $bookingCode => $bookingId) {
        // Find booking to get amount
        $bookingStmt = $pdo->prepare("SELECT final_price FROM bookings WHERE id = ?");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking && $booking['final_price'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO booking_payments (booking_id, payment_date, amount, payment_method) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $bookingId,
                date('Y-m-d'),
                $booking['final_price'],
                ['Bank Transfer', 'Cash', 'Credit Card', 'e-Wallet'][rand(0, 3)]
            ]);
            echo "  ✓ Payment for $bookingCode\n";
        }
    }
    
    echo "\n✅ SETUP COMPLETE!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Total Rooms: 12 (6 King, 3 Twin, 3 Queen)\n";
    echo "Total Guests: 8\n";
    echo "Total Bookings: 8\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n🎉 Sekarang buka calendar untuk lihat data-nya!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    die();
}
?>
