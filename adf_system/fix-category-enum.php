<?php
echo "=== FIX CATEGORY ENUM FOR DRINKS ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=adf_narayana_hotel', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check current ENUM
    $result = $pdo->query("SHOW COLUMNS FROM breakfast_menus WHERE Field = 'category'");
    $col = $result->fetch(PDO::FETCH_ASSOC);
    echo "Current ENUM: " . $col['Type'] . "\n\n";
    
    // Update ENUM to include 'drinks'
    echo "Adding 'drinks' to category ENUM...\n";
    $sql = "ALTER TABLE breakfast_menus MODIFY COLUMN category ENUM('western', 'indonesian', 'japanese', 'drinks') NOT NULL";
    $pdo->exec($sql);
    echo "✅ SUCCESS! Category ENUM updated.\n\n";
    
    // Verify
    $result = $pdo->query("SHOW COLUMNS FROM breakfast_menus WHERE Field = 'category'");
    $col = $result->fetch(PDO::FETCH_ASSOC);
    echo "New ENUM: " . $col['Type'] . "\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
