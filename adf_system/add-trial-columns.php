<?php
/**
 * Add Trial Columns to Users Table
 */

define('APP_ACCESS', true);
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// List of all business databases
$businessDatabases = [
    'adf_narayana_hotel',
    'adf_bens_cafe',
    'adf_eat_meet',
    'adf_furniture_jepara',
    'adf_pabrik_kapal',
    'adf_karimunjawa_party_boat'
];

echo "<h2>Adding Trial Columns to Users Table</h2>";
echo "<hr>";

foreach ($businessDatabases as $dbName) {
    echo "<h3>Database: $dbName</h3>";
    
    try {
        // Check if columns exist
        $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = 'users' 
                     AND TABLE_SCHEMA = ? 
                     AND COLUMN_NAME IN ('is_trial', 'trial_expires_at')";
        
        $stmt = $conn->prepare($checkSql);
        $stmt->execute([$dbName]);
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add is_trial column if it doesn't exist
        if (!in_array('is_trial', $existingColumns)) {
            $addTrialSql = "ALTER TABLE `$dbName`.`users` ADD COLUMN `is_trial` TINYINT(1) DEFAULT 0";
            $conn->exec($addTrialSql);
            echo "✅ Added column: is_trial<br>";
        } else {
            echo "✓ Column is_trial already exists<br>";
        }
        
        // Add trial_expires_at column if it doesn't exist
        if (!in_array('trial_expires_at', $existingColumns)) {
            $addExpirySql = "ALTER TABLE `$dbName`.`users` ADD COLUMN `trial_expires_at` DATETIME NULL DEFAULT NULL";
            $conn->exec($addExpirySql);
            echo "✅ Added column: trial_expires_at<br>";
        } else {
            echo "✓ Column trial_expires_at already exists<br>";
        }
        
        echo "✅ Success!<br><br>";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br><br>";
    }
}

echo "<hr>";
echo "<h2>✅ All databases updated successfully!</h2>";
echo "<p><a href='modules/settings/users.php'>Go to User Management</a></p>";
?>
