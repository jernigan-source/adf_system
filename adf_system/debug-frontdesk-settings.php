<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check tables
echo "<h2>Database Tables Check</h2>";
echo "<pre>";

// Check user_permissions table
try {
    $result = $pdo->query("SHOW TABLES LIKE 'user_permissions'")->fetch();
    echo "user_permissions table: " . (is_array($result) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
} catch (Exception $e) {
    echo "Error checking user_permissions: " . $e->getMessage() . "\n";
}

// Check rooms table
try {
    $result = $pdo->query("SHOW TABLES LIKE 'rooms'")->fetch();
    echo "rooms table: " . (is_array($result) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
} catch (Exception $e) {
    echo "Error checking rooms: " . $e->getMessage() . "\n";
}

// Check room_types table
try {
    $result = $pdo->query("SHOW TABLES LIKE 'room_types'")->fetch();
    echo "room_types table: " . (is_array($result) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
} catch (Exception $e) {
    echo "Error checking room_types: " . $e->getMessage() . "\n";
}

// Test session
if (isset($_SESSION['user_id'])) {
    echo "\nSession active for user ID: " . $_SESSION['user_id'] . "\n";
    
    $auth = new Auth();
    echo "Has frontdesk permission: " . ($auth->hasPermission('frontdesk') ? "YES ✓" : "NO ✗") . "\n";
    echo "Has cashbook permission: " . ($auth->hasPermission('cashbook') ? "YES ✓" : "NO ✗") . "\n";
} else {
    echo "\nNo session found - User not logged in\n";
    echo "To test: Login first at /login.php\n";
}

echo "</pre>";
echo "<a href='modules/frontdesk/settings.php'>Test Settings Page</a> | ";
echo "<a href='index.php'>Back to Dashboard</a>";
?>
