<?php
// DEBUG SCRIPT - Verify user_permissions data on HOSTING
ob_start();

require_once 'config/config.php';
require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug User Permissions</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #4338ca; }
        .success { border-left-color: green; color: green; }
        .error { border-left-color: red; color: red; }
        .warning { border-left-color: orange; color: orange; }
        code { background: #f0f0f0; padding: 2px 5px; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🔍 Debug User Permissions on Hosting</h1>

<?php

try {
    $conn = Database::getInstance()->getConnection();
    
    // 1. Check if table exists
    echo '<div class="box">';
    echo '<h3>1. Check user_permissions Table</h3>';
    $stmt = $conn->prepare("SHOW TABLES LIKE 'user_permissions'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo '<p class="success">✅ Table EXISTS</p>';
    } else {
        echo '<p class="error">❌ Table DOES NOT EXIST - Run setup-user-permissions.sql first!</p>';
        exit;
    }
    echo '</div>';
    
    // 2. Count total rows
    echo '<div class="box">';
    echo '<h3>2. Total Rows in user_permissions</h3>';
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_permissions");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "<p>Total: <strong>" . $count . " rows</strong></p>";
    echo '</div>';
    
    // 3. Check user_id=1 (admin)
    echo '<div class="box">';
    echo '<h3>3. Permissions for user_id = 1 (Admin)</h3>';
    $stmt = $conn->prepare("SELECT permission FROM user_permissions WHERE user_id = 1 ORDER BY permission");
    $stmt->execute();
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($perms) > 0) {
        echo '<p class="success">✅ Found ' . count($perms) . ' permissions:</p>';
        echo '<ul>';
        foreach ($perms as $p) {
            echo '<li><code>' . htmlspecialchars($p['permission']) . '</code></li>';
        }
        echo '</ul>';
        
        // Check if 'frontdesk' is there
        $has_frontdesk = false;
        foreach ($perms as $p) {
            if ($p['permission'] === 'frontdesk') {
                $has_frontdesk = true;
                break;
            }
        }
        
        if ($has_frontdesk) {
            echo '<p class="success">✅ <strong>frontdesk permission EXISTS!</strong></p>';
        } else {
            echo '<p class="error">❌ <strong>frontdesk permission MISSING!</strong></p>';
        }
    } else {
        echo '<p class="error">❌ NO PERMISSIONS FOUND for user_id=1</p>';
        echo '<p>This means the INSERT did not work!</p>';
    }
    echo '</div>';
    
    // 4. Check users table
    echo '<div class="box">';
    echo '<h3>4. Users in Database</h3>';
    $stmt = $conn->prepare("SELECT id, username, role FROM users LIMIT 10");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo '<p class="success">✅ Found ' . count($users) . ' users:</p>';
        echo '<ul>';
        foreach ($users as $u) {
            echo '<li>ID: ' . $u['id'] . ' | Username: <code>' . $u['username'] . '</code> | Role: ' . $u['role'] . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="error">❌ NO USERS FOUND</p>';
    }
    echo '</div>';
    
    // 5. Test Auth class
    echo '<div class="box">';
    echo '<h3>5. Test Permission Check (Simulate Admin Login)</h3>';
    
    $_SESSION['user_id'] = 1;
    $_SESSION['logged_in'] = true;
    $_SESSION['role'] = 'admin';
    
    require_once 'includes/auth.php';
    $auth = new Auth();
    
    $test_modules = ['frontdesk', 'dashboard', 'cashbook', 'settings'];
    echo '<ul>';
    foreach ($test_modules as $mod) {
        $result = $auth->hasPermission($mod);
        $icon = $result ? '✅' : '❌';
        $class = $result ? 'success' : 'error';
        echo '<li><span class="' . $class . '">' . $icon . ' hasPermission("' . $mod . '") = ' . ($result ? 'TRUE' : 'FALSE') . '</span></li>';
    }
    echo '</ul>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="box error">';
    echo '<h3>❌ ERROR</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

?>

</body>
</html>
<?php
ob_end_flush();
?>
