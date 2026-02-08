<?php
/**
 * BREAKFAST ORDER FORM
 * Create breakfast orders with menu selection
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

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// Verify permission
if (!$auth->hasPermission('frontdesk')) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$pdo = $db->getConnection();
$today = date('Y-m-d');
$message = '';
$error = '';

// ==================== VALIDATE USER ID ====================
// Check if current user exists in database before using in FK constraints
$validUserId = null;
if (!empty($_SESSION['user_id'])) {
    $userCheck = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if ($userCheck) {
        $validUserId = $_SESSION['user_id'];
    } else {
        // User doesn't exist - log the issue and continue gracefully
        error_log("Warning: SessionUser ID {$_SESSION['user_id']} not found in users table for breakfast order");
        // Try to find ANY admin/staff user to use as fallback
        $adminUser = $db->fetchOne("SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($adminUser) {
            $validUserId = $adminUser['id'];
        }
    }
}

// ==================== HANDLE BREAKFAST ORDER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_order') {
            // Validate user exists
            if (!$validUserId) {
                throw new Exception("❌ Sistem error: User tidak ditemukan di database. Hubungi administrator.");
            }
            
            // Create breakfast_orders table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_orders (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_id INT NULL,
                guest_name VARCHAR(100) NOT NULL,
                room_number VARCHAR(20),
                total_pax INT NOT NULL,
                breakfast_time TIME NOT NULL,
                breakfast_date DATE NOT NULL,
                location ENUM('restaurant', 'room_service') DEFAULT 'restaurant',
                menu_items TEXT COMMENT 'JSON array of menu items with quantities',
                special_requests TEXT,
                total_price DECIMAL(10,2) DEFAULT 0.00,
                order_status ENUM('pending', 'preparing', 'served', 'completed', 'cancelled') DEFAULT 'pending',
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                INDEX idx_date (breakfast_date),
                INDEX idx_status (order_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // ===== VALIDATION =====
            // Validate required fields
            if (empty($_POST['guest_name'])) {
                throw new Exception("Guest name is required");
            }
            if (empty($_POST['total_pax'])) {
                throw new Exception("Total pax is required");
            }
            if (empty($_POST['breakfast_time'])) {
                throw new Exception("Breakfast time is required");
            }
            if (empty($_POST['breakfast_date'])) {
                throw new Exception("Breakfast date is required");
            }
            
            // CRITICAL: Validate that at least ONE menu item is selected
            if (empty($_POST['menu_items']) || !is_array($_POST['menu_items']) || count($_POST['menu_items']) === 0) {
                throw new Exception("❌ Pilih minimal 1 menu item untuk breakfast order");
            }
            
            // Parse menu items from form
            $menuItems = [];
            $totalPrice = 0;
            
            foreach ($_POST['menu_items'] as $menuId) {
                $qty = (int)($_POST['menu_qty'][$menuId] ?? 1);
                if ($qty > 0) {
                    // Get menu price
                    $menuStmt = $pdo->prepare("SELECT menu_name, price, is_free FROM breakfast_menus WHERE id = ?");
                    $menuStmt->execute([$menuId]);
                    $menu = $menuStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($menu) {
                        $menuItems[] = [
                            'menu_id' => $menuId,
                            'menu_name' => $menu['menu_name'],
                            'quantity' => $qty,
                            'price' => $menu['price'],
                            'is_free' => $menu['is_free']
                        ];
                        
                        if (!$menu['is_free']) {
                            $totalPrice += ($menu['price'] * $qty);
                        }
                    }
                }
            }
            
            // Verify we have at least one valid menu item after processing
            if (count($menuItems) === 0) {
                throw new Exception("❌ No valid menu items selected");
            }
            
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO breakfast_orders 
                (booking_id, guest_name, room_number, total_pax, breakfast_time, breakfast_date, location, 
                 menu_items, special_requests, total_price, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $orderId = $stmt->execute([
                !empty($_POST['booking_id']) ? (int)$_POST['booking_id'] : null,
                trim($_POST['guest_name']),
                !empty($_POST['room_number']) ? trim($_POST['room_number']) : null,
                (int)$_POST['total_pax'],
                $_POST['breakfast_time'],
                $_POST['breakfast_date'],
                $_POST['location'] ?? 'restaurant',
                json_encode($menuItems),
                !empty($_POST['special_requests']) ? trim($_POST['special_requests']) : null,
                $totalPrice,
                $validUserId
            ]);
            
            $lastOrderId = $pdo->lastInsertId();
            
            // Build detailed message
            $itemscount = count($menuItems);
            $guestName = trim($_POST['guest_name']);
            $message = "✅ BERHASIL! Breakfast order untuk <strong>$guestName</strong> ($itemscount menu items) sudah tersimpan dengan ID #$lastOrderId";
            
            // Don't redirect - show success and keep form visible for more entries
            // header('Location: ' . $_SERVER['PHP_SELF']);
            // exit;
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
        error_log("Breakfast Order Error: " . $e->getMessage());
    }
}

// ==================== GET DATA FOR FORM ====================
try {
    // Create breakfast menus table if not exists
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

// Get available breakfast menus (Free and Paid separately)
$freeMenus = [];
$paidMenus = [];
try {
    $stmt = $pdo->query("SELECT * FROM breakfast_menus WHERE is_available = TRUE AND is_free = TRUE ORDER BY category, menu_name");
    $freeMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM breakfast_menus WHERE is_available = TRUE AND is_free = FALSE ORDER BY category, menu_name");
    $paidMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get in-house guests for dropdown
$inHouseGuests = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id as booking_id,
            g.guest_name,
            r.room_number
        FROM bookings b
        JOIN guests g ON b.guest_id = g.id
        JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
        ORDER BY r.room_number ASC
    ");
    $stmt->execute();
    $inHouseGuests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$pageTitle = 'Breakfast Order';
include '../../includes/header.php';
?>

<style>
.breakfast-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.breakfast-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.breakfast-header h1 {
    font-size: 2rem;
    font-weight: 900;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.header-actions .btn {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    text-decoration: none;
    display: inline-block;
    transition: transform 0.3s ease;
}

.header-actions .btn:hover {
    transform: translateY(-2px);
}

.message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 600;
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

.order-form-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(30px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    padding: 2rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.form-input {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--text-color);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.15);
}

.form-textarea {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--text-color);
    font-size: 1rem;
    font-family: inherit;
    resize: vertical;
}

.form-textarea:focus {
    outline: none;
    border-color: #6366f1;
}

.radio-group {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.radio-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(99, 102, 241, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.radio-label:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.2);
}

.radio-label input[type="radio"] {
    cursor: pointer;
}

.radio-label input[type="radio"]:checked + span {
    font-weight: 700;
}

.radio-label:has(input:checked) {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.3);
}

.menu-section {
    margin: 2rem 0;
}

.menu-section h3 {
    font-size: 1.5rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.menu-category {
    margin-bottom: 2rem;
}

.menu-category h4 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: rgba(255, 255, 255, 0.8);
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.menu-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.menu-item:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}

.menu-checkbox {
    display: flex;
    align-items: start;
    gap: 0.75rem;
    cursor: pointer;
}

.menu-checkbox input[type="checkbox"] {
    margin-top: 0.25rem;
    cursor: pointer;
    width: 18px;
    height: 18px;
}

.menu-info {
    flex: 1;
}

.menu-name {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
    color: rgba(255, 255, 255, 0.9);
}

.menu-price {
    font-weight: 700;
    color: #10b981;
    margin-bottom: 0.25rem;
}

.menu-category-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.menu-description {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.5rem;
}

.menu-qty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.menu-qty label {
    font-weight: 600;
    font-size: 0.9rem;
}

.qty-input {
    width: 80px;
    padding: 0.5rem;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--text-color);
    text-align: center;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-submit {
    flex: 1;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
}

.btn-reset {
    padding: 1rem 2rem;
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.5);
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    background: rgba(239, 68, 68, 0.3);
}

@media (max-width: 768px) {
    .breakfast-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .menu-grid {
        grid-template-columns: 1fr;
    }
    
    .radio-group {
        flex-direction: column;
    }
}
</style>

<div class="breakfast-container">
    <!-- Header -->
    <div class="breakfast-header">
        <div>
            <h1>🍽️ Breakfast Order</h1>
            <p style="color: rgba(255, 255, 255, 0.7); margin-top: 0.5rem;">
                Create new breakfast order • <?php echo date('l, d F Y'); ?>
            </p>
        </div>
        <div class="header-actions">
            <a href="breakfast-orders.php" class="btn">📋 View Orders</a>
            <a href="dashboard.php" class="btn">🏠 Dashboard</a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="message success" style="font-size: 1.1rem; padding: 1.5rem;">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="message error" style="font-size: 1.1rem; padding: 1.5rem;">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Order Form -->
    <div class="order-form-card">
        <form method="POST" action="" id="breakfastOrderForm">
            <input type="hidden" name="action" value="create_order">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="guest_select">Guest (Optional)</label>
                    <select name="booking_id" id="guest_select" class="form-input" onchange="fillGuestInfo(this)">
                        <option value="">-- Walk-in Guest / Manual Entry --</option>
                        <?php foreach ($inHouseGuests as $guest): ?>
                        <option value="<?php echo $guest['booking_id']; ?>" 
                                data-name="<?php echo htmlspecialchars($guest['guest_name']); ?>"
                                data-room="<?php echo htmlspecialchars($guest['room_number']); ?>">
                            Room <?php echo $guest['room_number']; ?> - <?php echo htmlspecialchars($guest['guest_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="guest_name">Guest Name *</label>
                    <input type="text" name="guest_name" id="guest_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="room_number">Room Number (if applicable)</label>
                    <input type="text" name="room_number" id="room_number" class="form-input">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="total_pax">Total Pax *</label>
                    <input type="number" name="total_pax" id="total_pax" class="form-input" min="1" max="20" required>
                </div>

                <div class="form-group">
                    <label for="breakfast_time">Breakfast Time *</label>
                    <input type="time" name="breakfast_time" id="breakfast_time" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="breakfast_date">Breakfast Date *</label>
                    <input type="date" name="breakfast_date" id="breakfast_date" class="form-input" 
                           value="<?php echo $today; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Location *</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="location" value="restaurant" checked>
                            <span>🍴 Restaurant</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="location" value="room_service">
                            <span>🛏️ Room Service</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="menu-section">
                <h3>Breakfast Menu</h3>
                
                <!-- Free Breakfast Menu -->
                <?php if (count($freeMenus) > 0): ?>
                <div class="menu-category">
                    <h4>✨ Complimentary Breakfast (Free)</h4>
                    <div class="menu-grid">
                        <?php foreach ($freeMenus as $menu): ?>
                        <div class="menu-item">
                            <label class="menu-checkbox">
                                <input type="checkbox" name="menu_items[]" value="<?php echo $menu['id']; ?>" 
                                       onchange="toggleQuantity(this)">
                                <div class="menu-info">
                                    <div class="menu-name"><?php echo htmlspecialchars($menu['menu_name']); ?></div>
                                    <div class="menu-category-badge"><?php echo $menu['category']; ?></div>
                                    <?php if ($menu['description']): ?>
                                    <div class="menu-description"><?php echo htmlspecialchars($menu['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <div class="menu-qty" style="display: none;">
                                <label>Qty:</label>
                                <input type="number" name="menu_qty[<?php echo $menu['id']; ?>]" 
                                       min="1" max="20" value="1" class="qty-input">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Paid Extras Menu -->
                <?php if (count($paidMenus) > 0): ?>
                <div class="menu-category">
                    <h4>💰 Extra Items (Paid)</h4>
                    <div class="menu-grid">
                        <?php foreach ($paidMenus as $menu): ?>
                        <div class="menu-item">
                            <label class="menu-checkbox">
                                <input type="checkbox" name="menu_items[]" value="<?php echo $menu['id']; ?>" 
                                       onchange="toggleQuantity(this)">
                                <div class="menu-info">
                                    <div class="menu-name"><?php echo htmlspecialchars($menu['menu_name']); ?></div>
                                    <div class="menu-price">Rp <?php echo number_format($menu['price'], 0, ',', '.'); ?></div>
                                    <div class="menu-category-badge"><?php echo $menu['category']; ?></div>
                                    <?php if ($menu['description']): ?>
                                    <div class="menu-description"><?php echo htmlspecialchars($menu['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <div class="menu-qty" style="display: none;">
                                <label>Qty:</label>
                                <input type="number" name="menu_qty[<?php echo $menu['id']; ?>]" 
                                       min="1" max="20" value="1" class="qty-input">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="special_requests">Special Requests / Notes</label>
                <textarea name="special_requests" id="special_requests" class="form-textarea" 
                         rows="3" placeholder="Any allergies, special preparation requests, etc."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    ✓ Create Breakfast Order
                </button>
                <button type="reset" class="btn-reset">
                    ↺ Reset Form
                </button>
            </div>
        </form>
    </div>

    <!-- TODAY'S BREAKFAST ORDERS SUMMARY -->
    <div style="margin-top: 3rem;">
        <h2 style="font-size: 1.5rem; font-weight: 900; margin-bottom: 1.5rem; color: rgba(255, 255, 255, 0.9);">
            📊 Today's Breakfast Orders
        </h2>
        
        <?php
        try {
            $todayOrders = [];
            $stmt = $pdo->prepare("
                SELECT bo.*, 
                       b.booking_code,
                       r.room_number
                FROM breakfast_orders bo
                LEFT JOIN bookings b ON bo.booking_id = b.id
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE bo.breakfast_date = ?
                ORDER BY bo.breakfast_time ASC, r.room_number ASC
            ");
            $stmt->execute([$today]);
            $todayOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($todayOrders as &$order) {
                $order['menu_items'] = json_decode($order['menu_items'], true) ?: [];
            }
        } catch (Exception $e) {
            error_log("Today's orders query error: " . $e->getMessage());
            $todayOrders = [];
        }
        ?>
        
        <?php if (count($todayOrders) > 0): ?>
        <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3));">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Waktu</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Guest & Room</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Menu Items</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Pax</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Total</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 700; color: rgba(255, 255, 255, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayOrders as $order): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8); font-weight: 700;">
                            🕐 <?php echo date('H:i', strtotime($order['breakfast_time'])); ?>
                        </td>
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">
                            <strong><?php echo htmlspecialchars($order['guest_name']); ?></strong>
                            <?php if (!empty($order['room_number'])): ?>
                            <br>
                            <span style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(99, 102, 241, 0.3); border-radius: 6px; font-weight: 600; font-size: 0.9rem;">🛏️ <?php echo htmlspecialchars($order['room_number']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">
                            <?php foreach ($order['menu_items'] as $item): ?>
                            <span style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(168, 85, 247, 0.2); border-radius: 6px; font-size: 0.85rem; color: rgba(255, 255, 255, 0.8); margin-bottom: 0.25rem; margin-right: 0.25rem;">
                                <?php echo htmlspecialchars($item['menu_name']); ?>
                                <?php if ($item['quantity'] > 1): ?> ×<?php echo $item['quantity']; ?><?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                        </td>
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">
                            <?php echo (int)$order['total_pax']; ?>
                        </td>
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">
                            <?php if ($order['total_price'] > 0): ?>
                            <strong>Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?></strong>
                            <?php else: ?>
                            <span style="color: rgba(255, 255, 255, 0.5);">Free</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; color: rgba(255, 255, 255, 0.8);">
                            <span style="display: inline-block; padding: 0.5rem 1rem; background: rgba(249, 115, 22, 0.2); color: #fdba74; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
                                ⏳ <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.5);">
            <p style="font-size: 1.1rem;">📭 Belum ada breakfast order untuk hari ini</p>
            <p style="margin-top: 0.5rem;">Pesanan yang dibuat akan muncul di sini</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function fillGuestInfo(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('guest_name').value = option.dataset.name || '';
        document.getElementById('room_number').value = option.dataset.room || '';
    } else {
        document.getElementById('guest_name').value = '';
        document.getElementById('room_number').value = '';
    }
}

function toggleQuantity(checkbox) {
    const menuItem = checkbox.closest('.menu-item');
    const qtyDiv = menuItem.querySelector('.menu-qty');
    
    if (checkbox.checked) {
        qtyDiv.style.display = 'flex';
    } else {
        qtyDiv.style.display = 'none';
    }
}

/**
 * CLEANER FORM VALIDATION
 * Validate form before submission
 */
function validateBreakfastForm(e) {
    const guestName = document.getElementById('guest_name').value.trim();
    const totalPax = document.getElementById('total_pax').value.trim();
    const breakfastTime = document.getElementById('breakfast_time').value.trim();
    const breakfastDate = document.getElementById('breakfast_date').value.trim();
    
    // Validate required fields
    if (!guestName) {
        alert('❌ Guest name harus diisi!');
        document.getElementById('guest_name').focus();
        e.preventDefault();
        return false;
    }
    
    if (!totalPax || parseInt(totalPax) < 1) {
        alert('❌ Total pax harus diisi (minimal 1)!');
        document.getElementById('total_pax').focus();
        e.preventDefault();
        return false;
    }
    
    if (!breakfastTime) {
        alert('❌ Breakfast time harus diisi!');
        document.getElementById('breakfast_time').focus();
        e.preventDefault();
        return false;
    }
    
    if (!breakfastDate) {
        alert('❌ Breakfast date harus diisi!');
        document.getElementById('breakfast_date').focus();
        e.preventDefault();
        return false;
    }
    
    // Check that at least one menu item is selected
    const selectedMenus = document.querySelectorAll('input[name="menu_items[]"]:checked');
    
    if (selectedMenus.length === 0) {
        alert('❌ PILIH MINIMAL 1 MENU ITEM!\n\nSelect menu dari "Complimentary Breakfast" atau "Extra Items"');
        e.preventDefault();
        return false;
    }
    
    console.log('✅ Validation PASSED. ' + selectedMenus.length + ' menu items selected. Form akan di-submit...');
    return true; // Allow form to submit
}

// Attach validation to form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('breakfastOrderForm');
    if (form) {
        form.addEventListener('submit', validateBreakfastForm);
        console.log('✅ breakfast form validation attached');
    } else {
        console.error('❌ Form dengan ID breakfastOrderForm tidak ditemukan!');
    }
});

// Auto-scroll to error messages
window.addEventListener('load', function() {
    const errorMsg = document.querySelector('.message.error');
    if (errorMsg) {
        errorMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
