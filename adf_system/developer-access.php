<?php
/**
 * Developer Quick Access - Auto Login Bypass
 * Direct access to business without manual login
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config/config.php';

// Clear any existing session first
session_start();
session_destroy();
session_start();

// Check if developer access token provided
if (!isset($_GET['dev_access'])) {
    header('Location: login.php');
    exit;
}

$devToken = base64_decode($_GET['dev_access']);

try {
    // Verify token is a valid database name
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=adf_system", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get business info
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE database_name = ?");
    $stmt->execute([$devToken]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) {
        die("Invalid business token");
    }
    
    // Connect to business database
    $bizPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . $devToken, DB_USER, DB_PASS);
    $bizPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get admin or owner user from business
    $userStmt = $bizPdo->query("SELECT * FROM users WHERE role IN ('admin', 'owner') AND is_active = 1 ORDER BY id LIMIT 1");
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("No admin user found in business database");
    }
    
    // Map database_name to business_id for config files
    $businessIdMap = [
        'adf_benscafe' => 'bens-cafe',
        'adf_narayana_hotel' => 'narayana-hotel'
    ];
    
    $activeBusinessId = $businessIdMap[$devToken] ?? 'bens-cafe';
    
    // Set session - bypass normal login
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['business_access'] = $user['business_access'] ?? 'all';
    $_SESSION['logged_in'] = true;
    $_SESSION['business_id'] = $business['id'];
    $_SESSION['business_code'] = $business['business_code'];
    $_SESSION['business_name'] = $business['business_name'];
    $_SESSION['database_name'] = $devToken;
    $_SESSION['developer_mode'] = true;
    $_SESSION['login_time'] = time();
    
    // CRITICAL: Set active_business_id so system knows which business to load
    $_SESSION['active_business_id'] = $activeBusinessId;
    
    // Log developer access
    try {
        $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, ip_address, new_data) VALUES (?, 'developer_access', 'businesses', ?, ?)")
            ->execute([
                null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                json_encode(['business' => $business['business_name'], 'database' => $devToken, 'as_user' => $user['username']])
            ]);
    } catch (Exception $e) {}
    
    // Redirect to dashboard
    header('Location: index.php');
    exit;
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
