<?php
/**
 * EXPORT DAILY REPORT TO PDF
 * Generates PDF with logo, header, and professional formatting
 */

define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_helper.php';

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('frontdesk')) {
    http_response_code(403);
    exit('Access denied');
}

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$printerName = $currentUser['username'] ?? 'System';
$printerRole = $currentUser['role'] ?? 'Staff';
$today = date('Y-m-d');
$todayDisplay = date('l, d F Y');
$printTime = date('d F Y H:i:s');

// Get company info with logo
$company = getCompanyInfo();

// Collect data
try {
    // Occupancy
    $totalRooms = $db->fetchOne("SELECT COUNT(*) as total FROM rooms WHERE status != 'maintenance'")['total'];
    $occupiedRooms = $db->fetchOne("SELECT COUNT(DISTINCT room_id) as occupied FROM bookings WHERE status = 'checked_in'")['occupied'];
    $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;
    
    // In-house
    $inHouseGuests = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date, b.payment_status
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in' ORDER BY r.room_number ASC");
    
    // Check-in today - only those with check-in date today but NOT yet checked in (status = confirmed)
    $checkInToday = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE DATE(b.check_in_date) = ? AND b.status = 'confirmed'
        ORDER BY b.check_in_date ASC", [$today]);
    
    // Check-out today - only checked_in guests with checkout date today
    $checkOutToday = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_out_date = ? AND b.status = 'checked_in' ORDER BY r.room_number ASC", [$today]);
    
    // Check-in tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $checkInTomorrow = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date, g.phone
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_in_date = ? AND b.status = 'confirmed' ORDER BY r.room_number ASC", [$tomorrow]);
    
    // Check-out tomorrow
    $checkOutTomorrow = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date, g.phone
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_out_date = ? AND b.status = 'checked_in' ORDER BY r.room_number ASC", [$tomorrow]);
    
    // Arrival tomorrow
    $arrivalTomorrow = $db->fetchAll("SELECT b.booking_code, g.guest_name, r.room_number, b.check_in_date, b.check_out_date, g.phone, b.guest_count
        FROM bookings b INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE b.check_in_date = ? AND b.status IN ('confirmed', 'pending') ORDER BY r.room_number ASC", [$tomorrow]);
    
    // Breakfast orders
    $breakfastOrders = $db->fetchAll("SELECT bo.*, b.booking_code, g.guest_name, r.room_number
        FROM breakfast_orders bo INNER JOIN bookings b ON bo.booking_id = b.id
        INNER JOIN guests g ON b.guest_id = g.id INNER JOIN rooms r ON b.room_id = r.id
        WHERE bo.breakfast_date = ? ORDER BY bo.breakfast_time ASC", [$today]);
    
    foreach ($breakfastOrders as &$order) {
        $order['menu_items'] = json_decode($order['menu_items'], true) ?: [];
    }
    
} catch (Exception $e) {
    error_log("Export PDF Error: " . $e->getMessage());
    exit('Error loading data');
}

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Report - <?php echo $todayDisplay; ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
        }
        
        /* Header Layout - Clean & Professional */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #6366f1;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 18pt;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        
        .company-details {
            font-size: 8pt;
            color: #666;
            line-height: 1.6;
        }
        
        .report-info {
            text-align: right;
            min-width: 200px;
        }
        
        .report-label {
            font-size: 8pt;
            color: #999;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-title {
            font-size: 14pt;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 5px;
        }
        
        .report-date {
            font-size: 9pt;
            color: #666;
            font-weight: 600;
        }
        
        /* Print Footer */
        .print-footer {
            position: fixed;
            bottom: 0;
            right: 0;
            margin-right: 15mm;
            margin-bottom: 8mm;
            font-size: 7pt;
            color: #999;
            text-align: right;
        }
        
        .print-footer .system-name {
            font-weight: 600;
            color: #6366f1;
        }
        
        /* Stats Container - Compact & Clean */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px;
            background: #f9fafb;
        }
        
        .stat-box {
            text-align: center;
            padding: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            background: white;
        }
        
        .stat-label {
            font-size: 7pt;
            color: #666;
            margin-bottom: 2px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 13pt;
            font-weight: 700;
            color: #6366f1;
        }
        
        .stat-icon {
            font-size: 13pt;
            margin-bottom: 1px;
        }
        
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        
        .section-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.85) 0%, rgba(139, 92, 246, 0.85) 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            margin-bottom: 6px;
            font-size: 10pt;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        
        th {
            background: #f3f4f6;
            padding: 5px;
            text-align: left;
            font-size: 8pt;
            font-weight: 600;
            border: 1px solid #e5e7eb;
        }
        
        td {
            padding: 4px 5px;
            border: 1px solid #e5e7eb;
            font-size: 8pt;
        }
        
        tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .room-badge {
            background: #6366f1;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 8pt;
        }
        
        .status-paid { color: #10b981; font-weight: 600; }
        .status-unpaid { color: #ef4444; font-weight: 600; }
        .status-partial { color: #f59e0b; font-weight: 600; }
        
        .menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 8pt;
        }
        
        .menu-item {
            padding: 1px 0;
        }
        
        .menu-qty {
            color: #6366f1;
            font-weight: 600;
            margin-right: 3px;
        }
        
        .empty-state {
            text-align: center;
            padding: 10px;
            color: #999;
            font-style: italic;
            font-size: 9pt;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <!-- Header without Logo -->
    <div class="report-header">
        <div class="company-info" style="padding-left: 0;">
            <div class="company-name"><?php echo htmlspecialchars($company['name']); ?></div>
            <div class="company-details">
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
        <div class="report-info">
            <div class="report-label">Laporan:</div>
            <div class="report-title">DAILY REPORT</div>
            <div class="report-date"><?php echo $todayDisplay; ?></div>
        </div>
    </div>

    <!-- Stats Container - Horizontal -->
    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-icon">🏨</div>
            <div class="stat-value"><?php echo $occupancyRate; ?>%</div>
            <div class="stat-label">Occupancy</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo count($inHouseGuests); ?></div>
            <div class="stat-label">In House</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">📥</div>
            <div class="stat-value"><?php echo count($checkInToday); ?></div>
            <div class="stat-label">Check-in Today</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">📤</div>
            <div class="stat-value"><?php echo count($checkOutToday); ?></div>
            <div class="stat-label">Check-out Today</div>
        </div>
    </div>

    <!-- In-House Guests -->
    <div class="section">
        <div class="section-header">👥 In-House Guests (<?php echo count($inHouseGuests); ?>)</div>
        
        <?php if (count($inHouseGuests) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_in_date'])); ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                    <td><span class="status-<?php echo $guest['payment_status']; ?>"><?php echo strtoupper($guest['payment_status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No in-house guests</div>
        <?php endif; ?>
    </div>

    <!-- Check-in Today -->
    <div class="section">
        <div class="section-header">📥 Check-in Today (<?php echo count($checkInToday); ?>)</div>
        
        <?php if (count($checkInToday) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
                    <td><?php echo date('d M', strtotime($guest['check_out_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No check-in today</div>
        <?php endif; ?>
    </div>

    <!-- Check-out Today -->
    <div class="section">
        <div class="section-header">📤 Check-out Today (<?php echo count($checkOutToday); ?>)</div>
        
        <?php if (count($checkOutToday) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
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

    <!-- Check-in Tomorrow -->
    <div class="section">
        <div class="section-header">📅 Check-in Tomorrow (<?php echo count($checkInTomorrow); ?>)</div>
        
        <?php if (count($checkInTomorrow) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['phone'] ?: '-'; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
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

    <!-- Check-out Tomorrow -->
    <div class="section">
        <div class="section-header">📤 Check-out Tomorrow (<?php echo count($checkOutTomorrow); ?>)</div>
        
        <?php if (count($checkOutTomorrow) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['phone'] ?: '-'; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
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

    <!-- Arrival Tomorrow -->
    <div class="section">
        <div class="section-header">✈️ Arrival Tomorrow - Reservations (<?php echo count($arrivalTomorrow); ?>)</div>
        
        <?php if (count($arrivalTomorrow) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $guest['room_number']; ?></span></td>
                    <td><?php echo $guest['guest_name']; ?></td>
                    <td><?php echo $guest['phone'] ?: '-'; ?></td>
                    <td><?php echo $guest['booking_code']; ?></td>
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

    <!-- Breakfast Orders -->
    <div class="section">
        <div class="section-header">🍳 Breakfast Orders Today (<?php echo count($breakfastOrders); ?>)</div>
        
        <?php if (count($breakfastOrders) > 0): ?>
        <table>
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
                    <td><span class="room-badge"><?php echo $order['room_number']; ?></span></td>
                    <td><?php echo $order['guest_name']; ?></td>
                    <td><?php echo $order['total_pax']; ?> pax</td>
                    <td><?php echo $order['location'] === 'restaurant' ? '🍽️ Restaurant' : '🚪 Room'; ?></td>
                    <td>
                        <ul class="menu-list">
                            <?php foreach ($order['menu_items'] as $item): ?>
                            <li class="menu-item">
                                <span class="menu-qty">x<?php echo $item['quantity']; ?></span>
                                <span><?php echo $item['menu_name']; ?></span>
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

    <!-- Footer with Watermark -->
    <div class="print-footer">
        <div style="margin-bottom: 4px; border-top: 1px solid #e5e7eb; padding-top: 4px;">
            <div><span class="system-name">✓ Printed by ADF System</span></div>
            <div style="font-size: 6pt; color: #bbb; margin-top: 2px;">Printer: <?php echo htmlspecialchars($printerName); ?> | <?php echo $printTime; ?></div>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
