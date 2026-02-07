<?php
echo "=== CHECK EXISTING CATEGORIES ===\n\n";

$databases = ['adf_benscafe', 'adf_narayana_hotel'];

foreach ($databases as $dbName) {
    echo "Database: $dbName\n";
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$dbName", 'root', '');
        $result = $pdo->query("SELECT DISTINCT category FROM breakfast_menus");
        echo "  Existing categories: ";
        $cats = [];
        while ($row = $result->fetch()) {
            $cats[] = $row['category'];
        }
        echo implode(', ', $cats) . "\n\n";
    } catch (Exception $e) {
        echo "  Error or no table\n\n";
    }
}
