<?php
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    echo "Testing INSERT to breakfast_menus...\n\n";
    
    $stmt = $pdo->prepare("INSERT INTO breakfast_menus (menu_name, description, category, price, is_free, is_available) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    
    $result = $stmt->execute([
        'Test Menu ' . date('His'),
        'This is a test menu',
        'western',
        35000,
        0,
        1
    ]);
    
    if ($result) {
        echo "✓ INSERT SUCCESS!\n";
        echo "Last Insert ID: " . $pdo->lastInsertId() . "\n\n";
        
        // Verify
        $stmt = $pdo->query("SELECT * FROM breakfast_menus WHERE id = " . $pdo->lastInsertId());
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($menu);
    } else {
        echo "❌ INSERT FAILED\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
