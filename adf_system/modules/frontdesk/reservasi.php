<?php
/**
 * FRONT DESK - RESERVASI MANAGEMENT
 * Booking Management dengan Direct/OTA + Fee Calculation
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

if (!$auth->hasPermission('frontdesk')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pageTitle = 'Reservasi Management - Direct/OTA Bookings';

// ============================================
// OTA CONFIGURATION (Default if not in DB)
// ============================================
$otaProviders = [
    'walk_in' => ['name' => 'Walk-in', 'fee' => 0, 'icon' => '🚶'],
    'phone' => ['name' => 'Phone Booking', 'fee' => 0, 'icon' => '📞'],
    'online' => ['name' => 'Direct Online', 'fee' => 0, 'icon' => '💻'],
    'agoda' => ['name' => 'Agoda', 'fee' => 15, 'icon' => '🏨'],
    'booking' => ['name' => 'Booking.com', 'fee' => 12, 'icon' => '📱'],
    'tiket' => ['name' => 'Tiket.com', 'fee' => 10, 'icon' => '✈️'],
    'airbnb' => ['name' => 'Airbnb', 'fee' => 3, 'icon' => '🏠'],
    'ota' => ['name' => 'OTA Lainnya', 'fee' => 10, 'icon' => '🌐'],
];

// ============================================
// GET BOOKINGS LIST
// ============================================
try {
    $status_filter = $_GET['status'] ?? 'all';
    
    $query = "
        SELECT 
            b.id, b.booking_code, b.check_in_date, b.check_out_date,
            b.room_price, b.total_price, b.final_price, b.discount,
            b.status, b.payment_status, b.booking_source, b.total_nights,
            b.paid_amount, b.special_request, b.adults, b.children,
            g.guest_name, g.phone, g.email,
            r.room_number,
            rt.type_name,
            COALESCE(SUM(bp.amount), b.paid_amount, 0) as total_paid
        FROM bookings b
        LEFT JOIN guests g ON b.guest_id = g.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        LEFT JOIN booking_payments bp ON b.id = bp.booking_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " GROUP BY b.id
    ORDER BY 
        CASE 
            WHEN b.status = 'confirmed' THEN 1
            WHEN b.status = 'checked_in' THEN 2
            WHEN b.status = 'pending' THEN 3
            ELSE 4
        END,
        b.check_in_date ASC
    LIMIT 50";
    
    $bookings = $db->fetchAll($query, $params);
    
} catch (Exception $e) {
    error_log("Reservasi List Error: " . $e->getMessage());
    $bookings = [];
}

// ============================================
// CALCULATE OTA FEE & NET INCOME
// ============================================
function calculateNetIncome($roomPrice, $otaProvider, $otaProviders) {
    $feePercent = $otaProviders[$otaProvider]['fee'] ?? 0;
    $feeAmount = ($roomPrice * $feePercent) / 100;
    $netIncome = $roomPrice - $feeAmount;
    
    return [
        'gross' => $roomPrice,
        'fee_percent' => $feePercent,
        'fee_amount' => $feeAmount,
        'net' => $netIncome
    ];
}

include '../../includes/header.php';
?>

<style>
/* SIMPLE & ELEGANT DESIGN */

.reservasi-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 1rem;
}

.reservasi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.reservasi-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-primary {
    background: #6366f1;
    color: white;
    padding: 0.4rem 0.85rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}

.btn-primary:hover {
    background: #4f46e5;
    transform: translateY(-1px);
}

.filter-section {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.filter-group label {
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
}

.filter-group select,
.filter-group input {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.85rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
}

/* Bookings Table */
.bookings-table-wrapper {
    overflow-x: auto;
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}

.bookings-table thead {
    border-bottom: 2px solid var(--border-color);
    background: var(--bg-secondary);
}

.bookings-table th {
    padding: 0.5rem 0.65rem;
    text-align: left;
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.3px;
}

.bookings-table td {
    padding: 0.5rem 0.65rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
}

.bookings-table tbody tr {
    transition: background 0.2s ease;
}

.bookings-table tbody tr:hover {
    background: var(--bg-secondary);
}

/* Badge */
.badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
}

.badge-confirmed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.badge-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.badge-checked-in {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.badge-paid {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.badge-unpaid {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

/* Actions */
.row-actions {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.3rem 0.6rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 600;
    transition: all 0.2s ease;
    background: #f3f4f6;
    color: #6366f1;
    border: 1px solid #e5e7eb;
    white-space: nowrap;
}

.action-btn:hover {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
}

.action-btn.action-cancel {
    color: #f59e0b;
    border-color: #fcd34d;
}

.action-btn.action-cancel:hover {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.action-btn.action-delete {
    color: #ef4444;
    border-color: #fca5a5;
}

.action-btn.action-delete:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.room-badge {
    display: inline-block;
    background: #e5e7eb;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.7rem;
    color: #374151;
}

.ota-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.7rem;
    background: #dbeafe;
    color: #1e40af;
}

.ota-badge .fee {
    color: #dc2626;
    font-weight: 700;
    margin-left: 0.25rem;
}

.price-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.price-item {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}

.price-gross {
    color: var(--text-secondary);
}

.price-fee {
    color: #dc2626;
}

.price-net {
    font-weight: 700;
    color: #059669;
}

/* Responsive */
@media (max-width: 768px) {
    .reservasi-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .bookings-table {
        font-size: 0.7rem;
    }
    
    .bookings-table th,
    .bookings-table td {
        padding: 0.5rem;
    }
}
</style>

<div class="reservasi-container">
    <!-- Header -->
    <div class="reservasi-header">
        <div>
            <h1>Reservasi Management</h1>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="openNewBookingModal()">
                New Booking
            </button>
            <button class="btn-primary" onclick="window.location='calendar.php'">
                Calendar View
            </button>
            <button class="btn-primary" onclick="window.location='breakfast.php'">
                Breakfast List
            </button>
            <button class="btn-primary" onclick="window.location='settings.php'">
                Settings
            </button>
            <button class="btn-primary btn-secondary" onclick="window.location='dashboard.php'">
                Dashboard
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-group">
            <label>Status Filter</label>
            <select onchange="filterBookings(this.value)">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="checked_in" <?php echo $status_filter === 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                <option value="checked_out" <?php echo $status_filter === 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="bookings-table-wrapper">
        <?php if (!empty($bookings)): ?>
        <table class="bookings-table">
            <thead>
                <tr>
                    <th>Booking Code</th>
                    <th>Guest Name</th>
                    <th>Room</th>
                    <th>Check-in / Check-out</th>
                    <th>Nights</th>
                    <th>OTA Source</th>
                    <th>Price Breakdown</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): 
                    $netIncome = calculateNetIncome(
                        $booking['room_price'], 
                        $booking['booking_source'],
                        $otaProviders
                    );
                    $otaIcon = $otaProviders[$booking['booking_source']]['icon'] ?? '🌐';
                    $otaName = $otaProviders[$booking['booking_source']]['name'] ?? 'Other';
                    $otaFee = $otaProviders[$booking['booking_source']]['fee'] ?? 0;
                ?>
                <tr>
                    <!-- Booking Code -->
                    <td>
                        <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong>
                    </td>

                    <!-- Guest -->
                    <td>
                        <div>
                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                <?php echo htmlspecialchars($booking['phone'] ?? '-'); ?>
                            </div>
                        </div>
                    </td>

                    <!-- Room -->
                    <td>
                        <span class="room-badge">
                            <?php echo htmlspecialchars($booking['room_number']); ?>
                        </span>
                        <div style="font-size: 0.7rem; margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($booking['type_name']); ?>
                        </div>
                    </td>

                    <!-- Dates -->
                    <td>
                        <div style="font-size: 0.75rem;">
                            <?php echo date('d M', strtotime($booking['check_in_date'])); ?> -
                            <?php echo date('d M', strtotime($booking['check_out_date'])); ?>
                        </div>
                    </td>

                    <!-- Nights -->
                    <td>
                        <strong><?php echo $booking['total_nights']; ?></strong>
                    </td>

                    <!-- OTA Source -->
                    <td>
                        <span class="ota-badge">
                            <?php echo $otaName; ?>
                            <?php if ($otaFee > 0): ?>
                            <span class="fee">-<?php echo $otaFee; ?>%</span>
                            <?php endif; ?>
                        </span>
                    </td>

                    <!-- Price Breakdown -->
                    <td>
                        <div class="price-breakdown" style="font-size: 0.75rem;">
                            <div class="price-item price-gross">
                                <span>Gross:</span>
                                <span>Rp <?php echo number_format($netIncome['gross'], 0, ',', '.'); ?></span>
                            </div>
                            <?php if ($netIncome['fee_percent'] > 0): ?>
                            <div class="price-item price-fee">
                                <span>Fee (<?php echo $netIncome['fee_percent']; ?>%):</span>
                                <span>-Rp <?php echo number_format($netIncome['fee_amount'], 0, ',', '.'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="price-item price-net">
                                <span>Net:</span>
                                <span>Rp <?php echo number_format($netIncome['net'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="badge badge-status badge-<?php echo str_replace('_', '-', $booking['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </span>
                    </td>

                    <!-- Payment Status -->
                    <td>
                        <span class="badge badge-payment-<?php echo str_replace('_', '-', $booking['payment_status']); ?>">
                            <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                        <div style="font-size: 0.7rem; margin-top: 0.3rem; line-height: 1.4;">
                            <div><span style="color: var(--text-secondary);">Total:</span> <strong>Rp <?php echo number_format($booking['final_price'], 0, ',', '.'); ?></strong></div>
                            <div><span style="color: var(--text-secondary);">Bayar:</span> <strong style="color: #10b981;">Rp <?php echo number_format($booking['total_paid'], 0, ',', '.'); ?></strong></div>
                            <?php 
                                $remaining = $booking['final_price'] - $booking['total_paid'];
                                if ($remaining > 0):
                            ?>
                            <div><span style="color: var(--text-secondary);">Sisa:</span> <strong style="color: #ef4444;">Rp <?php echo number_format($remaining, 0, ',', '.'); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="row-actions">
                            <button class="action-btn" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                View
                            </button>
                            <button class="action-btn" onclick="editBooking(<?php echo $booking['id']; ?>)">
                                Edit
                            </button>
                            
                            <?php if ($booking['status'] === 'confirmed'): ?>
                            <button class="action-btn action-checkin" style="background-color: #10b981; color: white; border-color: #059669;" onclick="checkinBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_code']); ?>')">
                                Check-in
                            </button>
                            <?php endif; ?>

                            <?php if ($booking['status'] !== 'checked_in' && $booking['status'] !== 'checked_out'): ?>
                            <button class="action-btn action-cancel" onclick="cancelBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_code']); ?>')">
                                Cancel
                            </button>
                            <button class="action-btn action-delete" onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_code']); ?>')">
                                Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p style="font-size: 1.1rem;">Tidak ada reservasi</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function filterBookings(value) {
    window.location.search = '?status=' + value;
}

function openNewBookingModal() {
    alert('Coming Soon: New Booking Modal');
}

function viewBooking(id) {
    alert('Coming Soon: View Booking #' + id);
}

function editBooking(id) {
    window.location.href = 'edit-booking.php?id=' + id;
}

function checkinBooking(id, bookingCode) {
    if (!confirm(`Konfirmasi Check-in untuk booking ${bookingCode}?\n\n- Status akan berubah menjadi CHECKED IN\n- Kamar akan ditandai TERISI`)) {
        return;
    }
    
    fetch('../../api/checkin-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            booking_id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Check-in BERHASIL! Tamu sudah masuk.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        console.error('Error:', error);
    });
}

function cancelBooking(id, bookingCode) {
    if (!confirm(`Yakin ingin CANCEL reservasi ${bookingCode}?\n\nStatus akan berubah menjadi CANCELLED`)) {
        return;
    }
    
    fetch('../../api/cancel-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            booking_id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reservasi berhasil di-CANCEL');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        console.error('Error:', error);
    });
}

function deleteBooking(id, bookingCode) {
    if (!confirm(`PERINGATAN: Yakin ingin menghapus reservasi ${bookingCode}?\n\nAksi ini TIDAK BISA DIBATALKAN!\n\nData akan dihapus permanen dari sistem.`)) {
        return;
    }
    
    fetch('../../api/delete-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            booking_id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reservasi berhasil dihapus permanen');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        console.error('Error:', error);
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
