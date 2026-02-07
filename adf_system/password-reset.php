<?php
/**
 * Password Reset Tool - Emergency Access
 * Access at: http://localhost:8080/adf_system/password-reset.php
 */

define("APP_ACCESS", true);
require_once "config/config.php";
require_once "config/database.php";

$db = Database::getInstance();
$message = "";
$error = "";

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize($_POST["username"] ?? "");
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    
    if ($username && $newPassword && $confirmPassword) {
        if ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match!";
        } else if (strlen($newPassword) < 4) {
            $error = "Password must be at least 4 characters!";
        } else {
            // Hash password (MD5 for compatibility with existing system)
            $passwordHash = md5($newPassword);
            
            try {
                $result = $db->update("users", ["password" => $passwordHash], "username = ?", [$username]);
                $message = " Password reset successfully for user: <strong>$username</strong>";
            } catch (Exception $e) {
                $error = "Error updating password: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please fill all fields!";
    }
}

// Get all users
$users = [];
try {
    $users = $db->fetchAll("SELECT id, username, role FROM users ORDER BY username");
} catch (Exception $e) {
    $error = "Could not fetch users: " . $e->getMessage();
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success { color: #4caf50; padding: 10px; background: #e8f5e9; border-radius: 4px; margin: 10px 0; }
        .error { color: #f44336; padding: 10px; background: #ffebee; border-radius: 4px; margin: 10px 0; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0b7dda; }
        .user-list { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .user-list h3 { margin-top: 0; }
        .user-item { padding: 8px; background: white; margin: 5px 0; border-radius: 3px; border-left: 3px solid #2196F3; }
        .user-item strong { color: #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1> Password Reset Tool</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <select id="username" name="username" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['username']; ?>">
                            <?php echo $user['username']; ?> (<?php echo $user['role']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit">Reset Password</button>
        </form>
        
        <div class="user-list">
            <h3>Current Users:</h3>
            <?php foreach ($users as $user): ?>
                <div class="user-item">
                    <strong><?php echo $user['username']; ?></strong> - Role: <?php echo $user['role']; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
        <p style="font-size: 12px; color: #999;">
            <strong>Default Passwords:</strong><br>
            admin  admin<br>
            manager  manager<br>
            cashier  cashier
        </p>
    </div>
</body>
</html>
