<?php
/**
 * API: Add Business with Auto Database Creation
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['name']) || !isset($input['database'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields: name, database']);
    exit;
}

try {
    // 1. Create Database Automatically
    $dbName = $input['database'];
    $db = Database::getInstance();
    
    // Create database
    $db->getConnection()->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 2. Add to businesses.php
    $configFile = '../config/businesses.php';
    $content = file_get_contents($configFile);
    
    // Get next ID
    preg_match_all("/'id' => (\d+)/", $content, $matches);
    $maxId = max($matches[1]);
    $nextId = $maxId + 1;
    
    // Create new business array
    $newBusiness = "    [\n" .
                   "        'id' => {$nextId},\n" .
                   "        'name' => '{$input['name']}',\n" .
                   "        'database' => '{$input['database']}',\n" .
                   "        'type' => '{$input['type']}',\n" .
                   "        'active' => true\n" .
                   "    ]\n";
    
    // Insert before closing bracket
    $content = str_replace(
        "];",
        ",\n" . $newBusiness . "];",
        $content
    );
    
    file_put_contents($configFile, $content);
    
    echo json_encode([
        'success' => true,
        'business_id' => $nextId,
        'database' => $dbName,
        'message' => 'Business added and database created automatically! Now sync tables.'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
