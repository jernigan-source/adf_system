<?php
echo "=== UPDATE CATEGORY ENUM (COMPLETE) ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $databases = ['adf_benscafe', 'adf_narayana_hotel'];
    
    foreach ($databases as $dbName) {
        echo "Processing: $dbName\n";
        $pdo->exec("USE {$dbName}");
        
        // Check if breakfast_menus exists
        $result = $pdo->query("SHOW TABLES LIKE 'breakfast_menus'");
        if ($result->rowCount() == 0) {
            echo "  ℹ️  Table not found, skip\n\n";
            continue;
        }
        
        // Update ENUM with ALL categories
        $sql = "ALTER TABLE breakfast_menus MODIFY COLUMN category 
                ENUM('western', 'indonesian', 'japanese', 'asian', 'drinks', 'beverages', 'extras') NOT NULL";
        $pdo->exec($sql);
        
        // Verify
        $result = $pdo->query("SHOW COLUMNS FROM breakfast_menus WHERE Field = 'category'");
        $col = $result->fetch(PDO::FETCH_ASSOC);
        echo "  Updated ENUM: " . $col['Type'] . "\n";
        echo "  ✅ Success!\n\n";
    }
    
    echo "All databases updated with complete ENUM!\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
