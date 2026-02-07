<?php
/**
 * EDIT BOOKING PAGE
 * Edit existing booking details
 */

define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('frontdesk')) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$bookingId = $_GET['id'] ?? null;

if (!$bookingId) {
    header('Location: reservasi.php');
    exit;
}

// Get booking details
$stmt = $pdo->prepare("
    SELECT b.*, g.guest_name, g.phone, g.email, r.room_number, rt.type_name
    FROM bookings b
    JOIN guests g ON b.guest_id = g.id
    JOIN rooms r ON b.room_id = r.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.id = ?
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: reservasi.php');
    exit;
}

// Handle update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $checkInDate = $_POST['check_in_date'] ?? $booking['check_in_date'];
        $checkOutDate = $_POST['check_out_date'] ?? $booking['check_out_date'];
        $totalNights = (strtotime($checkOutDate) - strtotime($checkInDate)) / (60 * 60 * 24);
        $roomPrice = $_POST['room_price'] ?? $booking['room_price'];
        $totalPrice = $roomPrice * $totalNights;
        
        $stmt = $pdo->prepare("
            UPDATE bookings SET 
                check_in_date = ?,
                check_out_date = ?,
                total_nights = ?,
                room_price = ?,
                total_price = ?,
                final_price = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $checkInDate,
            $checkOutDate,
            $totalNights,
            $roomPrice,
            $totalPrice,
            $totalPrice,
            $bookingId
        ]);
        
        $message = '✅ Booking updated successfully!';
        header('Refresh: 2; url=reservasi.php');
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<style>
.edit-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.edit-header {
    margin-bottom: 2rem;
}

.edit-header h1 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.95rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-save,
.btn-cancel {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.btn-save {
    background: #10b981;
    color: white;
}

.btn-save:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-cancel {
    background: #f3f4f6;
    color: #6366f1;
    border: 1px solid #e5e7eb;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid #d1fae5;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid #fee2e2;
}

.info-box {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.info-value {
    color: var(--text-primary);
}
</style>

<div class="edit-container">
    <div class="edit-header">
        <h1>✏️ Edit Booking</h1>
        <p style="color: var(--text-secondary); margin: 0;">Booking Code: <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Guest</span>
            <span class="info-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Room</span>
            <span class="info-value"><?php echo htmlspecialchars($booking['room_number'] . ' (' . $booking['type_name'] . ')'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Status</span>
            <span class="info-value"><strong><?php echo strtoupper($booking['status']); ?></strong></span>
        </div>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>Check-in Date</label>
            <input type="date" name="check_in_date" value="<?php echo $booking['check_in_date']; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Check-out Date</label>
            <input type="date" name="check_out_date" value="<?php echo $booking['check_out_date']; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Room Price (per night)</label>
            <input type="number" name="room_price" value="<?php echo $booking['room_price']; ?>" step="1000" required>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-save">💾 Save Changes</button>
            <button type="button" class="btn-cancel" onclick="window.location='reservasi.php'">❌ Cancel</button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
