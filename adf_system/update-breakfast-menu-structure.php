<?php
/**
 * UPDATE BREAKFAST MENU STRUCTURE
 * Add is_free column and drinks category
 */

require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "🔧 Updating breakfast_menus table structure...\n\n";
    
    // 1. Add is_free column if not exists
    echo "1. Adding is_free column...\n";
    try {
        $pdo->exec("ALTER TABLE breakfast_menus 
                    ADD COLUMN is_free BOOLEAN DEFAULT TRUE 
                    COMMENT 'TRUE = Free breakfast, FALSE = Extra/Paid' 
                    AFTER price");
        echo "   ✓ is_free column added successfully\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ℹ️  is_free column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 2. Update category enum to include 'drinks'
    echo "\n2. Updating category enum to include 'drinks'...\n";
    try {
        $pdo->exec("ALTER TABLE breakfast_menus 
                    MODIFY COLUMN category ENUM('western', 'indonesian', 'asian', 'drinks', 'beverages', 'extras') 
                    DEFAULT 'western'");
        echo "   ✓ Category enum updated successfully\n";
    } catch (PDOException $e) {
        echo "   ⚠️  Error: " . $e->getMessage() . "\n";
    }
    
    // 3. Set existing items with price=0 as free breakfast
    echo "\n3. Setting items with price=0 as free breakfast...\n";
    $stmt = $pdo->exec("UPDATE breakfast_menus SET is_free = TRUE WHERE price = 0");
    echo "   ✓ Updated $stmt items to free breakfast\n";
    
    // 4. Set existing items with price>0 as paid extras
    echo "\n4. Setting items with price>0 as paid extras...\n";
    $stmt = $pdo->exec("UPDATE breakfast_menus SET is_free = FALSE WHERE price > 0");
    echo "   ✓ Updated $stmt items to paid extras\n";
    
    // 5. Add index for is_free
    echo "\n5. Adding index for is_free...\n";
    try {
        $pdo->exec("ALTER TABLE breakfast_menus ADD INDEX idx_free (is_free)");
        echo "   ✓ Index added successfully\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ℹ️  Index already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 6. Show current structure
    echo "\n\n📋 Current breakfast_menus structure:\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM breakfast_menus");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s %-30s %s\n", $row['Field'], $row['Type'], $row['Null']);
    }
    
    // 7. Show sample data
    echo "\n\n📊 Sample breakfast menus:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo->query("SELECT id, menu_name, category, price, is_free FROM breakfast_menus LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['is_free'] ? '[FREE]' : '[PAID]';
        $price = $row['is_free'] ? 'FREE' : 'Rp ' . number_format($row['price'], 0, ',', '.');
        echo sprintf("%-4d %-8s %-35s %-12s %s\n", 
            $row['id'], 
            $type,
            $row['menu_name'], 
            $row['category'], 
            $price
        );
    }
    
    echo "\n\n✅ Breakfast menu structure updated successfully!\n";
    echo "You can now use the breakfast order form.\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
