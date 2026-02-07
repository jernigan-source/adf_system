<?php
/**
 * FRONT DESK - CALENDAR BOOKING VIEW
 * Interactive Calendar like CloudBeds
 * Horizontal: Dates | Vertical: Room Numbers + Guest Names
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

$pageTitle = 'Calendar Booking';

// ============================================
// GET CALENDAR DATE RANGE
// ============================================
$startDate = $_GET['start'] ?? date('Y-m-d');
$daysToShow = 365; // Show 365 days (1 full year) untuk banyak scrollable content
$dates = [];
for ($i = 0; $i < $daysToShow; $i++) {
    $dates[] = date('Y-m-d', strtotime($startDate . " +{$i} days"));
}

// ============================================
// GET ALL ROOMS WITH TYPES
// ============================================
try {
    $rooms = $db->fetchAll("
        SELECT r.id, r.room_number, r.floor_number, r.status, rt.type_name, rt.base_price, rt.color_code
        FROM rooms r
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.status != 'maintenance'
        ORDER BY rt.type_name ASC, r.floor_number ASC, r.room_number ASC
    ", []);
} catch (Exception $e) {
    error_log("Rooms Error: " . $e->getMessage());
    $rooms = [];
}

// ============================================
// GET BOOKINGS FOR DATE RANGE
// ============================================
try {
    $endDate = date('Y-m-d', strtotime($startDate . " +{$daysToShow} days"));
    
    // Fetch all bookings that overlap with date range
    $bookings = $db->fetchAll("
        SELECT 
            b.id, 
            b.booking_code, 
            b.room_id, 
            b.check_in_date, 
            b.check_out_date,
            b.status, 
            b.room_price, 
            b.booking_source,
            b.payment_status,
            g.guest_name, 
            g.phone
        FROM bookings b
        LEFT JOIN guests g ON b.guest_id = g.id
        WHERE b.check_in_date < ? 
        AND b.check_out_date > ?
        AND b.status IN ('pending', 'confirmed', 'checked_in')
        ORDER BY b.check_in_date ASC, b.room_id ASC
    ", [$endDate, $startDate]);
    
    echo "<!-- DEBUG: Found " . count($bookings) . " bookings -->\n";
    
} catch (Exception $e) {
    error_log("Bookings Error: " . $e->getMessage());
    $bookings = [];
}

// ============================================
// BUILD BOOKING MATRIX
// ============================================
$bookingMatrix = [];
foreach ($bookings as $booking) {
    $roomId = $booking['room_id'];
    if (!isset($bookingMatrix[$roomId])) {
        $bookingMatrix[$roomId] = [];
    }
    $bookingMatrix[$roomId][$booking['booking_code']] = $booking;
}

// ============================================
// BOOKING COLORS - SIMPLE: Default vs Checked-In
// ============================================
$defaultColor = ['bg' => '#3b82f6', 'text' => 'white'];      // Blue for pending/confirmed bookings
$checkedInColor = ['bg' => '#10b981', 'text' => 'white'];    // Green for checked-in guests (active)

include '../../includes/header.php';
?>

<style>
/* ============================================
   CLOUDBEDS STYLE CALENDAR - SYSTEM THEME
   ============================================ */

.calendar-container {
    width: 100%;
    padding: 0.3rem 0.1rem;
    overflow: visible;
    box-sizing: border-box;
    max-width: 100%;
    position: relative;
    z-index: 1;
}

/* Scroll Container - MUST BE CONSTRAINED */
.calendar-scroll-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    position: relative;
    box-sizing: border-box;
    display: block;
    white-space: nowrap;
    cursor: grab;
    background: transparent;
    touch-action: pan-x;
}

.calendar-scroll-container:active {
    cursor: grabbing;
}

.calendar-header {
    display: flex;
    flex-direction: column;
    margin-bottom: 0.5rem;
    gap: 0.5rem;
}

.calendar-header h1 {
    font-size: 1rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.calendar-header h1 .icon {
    -webkit-text-fill-color: #6366f1;
    background: none;
}

.calendar-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.btn-nav {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff !important;
    border: none;
    padding: 0.4rem 0.7rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    line-height: 1.2;
    text-decoration: none;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.btn-nav:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    color: #ffffff !important;
}

.btn-nav:visited,
.btn-nav:active,
.btn-nav:focus {
    color: #ffffff !important;
}

.date-display {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-primary);
}

.nav-date-input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.5rem 0.8rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Navigation Bar */
.calendar-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
    background: var(--card-bg);
    backdrop-filter: blur(30px);
    border: 0.5px solid var(--border-color);
    border-radius: 8px;
    padding: 0.4rem;
}

.nav-btn {
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
    border: 1px solid rgba(99, 102, 241, 0.3);
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-btn:hover {
    background: rgba(99, 102, 241, 0.4);
    border-color: rgba(99, 102, 241, 0.6);
    color: white;
}

.nav-date-input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--text-primary);
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-weight: 600;
}

/* Calendar Wrapper */
.calendar-wrapper {
    background: var(--card-bg);
    backdrop-filter: blur(30px);
    border: 0.5px solid var(--border-color);
    border-radius: 8px;
    overflow: visible !important;
    padding: 0.3rem;
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
    cursor: grab;
    width: fit-content;
    min-width: 100%;
    display: inline-block;
    box-sizing: border-box;
    position: relative;
    scroll-behavior: smooth;
    overscroll-behavior: contain;
}

.calendar-wrapper.dragging {
    cursor: grabbing;
}

.calendar-wrapper.dragging,
.calendar-wrapper.dragging * {
    user-select: none !important;
}

body.calendar-dragging {
    user-select: none;
    cursor: grabbing;
}

/* Light Theme - Make borders more visible */
body[data-theme="light"] .calendar-wrapper,
body[data-theme="light"] .calendar-nav,
body[data-theme="light"] .legend {
    border: 1px solid rgba(51, 65, 85, 0.2);
}
body[data-theme="light"] .calendar-wrapper,
body[data-theme="light"] .calendar-nav,
body[data-theme="light"] .legend {
    border: 1px solid rgba(51, 65, 85, 0.2);
}

body[data-theme="light"] .grid-header-date,
body[data-theme="light"] .grid-date-cell,
body[data-theme="light"] .grid-room-label,
body[data-theme="light"] .grid-room-type-header,
body[data-theme="light"] .grid-header-room {
    border-color: rgba(51, 65, 85, 0.15);
}

body[data-theme="light"] .grid-header-date,
body[data-theme="light"] .grid-date-cell {
    border-right-width: 1px;
    border-bottom-width: 1px;
}

body[data-theme="light"] .grid-room-label,
body[data-theme="light"] .grid-room-type-header {
    border-right-width: 1px;
    border-bottom-width: 1px;
}

.calendar-wrapper::-webkit-scrollbar {
    height: 12px;
}

.calendar-wrapper::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.calendar-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.7), rgba(139, 92, 246, 0.7));
    border-radius: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.calendar-wrapper::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.9), rgba(139, 92, 246, 0.9));
}

.calendar-grid {
    display: grid;
    gap: 0;
    grid-template-columns: 80px repeat(<?php echo count($dates); ?>, 60px);
    width: fit-content;
    min-width: fit-content;
    max-width: none;
}

/* Header Row */
.calendar-grid-header {
    display: contents;
}

.grid-header-room {
    background: var(--bg-secondary);
    backdrop-filter: none;
    border: 0.5px solid var(--border-color);
    padding: 0.15rem 0.1rem;
    font-weight: 900;
    text-align: center;
    position: sticky;
    left: 0;
    z-index: 20;
    font-size: 0.6rem;
    color: var(--text-primary);
    box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: 0.2px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 22px;
}

/* Light theme - better header visibility */
body[data-theme="light"] .grid-header-room {
    background: rgba(99, 102, 241, 0.1);
    font-weight: 900;
    border: 1px solid rgba(51, 65, 85, 0.2);
}

.grid-header-date {
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.15), rgba(99, 102, 241, 0.05));
    border-right: 0.5px solid var(--border-color);
    border-bottom: 0.5px solid var(--border-color);
    padding: 0.1rem 0.05rem;
    text-align: center;
    font-weight: 600;
    font-size: 0.45rem;
    color: var(--text-primary);
    position: relative;
    min-height: 20px;
}

/* Light theme - visible borders */
body[data-theme="light"] .grid-header-date {
    border-right: 1px solid rgba(51, 65, 85, 0.15);
    border-bottom: 1px solid rgba(51, 65, 85, 0.15);
    background: transparent;
}

/* TODAY HIGHLIGHT - SIMPLE & ELEGANT */
.grid-header-date.today {
    background: rgba(99, 102, 241, 0.1) !important;
}

.grid-header-date.today .grid-header-date-num {
    color: #6366f1;
    font-weight: 900;
}

.grid-date-cell.today {
    background: rgba(99, 102, 241, 0.05) !important;
}

/* Light theme - more visible today highlight */
body[data-theme="light"] .grid-header-date.today {
    background: rgba(99, 102, 241, 0.15) !important;
}

body[data-theme="light"] .grid-date-cell.today {
    background: rgba(99, 102, 241, 0.08) !important;
}

.grid-header-date-day {
    display: block;
    font-size: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.15px;
    margin-bottom: 0.03rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.grid-header-date-num {
    display: block;
    font-size: 0.6rem;
    font-weight: 900;
    margin-bottom: 0.03rem;
    line-height: 1;
    color: var(--text-primary);
}

.grid-header-price {
    display: block;
    font-size: 0.6rem;
    color: #6366f1;
    font-weight: 800;
    margin-top: 0.1rem;
    line-height: 1;
    background: rgba(99, 102, 241, 0.1);
    padding: 0.1rem 0.2rem;
    border-radius: 3px;
}

/* Room Row */
.grid-room-label {
    background: var(--bg-secondary);
    backdrop-filter: none;
    border-right: 0.5px solid var(--border-color);
    border-bottom: 0.5px solid var(--border-color);
    padding: 0.1rem 0.15rem;
    font-weight: 800;
    color: var(--text-primary);
    position: sticky;
    left: 0;
    z-index: 15;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.02rem;
    min-width: 80px;
    cursor: grab;
    font-size: 0.6rem;
    min-height: 22px;
    box-shadow: 1px 0 4px rgba(0, 0, 0, 0.1);
    white-space: normal;
    word-break: break-word;
}

/* Light theme - better room label contrast */
body[data-theme="light"] .grid-room-label {
    background: rgba(248, 250, 252, 0.98);
    font-weight: 900;
    border-right: 1px solid rgba(51, 65, 85, 0.15);
    border-bottom: 1px solid rgba(51, 65, 85, 0.15);
}

.grid-room-type-header {
    background: var(--bg-secondary);
    backdrop-filter: none;
    border-right: 0.5px solid var(--border-color);
    border-bottom: 0.5px solid var(--border-color);
    padding: 0.25rem 0.3rem;
    font-weight: 800;
    color: var(--text-primary);
    position: sticky;
    left: 0;
    z-index: 15;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    font-size: 0.9rem;
    gap: 0.25rem;
    min-height: 18px;
    box-shadow: 1px 0 4px rgba(0, 0, 0, 0.1);
}

/* Light theme - better type header visibility */
body[data-theme="light"] .grid-room-type-header {
    background: rgba(99, 102, 241, 0.08);
    font-weight: 900;
    border-right: 1px solid rgba(51, 65, 85, 0.15);
    border-bottom: 1px solid rgba(51, 65, 85, 0.15);
}

/* Ensure booking bar text stays white in light theme */
body[data-theme="light"] .booking-bar,
body[data-theme="light"] .booking-bar span,
body[data-theme="light"] .booking-bar * {
    color: #ffffff !important;
}

/* Maximum specificity - force white text in all scenarios */
.booking-bar,
.booking-bar span,
.booking-bar > span,
body .booking-bar,
body .booking-bar span,
body .booking-bar > span {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
}

.grid-room-number {
    font-size: 0.78rem;
    color: var(--text-primary);
    font-weight: 900;
    line-height: 1;
    letter-spacing: 0.2px;
}

.grid-room-type {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 600;
    line-height: 1;
}

.grid-room-price {
    display: none;
}

/* Date Cells */
.grid-date-cell {
    border-right: 0.5px solid var(--border-color);
    border-bottom: 0.5px solid var(--border-color);
    padding: 0.08rem 0.05rem;
    min-height: 22px;
    position: relative;
    background: transparent;
    cursor: pointer;
    transition: background 0.2s ease;
}

/* Same-day turnover divider (checkout left, checkin right) */
.grid-date-cell.has-turnover::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #ef4444, #f97316, #ef4444);
    z-index: 5;
    box-shadow: 0 0 6px rgba(239, 68, 68, 0.6);
    transform: translateX(-50%);
}

/* Light theme - visible cell borders */
body[data-theme="light"] .grid-date-cell {
    border-right: 1px solid rgba(51, 65, 85, 0.15);
    border-bottom: 1px solid rgba(51, 65, 85, 0.15);
}

.grid-date-cell:last-child {
    border-right: none;
}

.grid-date-cell:hover {
    background: rgba(99, 102, 241, 0.05);
}

/* Booking Bars - CLOUDBED STYLE (Noon to Noon) */
.booking-bar-container {
    position: absolute;
    top: 1px;
    left: 1px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    overflow: visible;
    pointer-events: auto;
    z-index: 10;
}

.booking-bar {
    width: 100%;
    height: 18px;
    padding: 0 0.25rem;
    cursor: pointer;
    overflow: visible;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2), 0 2px 3px rgba(0, 0, 0, 0.15);
    font-weight: 700;
    font-size: 0.6rem;
    line-height: 1;
    position: relative;
    pointer-events: auto;
    border-radius: 3px;
    white-space: nowrap;
    transform: skewX(-20deg);
    background: linear-gradient(135deg, #06b6d4, #22d3ee) !important;
    color: #ffffff !important;
}

.booking-bar > span {
    transform: skewX(20deg);
    color: #ffffff !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.7);
    font-weight: 800;
    display: block;
}

.booking-bar *,
.booking-bar > * {
    color: #ffffff !important;
}

.booking-bar::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 12px solid transparent;
    border-bottom: 12px solid transparent;
    border-right: 8px solid;
    border-right-color: inherit;
}

.booking-bar::after {
    content: '';
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 12px solid transparent;
    border-bottom: 12px solid transparent;
    border-left: 8px solid;
    border-left-color: inherit;
}

.booking-bar:hover {
    transform: skewX(-20deg) scaleY(1.15);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    z-index: 20;
}

/* Past Booking Styling - Samar-samar Abu-abu Transparan */
.booking-bar.booking-past {
    opacity: 0.4 !important;
    background: linear-gradient(135deg, #9ca3af, #d1d5db) !important;
    border-right-color: #9ca3af !important;
    border-left-color: #d1d5db !important;
}

.booking-bar.booking-past > span {
    color: #6b7280 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
}

.booking-bar.booking-past:hover {
    opacity: 0.6 !important;
    transform: skewX(-20deg) scaleY(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

/* Status specific bars */
.booking-confirmed {
    background: linear-gradient(135deg, #06b6d4, #22d3ee) !important;
    border-right-color: #06b6d4;
    border-left-color: #22d3ee;
}

.booking-pending {
    background: linear-gradient(135deg, #0ea5e9, #38bdf8) !important;
    border-right-color: #0ea5e9;
    border-left-color: #38bdf8;
}

.booking-checked-in {
    background: linear-gradient(135deg, #0284c7, #0ea5e9) !important;
    border-right-color: #0284c7;
    border-left-color: #0ea5e9;
}

.booking-bar-guest,
.booking-bar-code,
.booking-bar-status {
    color: #ffffff !important;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
    font-weight: 800;
}

/* Legend */
.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin-top: 0.4rem;
    padding: 0.4rem 0.5rem;
    background: var(--card-bg);
    backdrop-filter: blur(30px);
    border: 0.5px solid var(--border-color);
    border-radius: 8px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 3px;
    border: 0.5px solid var(--border-color);
}

.legend-label {
    font-weight: 600;
    font-size: 0.65rem;
    color: var(--text-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .calendar-container {
        padding: 0.75rem 0.25rem;
    }

    .calendar-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .calendar-header h1 {
        font-size: 1.75rem;
    }

    .grid-header-date {
        padding: 0.55rem 0.25rem;
        font-size: 0.72rem;
    }

    .grid-header-date-num {
        font-size: 1rem;
    }

    .grid-room-label {
        padding: 0.6rem 0.35rem;
        min-width: 80px;
    }

    .grid-date-cell {
        min-height: 85px;
    }

    .booking-bar {
        height: 80px;
        font-size: 0.65rem;
    }

    .booking-bar-guest {
        font-size: 0.7rem;
    }

    .calendar-nav {
        flex-direction: column;
        gap: 1rem;
    }

    .form-row-3 {
        grid-template-columns: 1fr 1fr;
    }

    .form-row-3 .form-group:last-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 480px) {
    .calendar-wrapper {
        padding: 0.5rem;
    }

    .grid-room-label {
        padding: 0.5rem;
        font-size: 0.72rem;
        min-width: 60px;
    }

    .grid-date-cell {
        min-height: 75px;
        padding: 0.25rem;
    }

    .booking-bar {
        height: 70px;
        font-size: 0.6rem;
        padding: 0.2rem;
        color: #ffffff !important;
    }

    .form-row-3 {
        grid-template-columns: 1fr;
    }

    .form-row-3 .form-group:last-child {
        grid-column: 1;
    }

    .booking-bar-guest {
        font-size: 0.65rem;
    }

    .grid-header-date-num {
        font-size: 0.9rem;
    }

    .legend {
        flex-direction: column;
        gap: 1rem;
    }
}

/* ============================================
   MODAL POPUP STYLES
   ============================================ */
.modal-overlay {
    display: none !important;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex !important;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 10000;
}

body[data-theme="light"] .modal-content {
    background: white;
    border: 1px solid rgba(51, 65, 85, 0.15);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    margin-bottom: 1rem;
    text-align: center;
}

.modal-header h2 {
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.modal-header p {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.modal-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: rgba(239, 68, 68, 0.15);
    border: 2px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.5rem;
    font-weight: 700;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    z-index: 1000;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
    color: #dc2626;
    transform: rotate(90deg) scale(1.1);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.modal-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.modal-btn {
    padding: 1rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.modal-btn-primary {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.modal-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(16, 185, 129, 0.3);
}

.modal-btn-secondary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.modal-btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(99, 102, 241, 0.3);
}

.modal-date-info {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* RESERVATION FORM STYLES */
.modal-content-large {
    max-width: 650px;
    max-height: 85vh;
    overflow-y: auto;
}

.modal-content-medium {
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
}

/* Booking Details Modal Styles */
.booking-details-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 1rem 0;
}

.detail-section {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 8px;
    padding: 0.75rem;
}

body[data-theme="light"] .detail-section {
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(51, 65, 85, 0.15);
}

.detail-section h3 {
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.4rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.3rem 0;
}

.detail-label {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 600;
}

.detail-value {
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 700;
    text-align: right;
}

.status-badge {
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.status-confirmed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-badge.status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-badge.status-checked_in {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.status-badge.status-paid {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-badge.status-unpaid {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.status-badge.status-partial {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

/* Booking Action Buttons */
.booking-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn-action {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-checkin {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.btn-checkin:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-checkout {
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    color: white;
}

.btn-checkout:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.btn-move {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
}

.btn-move:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
}

.form-grid {
    display: grid;
    gap: 1rem;
}

.form-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.85rem;
}

body[data-theme="light"] .form-section {
    background: rgba(248, 250, 252, 0.5);
    border: 1px solid rgba(51, 65, 85, 0.15);
}

.form-section h3 {
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}

.form-row-3 {
    grid-template-columns: repeat(3, 1fr);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.form-group label {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    background: var(--sidebar-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.6rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.readonly-input {
    background: rgba(99, 102, 241, 0.1) !important;
    cursor: not-allowed;
    font-weight: 600;
    color: #6366f1 !important;
}

body[data-theme="light"] .readonly-input {
    background: rgba(99, 102, 241, 0.08) !important;
}

body[data-theme="light"] .form-group input,
body[data-theme="light"] .form-group select,
body[data-theme="light"] .form-group textarea {
    background: white;
    border: 1px solid rgba(51, 65, 85, 0.2);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.form-group textarea {
    resize: vertical;
    font-family: inherit;
    min-height: 70px;
}

.payment-method-group,
.dp-percent-group {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.payment-method-btn,
.dp-percent-btn {
    border: 1px solid var(--border-color);
    background: var(--sidebar-bg);
    color: var(--text-primary);
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-method-btn:hover,
.dp-percent-btn:hover {
    border-color: #6366f1;
    color: #6366f1;
}

.payment-method-btn.active,
.dp-percent-btn.active {
    background: #6366f1;
    color: white;
    border-color: #6366f1;
}

.price-summary {
    background: var(--sidebar-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.75rem;
    margin-top: 0.5rem;
}

body[data-theme="light"] .price-summary {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.price-row strong {
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
}

.price-row-total {
    border-top: 1px solid var(--border-color);
    margin-top: 0.4rem;
    padding-top: 0.6rem;
    font-size: 0.9rem;
    font-weight: 700;
}

body[data-theme="light"] .price-row-total {
    border-top-color: rgba(16, 185, 129, 0.3);
}

.price-row-total strong {
    color: #10b981;
    font-size: 1rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn-primary,
.btn-secondary {
    padding: 0.6rem 1.25rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white !important;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    background: var(--sidebar-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

body[data-theme="light"] .btn-secondary {
    background: white;
    border: 1px solid rgba(51, 65, 85, 0.2);
}

.btn-secondary:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
}

.modal-date-info strong {
    color: var(--text-primary);
    font-size: 1.1rem;
    display: block;
    margin-top: 0.5rem;
}
</style>

<div class="calendar-container">
    <!-- Header -->
    <div class="calendar-header">
        <div>
            <h1><span class="icon">📆</span> Calendar Booking</h1>
        </div>
        <div class="calendar-controls">
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/reservasi.php" class="btn-nav">
                📋 List View
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/breakfast.php" class="btn-nav">
                🍽️ Breakfast List
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/settings.php" class="btn-nav">
                ⚙️ Settings
            </a>
            <a href="<?php echo BASE_URL; ?>/modules/frontdesk/dashboard.php" class="btn-nav">
                📊 Dashboard
            </a>
        </div>
    </div>

    <!-- Navigation -->
    <div class="calendar-nav">
        <button class="nav-btn" id="prevMonthBtn" type="button">← Previous 30 Days</button>
        <input type="date" class="nav-date-input" id="dateInput" value="<?php echo $startDate; ?>" onchange="changeDate()">
        <button class="nav-btn" id="nextMonthBtn" type="button">Next 30 Days →</button>
        <span class="date-display">
            <?php echo date('M d', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($startDate . ' +29 days')); ?>
        </span>
    </div>

    <!-- Calendar Grid - WRAPPED IN SCROLL CONTAINER -->
    <div class="calendar-scroll-container">
        <div class="calendar-wrapper">
            <div class="calendar-grid">
            <!-- Header Row -->
            <div class="calendar-grid-header">
                <div class="grid-header-room">ROOMS</div>
                <?php foreach ($dates as $date): ?>
                <div class="grid-header-date<?php echo ($date === date('Y-m-d')) ? ' today' : ''; ?>">
                    <span class="grid-header-date-day">
                        <?php echo date('D', strtotime($date)); ?>
                    </span>
                    <span class="grid-header-date-num">
                        <?php echo date('d', strtotime($date)); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Room Rows -->
            <?php 
            // Group rooms by type
            $roomsByType = [];
            foreach ($rooms as $room) {
                $typeKey = $room['type_name'];
                if (!isset($roomsByType[$typeKey])) {
                    $roomsByType[$typeKey] = [];
                }
                $roomsByType[$typeKey][] = $room;
            }
            
            // Display rooms grouped by type with type headers
            foreach ($roomsByType as $typeName => $typeRooms): 
                // Get base price from first room of this type
                $typePrice = $typeRooms[0]['base_price'] ?? 0;
            ?>
                <!-- Type Header Row -->
                <div class="grid-room-type-header">
                    📂 <?php echo htmlspecialchars($typeName); ?>
                </div>
                <?php foreach ($dates as $date): ?>
                <div style="background: rgba(99, 102, 241, 0.08); border-right: 2px solid rgba(99, 102, 241, 0.4); border-bottom: 1px solid rgba(255, 255, 255, 0.1); min-height: 30px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; color: rgba(99, 102, 241, 0.9);">
                    Rp<?php echo number_format($typePrice / 1000, 0, ',', '.'); ?>K
                </div>
                <?php endforeach; ?>
                
                <!-- Individual Rooms of This Type -->
                <?php foreach ($typeRooms as $room): ?>
                <div class="grid-room-label">
                    <span class="grid-room-number"><?php echo htmlspecialchars($room['room_number']); ?></span>
                </div>

                <?php foreach ($dates as $date): ?>
                <?php
                // Check for same-day turnover (checkout + checkin on same day)
                $hasTurnover = false;
                if (isset($bookingMatrix[$room['id']])) {
                    $checkouts = 0;
                    $checkins = 0;
                    foreach ($bookingMatrix[$room['id']] as $booking) {
                        $checkinDate = date('Y-m-d', strtotime($booking['check_in_date']));
                        $checkoutDate = date('Y-m-d', strtotime($booking['check_out_date']));
                        if ($checkoutDate === $date) $checkouts++;
                        if ($checkinDate === $date) $checkins++;
                    }
                    $hasTurnover = ($checkouts > 0 && $checkins > 0);
                }
                ?>
                <div class="grid-date-cell<?php echo ($date === date('Y-m-d')) ? ' today' : ''; ?><?php echo $hasTurnover ? ' has-turnover' : ''; ?>" 
                     data-date="<?php echo $date; ?>"
                     data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                     data-room-id="<?php echo $room['id']; ?>"
                     title="<?php echo htmlspecialchars($room['room_number']); ?> - <?php echo date('d M Y', strtotime($date)); ?><?php echo $hasTurnover ? ' (Turnover: CO + CI)' : ''; ?>">
                    <?php
                    // Find bookings for this room and date - CLOUDBED STYLE (bar from noon to noon)
                    if (isset($bookingMatrix[$room['id']])) {
                        foreach ($bookingMatrix[$room['id']] as $booking) {
                            $checkinDate = strtotime($booking['check_in_date']);
                            $checkoutDate = strtotime($booking['check_out_date']);
                            $currentDate = strtotime($date);
                            
                            // Only render bar on check-in date
                            if ($currentDate === $checkinDate) {
                                // Calculate nights (days between check-in and check-out)
                                $totalNights = ceil(($checkoutDate - $checkinDate) / 86400);
                                
                                // Calculate width: start from 50% of check-in cell, end at 50% of check-out cell
                                // Width = (nights × 100px) = full span from noon to noon
                                $barWidth = ($totalNights * 100); // 100px per day
                                
                                $statusClass = 'booking-' . str_replace('_', '-', $booking['status']);
                                
                                // Check if booking is past (check-out date is before today)
                                $today = strtotime(date('Y-m-d'));
                                $isPastBooking = ($checkoutDate < $today);
                                if ($isPastBooking) {
                                    $statusClass .= ' booking-past';
                                }
                                
                                // Determine color based on check-in status
                                $isCheckedIn = ($booking['status'] === 'checked_in');
                                $bookingColor = $isCheckedIn ? $checkedInColor : $defaultColor;
                                
                                // Add checkmark icon for checked-in guests
                                $statusIcon = $isCheckedIn ? '✓ ' : '';
                                
                                $guestName = htmlspecialchars(substr($booking['guest_name'] ?? 'Guest', 0, 12));
                                $bookingCode = htmlspecialchars($booking['booking_code']);
                                $shortCode = substr($bookingCode, 0, 8); // Show first 8 chars
                                $statusText = ucfirst(str_replace('_', ' ', $booking['status']));
                                ?>
                                <div class="booking-bar-container" style="left: 50%; width: <?php echo $barWidth; ?>px;">
                                    <div class="booking-bar <?php echo $statusClass; ?>" 
                                         style="background: linear-gradient(135deg, <?php echo $bookingColor['bg']; ?>, <?php echo $bookingColor['bg']; ?>dd) !important; border-right-color: <?php echo $bookingColor['bg']; ?>; border-left-color: <?php echo $bookingColor['bg']; ?>dd;"
                                         onclick="event.stopPropagation(); viewBooking(<?php echo $booking['id']; ?>, event);"
                                         title="<?php echo $statusIcon . $guestName; ?> (<?php echo $bookingCode; ?>) - <?php echo $statusText; ?><?php echo $isPastBooking ? ' [PAST]' : ''; ?>">
                                        <span><?php echo $statusIcon . $guestName; ?> • <?php echo $shortCode; ?></span>
                                    </div>
                                </div>
                                <?php
                                break; // Only one bar per booking
                            }
                        }
                    }
                    ?>
                </div>
                <?php endforeach; // End dates loop for each room ?>
                <?php endforeach; // End individual rooms loop ?>
            <?php endforeach; // End room types loop
            ?>
        </div>
    </div>
    </div>

    <!-- Legend -->
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"></div>
            <span class="legend-label">📋 Booking (Confirmed/Pending)</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: linear-gradient(135deg, #10b981, #34d399);"></div>
            <span class="legend-label">✓ Checked In (Active)</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: linear-gradient(135deg, #9ca3af, #d1d5db); opacity: 0.4;"></div>
            <span class="legend-label">📭 Past Booking (History)</span>
        </div>
    </div>

</div>

<script>
window.viewBooking = function viewBooking(id, event) {
    event.preventDefault();
    event.stopPropagation();
    
    console.log('📋 Loading booking details:', id);
    
    // Fetch booking details via AJAX - use relative path from modules/frontdesk/
    fetch('../../api/get-booking-details.php?id=' + id)
        .then(response => {
            console.log('📡 API Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('📥 API Response text:', text);
            try {
                const data = JSON.parse(text);
                console.log('✅ Parsed JSON:', data);
                if (data.success) {
                    console.log('🎯 Showing booking:', data.booking);
                    showBookingQuickView(data.booking);
                } else {
                    console.error('❌ API Error:', data.message);
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                console.error('❌ JSON Parse Error:', e);
                console.error('Raw text:', text);
                alert('Failed to parse response');
            }
        })
        .catch(error => {
            console.error('❌ Fetch Error:', error);
            alert('Failed to load booking details: ' + error.message);
        });
}

let currentPaymentBooking = null;

// Quick view popup - simple and elegant
function showBookingQuickView(booking) {
    currentPaymentBooking = booking;
    console.log('🎯 showBookingQuickView called with:', booking);
    
    const modal = document.getElementById('bookingQuickView');
    console.log('📦 Modal element found:', modal);
    
    if (!modal) {
        console.error('❌ Modal not found!');
        alert('Error: Modal element not found');
        return;
    }
    
    // Format data
    const checkIn = new Date(booking.check_in_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    const checkOut = new Date(booking.check_out_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    const totalPrice = new Intl.NumberFormat('id-ID').format(booking.final_price);
    const paidAmount = new Intl.NumberFormat('id-ID').format(booking.paid_amount);
    const remaining = new Intl.NumberFormat('id-ID').format(booking.final_price - booking.paid_amount);
    
    // Payment status badge color
    let paymentBadge = '';
    if (booking.payment_status === 'paid') {
        paymentBadge = '<span style="background: #10b981; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">LUNAS</span>';
    } else if (booking.payment_status === 'partial') {
        paymentBadge = '<span style="background: #f59e0b; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">CICILAN</span>';
    } else {
        paymentBadge = '<span style="background: #ef4444; color: white; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">BELUM BAYAR</span>';
    }
    
    // Booking source
    const sourceMap = {
        'walk_in': 'Walk-in',
        'phone': 'Phone',
        'online': 'Online',
        'ota': 'OTA'
    };
    const source = sourceMap[booking.booking_source] || booking.booking_source;
    
    // Populate modal
    document.getElementById('qv-content').innerHTML = `
        <div style="text-align: center; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(99, 102, 241, 0.2);">
            <div style="font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem;">BOOKING CODE</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: #6366f1; font-family: 'Courier New', monospace;">${booking.booking_code}</div>
        </div>
        
        <div style="padding: 0.75rem 0;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.25rem;">👤</span>
                <div style="flex: 1;">
                    <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.3px;">Tamu</div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-primary);">${booking.guest_name}</div>
                </div>
            </div>
            
            ${booking.guest_phone ? `
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.25rem;">📞</span>
                <div style="flex: 1;">
                    <div style="font-size: 0.65rem; color: var(--text-secondary);">Phone</div>
                    <div style="font-size: 0.85rem; color: var(--text-primary);">${booking.guest_phone}</div>
                </div>
            </div>
            ` : ''}
            
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.25rem;">🏠</span>
                <div style="flex: 1;">
                    <div style="font-size: 0.65rem; color: var(--text-secondary);">Room</div>
                    <div style="font-size: 0.85rem; color: var(--text-primary); font-weight: 600;">Room ${booking.room_number} - ${booking.room_type}</div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.25rem;">📅</span>
                <div style="flex: 1;">
                    <div style="font-size: 0.65rem; color: var(--text-secondary);">Check-in / Check-out</div>
                    <div style="font-size: 0.8rem; color: var(--text-primary);">${checkIn} → ${checkOut}</div>
                    <div style="font-size: 0.7rem; color: var(--text-secondary);">${booking.total_nights} malam • ${source}</div>
                </div>
            </div>
        </div>
        
        <div style="background: rgba(99, 102, 241, 0.05); border-radius: 8px; padding: 0.75rem; margin-top: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <div style="font-size: 0.7rem; color: var(--text-secondary); font-weight: 600;">STATUS PEMBAYARAN</div>
                ${paymentBadge}
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="font-size: 0.75rem; color: var(--text-secondary);">Total Harga:</span>
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary);">Rp ${totalPrice}</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="font-size: 0.75rem; color: var(--text-secondary);">Sudah Bayar:</span>
                <span style="font-size: 0.85rem; font-weight: 700; color: #10b981;">Rp ${paidAmount}</span>
            </div>
            
            ${booking.payment_status !== 'paid' ? `
            <div style="display: flex; justify-content: space-between; padding-top: 0.35rem; border-top: 1px dashed rgba(99, 102, 241, 0.3);">
                <span style="font-size: 0.75rem; color: var(--text-secondary);">Sisa:</span>
                <span style="font-size: 0.9rem; font-weight: 800; color: #ef4444;">Rp ${remaining}</span>
            </div>
            ` : ''}
        </div>
        ${booking.payment_status !== 'paid' ? `
        <div class="qv-actions">
            <button type="button" class="qv-btn qv-checkin-btn" onclick="quickViewCheckIn()">Check-in</button>
            <button type="button" class="qv-btn qv-move-btn" onclick="quickViewMoveRoom()">Move</button>
            <button type="button" class="qv-btn qv-pay-btn" onclick="openBookingPaymentModal()">Pay</button>
        </div>
        ` : `
        <div class="qv-actions">
            <button type="button" class="qv-btn qv-checkin-btn" onclick="quickViewCheckIn()">Check-in</button>
            <button type="button" class="qv-btn qv-move-btn" onclick="quickViewMoveRoom()">Move</button>
        </div>
        `}
    `;
    
    console.log('✅ Content populated');
    
    // Add active class FIRST to ensure CSS applies
    modal.classList.add('active');
    console.log('✅ Active class added');
    
    // Then set inline styles as fallback
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.zIndex = '99999';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    
    console.log('✅ Modal should be visible now!', {
        display: modal.style.display,
        position: modal.style.position,
        zIndex: modal.style.zIndex,
        hasActiveClass: modal.classList.contains('active')
    });
}

window.closeBookingQuickView = function closeBookingQuickView() {
    const modal = document.getElementById('bookingQuickView');
    modal.classList.remove('active');
    modal.style.display = '';
    modal.style.position = '';
    modal.style.zIndex = '';
}

window.showBookingDetailsModal = function showBookingDetailsModal(booking) {
    const modal = document.getElementById('bookingDetailsModal');
    currentPaymentBooking = booking;
    
    // Populate modal with booking data
    document.getElementById('detailGuestName').textContent = booking.guest_name;
    document.getElementById('detailGuestPhone').textContent = booking.guest_phone || '-';
    document.getElementById('detailGuestEmail').textContent = booking.guest_email || '-';
    document.getElementById('detailRoomNumber').textContent = booking.room_number;
    document.getElementById('detailRoomType').textContent = booking.room_type;
    document.getElementById('detailCheckIn').textContent = formatDateFull(booking.check_in_date);
    document.getElementById('detailCheckOut').textContent = formatDateFull(booking.check_out_date);
    document.getElementById('detailNights').textContent = booking.total_nights + ' night(s)';
    document.getElementById('detailBookingCode').textContent = booking.booking_code;
    document.getElementById('detailPaymentStatus').textContent = booking.payment_status.toUpperCase();
    document.getElementById('detailPaymentStatus').className = 'status-badge status-' + booking.payment_status;
    document.getElementById('detailBookingStatus').textContent = booking.status.toUpperCase().replace('_', ' ');
    document.getElementById('detailBookingStatus').className = 'status-badge status-' + booking.status;
    document.getElementById('detailTotalPrice').textContent = 'Rp ' + formatNumberIDR(booking.final_price);
    
    // Set booking ID for action buttons
    modal.dataset.bookingId = booking.id;
    modal.dataset.bookingStatus = booking.status;
    modal.dataset.paymentStatus = booking.payment_status;
    
    // Show/hide action buttons based on status
    updateActionButtons(booking.status, booking.payment_status);
    
    modal.classList.add('active');
}

function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').classList.remove('active');
}

function formatDateFull(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatNumberIDR(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

function updateActionButtons(status, paymentStatus) {
    const checkInBtn = document.getElementById('btnCheckIn');
    const checkOutBtn = document.getElementById('btnCheckOut');
    const moveBtn = document.getElementById('btnMove');
    const payBtn = document.getElementById('btnPay');
    
    // Show/hide buttons based on status
    if (status === 'confirmed' || status === 'pending') {
        checkInBtn.style.display = 'flex';
        checkOutBtn.style.display = 'none';
        moveBtn.style.display = 'flex';
    } else if (status === 'checked_in') {
        checkInBtn.style.display = 'none';
        checkOutBtn.style.display = 'flex';
        moveBtn.style.display = 'flex';
    } else {
        checkInBtn.style.display = 'none';
        checkOutBtn.style.display = 'none';
        moveBtn.style.display = 'none';
    }
    
    // Show Pay button if unpaid or partial
    if (paymentStatus === 'unpaid' || paymentStatus === 'partial') {
        payBtn.style.display = 'flex';
    } else {
        payBtn.style.display = 'none';
    }
}

function doCheckIn() {
    const modal = document.getElementById('bookingDetailsModal');
    const bookingId = modal.dataset.bookingId;
    const guestName = document.getElementById('detailGuestName').textContent;
    const roomNumber = document.getElementById('detailRoomNumber').textContent;
    const paymentStatus = modal.dataset.paymentStatus;

    if (paymentStatus !== 'paid') {
        const proceed = confirm('Pembayaran belum lunas. Lanjut check-in dan buat invoice sisa?');
        if (!proceed) {
            openBookingPaymentModal();
            return;
        }
    }
    
    if (confirm(`Check-in ${guestName} ke Room ${roomNumber} sekarang?`)) {
        // Show loading state
        const checkInBtn = document.getElementById('btnCheckIn');
        const originalText = checkInBtn.innerHTML;
        checkInBtn.innerHTML = '<span>⏳</span><span>Processing...</span>';
        checkInBtn.disabled = true;
        
        // Call check-in API
        const createInvoice = paymentStatus !== 'paid' ? 1 : 0;
        fetch('<?php echo BASE_URL; ?>/api/checkin-guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include', // Important: Send session cookies
            body: 'booking_id=' + bookingId + '&create_invoice=' + createInvoice
        })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server mengembalikan response non-JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.invoice_number) {
                    alert('✅ ' + data.message + '\nInvoice: ' + data.invoice_number);
                } else {
                    alert('✅ ' + data.message);
                }
                // Reload page to reflect changes
                window.location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                checkInBtn.innerHTML = originalText;
                checkInBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan sistem: ' + error.message);
            checkInBtn.innerHTML = originalText;
            checkInBtn.disabled = false;
        });
    }
}

function doCheckOut() {
    const modal = document.getElementById('bookingDetailsModal');
    const bookingId = modal.dataset.bookingId;
    const guestName = document.getElementById('detailGuestName').textContent;
    const roomNumber = document.getElementById('detailRoomNumber').textContent;
    
    if (confirm(`Check-out ${guestName} dari Room ${roomNumber} sekarang?`)) {
        // Show loading state
        const checkOutBtn = document.getElementById('btnCheckOut');
        const originalText = checkOutBtn.innerHTML;
        checkOutBtn.innerHTML = '<span>⏳</span><span>Processing...</span>';
        checkOutBtn.disabled = true;
        
        // Call check-out API
        fetch('<?php echo BASE_URL; ?>/api/checkout-guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include', // Important: Send session cookies
            body: 'booking_id=' + bookingId
        })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server mengembalikan response non-JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                // Reload page to reflect changes
                window.location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                checkOutBtn.innerHTML = originalText;
                checkOutBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan sistem: ' + error.message);
            checkOutBtn.innerHTML = originalText;
            checkOutBtn.disabled = false;
        });
    }
}

function doMoveRoom() {
    const modal = document.getElementById('bookingDetailsModal');
    const bookingId = modal.dataset.bookingId;
    
    // TODO: Implement move room modal
    alert('Move room feature coming soon for booking #' + bookingId);
}

window.doPayment = function doPayment() {
    openBookingPaymentModal();
}

window.quickViewCheckIn = function quickViewCheckIn() {
    if (!currentPaymentBooking) {
        alert('Booking data not found');
        return;
    }

    const booking = currentPaymentBooking;
    const guestName = booking.guest_name;
    const roomNumber = booking.room_number;
    const paymentStatus = booking.payment_status;

    if (paymentStatus !== 'paid') {
        const proceed = confirm('Pembayaran belum lunas. Lanjut check-in dan buat invoice sisa?');
        if (!proceed) {
            openBookingPaymentModal();
            return;
        }
    }

    if (confirm(`Check-in ${guestName} ke Room ${roomNumber} sekarang?`)) {
        const createInvoice = paymentStatus !== 'paid' ? 1 : 0;
        const btnCheckIn = document.querySelector('.qv-checkin-btn');
        const originalText = btnCheckIn.innerHTML;
        btnCheckIn.innerHTML = 'Processing...';
        btnCheckIn.disabled = true;

        fetch('<?php echo BASE_URL; ?>/api/checkin-guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include',
            body: 'booking_id=' + booking.id + '&create_invoice=' + createInvoice
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.invoice_number) {
                    alert('✅ ' + data.message + '\nInvoice: ' + data.invoice_number);
                } else {
                    alert('✅ ' + data.message);
                }
                closeBookingQuickView();
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btnCheckIn.innerHTML = originalText;
                btnCheckIn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan: ' + error.message);
            btnCheckIn.innerHTML = originalText;
            btnCheckIn.disabled = false;
        });
    }
}

window.quickViewMoveRoom = function quickViewMoveRoom() {
    if (!currentPaymentBooking) {
        alert('Booking data not found');
        return;
    }

    const booking = currentPaymentBooking;
    alert('Move room feature untuk booking ' + booking.booking_code + ' segera hadir!');
    // TODO: Implement move room modal
}

window.openBookingPaymentModal = function openBookingPaymentModal() {
    if (!currentPaymentBooking) {
        alert('Booking data tidak ditemukan. Silakan buka detail booking lagi.');
        return;
    }

    const total = parseFloat(currentPaymentBooking.final_price) || 0;
    const paid = parseFloat(currentPaymentBooking.paid_amount) || 0;
    const remaining = Math.max(0, total - paid);

    document.getElementById('paymentTotal').textContent = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('paymentPaid').textContent = 'Rp ' + paid.toLocaleString('id-ID');
    document.getElementById('paymentRemaining').textContent = 'Rp ' + remaining.toLocaleString('id-ID');
    document.getElementById('paymentAmount').value = remaining;
    document.getElementById('paymentModalSubtitle').textContent = currentPaymentBooking.booking_code + ' • ' + (currentPaymentBooking.guest_name || '-');

    const methodInput = document.getElementById('paymentMethodPay');
    const methodButtons = document.querySelectorAll('#bookingPaymentModal .payment-method-btn');
    methodButtons.forEach(btn => btn.classList.remove('active'));
    const defaultBtn = document.querySelector('#bookingPaymentModal .payment-method-btn[data-value="cash"]');
    if (defaultBtn) defaultBtn.classList.add('active');
    if (methodInput) methodInput.value = 'cash';

    const modal = document.getElementById('bookingPaymentModal');
    modal.classList.add('active');
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.zIndex = '99999';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
}

window.closeBookingPaymentModal = function closeBookingPaymentModal() {
    const modal = document.getElementById('bookingPaymentModal');
    modal.classList.remove('active');
    modal.style.display = '';
    modal.style.position = '';
    modal.style.zIndex = '';
}

window.submitBookingPayment = function submitBookingPayment() {
    if (!currentPaymentBooking) return;

    const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const method = document.getElementById('paymentMethodPay').value || 'cash';

    if (amount <= 0) {
        alert('Jumlah bayar harus lebih dari 0');
        return;
    }

    fetch('<?php echo BASE_URL; ?>/api/add-booking-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        credentials: 'include',
        body: 'booking_id=' + encodeURIComponent(currentPaymentBooking.id) +
              '&amount=' + encodeURIComponent(amount) +
              '&payment_method=' + encodeURIComponent(method)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeBookingPaymentModal();
            // Refresh booking details
            return fetch('../../api/get-booking-details.php?id=' + currentPaymentBooking.id)
                .then(res => res.json())
                .then(updated => {
                    if (updated.success) {
                        showBookingQuickView(updated.booking);
                        const detailsModal = document.getElementById('bookingDetailsModal');
                        if (detailsModal && detailsModal.classList.contains('active')) {
                            showBookingDetailsModal(updated.booking);
                        }
                    }
                });
        }
        alert('Error: ' + data.message);
    })
    .catch(err => {
        console.error(err);
        alert('Gagal menyimpan pembayaran');
    });
}

window.changeDate = function changeDate() {
    const dateInput = document.getElementById('dateInput');
    if (!dateInput) return;
    window.location.search = '?start=' + dateInput.value;
}

const shiftCalendarDays = (days) => {
    const dateInput = document.getElementById('dateInput');
    if (!dateInput) return;
    const currentDate = new Date(dateInput.value);
    currentDate.setDate(currentDate.getDate() + days);
    dateInput.value = currentDate.toISOString().split('T')[0];
    changeDate();
};

window.prevMonth = function prevMonth() {
    shiftCalendarDays(-30);
}

window.nextMonth = function nextMonth() {
    shiftCalendarDays(30);
}
// ========================================
// COMMENTED OUT - Reservation Form Code
// Will rebuild from scratch
// ========================================
/*
// Store form pre-fill data
let formPreFillData = {
    date: null,
    roomId: null
};

// Expose functions to global scope for onclick handlers
window.showReservationForm = function showReservationForm() {
    // Close any other open modals first
    const bookingPaymentModal = document.getElementById('bookingPaymentModal');
    if (bookingPaymentModal) {
        bookingPaymentModal.classList.remove('active');
    }
    const bookingDetailsModal = document.getElementById('bookingDetailsModal');
    if (bookingDetailsModal) {
        bookingDetailsModal.classList.remove('active');
    }
    const bookingQuickView = document.getElementById('bookingQuickView');
    if (bookingQuickView) {
        bookingQuickView.classList.remove('active');
    }
    
    // Use pre-fill data if available
    const savedDate = formPreFillData.date;
    const savedRoomId = formPreFillData.roomId;
    
    console.log('📅 Opening reservation form with date:', savedDate, 'room:', savedRoomId);
    
    const modal = document.getElementById('reservationModal');
    
    // FIRST: Reset form completely to avoid stale data
    document.getElementById('reservationForm').reset();
    const guestName = document.getElementById('guestName');
    if (guestName) guestName.value = '';
    
    // Pre-fill form with SAVED data (not selectedDate which is now null)
    if (savedDate) {
        console.log('✅ Setting check-in date:', savedDate);
        
        // Set check-in date from selected date
        const checkInInput = document.getElementById('checkInDate');
        checkInInput.value = savedDate;
        
        // Set check-out date to next day
        const checkOut = new Date(savedDate);
        checkOut.setDate(checkOut.getDate() + 1);
        const checkOutDate = checkOut.toISOString().split('T')[0];
        
        const checkOutInput = document.getElementById('checkOutDate');
        checkOutInput.value = checkOutDate;
        checkOutInput.min = checkOutDate;
        
        console.log('Check-in:', checkInInput.value, 'Check-out:', checkOutInput.value);
    } else {
        console.error('❌ No savedDate available!');
    }
    
    if (savedRoomId) {
        console.log('✅ Setting room:', savedRoomId);
        document.getElementById('roomSelect').value = savedRoomId;
        // Trigger change to update price
        document.getElementById('roomSelect').dispatchEvent(new Event('change'));
    }
    
    // Calculate initial nights
    calculateNights();
    
    // Show modal AFTER setting all values
    modal.classList.add('active');
}

window.closeReservationModal = function closeReservationModal() {
    const modal = document.getElementById('reservationModal');
    modal.classList.remove('active');
    
    // Also close other modals if open
    const bookingPaymentModal = document.getElementById('bookingPaymentModal');
    if (bookingPaymentModal) {
        bookingPaymentModal.classList.remove('active');
    }
    const bookingDetailsModal = document.getElementById('bookingDetailsModal');
    if (bookingDetailsModal) {
        bookingDetailsModal.classList.remove('active');
    }
    const bookingQuickView = document.getElementById('bookingQuickView');
    if (bookingQuickView) {
        bookingQuickView.classList.remove('active');
    }
    
    // Clear pre-fill data
    formPreFillData.date = null;
    formPreFillData.roomId = null;
    
    // Remove inline styles to allow CSS to take over
    modal.style.display = '';
    modal.style.position = '';
    modal.style.top = '';
    modal.style.left = '';
    modal.style.right = '';
    modal.style.bottom = '';
    modal.style.zIndex = '';
    modal.style.backgroundColor = '';
    modal.style.alignItems = '';
    modal.style.justifyContent = '';
    
    // Completely reset form
    document.getElementById('reservationForm').reset();
    
    // Explicitly clear guest information fields
    const guestName = document.getElementById('guestName');
    const guestPhone = document.getElementById('guestPhone');
    const guestEmail = document.getElementById('guestEmail');
    const guestId = document.getElementById('guestId');
    if (guestName) guestName.value = '';
    if (guestPhone) guestPhone.value = '';
    if (guestEmail) guestEmail.value = '';
    if (guestId) guestId.value = '';
    
    // Reset dates
    const checkInDate = document.getElementById('checkInDate');
    const checkOutDate = document.getElementById('checkOutDate');
    if (checkInDate) checkInDate.value = '';
    if (checkOutDate) checkOutDate.value = '';
    
    // Reset room and price
    const roomSelect = document.getElementById('roomSelect');
    const roomPrice = document.getElementById('roomPrice');
    if (roomSelect) roomSelect.value = '';
    if (roomPrice) roomPrice.value = '';
    
    // Reset discount
    const discount = document.getElementById('discount');
    if (discount) discount.value = '0';
    
    // Reset special request
    const specialRequest = document.getElementById('specialRequest');
    if (specialRequest) specialRequest.value = '';
    
    // Reset DP/Paid Amount
    const paidAmount = document.getElementById('paidAmount');
    if (paidAmount) {
        paidAmount.value = '0';
        delete paidAmount.dataset.dpPercent;
    }
    
    // Reset payment method to Cash
    const paymentMethod = document.getElementById('paymentMethod');
    if (paymentMethod) paymentMethod.value = 'cash';
    
    // Reset payment status to unpaid
    const paymentStatus = document.getElementById('paymentStatus');
    if (paymentStatus) paymentStatus.value = 'unpaid';
    
    // Reset button states
    const paymentButtons = document.querySelectorAll('#reservationModal .payment-method-btn');
    paymentButtons.forEach((btn, idx) => {
        if (idx === 1) {  // Cash is second button (index 1)
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    const dpButtons = document.querySelectorAll('.dp-percent-btn');
    dpButtons.forEach(btn => btn.classList.remove('active'));
    
    // Reset Total Pax to default values
    const adultInput = document.getElementById('adultCount');
    const childrenInput = document.getElementById('childrenCount');
    if (adultInput) adultInput.value = 1;
    if (childrenInput) childrenInput.value = 0;
    calculateTotalPax();
    
    // Reset price display
    document.getElementById('totalPrice').textContent = 'Rp 0';
    document.getElementById('discountAmount').textContent = '- Rp 0';
    document.getElementById('finalPrice').textContent = 'Rp 0';
    
    selectedDate = null;
    selectedRoom = null;
}

window.calculateNights = function calculateNights() {
    const checkIn = document.getElementById('checkInDate').value;
    const checkOut = document.getElementById('checkOutDate').value;
    
    if (checkIn && checkOut) {
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        
        document.getElementById('totalNights').value = nights > 0 ? nights : 0;
        calculatePrice();
    }
}

window.calculatePrice = function calculatePrice() {
    const roomPrice = parseFloat(document.getElementById('roomPrice').value) || 0;
    const nights = parseInt(document.getElementById('totalNights').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    
    const total = roomPrice * nights;
    const final = total - discount;
    
    document.getElementById('totalPrice').textContent = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('discountAmount').textContent = '- Rp ' + discount.toLocaleString('id-ID');
    document.getElementById('finalPrice').textContent = 'Rp ' + final.toLocaleString('id-ID');

    // Recalculate DP amount if a percent is selected
    const paidInput = document.getElementById('paidAmount');
    if (paidInput && paidInput.dataset.dpPercent) {
        applyDpPercent(parseFloat(paidInput.dataset.dpPercent));
    }
}

window.getFinalPriceNumber = function getFinalPriceNumber() {
    const roomPrice = parseFloat(document.getElementById('roomPrice').value) || 0;
    const nights = parseInt(document.getElementById('totalNights').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    return (roomPrice * nights) - discount;
}

window.applyDpPercent = function applyDpPercent(percent) {
    const paidInput = document.getElementById('paidAmount');
    if (!paidInput) return;

    const finalPrice = getFinalPriceNumber();
    const amount = Math.round(finalPrice * (percent / 100));
    paidInput.value = amount;
    paidInput.dataset.dpPercent = percent;

    updatePaymentStatusFromAmount();
}

window.updatePaymentStatusFromAmount = function updatePaymentStatusFromAmount() {
    const paidInput = document.getElementById('paidAmount');
    const statusSelect = document.getElementById('paymentStatus');
    if (!paidInput || !statusSelect) return;

    const paid = parseFloat(paidInput.value) || 0;
    const finalPrice = getFinalPriceNumber();

    if (paid <= 0) {
        statusSelect.value = 'unpaid';
    } else if (paid >= finalPrice) {
        statusSelect.value = 'paid';
    } else {
        statusSelect.value = 'partial';
    }
}

// Calculate Total Pax (Adult + Children)
window.calculateTotalPax = function calculateTotalPax() {
    const adults = parseInt(document.getElementById('adultCount').value) || 0;
    const children = parseInt(document.getElementById('childrenCount').value) || 0;
    const totalPax = adults + children;
    
    document.getElementById('totalPax').value = totalPax;
}

window.submitReservation = function submitReservation(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('reservationForm'));
    
    // Add calculated values
    const roomPrice = parseFloat(document.getElementById('roomPrice').value) || 0;
    const nights = parseInt(document.getElementById('totalNights').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    
    formData.append('total_price', roomPrice * nights);
    formData.append('final_price', (roomPrice * nights) - discount);
    formData.append('action', 'create_reservation');
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Saving...';
    submitBtn.disabled = true;
    
    // Submit via AJAX
    fetch('<?php echo BASE_URL; ?>/api/create-reservation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error(text || 'Server returned non-JSON response');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Reservation created successfully!\nBooking Code: ' + data.booking_code);
            closeReservationModal();
            location.reload(); // Reload to show new booking
        } else {
            alert('Error: ' + (data.message || 'Failed to create reservation'));
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + (error.message || 'Connection error. Please try again.'));
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}


// Setup form event listeners (removed click-outside-to-close functionality)
document.addEventListener('DOMContentLoaded', function() {
    
    // Form event listeners
    const checkInDate = document.getElementById('checkInDate');
    const checkOutDate = document.getElementById('checkOutDate');
    const roomSelect = document.getElementById('roomSelect');
    const roomPriceInput = document.getElementById('roomPrice');
    const discountInput = document.getElementById('discount');
    const adultInput = document.getElementById('adultCount');
    const childrenInput = document.getElementById('childrenCount');
    const paidAmountInput = document.getElementById('paidAmount');
    const paymentMethodInput = document.getElementById('paymentMethod');
    
    if (checkInDate) checkInDate.addEventListener('change', calculateNights);
    if (checkOutDate) checkOutDate.addEventListener('change', calculateNights);
    if (roomPriceInput) roomPriceInput.addEventListener('input', calculatePrice);
    if (discountInput) discountInput.addEventListener('input', calculatePrice);
    if (adultInput) adultInput.addEventListener('change', calculateTotalPax);
    if (childrenInput) childrenInput.addEventListener('change', calculateTotalPax);
    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', function() {
            const dpButtons = document.querySelectorAll('.dp-percent-btn');
            dpButtons.forEach(btn => btn.classList.remove('active'));
            delete paidAmountInput.dataset.dpPercent;
            updatePaymentStatusFromAmount();
        });
    }

    const paymentButtons = document.querySelectorAll('#reservationModal .payment-method-btn');
    paymentButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            paymentButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            if (paymentMethodInput) {
                paymentMethodInput.value = this.dataset.value;
            }
        });
    });

    const payModalButtons = document.querySelectorAll('#bookingPaymentModal .payment-method-btn');
    payModalButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            payModalButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const payMethodInput = document.getElementById('paymentMethodPay');
            if (payMethodInput) {
                payMethodInput.value = this.dataset.value;
            }
        });
    });

    const dpButtons = document.querySelectorAll('.dp-percent-btn');
    dpButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            dpButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const percent = parseFloat(this.dataset.percent);
            applyDpPercent(percent);
        });
    });
    
    if (roomSelect) {
        roomSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('roomPrice').value = price;
                calculatePrice();
            }
        });
    }

    const prevBtn = document.getElementById('prevMonthBtn');
    const nextBtn = document.getElementById('nextMonthBtn');
    if (prevBtn) prevBtn.addEventListener('click', window.prevMonth);
    if (nextBtn) nextBtn.addEventListener('click', window.nextMonth);
*/
    
    // ========================================
    // DRAG SCROLL WITH FORCED SCROLLING
    // ========================================
    const scroller = document.querySelector('.calendar-scroll-container');
    const wrapper = document.querySelector('.calendar-wrapper');
    const grid = document.querySelector('.calendar-grid');
    
    if (!scroller || !wrapper || !grid) {
        console.error('❌ Elements not found');
        return;
    }
    
    // Force scroller to be scrollable
    scroller.style.overflowX = 'auto';
    scroller.style.overflowY = 'hidden';
    grid.style.width = 'fit-content';
    grid.style.minWidth = '100%';
    
    console.log('✅ Drag initialized');
    console.log('Scroller:', scroller.clientWidth, 'scrollWidth:', scroller.scrollWidth, 'Grid:', grid.offsetWidth, 'Scrollable:', grid.offsetWidth > scroller.clientWidth);
    
    let isDown = false;
    let startX;
    let scrollLeft;
    let moved = false;
    let dragStartTarget = null;

    const startDrag = (clientX, target) => {
        isDown = true;
        moved = false;
        startX = clientX;
        scrollLeft = scroller.scrollLeft;
        dragStartTarget = target;
        wrapper.classList.add('dragging');
        document.body.classList.add('calendar-dragging');
    };

    const moveDrag = (clientX) => {
        if (!isDown) return;

        const walk = (clientX - startX);
        const newScroll = scrollLeft - walk;

        scroller.scrollLeft = newScroll;

        if (Math.abs(walk) > 5) {
            moved = true;
        }
    };

    const endDrag = () => {
        if (!isDown) return;

        isDown = false;
        wrapper.classList.remove('dragging');
        document.body.classList.remove('calendar-dragging');

        const columnWidth = 60;
        const currentScroll = scroller.scrollLeft;
        const columnIndex = Math.round(currentScroll / columnWidth);
        const targetScroll = columnIndex * columnWidth;

        scroller.scrollTo({
            left: targetScroll,
            behavior: 'smooth'
        });

        setTimeout(() => {
            moved = false;
            dragStartTarget = null;
        }, 200);
    };

    scroller.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        if (e.target.closest('.booking-bar')) return;
        e.preventDefault();
        startDrag(e.clientX, e.target);
    }, { capture: true });

    window.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        moveDrag(e.clientX);
    }, { capture: true });

    window.addEventListener('mouseup', (e) => {
        if (!isDown) return;
        e.preventDefault();
        endDrag();
    }, { capture: true });

    scroller.addEventListener('mouseleave', endDrag);

    scroller.addEventListener('touchstart', (e) => {
        if (e.target.closest('.booking-bar')) return;
        const touch = e.touches[0];
        startDrag(touch.clientX, e.target);
    }, { passive: true, capture: true });

    scroller.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        const touch = e.touches[0];
        moveDrag(touch.clientX);
    }, { passive: true });

    scroller.addEventListener('touchend', endDrag, { passive: true });
    
    /*
    // COMMENTED OUT - Reservation form click handler
    // Will rebuild this properly later
    wrapper.addEventListener('click', (e) => {
        // If dragging was detected, block the click
        if (moved) {
            console.log('🚫 BLOCKED - Drag detected, moved:', moved);
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        
        // Check if clicked on booking bar - don't open reservation form
        if (e.target.closest('.booking-bar')) {
            console.log('📋 Booking bar clicked - ignoring cell click');
            return;
        }
        
        const cell = e.target.closest('.grid-date-cell');
        if (!cell) {
            console.log('❌ No cell found');
            return;
        }
        
        // Store selected date and room for form pre-fill
        const date = cell.dataset.date;
        const roomId = cell.dataset.roomId;
        const roomNumber = cell.dataset.roomNumber;
        
        console.log('✅ Cell clicked successfully!');
        console.log('📅 Date:', date, 'Room:', roomNumber, 'ID:', roomId);
        
        // Save to pre-fill data
        formPreFillData.date = date;
        formPreFillData.roomId = roomId;
        
        // Close any open modals first to prevent overlap
        const bookingPaymentModal = document.getElementById('bookingPaymentModal');
        if (bookingPaymentModal) {
            bookingPaymentModal.classList.remove('active');
        }
        const bookingDetailsModal = document.getElementById('bookingDetailsModal');
        if (bookingDetailsModal) {
            bookingDetailsModal.classList.remove('active');
        }
        const bookingQuickView = document.getElementById('bookingQuickView');
        if (bookingQuickView) {
            bookingQuickView.classList.remove('active');
        }
        
        // Open reservation form
        showReservationForm();
    });
    */
});
</script>

<!-- COMMENTED OUT SECTION: Reservation Form Modal - Will rebuild from scratch
<div id="reservationModal-hidden" class="modal-overlay">
    <div class="modal-content modal-content-large">
        <button class="modal-close" onclick="closeReservationModal()">×</button>
        
        <div class="modal-header">
            <h2>New Reservation</h2>
            <p>Fill in the guest and booking details</p>
        </div>
        
        <form id="reservationForm" onsubmit="submitReservation(event)">
            ... reservation form html was here ...
        </form>
    </div>
</div>
END COMMENTED OUT SECTION -->




<div id="bookingDetailsModal" class="modal-overlay" style="display: none !important;">
    <div class="modal-content modal-content-medium"></div>
</div>

<!-- Quick View Modal - Compact & Elegant -->
<div id="bookingQuickView" class="modal-overlay">
    <div class="quick-view-popup">
        <button class="quick-view-close" onclick="closeBookingQuickView()">×</button>
        <div id="qv-content">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="bookingPaymentModal" class="modal-overlay">
    <div class="payment-modal">
        <button class="payment-modal-close" onclick="closeBookingPaymentModal()">×</button>
        <div class="payment-modal-header">
            <h3>Payment</h3>
            <p id="paymentModalSubtitle">Pembayaran booking</p>
        </div>
        <div class="payment-modal-body">
            <div class="payment-info">
                <div><span>Total:</span> <strong id="paymentTotal">Rp 0</strong></div>
                <div><span>Sudah Bayar:</span> <strong id="paymentPaid">Rp 0</strong></div>
                <div><span>Sisa:</span> <strong id="paymentRemaining">Rp 0</strong></div>
            </div>
            <div class="form-group">
                <label>Metode Pembayaran</label>
                <input type="hidden" id="paymentMethodPay" value="cash">
                <div class="payment-method-group">
                    <button type="button" class="payment-method-btn" data-value="ota">OTA</button>
                    <button type="button" class="payment-method-btn active" data-value="cash">Cash</button>
                    <button type="button" class="payment-method-btn" data-value="transfer">Transfer</button>
                    <button type="button" class="payment-method-btn" data-value="qris">QR</button>
                </div>
            </div>
            <div class="form-group">
                <label>Jumlah Bayar (Rp)</label>
                <input type="number" id="paymentAmount" min="0" value="0">
            </div>
            <div class="payment-modal-actions">
                <button type="button" class="btn-secondary" onclick="closeBookingPaymentModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="submitBookingPayment()">Pay</button>
            </div>
        </div>
    </div>
</div>

<style>
/* FORCE HIDE RESERVATION MODAL - COMMENTED OUT SECTION */
#reservationModal {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
}

/* FORCE HIDE PAYMENT MODAL - NOT NEEDED YET */
#bookingPaymentModal {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
}

/* FORCE HIDE QUICK VIEW MODAL - NOT NEEDED YET */
#bookingQuickView {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
}

/* Quick View Popup - Simple & Elegant */
.quick-view-popup {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 380px;
    padding: 1.25rem;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(99, 102, 241, 0.1);
    animation: quickViewIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes quickViewIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.quick-view-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: rgba(239, 68, 68, 0.1);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    line-height: 1;
    font-weight: 300;
}

.quick-view-close:hover {
    background: #ef4444;
    color: white;
    transform: rotate(90deg);
}

.qv-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.qv-btn {
    border: 1px solid #ddd;
    background: white;
    color: #333;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.qv-btn:hover {
    background: #f3f4f6;
    border-color: #999;
}

.qv-pay-btn {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.qv-pay-btn:hover {
    background: #059669;
    border-color: #059669;
}

.qv-checkin-btn {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.qv-checkin-btn:hover {
    background: #2563eb;
    border-color: #2563eb;
}

.qv-move-btn {
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
}

.qv-move-btn:hover {
    background: #7c3aed;
    border-color: #7c3aed;
}

.payment-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 420px;
    padding: 1.25rem;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(99, 102, 241, 0.1);
}

.payment-modal-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: rgba(239, 68, 68, 0.1);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    line-height: 1;
    font-weight: 300;
}

.payment-modal-header {
    text-align: center;
    margin-bottom: 0.75rem;
}

.payment-modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}

.payment-modal-header p {
    margin: 0.25rem 0 0;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.payment-info {
    background: rgba(99, 102, 241, 0.05);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.8rem;
    line-height: 1.5;
    margin-bottom: 0.75rem;
}

.payment-info span {
    color: var(--text-secondary);
}

.payment-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

/* Scrollbar styling for popup */
.quick-view-popup::-webkit-scrollbar {
    width: 6px;
}

.quick-view-popup::-webkit-scrollbar-track {
    background: rgba(99, 102, 241, 0.05);
    border-radius: 3px;
}

.quick-view-popup::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.3);
    border-radius: 3px;
}

.quick-view-popup::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.5);
}
</style>

<?php include '../../includes/footer.php'; ?>


