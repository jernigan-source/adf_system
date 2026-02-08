<?php
/**
 * FORCE SHOW ERRORS - Debug Businesses Page
 */

// Force show ALL errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start output
echo "<!DOCTYPE html>
<html>
<head><title>DEBUG</title><style>pre { background: #f0f0f0; padding: 10px; }</style></head>
<body>";

echo "<h1>BUSINESSES PAGE DEBUG</h1>";
echo "<pre>";

// 1. Check config
echo "=== 1. CONFIG ===\n";
if (file_exists('config/config.php')) {
    echo "✓ config/config.php exists\n";
    require_once 'config/config.php';
    echo "✓ config/config.php loaded\n";
    echo "  DB_HOST: " . DB_HOST . "\n";
    echo "  DB_NAME: " . DB_NAME . "\n";
} else {
    echo "✗ config/config.php NOT FOUND!\n";
    die();
}

// 2. Check dev_auth
echo "\n=== 2. DEV AUTH ===\n";
if (file_exists('developer/includes/dev_auth.php')) {
    echo "✓ developer/includes/dev_auth.php exists\n";
    require_once 'developer/includes/dev_auth.php';
    echo "✓ dev_auth.php loaded\n";
    
    try {
        $auth = new DevAuth();
        echo "✓ DevAuth class instantiated\n";
        
        $user = $auth->getCurrentUser();
        if ($user) {
            echo "✓ User logged in: " . $user['username'] . "\n";
        } else {
            echo "⚠ No user logged in\n";
        }
    } catch (Exception $e) {
        echo "✗ DevAuth Error: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . "\n";
        echo "  Line: " . $e->getLine() . "\n";
    }
} else {
    echo "✗ developer/includes/dev_auth.php NOT FOUND!\n";
}

// 3. Check DatabaseManager
echo "\n=== 3. DATABASE MANAGER ===\n";
if (file_exists('includes/DatabaseManager.php')) {
    echo "✓ includes/DatabaseManager.php exists\n";
    try {
        require_once 'includes/DatabaseManager.php';
        echo "✓ DatabaseManager.php loaded\n";
    } catch (Exception $e) {
        echo "✗ Error loading DatabaseManager: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ includes/DatabaseManager.php NOT FOUND!\n";
}

// 4. Database connection
echo "\n=== 4. DATABASE CONNECTION ===\n";
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    echo "✓ Connected to " . DB_NAME . "\n";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tables count: " . count($tables) . "\n";
    
    // Check businesses
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM businesses");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Businesses: " . $row['cnt'] . " records\n";
    
} catch (Exception $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
}

// 5. Try include businesses.php
echo "\n=== 5. LOAD BUSINESSES.PHP ===\n";
try {
    ob_start();
    
    // Set minimal vars that businesses.php needs
    $_GET['action'] = $_GET['action'] ?? 'list';
    $_GET['id'] = $_GET['id'] ?? null;
    
    // Include the file
    include 'developer/businesses.php';
    
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "✓ businesses.php generated output\n";
        echo "  Output length: " . strlen($output) . " bytes\n";
        echo "\n--- OUTPUT START ---\n";
        echo substr($output, 0, 500) . (strlen($output) > 500 ? "\n... (truncated)" : "");
        echo "\n--- OUTPUT END ---\n";
    } else {
        echo "✗ businesses.php produced NO output\n";
    }
    
} catch (Throwable $e) {
    echo "✗ Error including businesses.php:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
    echo "  Code: " . $e->getCode() . "\n";
    
    // Show trace
    echo "\n--- TRACE ---\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "</body></html>";
?>
