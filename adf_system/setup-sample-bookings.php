<?php
/**
 * SAMPLE DATA GENERATOR
 * Delete all bookings and create sample data for testing
 */

define('APP_ACCESS', true);
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    $pdo->beginTransaction();
    
    echo "<h2>🔄 Generating Sample Data...</h2>\n";
    
    // 1. Delete all related data - handle non-existent tables
    echo "1️⃣ Deleting existing data...\n";
    try { $pdo->exec("DELETE FROM breakfast_orders"); } catch (Exception $e) {}
    try { $pdo->exec("DELETE FROM booking_payments"); } catch (Exception $e) {}
    $pdo->exec("DELETE FROM bookings");
    $pdo->exec("DELETE FROM guests");
    echo "✅ Old data deleted\n\n";
    
    // Get rooms first
    $rooms = $pdo->query("SELECT id FROM rooms LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rooms)) {
        echo "❌ No rooms found! Create rooms first.\n";
        exit;
    }
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $dayAfterTomorrow = date('Y-m-d', strtotime('+2 days'));
    
    echo "2️⃣ Creating sample guests and bookings...\n";
    
    // Sample data
    $samples = [
        // Check-in today (confirmed - not yet checked in)
        [
            'guest_name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'check_in_date' => $today,
            'check_out_date' => date('Y-m-d', strtotime('+2 days')),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'booking_source' => 'walk_in',
            'room_index' => 0,
            'nights' => 2
        ],
        // Checked in today
        [
            'guest_name' => 'Siti Nurhaliza',
            'phone' => '082345678901',
            'email' => 'siti@example.com',
            'check_in_date' => $today,
            'check_out_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'booking_source' => 'online',
            'room_index' => 1,
            'nights' => 3
        ],
        // Check-in tomorrow (confirmed)
        [
            'guest_name' => 'Ahmad Wijaya',
            'phone' => '083456789012',
            'email' => 'ahmad@example.com',
            'check_in_date' => $tomorrow,
            'check_out_date' => date('Y-m-d', strtotime('+4 days')),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'booking_source' => 'ota',
            'room_index' => 2,
            'nights' => 3
        ],
        // Check-in day after tomorrow (confirmed)
        [
            'guest_name' => 'Dina Putri',
            'phone' => '084567890123',
            'email' => 'dina@example.com',
            'check_in_date' => $dayAfterTomorrow,
            'check_out_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'booking_source' => 'online',
            'room_index' => 0,
            'nights' => 2
        ],
        // Checkout today (in-house)
        [
            'guest_name' => 'Eka Prasetyo',
            'phone' => '085678901234',
            'email' => 'eka@example.com',
            'check_in_date' => date('Y-m-d', strtotime('-1 day')),
            'check_out_date' => $today,
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'booking_source' => 'phone',
            'room_index' => 1,
            'nights' => 1
        ]
    ];
    
    // Insert guests and bookings
    foreach ($samples as $idx => $data) {
        // Insert guest
        $guestStmt = $pdo->prepare("INSERT INTO guests (guest_name, phone, email, id_card_number, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())");
        $idCardNumber = '12345' . str_pad($idx + 1, 8, '0', STR_PAD_LEFT);
        $guestStmt->execute([$data['guest_name'], $data['phone'], $data['email'], $idCardNumber]);
        $guestId = $pdo->lastInsertId();
        
        // Calculate nights
        $nights = (int)$data['nights'];
        $roomPrice = 500000; // Default room price
        
        // Insert booking
        $bookingStmt = $pdo->prepare("INSERT INTO bookings 
            (booking_code, guest_id, room_id, check_in_date, check_out_date, total_nights, 
             room_price, total_price, final_price, discount, status, payment_status, 
             booking_source, paid_amount, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, NOW(), NOW())");
        
        $bookingCode = 'BK-' . date('Ymd') . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
        $totalPrice = $roomPrice * $nights;
        
        $bookingStmt->execute([
            $bookingCode,
            $guestId,
            $rooms[$data['room_index']]['id'],
            $data['check_in_date'],
            $data['check_out_date'],
            $nights,
            $roomPrice,
            $totalPrice,
            $totalPrice,
            $data['status'],
            $data['payment_status'],
            $data['booking_source']
        ]);
        
        $bookingId = $pdo->lastInsertId();
        
        // Insert payment if paid
        if ($data['payment_status'] === 'paid') {
            try {
                $paymentStmt = $pdo->prepare("INSERT INTO booking_payments 
                    (booking_id, amount, payment_method, paid_at, created_at) 
                    VALUES (?, ?, ?, NOW(), NOW())");
                $paymentStmt->execute([$bookingId, $totalPrice, 'cash']);
            } catch (Exception $e) {
                // Table may not exist, skip
            }
        }
        
        echo "✅ {$data['guest_name']} - {$bookingCode}\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Sample data created successfully!\n";
    echo "📊 Summary:\n";
    echo "  - 1 Tamu Check-in Hari Ini (Pending)\n";
    echo "  - 1 Tamu Sudah Check-in Hari Ini (In-House)\n";
    echo "  - 1 Tamu Check-in Besuk\n";
    echo "  - 1 Tamu Check-in Lusa\n";
    echo "  - 1 Tamu Checkout Hari Ini\n";
    echo "\n<a href='modules/frontdesk/laporan.php'>👉 View Laporan Harian</a>\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage();
    error_log("Sample Data Error: " . $e->getMessage());
}
?>
