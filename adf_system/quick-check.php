<?php
define('APP_ACCESS', true);
require_once 'config/config.php';

// Start session with correct name
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

echo "<h2>QUICK SESSION CHECK</h2>";
echo "<pre>";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n\n";

echo "=== USER DATA IN SESSION ===\n";
$sessionKeys = ['user_id', 'username', 'role', 'logged_in', 'active_business_id'];
foreach ($sessionKeys as $key) {
    $value = $_SESSION[$key] ?? 'NOT SET';
    echo "$key: $value\n";
}

if (!isset($_SESSION['user_id'])) {
    echo "\n❌ USER NOT LOGGED IN!\n";
    echo "Harus login dulu: http://localhost:8081/adf_system/login.php\n";
} else {
    echo "\n✅ USER LOGGED IN\n";
}

echo "</pre>";
