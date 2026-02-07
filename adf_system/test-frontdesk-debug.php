<?php
// Test page untuk debug settings.php redirect issue
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();

echo "<h1>Debug FrontDesk Settings Issue</h1>";
echo "<pre>";

// Check 1: User logged in?
echo "✓ User Logged In: " . ($auth->isLoggedIn() ? "YES" : "NO") . "\n";

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    echo "✓ Current User: " . $user['username'] . " (Role: " . $user['role'] . ")\n";
    echo "✓ Has frontdesk permission: " . ($auth->hasPermission('frontdesk') ? "YES" : "NO") . "\n";
} else {
    echo "❌ No logged in user - settings.php will redirect to login\n";
}

// Check 2: Database connection
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "✓ Database connected\n";
    
    // Check if rooms table exists
    $result = $pdo->query("SHOW TABLES LIKE 'rooms'")->fetch();
    echo "✓ Rooms table exists: " . (is_array($result) ? "YES" : "NO") . "\n";
    
    // Check if room_types table exists
    $result = $pdo->query("SHOW TABLES LIKE 'room_types'")->fetch();
    echo "✓ Room_types table exists: " . (is_array($result) ? "YES" : "NO") . "\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<h2>Test Links</h2>";
echo "<ul>";
echo "<li><a href='modules/frontdesk/dashboard.php'>FrontDesk Dashboard</a></li>";
echo "<li><a href='modules/frontdesk/settings.php'>FrontDesk Settings</a></li>";
echo "<li><a href='modules/frontdesk/reservasi.php'>Reservasi</a></li>";
echo "<li><a href='modules/frontdesk/calendar.php'>Calendar</a></li>";
echo "</ul>";

if (!$auth->isLoggedIn()) {
    echo "<p><strong>⚠️ Not logged in. </strong><a href='login.php'>Login first</a></p>";
}
?>
