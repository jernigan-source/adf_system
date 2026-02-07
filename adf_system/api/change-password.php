<?php
/**
 * API: Change Password
 */
session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/businesses.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['current_password']) || !isset($input['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();

try {
    // Verify current password
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ? AND password = MD5(?)",
        [$_SESSION['user_id'], $input['current_password']]
    );
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Update password in main database
    $db->query(
        "UPDATE users SET password = MD5(?) WHERE id = ?",
        [$input['new_password'], $_SESSION['user_id']]
    );
    
    // Update in all business databases
    foreach ($BUSINESSES as $business) {
        try {
            $db->query(
                "UPDATE {$business['database']}.users SET password = MD5(?) WHERE id = ?",
                [$input['new_password'], $_SESSION['user_id']]
            );
        } catch (Exception $e) {
            // Skip if database doesn't exist
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
