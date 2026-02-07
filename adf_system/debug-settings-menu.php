<?php
ob_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

// Simulate logged in admin
$_SESSION['user_id'] = 1;
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'admin';

$auth = new Auth();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Settings Menu</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid blue; }
        .ok { border-left-color: green; color: green; }
        .error { border-left-color: red; color: red; }
        code { background: #f0f0f0; padding: 2px 5px; }
    </style>
</head>
<body>

<h1>🔍 Debug Settings Menu Issue</h1>

<div class="box">
    <h3>1. Permission Check</h3>
    <?php
    $has_perm = $auth->hasPermission('settings');
    $class = $has_perm ? 'ok' : 'error';
    echo '<p class="' . $class . '">';
    echo 'hasPermission("settings") = ' . ($has_perm ? 'TRUE ✅' : 'FALSE ❌');
    echo '</p>';
    ?>
</div>

<div class="box">
    <h3>2. Module Enabled Check</h3>
    <?php
    $config_file = 'config/businesses/narayana-hotel.php';
    if (file_exists($config_file)) {
        $business_config = include($config_file);
        $modules = $business_config['enabled_modules'] ?? [];
        
        $settings_enabled = in_array('settings', $modules);
        $class = $settings_enabled ? 'ok' : 'error';
        
        echo '<p class="' . $class . '">';
        echo 'Settings module enabled = ' . ($settings_enabled ? 'TRUE ✅' : 'FALSE ❌');
        echo '</p>';
        
        echo '<p>Enabled modules: ' . implode(', ', $modules) . '</p>';
    } else {
        echo '<p class="error">❌ Config file not found: ' . $config_file . '</p>';
    }
    ?>
</div>

<div class="box">
    <h3>3. Header.php File Status</h3>
    <?php
    $header_file = 'includes/header.php';
    if (file_exists($header_file)) {
        $size = filesize($header_file);
        $mtime = filemtime($header_file);
        echo '<p class="ok">✅ File EXISTS</p>';
        echo '<p>Size: ' . $size . ' bytes</p>';
        echo '<p>Last modified: ' . date('Y-m-d H:i:s', $mtime) . '</p>';
        
        // Check if settings menu code is in the file
        $content = file_get_contents($header_file);
        if (strpos($content, "hasPermission('settings')") !== false) {
            echo '<p class="ok">✅ Settings permission check code found in header.php</p>';
        } else {
            echo '<p class="error">❌ Settings permission check NOT found in header.php</p>';
        }
    } else {
        echo '<p class="error">❌ File NOT found: ' . $header_file . '</p>';
    }
    ?>
</div>

<div class="box">
    <h3>4. Database Connection</h3>
    <?php
    try {
        $conn = Database::getInstance()->getConnection();
        echo '<p class="ok">✅ Database connected</p>';
        echo '<p>Database: ' . DB_NAME . '</p>';
        echo '<p>Host: ' . DB_HOST . '</p>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
    }
    ?>
</div>

<div class="box">
    <h3>5. User Session</h3>
    <?php
    echo '<p>User ID: ' . ($_SESSION['user_id'] ?? 'NOT SET') . '</p>';
    echo '<p>Role: ' . ($_SESSION['role'] ?? 'NOT SET') . '</p>';
    echo '<p>Logged in: ' . ($_SESSION['logged_in'] ? 'YES' : 'NO') . '</p>';
    ?>
</div>

</body>
</html>
<?php
ob_end_flush();
?>
