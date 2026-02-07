<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

session_start();
$auth = new Auth();

echo "<h2>Debug: User Permissions Check</h2>";
echo "<pre>";

if (!$auth->isLoggedIn()) {
    echo "User not logged in. Please <a href='login.php'>login</a> first.\n";
    die;
}

$user = $auth->getCurrentUser();
echo "Logged in user: " . $user['username'] . " (ID: " . $user['id'] . ", Role: " . $user['role'] . ")\n\n";

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check if user_permissions table exists
try {
    $result = $pdo->query("SHOW TABLES LIKE 'user_permissions'")->fetch();
    echo "user_permissions table: " . (is_array($result) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
} catch (Exception $e) {
    echo "Error checking table: " . $e->getMessage() . "\n";
}

// Show all permissions for this user
try {
    $stmt = $pdo->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nPermissions for user " . $user['id'] . ": " . count($perms) . " found\n";
    foreach ($perms as $perm) {
        echo "  - Permission: " . $perm['permission'] . "\n";
    }
} catch (Exception $e) {
    echo "Error fetching permissions: " . $e->getMessage() . "\n";
}

// Test specific permissions
echo "\nTesting specific permissions:\n";
echo "  frontdesk: " . ($auth->hasPermission('frontdesk') ? "✓ YES" : "✗ NO") . "\n";
echo "  dashboard: " . ($auth->hasPermission('dashboard') ? "✓ YES" : "✗ NO") . "\n";
echo "  cashbook: " . ($auth->hasPermission('cashbook') ? "✓ YES" : "✗ NO") . "\n";
echo "  settings: " . ($auth->hasPermission('settings') ? "✓ YES" : "✗ NO") . "\n";

// Show role-based fallback permissions
$rolePermissions = [
    'admin' => ['dashboard', 'cashbook', 'divisions', 'frontdesk', 'sales_invoice', 'procurement', 'users', 'reports', 'settings', 'investor', 'project'],
    'manager' => ['dashboard', 'cashbook', 'divisions', 'frontdesk', 'sales_invoice', 'procurement', 'users', 'reports', 'settings', 'investor', 'project'],
    'accountant' => ['dashboard', 'cashbook', 'reports', 'procurement', 'investor', 'project'],
    'staff' => ['dashboard', 'cashbook', 'investor', 'project']
];

$userRole = $user['role'] ?? 'staff';
echo "\nUser Role: " . $userRole . "\n";
echo "Role-based permissions (fallback):\n";
if (isset($rolePermissions[$userRole])) {
    foreach ($rolePermissions[$userRole] as $perm) {
        echo "  - " . $perm . "\n";
    }
} else {
    echo "  (Role not found in fallback, defaults to staff)\n";
}

echo "</pre>";
echo "<a href='modules/frontdesk/settings.php'>Try Settings Page</a>";
?>
