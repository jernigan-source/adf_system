<?php
/**
 * UPDATE ALL BREAKFAST MENU TABLES
 * Add is_free column to all databases
 */

$databases = [
    'adf_benscafe',
    'adf_eat_meet', 
    'adf_furniture',
    'adf_karimunjawa',
    'adf_narayana_hotel',
    'adf_pabrik_kapal',
    'adf_system',
    'narayana_benscafe',
    'narayana_db',
    'narayana_eat_meet',
    'narayana_furniture',
    'narayana_hotel',
    'narayana_karimunjawa',
    'narayana_pabrikkapal'
];

try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔧 Updating breakfast_menus in all databases...\n\n";
    
    foreach ($databases as $dbName) {
        echo "Checking {$dbName}...\n";
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbName}'");
        if (!$stmt->fetch()) {
            echo "  ⚠️  Database tidak ada, skip\n\n";
            continue;
        }
        
        $pdo->exec("USE {$dbName}");
        
        // Check if breakfast_menus table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'breakfast_menus'");
        if (!$stmt->fetch()) {
            echo "  ℹ️  Table breakfast_menus tidak ada, skip\n\n";
            continue;
        }
        
        // Check if is_free column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM breakfast_menus LIKE 'is_free'");
        if ($stmt->fetch()) {
            echo "  ✅ Kolom is_free sudah ada\n\n";
            continue;
        }
        
        // Add is_free column
        try {
            $pdo->exec("ALTER TABLE breakfast_menus 
                        ADD COLUMN is_free BOOLEAN DEFAULT TRUE 
                        COMMENT 'TRUE = Free breakfast, FALSE = Extra/Paid' 
                        AFTER price");
            echo "  ✅ Kolom is_free berhasil ditambahkan!\n";
            
            // Set existing items
            $updated1 = $pdo->exec("UPDATE breakfast_menus SET is_free = TRUE WHERE price = 0");
            echo "  ✅ Set {$updated1} items dengan price=0 sebagai FREE\n";
            
            $updated2 = $pdo->exec("UPDATE breakfast_menus SET is_free = FALSE WHERE price > 0");
            echo "  ✅ Set {$updated2} items dengan price>0 sebagai PAID\n\n";
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "\n✅ SELESAI! Semua database sudah diupdate.\n";
    echo "Silakan test add breakfast menu sekarang!\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
