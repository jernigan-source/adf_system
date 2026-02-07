<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

echo "<h2>🔧 FrontDesk Database Setup</h2>";
echo "<pre>";

try {
    // Read the SQL file
    $sqlFile = __DIR__ . '/database/frontdesk_new.sql';
    
    if (!file_exists($sqlFile)) {
        echo "❌ SQL file not found: $sqlFile\n";
        die;
    }
    
    $sql = file_get_contents($sqlFile);
    
    if (empty($sql)) {
        echo "❌ SQL file is empty\n";
        die;
    }
    
    echo "✓ SQL file loaded (" . strlen($sql) . " bytes)\n\n";
    
    // Remove SQL comments
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);
    
    // Split by semicolon, but be more careful
    $statements = array_filter(array_map('trim', explode(';', $sql)), function($s) {
        return !empty($s);
    });
    
    echo "📝 Found " . count($statements) . " SQL statements\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✓ Statement " . ($index + 1) . " executed\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "⚠️ Statement " . ($index + 1) . " warning: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Database setup complete!\n";
    echo "   Successful: $successCount\n";
    echo "   Warnings: $errorCount\n\n";
    
    // Verify tables exist
    echo "📋 Verifying tables:\n";
    $tables = ['room_types', 'rooms', 'guests', 'bookings', 'booking_payments'];
    $allTablesOk = true;
    
    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "  ✓ Table: $table ($count records)\n";
        } catch (Exception $e) {
            echo "  ✗ Table: $table (error)\n";
            $allTablesOk = false;
        }
    }
    
    if ($allTablesOk) {
        echo "\n✅ All tables created successfully!\n";
    } else {
        echo "\n⚠️ Some tables may have issues. Check above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "</pre>";
echo "<p><a href='modules/frontdesk/settings.php'>🔄 Go to FrontDesk Settings</a></p>";
?>
