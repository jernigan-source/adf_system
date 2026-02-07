<?php
define('APP_ACCESS', true);
require_once 'config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

echo "<h2>Business & Database Check</h2>";
echo "<pre>";

echo "=== ACTIVE BUSINESS ===\n";
echo "Business ID: " . ($_SESSION['active_business_id'] ?? 'NOT SET') . "\n";
echo "Constant ACTIVE_BUSINESS_ID: " . (defined('ACTIVE_BUSINESS_ID') ? ACTIVE_BUSINESS_ID : 'NOT DEFINED') . "\n";
echo "Database Name (DB_NAME): " . DB_NAME . "\n\n";

echo "=== CHECK TABLES IN DATABASE ===\n";
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

$tables = ['investors', 'projects', 'user_permissions'];
foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->fetch();
    echo "Table '$table': " . ($exists ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
}

echo "\n=== SOLUSI ===\n";
if (($_SESSION['active_business_id'] ?? '') !== 'narayana-hotel') {
    echo "⚠️ Anda sedang di business: " . ($_SESSION['active_business_id'] ?? 'unknown') . "\n";
    echo "Menu Investor & Project HANYA ada di Narayana Hotel!\n\n";
    echo "Untuk akses Investor/Project:\n";
    echo "1. Switch business ke 'Narayana Hotel' di dropdown sidebar\n";
    echo "2. Atau menu Investor/Project harus di-hide jika bukan di Narayana Hotel\n";
}

echo "</pre>";
