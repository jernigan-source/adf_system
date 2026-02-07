<?php
echo "=== FIX CATEGORY ENUM IN ALL DATABASES ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all databases
    $databases = ['adf_benscafe', 'adf_narayana_hotel'];
    
    foreach ($databases as $dbName) {
        echo "Processing: $dbName\n";
        $pdo->exec("USE {$dbName}");
        
        // Check if breakfast_menus exists
        $result = $pdo->query("SHOW TABLES LIKE 'breakfast_menus'");
        if ($result->rowCount() == 0) {
            echo "  ℹ️  Table breakfast_menus tidak ada, skip\n\n";
            continue;
        }
        
        // Check current ENUM
        $result = $pdo->query("SHOW COLUMNS FROM breakfast_menus WHERE Field = 'category'");
        $col = $result->fetch(PDO::FETCH_ASSOC);
        echo "  Current: " . $col['Type'] . "\n";
        
        // Update ENUM
        $sql = "ALTER TABLE breakfast_menus MODIFY COLUMN category ENUM('western', 'indonesian', 'japanese', 'drinks') NOT NULL";
        $pdo->exec($sql);
        
        // Verify
        $result = $pdo->query("SHOW COLUMNS FROM breakfast_menus WHERE Field = 'category'");
        $col = $result->fetch(PDO::FETCH_ASSOC);
        echo "  Updated: " . $col['Type'] . "\n";
        echo "  ✅ Success!\n\n";
    }
    
    echo "All databases updated!\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
