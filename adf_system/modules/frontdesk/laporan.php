<?php
/**
 * FRONT DESK - LAPORAN HARIAN
 * Laporan occupancy, in-house, check-in/out hari ini, dan breakfast orders
 */

define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/report_helper.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();

if (!$auth->hasPermission('frontdesk')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pageTitle = 'Laporan Harian';
$today = date('Y-m-d');
$todayDisplay = date('l, d F Y');

// Get company info
$company = getCompanyInfo();

// ============================================
// DATA COLLECTION
// ============================================

try {
    // 1. OCCUPANCY STATS
    $totalRoomsQuery = "SELECT COUNT(*) as total FROM rooms WHERE status != 'maintenance'";
    $totalRooms = $db->fetchOne($totalRoomsQuery)['total'];
    
    $occupiedRoomsQuery = "SELECT COUNT(DISTINCT room_id) as occupied 
                           FROM bookings 
                           WHERE status = 'checked_in'";
    $occupiedRooms = $db->fetchOne($occupiedRoomsQuery)['occupied'];
    
    $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;
    
    // 2. IN-HOUSE GUESTS
    $inHouseQuery = "SELECT 
            b.id as booking_id,
            b.booking_code,
            g.guest_name,
            r.room_number,
            b.check_in_date,
            b.check_out_date,
            b.payment_status
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
        ORDER BY r.room_number ASC";
    $inHouseGuests = $db->fetchAll($inHouseQuery);
    
    // 3. CHECK-IN TODAY - Only guests with check-in date TODAY but NOT YET checked in (status = confirmed)
    $checkInTodayQuery = "SELECT 
            b.booking_code,
            g.guest_name,
            r.room_number,
            b.check_in_date,
            b.check_out_date,
            b.actual_checkin_time
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE DATE(b.check_in_date) = ? AND b.status = 'confirmed'
        ORDER BY b.check_in_date ASC";
    $checkInToday = $db->fetchAll($checkInTodayQuery, [$today]);
    
    // 4. CHECK-OUT TODAY - Only guests with status checked_in and checkout date is today
    $checkOutTodayQuery = "SELECT 
            b.booking_code,
            g.guest_name,
            r.room_number,
            b.check_in_date,
            b.check_out_date
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_out_date = ? AND b.status = 'checked_in'
        ORDER BY r.room_number ASC";
    $checkOutToday = $db->fetchAll($checkOutTodayQuery, [$today]);
    
    // 5. CHECK-IN TOMORROW
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $checkInTomorrowQuery = "SELECT 
            b.booking_code,
            g.guest_name,
            g.phone,
            r.room_number,
            b.check_in_date,
            b.check_out_date
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_in_date = ? AND b.status = 'confirmed'
        ORDER BY r.room_number ASC";
    $checkInTomorrow = $db->fetchAll($checkInTomorrowQuery, [$tomorrow]);
    
    // 6. CHECK-OUT TOMORROW
    $checkOutTomorrowQuery = "SELECT 
            b.booking_code,
            g.guest_name,
            g.phone,
            r.room_number,
            b.check_in_date,
            b.check_out_date
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_out_date = ? AND b.status = 'checked_in'
        ORDER BY r.room_number ASC";
    $checkOutTomorrow = $db->fetchAll($checkOutTomorrowQuery, [$tomorrow]);
    
    // 7. ARRIVAL TOMORROW (All reservations)
    $arrivalTomorrowQuery = "SELECT 
            b.booking_code,
            g.guest_name,
            g.phone,
            r.room_number,
            b.check_in_date,
            b.check_out_date,
            b.guest_count
        FROM bookings b
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_in_date = ? AND b.status IN ('confirmed', 'pending')
        ORDER BY r.room_number ASC";
    $arrivalTomorrow = $db->fetchAll($arrivalTomorrowQuery, [$tomorrow]);
    
    // 8. BREAKFAST ORDERS TODAY
    $breakfastQuery = "SELECT 
            bo.*,
            b.booking_code,
            g.guest_name,
            r.room_number
        FROM breakfast_orders bo
        INNER JOIN bookings b ON bo.booking_id = b.id
        INNER JOIN guests g ON b.guest_id = g.id
        INNER JOIN rooms r ON b.room_id = r.id
        WHERE bo.breakfast_date = ?
        ORDER BY bo.breakfast_time ASC, r.room_number ASC";
    $breakfastOrders = $db->fetchAll($breakfastQuery, [$today]);
    
    // Decode menu items
    foreach ($breakfastOrders as &$order) {
        $order['menu_items'] = json_decode($order['menu_items'], true) ?: [];
    }
    
} catch (Exception $e) {
    error_log("Laporan Error: " . $e->getMessage());
    $error = $e->getMessage();
}

include '../../includes/header.php';
?>

<style>
:root {
    --primary: #6366f1;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] {
    --glass-bg: rgba(30, 41, 59, 0.9);
    --glass-border: rgba(71, 85, 105, 0.5);
}

.laporan-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 1rem;
    text-align: center;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.8rem;
    padding-bottom: 0.8rem;
    border-bottom: 3px solid #6366f1;
}

.hotel-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.2rem;
}

.company-details-header {
    font-size: 0.5rem;
    color: #666;
    line-height: 1.15;
    margin-top: 0.05rem;
}

.page-header h1 {
    flex-shrink: 0;
    min-width: 110px;
    text-align: right;
    font-size: 0.8rem;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 0;
    background: none;
    -webkit-text-fill-color: unset;
}

.page-subtitle {
    font-size: 0.5rem;
    color: #666;
    text-align: right;
    margin-top: 0.05rem;
}

.report-label {
    font-size: 0.55rem;
    color: #999;
    text-align: right;
    margin-bottom: 0.05rem;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .laporan-container, .laporan-container * {
        visibility: visible;
    }
    
    .laporan-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 15mm;
    }
    
    .action-buttons, .btn, button {
        display: none !important;
    }
    
    /* Print Header */
    .page-header {
        padding-bottom: 12px;
        border-bottom: 3px solid #6366f1;
        margin-bottom: 1rem;
    }
    
    .hotel-name {
        flex: 1;
        font-size: 16pt;
        text-align: left;
    }
    
    .page-header h1 {
        font-size: 12pt;
        color: #6366f1 !important;
        -webkit-text-fill-color: #6366f1;
        text-align: right;
        min-width: 180px;
    }
    
    .page-subtitle {
        font-size: 9pt;
        text-align: right;
    }
    
    /* Stats Horizontal */
    .stats-grid {
        display: flex;
        gap: 8px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px;
        background: #f9fafb;
        max-width: 100%;
    }
    
    .stat-card {
        flex: 1;
        border: 1px solid #e5e7eb;
        background: white;
        page-break-inside: avoid;
    }
    
    .section {
        page-break-inside: avoid;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.4rem;
    margin-bottom: 0.6rem;
    width: 100%;
    margin-left: 0;
    margin-right: 0;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.5rem;
    background: #f9fafb;
}

.stat-card {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.5rem 0.4rem;
    text-align: center;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 0.1rem;
}

.stat-label {
    color: #666;
    font-size: 0.45rem;
    font-weight: 600;
}

/* Section */
.report-section {
    background: var(--glass-bg);
    backdrop-filter: blur(16px);
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    padding: 0.35rem;
    margin-bottom: 0.35rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-bottom: 0.3rem;
    padding: 0.35rem 0.4rem;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border-radius: 4px;
    border-bottom: none;
    font-weight: 700;
    font-size: 0.75rem;
}

.section-icon {
    font-size: 1.25rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}

.section-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: auto;
}

/* Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.data-table th {
    background: #f3f4f6;
    padding: 0.3rem 0.4rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.72rem;
    border: 1px solid #e5e7eb;
}

.data-table td {
    padding: 0.25rem 0.4rem;
    border: 1px solid #e5e7eb;
    font-size: 0.78rem;
}

.data-table tr:hover {
    background: #f9fafb;
}

.room-badge {
    background: #6366f1;
    color: white;
    padding: 0.1rem 0.25rem;
    border-radius: 3px;
    font-weight: 600;
    font-size: 0.55rem;
    display: inline-block;
}

.status-badge {
    padding: 0.1rem 0.25rem;
    border-radius: 2px;
    font-size: 0.55rem;
    font-weight: 600;
}

.status-paid {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-unpaid {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.status-partial {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

/* Breakfast Menu List */
.breakfast-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.breakfast-item {
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--bg-tertiary);
}

.breakfast-item:last-child {
    border-bottom: none;
}

.menu-name {
    font-weight: 600;
    color: var(--text-primary);
}

.menu-qty {
    color: var(--primary);
    font-weight: 600;
    margin-right: 0.5rem;
}

.location-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.location-restaurant {
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
}

.location-room {
    background: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    justify-content: center;
}

.btn {
    padding: 0.875rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
    font-style: italic;
}

/* Print Footer Watermark */
.print-footer {
    position: fixed;
    bottom: 10mm;
    right: 15mm;
    font-size: 7pt;
    color: #999;
    text-align: right;
    border-top: 1px solid #e5e7eb;
    padding-top: 4px;
}

.print-footer .system-name {
    font-weight: 600;
    color: #6366f1;
}

@media print {
    .action-buttons {
        display: none;
    }
    
    .report-section {
        break-inside: avoid;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }
    
    .page-header h1 {
        font-size: 1.25rem;
    }
    
    .hotel-name {
        font-size: 1rem;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
}
</style>

<div class="laporan-container">
    <!-- Action Buttons - Moved to Top -->
    <div class="action-buttons" style="margin-bottom: 1rem;">
        <button class="btn btn-primary" onclick="exportToPDF()">
            <span>📄</span>
            <span>Export PDF</span>
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <span>🖨️</span>
            <span>Print Report</span>
        </button>
        <button class="btn btn-success" onclick="shareToWhatsApp()">
            <span>📱</span>
            <span>Send to WhatsApp</span>
        </button>
    </div>

    <!-- Header -->
    <div class="page-header">
        <!-- Company Info (Center) -->
        <div style="flex: 1;">
            <div class="hotel-name"><?php echo htmlspecialchars($company['name']); ?></div>
            <div class="company-details-header">
                <?php if ($company['address']): ?>
                    <?php echo htmlspecialchars($company['address']); ?><br>
                <?php endif; ?>
                <?php if ($company['phone']): ?>
                    Tel: <?php echo htmlspecialchars($company['phone']); ?>
                <?php endif; ?>
                <?php if ($company['email']): ?>
                    | Email: <?php echo htmlspecialchars($company['email']); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Report Title & Date (Right) -->
        <div style="text-align: right; min-width: 200px;">
            <div class="report-label">Laporan:</div>
            <h1>LAPORAN HARIAN</h1>
            <p class="page-subtitle"><?php echo $todayDisplay; ?></p>
        </div>
    </div>

    <!-- Occupancy Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏨</div>
            <div class="stat-value"><?php echo $occupancyRate; ?>%</div>
            <div class="stat-label">Occupancy Rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo count($inHouseGuests); ?></div>
            <div class="stat-label">In House</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📥</div>
            <div class="stat-value"><?php echo count($checkInToday); ?></div>
            <div class="stat-label">Check-in Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📤</div>
            <div class="stat-value"><?php echo count($checkOutToday); ?></div>
            <div class="stat-label">Check-out Today</div>
        </div>
    </div>

    <!-- IN HOUSE GUESTS -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">👥</span>
            <h2 class="section-title">In-House Guests</h2>
            <span class="section-badge"><?php echo count($inHouseGuests); ?> Guests</span>
        </div>
        
        <?php if (count($inHouseGuests) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Booking Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inHouseGuests as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                    <td><span class="status-badge status-<?php echo $guest['payment_status']; ?>"><?php echo strtoupper($guest['payment_status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No in-house guests</div>
        <?php endif; ?>
    </div>

    <!-- CHECK-IN TODAY -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">📥</span>
            <h2 class="section-title">Check-in Today</h2>
            <span class="section-badge"><?php echo count($checkInToday); ?> Guests</span>
        </div>
        
        <?php if (count($checkInToday) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Booking Code</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkInToday as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No check-in today</div>
        <?php endif; ?>
    </div>

    <!-- CHECK-OUT TODAY -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">📤</span>
            <h2 class="section-title">Check-out Today</h2>
            <span class="section-badge"><?php echo count($checkOutToday); ?> Guests</span>
        </div>
        
        <?php if (count($checkOutToday) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Booking Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkOutToday as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No check-out today</div>
        <?php endif; ?>
    </div>

    <!-- CHECK-IN TOMORROW -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">📅</span>
            <h2 class="section-title">Check-in Tomorrow</h2>
            <span class="section-badge"><?php echo count($checkInTomorrow); ?> Guests</span>
        </div>
        
        <?php if (count($checkInTomorrow) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Phone</th>
                    <th>Booking Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkInTomorrow as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['phone'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No check-in tomorrow</div>
        <?php endif; ?>
    </div>

    <!-- CHECK-OUT TOMORROW -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">📤</span>
            <h2 class="section-title">Check-out Tomorrow</h2>
            <span class="section-badge"><?php echo count($checkOutTomorrow); ?> Guests</span>
        </div>
        
        <?php if (count($checkOutTomorrow) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Phone</th>
                    <th>Booking Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkOutTomorrow as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['phone'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No check-out tomorrow</div>
        <?php endif; ?>
    </div>

    <!-- ARRIVAL TOMORROW -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">✈️</span>
            <h2 class="section-title">Arrival Tomorrow - Reservations</h2>
            <span class="section-badge"><?php echo count($arrivalTomorrow); ?> Reservations</span>
        </div>
        
        <?php if (count($arrivalTomorrow) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Phone</th>
                    <th>Booking Code</th>
                    <th>Pax</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($arrivalTomorrow as $guest): ?>
                <tr>
                    <td><span class="room-badge"><?php echo htmlspecialchars($guest['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['phone'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($guest['booking_code']); ?></td>
                    <td><?php echo $guest['guest_count'] ?: '1'; ?> pax</td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No arrival tomorrow</div>
        <?php endif; ?>
    </div>

    <!-- BREAKFAST ORDERS -->
    <div class="report-section">
        <div class="section-header">
            <span class="section-icon">🍳</span>
            <h2 class="section-title">Breakfast Orders Today</h2>
            <span class="section-badge"><?php echo count($breakfastOrders); ?> Orders</span>
        </div>
        
        <?php if (count($breakfastOrders) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Guest Name</th>
                    <th>Pax</th>
                    <th>Location</th>
                    <th>Menu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakfastOrders as $order): ?>
                <tr>
                    <td><?php echo date('H:i', strtotime($order['breakfast_time'])); ?></td>
                    <td><span class="room-badge"><?php echo htmlspecialchars($order['room_number']); ?></span></td>
                    <td><?php echo htmlspecialchars($order['guest_name']); ?></td>
                    <td><?php echo $order['total_pax']; ?> pax</td>
                    <td>
                        <span class="location-badge location-<?php echo $order['location']; ?>">
                            <?php echo $order['location'] === 'restaurant' ? '🍽️ Restaurant' : '🚪 Room Service'; ?>
                        </span>
                    </td>
                    <td>
                        <ul class="breakfast-list">
                            <?php foreach ($order['menu_items'] as $item): ?>
                            <li class="breakfast-item">
                                <span class="menu-qty">x<?php echo $item['quantity']; ?></span>
                                <span class="menu-name"><?php echo htmlspecialchars($item['menu_name']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No breakfast orders today</div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToPDF() {
    // Open export PDF page in new window
    window.open('export-daily-report.php', '_blank');
}

function shareToWhatsApp() {
    // Build report text
    let text = `*DAILY REPORT - FRONTDESK*\n`;
    text += `📅 ${<?php echo json_encode($todayDisplay); ?>}\n\n`;
    
    text += `*📊 OCCUPANCY*\n`;
    text += `Rate: ${<?php echo $occupancyRate; ?>}%\n`;
    text += `In House: ${<?php echo count($inHouseGuests); ?>} guests\n\n`;
    
    <?php if (count($checkInToday) > 0): ?>
    text += `*📥 CHECK-IN HARI INI (${<?php echo count($checkInToday); ?>})*\n`;
    <?php foreach ($checkInToday as $guest): ?>
    text += `• Room ${<?php echo json_encode($guest['room_number']); ?>} - ${<?php echo json_encode($guest['guest_name']); ?>}\n`;
    <?php endforeach; ?>
    text += `\n`;
    <?php endif; ?>
    
    <?php if (count($checkOutToday) > 0): ?>
    text += `*📤 CHECK-OUT HARI INI (${<?php echo count($checkOutToday); ?>})*\n`;
    <?php foreach ($checkOutToday as $guest): ?>
    text += `• Room ${<?php echo json_encode($guest['room_number']); ?>} - ${<?php echo json_encode($guest['guest_name']); ?>}\n`;
    <?php endforeach; ?>
    text += `\n`;
    <?php endif; ?>
    
    <?php if (count($breakfastOrders) > 0): ?>
    text += `*🍳 BREAKFAST ORDERS (${<?php echo count($breakfastOrders); ?>})*\n`;
    <?php foreach ($breakfastOrders as $order): ?>
    text += `• ${<?php echo json_encode($order['breakfast_time']); ?>} - Room ${<?php echo json_encode($order['room_number']); ?>} - ${<?php echo json_encode($order['guest_name']); ?>}\n`;
    text += `  Pax: ${<?php echo $order['total_pax']; ?>} | ${<?php echo $order['location'] === 'restaurant' ? 'Restaurant' : 'Room Service'; ?>}\n`;
    text += `  Menu:\n`;
    <?php foreach ($order['menu_items'] as $item): ?>
    text += `   - ${<?php echo $item['quantity']; ?>}x ${<?php echo json_encode($item['menu_name']); ?>}\n`;
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    
    // Encode for WhatsApp
    const encoded = encodeURIComponent(text);
    const whatsappURL = `https://wa.me/?text=${encoded}`;
    
    // Open WhatsApp
    window.open(whatsappURL, '_blank');
}
</script>

<!-- Print Footer Watermark -->
<div class="print-footer">
    <div><span class="system-name">✓ Printed by ADF System</span></div>
    <div style="font-size: 6pt; color: #ccc; margin-top: 2px;">Generated: <?php echo date('d M Y H:i'); ?></div>
</div>

<?php include '../../includes/footer.php'; ?>
