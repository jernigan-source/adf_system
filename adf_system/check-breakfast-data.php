<?php
echo "=== CEK BREAKFAST ORDERS DATA ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=adf_narayana_hotel', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cek apakah table ada
    $result = $pdo->query("SHOW TABLES LIKE 'breakfast_orders'");
    if ($result->rowCount() == 0) {
        echo "❌ Table breakfast_orders TIDAK ADA!\n";
        exit;
    }
    
    echo "✅ Table breakfast_orders ada\n\n";
    
    // Cek struktur table
    echo "STRUKTUR TABLE:\n";
    $columns = $pdo->query("SHOW COLUMNS FROM breakfast_orders")->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Cek total data
    $total = $pdo->query("SELECT COUNT(*) as total FROM breakfast_orders")->fetch();
    echo "Total breakfast orders: {$total['total']}\n\n";
    
    if ($total['total'] > 0) {
        echo "DATA BREAKFAST ORDERS:\n";
        $orders = $pdo->query("SELECT bo.*, 
                                      b.booking_code,
                                      g.guest_name,
                                      r.room_number
                               FROM breakfast_orders bo
                               LEFT JOIN bookings b ON bo.booking_id = b.id
                               LEFT JOIN guests g ON b.guest_id = g.id
                               LEFT JOIN rooms r ON b.room_id = r.id
                               ORDER BY bo.created_at DESC 
                               LIMIT 10")->fetchAll();
        
        foreach ($orders as $order) {
            echo "\n";
            echo "ID: {$order['id']}\n";
            echo "Booking ID: {$order['booking_id']}\n";
            echo "Booking Code: {$order['booking_code']}\n";
            echo "Guest: {$order['guest_name']}\n";
            echo "Room: {$order['room_number']}\n";
            echo "Date: {$order['order_date']}\n";
            echo "Time: {$order['order_time']}\n";
            echo "Pax: {$order['pax']}\n";
            echo "Location: {$order['service_location']}\n";
            echo "Menu Items: {$order['menu_items']}\n";
            echo "Created: {$order['created_at']}\n";
            echo str_repeat("-", 60) . "\n";
        }
    } else {
        echo "⚠️ TIDAK ADA DATA breakfast orders!\n";
    }
    
    // Cek booking yang sedang checked_in
    echo "\n\nBOOKING CHECKED_IN:\n";
    $bookings = $pdo->query("SELECT b.id, b.booking_code, g.guest_name, r.room_number 
                             FROM bookings b
                             INNER JOIN guests g ON b.guest_id = g.id
                             INNER JOIN rooms r ON b.room_id = r.id
                             WHERE b.status = 'checked_in'
                             ORDER BY r.room_number")->fetchAll();
    
    foreach ($bookings as $b) {
        echo "  Booking ID: {$b['id']} | Code: {$b['booking_code']} | Guest: {$b['guest_name']} | Room: {$b['room_number']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
