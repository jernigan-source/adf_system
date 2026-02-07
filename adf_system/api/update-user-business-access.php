<?php
/**
 * API: Update User Business Access - Using Direct PDO
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/businesses.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['business_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$targetUserId = (int)$input['user_id'];
$businessIds = $input['business_ids'];

// Validate business_ids is array
if (!is_array($businessIds)) {
    echo json_encode(['success' => false, 'message' => 'business_ids must be an array']);
    exit;
}

try {
    // Create PDO connection
    $pdo = new PDO('mysql:host=localhost;dbname=narayana', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Convert to JSON
    $businessAccessJson = json_encode(array_map('intval', $businessIds));
    
    // Update main database
    $stmt = $pdo->prepare("UPDATE users SET business_access = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$businessAccessJson, $targetUserId]);
    
    // Update all business databases
    $syncedDatabases = [];
    foreach ($BUSINESSES as $business) {
        try {
            $stmt = $pdo->prepare("UPDATE {$business['database']}.users SET business_access = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$businessAccessJson, $targetUserId]);
            $syncedDatabases[] = $business['name'];
        } catch (Exception $e) {
            error_log("Failed to sync to {$business['database']}: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'synced_to' => $syncedDatabases,
        'message' => 'Business access updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Validate all requested business IDs are accessible by owner
foreach ($businessIds as $businessId) {
    if (!in_array((int)$businessId, $ownerBusinessIds)) {
        echo json_encode([
            'success' => false, 
            'message' => 'You cannot grant access to business ID ' . $businessId
        ]);
        exit;
    }
}

try {
    // Convert array to JSON string
    $businessAccessJson = json_encode(array_map('intval', $businessIds));
    
    // Update in main database
    $mainDb = Database::getInstance();
    $mainDb->query(
        "UPDATE users SET business_access = ? WHERE id = ?",
        [$businessAccessJson, $targetUserId]
    );
    
    // Update in all business databases
    require_once '../config/businesses.php';
    
    foreach ($BUSINESSES as $business) {
        try {
            $businessDb = new Database($business['database']);
            $businessDb->query(
                "UPDATE users SET business_access = ? WHERE id = ?",
                [$businessAccessJson, $targetUserId]
            );
        } catch (Exception $e) {
            // Continue if business database doesn't exist or has error
            continue;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User access updated successfully',
        'user_id' => $targetUserId,
        'business_ids' => $businessIds
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
