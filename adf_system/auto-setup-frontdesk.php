<?php
// ============================================
// AUTO-SETUP FRONTDESK TABLES
// Open this file in browser to create tables
// ============================================

require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h1>🏨 Frontdesk Tables Setup</h1>";
echo "<p>Database: " . DB_NAME . "</p>";
echo "<hr>";

try {
    $conn->beginTransaction();
    
    // 1. Rooms table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number VARCHAR(20) UNIQUE NOT NULL,
            room_type VARCHAR(50),
            floor INT,
            capacity INT DEFAULT 1,
            status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
            price_per_night DECIMAL(12, 2),
            description TEXT,
            amenities JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_room_number (room_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'rooms' created<br>";
    
    // 2. Guests table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS guests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            id_type VARCHAR(20),
            id_number VARCHAR(50),
            country VARCHAR(100),
            city VARCHAR(100),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_email (email),
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'guests' created<br>";
    
    // 3. Bookings table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            guest_id INT NOT NULL,
            room_id INT NOT NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            number_of_guests INT DEFAULT 1,
            total_price DECIMAL(12, 2),
            paid_amount DECIMAL(12, 2) DEFAULT 0,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guest_id (guest_id),
            INDEX idx_room_id (room_id),
            INDEX idx_status (status),
            INDEX idx_check_in (check_in_date),
            INDEX idx_check_out (check_out_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'bookings' created<br>";
    
    // 4. Booking payments table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS booking_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            amount DECIMAL(12, 2) NOT NULL,
            payment_method VARCHAR(50),
            payment_date DATE NOT NULL,
            transaction_id VARCHAR(100),
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking_id (booking_id),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'booking_payments' created<br>";
    
    // 5. Occupancy log table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS occupancy_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            booking_id INT,
            old_status VARCHAR(50),
            new_status VARCHAR(50),
            changed_by INT,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_changed_at (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'occupancy_log' created<br>";
    
    // Insert sample rooms
    $conn->exec("
        INSERT IGNORE INTO rooms (room_number, room_type, floor, capacity, status, price_per_night) VALUES
        ('101', 'Single', 1, 1, 'available', 500000),
        ('102', 'Double', 1, 2, 'available', 750000),
        ('103', 'Suite', 1, 3, 'available', 1500000),
        ('201', 'Single', 2, 1, 'available', 500000),
        ('202', 'Double', 2, 2, 'available', 750000),
        ('203', 'Suite', 2, 3, 'available', 1500000)
    ");
    echo "✅ Sample rooms inserted<br>";
    
    // Insert sample guests
    $conn->exec("
        INSERT IGNORE INTO guests (name, email, phone) VALUES
        ('John Doe', 'john@example.com', '081234567890'),
        ('Jane Smith', 'jane@example.com', '082345678901'),
        ('Bob Johnson', 'bob@example.com', '083456789012')
    ");
    echo "✅ Sample guests inserted<br>";
    
    $conn->commit();
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✅ All tables created successfully!</p>";
    echo "<p><a href='index.php' class='btn btn-primary'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
