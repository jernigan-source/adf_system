<?php
define('APP_ACCESS', true);
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

echo "<h2>Debug Permission Check</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
echo "Logged in: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "<br>";

echo "<hr>";
echo "<h3>Permission Checks:</h3>";
$modules = ['dashboard', 'cashbook', 'investor', 'project', 'frontdesk', 'reports'];
foreach ($modules as $module) {
    $hasPermission = $auth->hasPermission($module);
    echo "$module: " . ($hasPermission ? '✅ YES' : '❌ NO') . "<br>";
}

echo "<hr>";
echo "<h3>Check Database Permissions:</h3>";
if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    $user_id = $_SESSION['user_id'];
    
    $permissions = $db->fetchAll("SELECT * FROM user_permissions WHERE user_id = ?", [$user_id]);
    
    if (empty($permissions)) {
        echo "❌ No permissions found in database for user $user_id<br>";
        echo "<br>Checking user role...<br>";
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    } else {
        echo "Found " . count($permissions) . " permissions:<br>";
        echo "<pre>";
        print_r($permissions);
        echo "</pre>";
    }
}
?>
