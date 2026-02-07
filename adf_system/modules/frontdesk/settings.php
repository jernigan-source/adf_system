<?php
/**
 * FRONT DESK SETTINGS
 */
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// ============================================
// SECURITY & AUTHENTICATION
// ============================================
$auth = new Auth();
$auth->requireLogin();

$currentUser = $auth->getCurrentUser();

// Verify permission - with fallback to role-based
if (!$auth->hasPermission('frontdesk')) {
    // Check role-based fallback
    $allowedRoles = ['admin', 'manager'];
    if (!in_array($currentUser['role'], $allowedRoles)) {
        header('Location: ' . BASE_URL . '/403.php');
        exit;
    }
}

$db = Database::getInstance();
$pdo = $db->getConnection();

// Debug: Check which database we're using
$currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
error_log("SETTINGS: Using database: " . $currentDb);

// Force fresh schema info
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$activeTab = $_GET['tab'] ?? 'rooms';
$message = '';
$error = '';

// ==================== CHECK IF TABLES EXIST ====================
$tablesExist = false;
try {
    $result = $pdo->query("SHOW TABLES LIKE 'rooms'")->fetch();
    $tablesExist = (is_array($result));
} catch (Exception $e) {
    $tablesExist = false;
}

if (!$tablesExist) {
    $error = "⚠️ Database tables belum diinisialisasi. <a href='" . BASE_URL . "/setup-frontdesk-tables.php' style='color: #6366f1; text-decoration: underline; font-weight: 600;'>Klik di sini untuk setup database FrontDesk</a>";
    $activeTab = 'setup'; // Force to setup tab
}

// ==================== ROOMS MANAGEMENT ====================
if ($activeTab === 'rooms' && $tablesExist) {
    // Add/Edit Room
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_room') {
                $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_type_id, floor_number, status) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['room_number'],
                    $_POST['room_type_id'],
                    $_POST['floor_number'],
                    'available'
                ]);
                $message = "✓ Kamar berhasil ditambahkan!";
                
            } elseif ($_POST['action'] === 'edit_room') {
                $stmt = $pdo->prepare("UPDATE rooms SET room_number=?, room_type_id=?, floor_number=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['room_number'],
                    $_POST['room_type_id'],
                    $_POST['floor_number'],
                    $_POST['status'],
                    $_POST['room_id']
                ]);
                $message = "✓ Kamar berhasil diupdate!";
                
            } elseif ($_POST['action'] === 'delete_room') {
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id=?");
                $stmt->execute([$_POST['room_id']]);
                $message = "✓ Kamar berhasil dihapus!";
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
    
    // Get rooms with types
    try {
        $stmt = $pdo->query("SELECT r.id, r.room_number, r.room_type_id, r.floor_number, r.status, 
                                   rt.type_name, rt.base_price
                            FROM rooms r
                            JOIN room_types rt ON r.room_type_id = rt.id
                            ORDER BY r.floor_number, r.room_number");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rooms = [];
    }
    
    // Get room types
    try {
        $stmt = $pdo->query("SELECT id, type_name, base_price FROM room_types ORDER BY type_name");
        $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $roomTypes = [];
    }
}

// ==================== ROOM TYPES MANAGEMENT ====================
elseif ($activeTab === 'room_types') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_type') {
                $stmt = $pdo->prepare("INSERT INTO room_types (type_name, base_price, max_occupancy, amenities, color_code) 
                                     VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['type_name'],
                    $_POST['base_price'],
                    $_POST['max_occupancy'],
                    $_POST['amenities'],
                    $_POST['color_code']
                ]);
                $message = "✓ Tipe kamar berhasil ditambahkan!";
                
            } elseif ($_POST['action'] === 'edit_type') {
                $stmt = $pdo->prepare("UPDATE room_types SET type_name=?, base_price=?, max_occupancy=?, amenities=?, color_code=? WHERE id=?");
                $stmt->execute([
                    $_POST['type_name'],
                    $_POST['base_price'],
                    $_POST['max_occupancy'],
                    $_POST['amenities'],
                    $_POST['color_code'],
                    $_POST['type_id']
                ]);
                $message = "✓ Tipe kamar berhasil diupdate!";
                
            } elseif ($_POST['action'] === 'delete_type') {
                // Check if there are any bookings with rooms of this type
                $checkBookings = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE r.room_type_id = ?");
                $checkBookings->execute([$_POST['type_id']]);
                $bookingCount = $checkBookings->fetchColumn();
                
                if ($bookingCount > 0) {
                    $error = "❌ Tidak bisa hapus! Ada {$bookingCount} booking aktif menggunakan kamar dengan tipe ini.";
                } else {
                    // Delete all rooms with this type first
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_type_id=?");
                    $stmt->execute([$_POST['type_id']]);
                    
                    // Then delete the room type
                    $stmt = $pdo->prepare("DELETE FROM room_types WHERE id=?");
                    $stmt->execute([$_POST['type_id']]);
                    $message = "✓ Tipe kamar dan semua kamar terkait berhasil dihapus!";
                }
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
    
    $stmt = $pdo->query("SELECT id, type_name, base_price, max_occupancy, amenities, color_code FROM room_types ORDER BY type_name");
    $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== BREAKFAST MENU MANAGEMENT ====================
elseif ($activeTab === 'breakfast_menu') {
    // Create table if not exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_menus (
            id INT PRIMARY KEY AUTO_INCREMENT,
            menu_name VARCHAR(100) NOT NULL,
            description TEXT,
            category ENUM('western', 'indonesian', 'asian', 'drinks', 'beverages', 'extras') DEFAULT 'western',
            price DECIMAL(10,2) DEFAULT 0.00,
            is_free BOOLEAN DEFAULT TRUE COMMENT 'TRUE = Free breakfast, FALSE = Extra/Paid',
            is_available BOOLEAN DEFAULT TRUE,
            image_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_available (is_available),
            INDEX idx_free (is_free)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_menu') {
                // Debug log
                error_log("ADD MENU: " . print_r($_POST, true));
                
                // Check current database and table structure
                $currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
                error_log("INSERT TO DATABASE: " . $currentDb);
                
                // Check if table exists and get columns
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'breakfast_menus'")->fetch();
                if (!$tableCheck) {
                    throw new Exception("Table breakfast_menus tidak ada di database {$currentDb}!");
                }
                
                $columns = $pdo->query("SHOW COLUMNS FROM breakfast_menus")->fetchAll(PDO::FETCH_COLUMN);
                error_log("AVAILABLE COLUMNS: " . implode(', ', $columns));
                
                $hasIsFree = in_array('is_free', $columns);
                
                if (!$hasIsFree) {
                    // Try to add the column
                    error_log("KOLOM is_free TIDAK ADA! Mencoba menambahkan...");
                    try {
                        $pdo->exec("ALTER TABLE breakfast_menus ADD COLUMN is_free BOOLEAN DEFAULT TRUE AFTER price");
                        error_log("Kolom is_free berhasil ditambahkan!");
                        $hasIsFree = true;
                    } catch (Exception $e) {
                        error_log("Gagal menambahkan kolom is_free: " . $e->getMessage());
                        // Will try INSERT without is_free column
                    }
                }
                
                // Try INSERT
                try {
                    if ($hasIsFree) {
                        $stmt = $pdo->prepare("INSERT INTO breakfast_menus (menu_name, description, category, price, is_free, is_available) 
                                             VALUES (?, ?, ?, ?, ?, ?)");
                        $result = $stmt->execute([
                            $_POST['menu_name'],
                            $_POST['description'] ?? null,
                            $_POST['category'],
                            $_POST['price'] ?? 0,
                            isset($_POST['is_free']) ? 1 : 0,
                            isset($_POST['is_available']) ? 1 : 0
                        ]);
                    } else {
                        // Fallback: INSERT without is_free
                        error_log("FALLBACK: INSERT tanpa kolom is_free");
                        $stmt = $pdo->prepare("INSERT INTO breakfast_menus (menu_name, description, category, price, is_available) 
                                             VALUES (?, ?, ?, ?, ?)");
                        $result = $stmt->execute([
                            $_POST['menu_name'],
                            $_POST['description'] ?? null,
                            $_POST['category'],
                            $_POST['price'] ?? 0,
                            isset($_POST['is_available']) ? 1 : 0
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("INSERT FAILED: " . $e->getMessage());
                    throw $e;
                }
                
                error_log("INSERT RESULT: " . ($result ? 'SUCCESS' : 'FAILED'));
                error_log("LAST INSERT ID: " . $pdo->lastInsertId());
                
                $message = "✓ Menu breakfast berhasil ditambahkan!";
                
                // Refresh page to show new menu
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=breakfast_menu");
                exit;
                
            } elseif ($_POST['action'] === 'edit_menu') {
                $stmt = $pdo->prepare("UPDATE breakfast_menus SET menu_name=?, description=?, category=?, price=?, is_free=?, is_available=? WHERE id=?");
                $stmt->execute([
                    $_POST['menu_name'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['price'],
                    isset($_POST['is_free']) ? 1 : 0,
                    isset($_POST['is_available']) ? 1 : 0,
                    $_POST['menu_id']
                ]);
                $message = "✓ Menu breakfast berhasil diupdate!";
                
            } elseif ($_POST['action'] === 'delete_menu') {
                $stmt = $pdo->prepare("DELETE FROM breakfast_menus WHERE id=?");
                $stmt->execute([$_POST['menu_id']]);
                $message = "✓ Menu breakfast berhasil dihapus!";
                
            } elseif ($_POST['action'] === 'toggle_availability') {
                $stmt = $pdo->prepare("UPDATE breakfast_menus SET is_available = NOT is_available WHERE id=?");
                $stmt->execute([$_POST['menu_id']]);
                $message = "✓ Status ketersediaan menu berhasil diupdate!";
            }
        } catch (Exception $e) {
            error_log("ERROR BREAKFAST MENU: " . $e->getMessage());
            error_log("ERROR TRACE: " . $e->getTraceAsString());
            $error = "❌ Error: " . $e->getMessage();
        }
    }
    
    // Get all breakfast menus
    try {
        $stmt = $pdo->query("SELECT 
            id, 
            menu_name, 
            description, 
            category, 
            price, 
            COALESCE(is_free, IF(price = 0, TRUE, FALSE)) as is_free,
            is_available, 
            image_url, 
            created_at, 
            updated_at 
            FROM breakfast_menus 
            ORDER BY category, menu_name");
        $breakfastMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $breakfastMenus = [];
    }
}

// ==================== OTA FEES MANAGEMENT ====================
elseif ($activeTab === 'ota_fees') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_fee') {
                $provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
                $feePercentage = isset($_POST['fee_percentage']) ? intval($_POST['fee_percentage']) : 0;
                
                // Validate
                if (empty($provider)) {
                    throw new Exception("Provider tidak boleh kosong");
                }
                
                if ($feePercentage < 0 || $feePercentage > 100) {
                    throw new Exception("Fee harus antara 0-100%");
                }
                
                $settingKey = 'ota_fee_' . strtolower(str_replace([' ', '_', '-'], '_', $provider));
                
                // Use correct column name: setting_type not type
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) 
                                     VALUES (?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()");
                $stmt->execute([
                    $settingKey,
                    $feePercentage,
                    'number',
                    $feePercentage
                ]);
                
                $message = "✓ Fee OTA untuk " . htmlspecialchars($provider) . " berhasil diupdate menjadi " . $feePercentage . "%!";
                
                // Log untuk debug
                error_log("OTA Fee Updated: $settingKey = $feePercentage%");
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
            error_log("OTA Fee Error: " . $e->getMessage());
        }
    }
    
    // Get OTA fees from settings or use defaults
    $otaFees = [
        'Direct' => 0,
        'Phone Booking' => 0,
        'Online Direct' => 0,
        'Agoda' => 15,
        'Booking.com' => 12,
        'Tiket.com' => 10,
        'Airbnb' => 3,
        'Other OTA' => 10
    ];
    
    // Try to load from database
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ota_fee_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $provider = str_replace(['ota_fee_', '_'], ['', ' '], $row['setting_key']);
            $provider = ucwords($provider);
            $otaFees[$provider] = (int)$row['setting_value'];
        }
    } catch (Exception $e) {}
}

include '../../includes/header.php';
?>

<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --bg-secondary: rgba(255, 255, 255, 0.08);
    --border-color: rgba(255, 255, 255, 0.15);
    --text-primary: var(--text-color);
    --text-secondary: rgba(255, 255, 255, 0.7);
}

.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 2rem;
}

.settings-header h1 {
    font-size: 2.5rem;
    font-weight: 950;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.settings-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0;
}

.tab-btn {
    padding: 1rem 1.5rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-weight: 700;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-btn:hover {
    color: var(--text-primary);
}

.message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 600;
    animation: slideIn 0.3s ease;
}

.message.success {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.5);
    color: #6ee7b7;
}

.message.error {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.5);
    color: #fca5a5;
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.table-wrapper {
    background: var(--bg-secondary);
    backdrop-filter: blur(30px);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
    margin-top: 2rem;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.table thead {
    background: rgba(99, 102, 241, 0.1);
    border-bottom: 2px solid var(--border-color);
}

.table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: var(--primary);
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.badge-success {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}

.form-card {
    background: var(--bg-secondary);
    backdrop-filter: blur(30px);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.color-picker-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.color-picker {
    width: 60px;
    height: 60px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.modal-form {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-form.show {
    display: flex;
}

.modal-content {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
}

.modal-close:hover {
    color: var(--text-primary);
}

.responsive-table {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .settings-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .settings-header h1 {
        font-size: 1.75rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .settings-nav {
        flex-wrap: wrap;
    }

    .tab-btn {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    .table {
        font-size: 0.85rem;
    }

    .table th, .table td {
        padding: 0.75rem;
    }
}
</style>

<div class="settings-container">
    <!-- Header -->
    <div class="settings-header">
        <div>
            <h1>⚙️ Settings & Configuration</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Manage rooms, room types, and OTA fees
            </p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/index.php" class="btn btn-primary" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                🏁 Back to FrontDesk
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/dashboard.php" class="btn btn-primary">
                📊 Full Dashboard
            </a>
        </div>
    </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!$tablesExist): ?>
    <div style="background: rgba(245, 158, 11, 0.1); border: 2px solid #f59e0b; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; text-align: center;">
        <h3 style="color: #d97706; margin: 0 0 1rem 0;">⚠️ Database Setup Required</h3>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">FrontDesk tables belum diinisialisasi. Silakan setup database terlebih dahulu.</p>
        <a href="<?php echo BASE_URL; ?>/setup-frontdesk-tables.php" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none; display: inline-block; font-weight: 600;">
            🔧 Setup Database Now
        </a>
    </div>
    <?php else: ?>

    <!-- Tabs Navigation -->
    <div class="settings-nav">
        <button class="tab-btn <?php echo $activeTab === 'rooms' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=rooms'">
            🚪 Manage Rooms
        </button>
        <button class="tab-btn <?php echo $activeTab === 'room_types' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=room_types'">
            🏢 Room Types
        </button>
        <button class="tab-btn <?php echo $activeTab === 'breakfast_menu' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=breakfast_menu'">
            🍳 Breakfast Menu
        </button>
        <button class="tab-btn <?php echo $activeTab === 'ota_fees' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=ota_fees'">
            💰 OTA Fees
        </button>
    </div>

    <!-- ==================== ROOMS TAB ==================== -->
    <?php if ($activeTab === 'rooms'): ?>
    
    <div class="form-card">
        <h2 style="margin-top: 0; color: var(--primary);">➕ Add New Room</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_room">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Room Number</label>
                    <input type="text" name="room_number" class="form-input" placeholder="e.g., 101" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Room Type</label>
                    <select name="room_type_id" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($roomTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>">
                            <?php echo $type['type_name']; ?> (Rp <?php echo number_format($type['base_price']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor Number</label>
                    <input type="number" name="floor_number" class="form-input" placeholder="e.g., 1" required>
                </div>
            </div>

            <button type="submit" class="btn btn-success">✓ Add Room</button>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Room Number</th>
                    <th>Type</th>
                    <th>Floor</th>
                    <th>Base Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><strong>🚪 <?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($room['type_name']); ?></td>
                    <td><span class="badge badge-primary">Floor <?php echo $room['floor_number']; ?></span></td>
                    <td>Rp <?php echo number_format($room['base_price']); ?></td>
                    <td>
                        <span class="badge <?php echo $room['status'] === 'available' ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo ucfirst($room['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)">
                                ✏️ Edit
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kamar ini?')">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <!-- ==================== ROOM TYPES TAB ==================== -->
    <?php if ($activeTab === 'room_types'): ?>
    
    <div class="form-card">
        <h2 style="margin-top: 0; color: var(--primary);">➕ Add New Room Type</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_type">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type Name</label>
                    <input type="text" name="type_name" class="form-input" placeholder="e.g., Deluxe Suite" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Base Price (IDR)</label>
                    <input type="number" name="base_price" class="form-input" placeholder="e.g., 500000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Occupancy</label>
                    <input type="number" name="max_occupancy" class="form-input" placeholder="e.g., 2" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Amenities</label>
                <textarea name="amenities" class="form-textarea" placeholder="e.g., King Bed, AC, WiFi, TV" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Color Code</label>
                <div class="color-picker-wrapper">
                    <input type="color" name="color_code" class="color-picker" value="#6366f1" required>
                    <input type="text" name="color_code" class="form-input" placeholder="#6366f1" value="#6366f1" style="max-width: 150px;">
                </div>
            </div>

            <button type="submit" class="btn btn-success">✓ Add Room Type</button>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Type Name</th>
                    <th>Base Price</th>
                    <th>Occupancy</th>
                    <th>Amenities</th>
                    <th>Color</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roomTypes as $type): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($type['type_name']); ?></strong></td>
                    <td>Rp <?php echo number_format($type['base_price']); ?></td>
                    <td><span class="badge badge-primary"><?php echo $type['max_occupancy']; ?> pax</span></td>
                    <td style="font-size: 0.85rem;">
                        <?php 
                        $amenities = explode(',', $type['amenities']);
                        foreach (array_slice($amenities, 0, 2) as $amenity) {
                            echo trim($amenity) . '<br>';
                        }
                        if (count($amenities) > 2) {
                            echo '...dan ' . (count($amenities) - 2) . ' lagi<br>';
                        }
                        ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <div style="width: 30px; height: 30px; background: <?php echo $type['color_code']; ?>; border-radius: 6px; border: 1px solid var(--border-color);"></div>
                            <span style="font-size: 0.85rem;"><?php echo $type['color_code']; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="editRoomType(<?php echo htmlspecialchars(json_encode($type)); ?>)">
                                ✏️ Edit
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus tipe kamar ini?\n\n⚠️ PERHATIAN: Semua kamar dengan tipe ini akan ikut terhapus!')">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <!-- ==================== BREAKFAST MENU TAB ==================== -->
    <?php if ($activeTab === 'breakfast_menu'): ?>
    
    <div class="form-card">
        <h2 style="margin-top: 0; color: var(--primary);">➕ Add New Breakfast Menu</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_menu">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Menu Name</label>
                    <input type="text" name="menu_name" class="form-input" placeholder="e.g., American Breakfast" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="western">🍳 Western</option>
                        <option value="indonesian">🍛 Indonesian</option>
                        <option value="asian">🍜 Asian</option>
                        <option value="drinks">🥤 Drinks</option>
                        <option value="extras">➕ Extra (Berbayar)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (Rp) - Kosongkan jika gratis</label>
                    <input type="number" name="price" class="form-input" placeholder="e.g., 35000 (0 untuk gratis)" step="0.01" min="0" value="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" placeholder="e.g., Eggs, bacon, sausage, toast, hash browns"></textarea>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-bottom: 0.5rem;">
                    <input type="checkbox" name="is_free" checked style="width: 20px; height: 20px;">
                    <span style="font-weight: 700;">🆓 Free Breakfast (Included in room rate)</span>
                </label>
                <small style="color: var(--text-secondary); display: block; margin-left: 28px;">
                    Unchecked = Extra Breakfast (Paid/Berbayar)
                </small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_available" checked style="width: 20px; height: 20px;">
                    <span>Available for ordering</span>
                </label>
            </div>

            <button type="submit" class="btn btn-success">✓ Add Menu</button>
        </form>
    </div>

    <div class="table-wrapper">
        <h3 style="color: var(--text-primary); margin-bottom: 1rem;">📋 Breakfast Menu List</h3>
        
        <?php 
        $categories = [
            'western' => '🍳 Western (Free)',
            'indonesian' => '🍛 Indonesian (Free)',
            'asian' => '🍜 Asian (Free)',
            'drinks' => '🥤 Drinks (Free)',
            'extras' => '➕ Extra Breakfast (Berbayar)'
        ];
        
        foreach ($categories as $catKey => $catLabel):
            $categoryMenus = array_filter($breakfastMenus, fn($m) => $m['category'] === $catKey);
            if (empty($categoryMenus)) continue;
        ?>
        
        <div style="margin-bottom: 2rem;">
            <h4 style="color: var(--primary); margin: 1rem 0 0.5rem 0;"><?php echo $catLabel; ?></h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Menu Name</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryMenus as $menu): ?>
                    <?php 
                        // Ensure is_free has a value (default based on price)
                        $isFree = isset($menu['is_free']) ? (bool)$menu['is_free'] : ($menu['price'] == 0);
                    ?>
                    <tr style="<?php echo $menu['is_available'] ? '' : 'opacity: 0.5;'; ?>">
                        <td><strong><?php echo htmlspecialchars($menu['menu_name']); ?></strong></td>
                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($menu['description'] ?? '-'); ?>
                        </td>
                        <td>
                            <span class="badge" style="<?php echo $isFree ? 'background: rgba(16, 185, 129, 0.2); color: #6ee7b7;' : 'background: rgba(245, 158, 11, 0.2); color: #fbbf24;'; ?>">
                                <?php echo $isFree ? '🆓 Free' : '💰 Paid'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isFree || $menu['price'] == 0): ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php else: ?>
                                <strong>Rp <?php echo number_format($menu['price'], 0, ',', '.'); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_availability">
                                <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
                                <button type="submit" class="badge" style="border: none; cursor: pointer; padding: 0.5rem 1rem; <?php echo $menu['is_available'] ? 'background: rgba(16, 185, 129, 0.2); color: #6ee7b7;' : 'background: rgba(239, 68, 68, 0.2); color: #fca5a5;'; ?>">
                                    <?php echo $menu['is_available'] ? '✓ Available' : '✗ Unavailable'; ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-primary btn-sm" 
                                        onclick="editBreakfastMenu(<?php echo htmlspecialchars(json_encode($menu)); ?>)">
                                    ✏️ Edit
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_menu">
                                    <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus menu ini?')">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <!-- ==================== OTA FEES TAB ==================== -->
    <?php if ($activeTab === 'ota_fees'): ?>
    
    <div class="form-card">
        <h2 style="margin-top: 0; color: var(--primary);">💰 Configure OTA Commission Fees</h2>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
            Atur persentase komisi untuk setiap OTA. Fee ini akan otomatis dikurangi dari harga booking (Net Income = Harga - Fee).
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem;">
            <?php foreach ($otaFees as $provider => $fee): ?>
            <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem;">
                <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="action" value="update_fee">
                    <input type="hidden" name="provider" value="<?php echo htmlspecialchars($provider); ?>">
                    
                    <div>
                        <h3 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">
                            <?php echo htmlspecialchars($provider); ?>
                        </h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);">
                            <?php 
                            if ($fee === 0) {
                                echo 'Tidak ada komisi';
                            } else {
                                echo 'Komisi: ' . $fee . '%';
                            }
                            ?>
                        </p>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">Commission Fee (%)</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="range" name="fee_percentage" min="0" max="30" step="1" 
                                   value="<?php echo $fee; ?>" class="form-input" 
                                   onchange="updateFeeDisplay(this)"
                                   style="flex: 1; padding: 0.5rem;">
                            <input type="number" name="fee_percentage_display" min="0" max="30" step="1" 
                                   value="<?php echo $fee; ?>" class="form-input" 
                                   style="width: 80px; text-align: center;" readonly>
                            <span style="display: flex; align-items: center; color: var(--text-secondary); font-weight: 700;">%</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success" style="margin-top: 0.5rem;">✓ Update Fee</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-card" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3);">
        <h3 style="margin-top: 0; color: #6ee7b7;">📊 Fee Calculation Example</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <div>
                <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">Agoda (15% fee)</p>
                <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 8px;">
                    <p style="margin: 0.5rem 0; color: var(--text-primary);"><strong>Harga Kamar:</strong> Rp 500.000</p>
                    <p style="margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.85rem;">Fee (15%): Rp 75.000</p>
                    <p style="margin: 0.5rem 0; color: #6ee7b7;"><strong>Net Income:</strong> Rp 425.000</p>
                </div>
            </div>
            <div>
                <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">Booking.com (12% fee)</p>
                <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 8px;">
                    <p style="margin: 0.5rem 0; color: var(--text-primary);"><strong>Harga Kamar:</strong> Rp 500.000</p>
                    <p style="margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.85rem;">Fee (12%): Rp 60.000</p>
                    <p style="margin: 0.5rem 0; color: #6ee7b7;"><strong>Net Income:</strong> Rp 440.000</p>
                </div>
            </div>
            <div>
                <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">Direct (0% fee)</p>
                <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 8px;">
                    <p style="margin: 0.5rem 0; color: var(--text-primary);"><strong>Harga Kamar:</strong> Rp 500.000</p>
                    <p style="margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.85rem;">Fee (0%): Rp 0</p>
                    <p style="margin: 0.5rem 0; color: #6ee7b7;"><strong>Net Income:</strong> Rp 500.000</p>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <?php endif; // Close if $tablesExist ?>

</div>

<!-- Modal Edit Room Type -->
<div id="editRoomTypeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin-top: 0; color: var(--primary);">✏️ Edit Room Type</h2>
        <form method="POST" id="editRoomTypeForm">
            <input type="hidden" name="action" value="edit_type">
            <input type="hidden" name="type_id" id="edit_type_id">
            
            <div class="form-group">
                <label class="form-label">Type Name</label>
                <input type="text" name="type_name" id="edit_type_name" class="form-input" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Base Price (IDR)</label>
                    <input type="number" name="base_price" id="edit_base_price" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Occupancy</label>
                    <input type="number" name="max_occupancy" id="edit_max_occupancy" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Amenities</label>
                <textarea name="amenities" id="edit_amenities" class="form-textarea" required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Color Code</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="edit_color_code_picker" class="color-picker" required onchange="document.getElementById('edit_color_code').value = this.value">
                    <input type="text" name="color_code" id="edit_color_code" class="form-input" placeholder="#6366f1" required style="max-width: 150px;">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success" style="flex: 1;">✓ Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Room -->
<div id="editRoomModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
        <h2 style="margin-top: 0; color: var(--primary);">✏️ Edit Room</h2>
        <form method="POST" id="editRoomForm">
            <input type="hidden" name="action" value="edit_room">
            <input type="hidden" name="room_id" id="edit_room_id">
            
            <div class="form-group">
                <label class="form-label">Room Number</label>
                <input type="text" name="room_number" id="edit_room_number" class="form-input" placeholder="e.g., 101" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Room Type</label>
                    <select name="room_type_id" id="edit_room_type_id" class="form-input" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($roomTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>">
                            <?php echo $type['type_name']; ?> (Rp <?php echo number_format($type['base_price']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor Number</label>
                    <input type="number" name="floor_number" id="edit_floor_number" class="form-input" placeholder="e.g., 1" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-input" required>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="cleaning">Cleaning</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="blocked">Blocked</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success" style="flex: 1;">✓ Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditRoomModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Breakfast Menu -->
<div id="editBreakfastModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin-top: 0; color: var(--primary);">✏️ Edit Breakfast Menu</h2>
        <form method="POST" id="editBreakfastForm">
            <input type="hidden" name="action" value="edit_menu">
            <input type="hidden" name="menu_id" id="edit_menu_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Menu Name</label>
                    <input type="text" name="menu_name" id="edit_menu_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="edit_category" class="form-select" required>
                        <option value="western">🍳 Western</option>
                        <option value="indonesian">🍛 Indonesian</option>
                        <option value="asian">🍜 Asian</option>
                        <option value="drinks">🥤 Drinks</option>
                        <option value="extras">➕ Extra (Berbayar)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (Rp)</label>
                    <input type="number" name="price" id="edit_price" class="form-input" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-bottom: 0.5rem;">
                    <input type="checkbox" name="is_free" id="edit_is_free" style="width: 20px; height: 20px;">
                    <span style="font-weight: 700;">🆓 Free Breakfast</span>
                </label>
                <small style="color: var(--text-secondary); display: block; margin-left: 28px;">
                    Unchecked = Extra Breakfast (Paid)
                </small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_available" id="edit_is_available" style="width: 20px; height: 20px;">
                    <span>Available for ordering</span>
                </label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success" style="flex: 1;">✓ Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditBreakfastModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRoom(room) {
    document.getElementById('edit_room_id').value = room.id;
    document.getElementById('edit_room_number').value = room.room_number;
    document.getElementById('edit_room_type_id').value = room.room_type_id;
    document.getElementById('edit_floor_number').value = room.floor_number;
    document.getElementById('edit_status').value = room.status;
    
    const modal = document.getElementById('editRoomModal');
    modal.style.display = 'flex';
}

function closeEditRoomModal() {
    document.getElementById('editRoomModal').style.display = 'none';
}

function editRoomType(type) {
    document.getElementById('edit_type_id').value = type.id;
    document.getElementById('edit_type_name').value = type.type_name;
    document.getElementById('edit_base_price').value = type.base_price;
    document.getElementById('edit_max_occupancy').value = type.max_occupancy;
    document.getElementById('edit_amenities').value = type.amenities;
    document.getElementById('edit_color_code').value = type.color_code;
    document.getElementById('edit_color_code_picker').value = type.color_code;
    
    const modal = document.getElementById('editRoomTypeModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editRoomTypeModal').style.display = 'none';
}

function editBreakfastMenu(menu) {
    document.getElementById('edit_menu_id').value = menu.id;
    document.getElementById('edit_menu_name').value = menu.menu_name;
    document.getElementById('edit_category').value = menu.category;
    document.getElementById('edit_price').value = menu.price;
    document.getElementById('edit_description').value = menu.description || '';
    document.getElementById('edit_is_free').checked = menu.is_free == 1;
    document.getElementById('edit_is_available').checked = menu.is_available == 1;
    
    const modal = document.getElementById('editBreakfastModal');
    modal.style.display = 'flex';
}

function closeEditBreakfastModal() {
    document.getElementById('editBreakfastModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editRoomTypeModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.getElementById('editRoomModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditRoomModal();
    }
});

document.getElementById('editBreakfastModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditBreakfastModal();
    }
});

function updateFeeDisplay(element) {
    const value = element.value;
    const display = element.parentElement.querySelector('input[name="fee_percentage_display"]');
    if (display) {
        display.value = value;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
