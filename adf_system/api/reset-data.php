<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

// Only admin can reset data
if (!$auth->hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya admin yang bisa reset data.']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$resetType = $input['reset_type'] ?? '';

if (empty($resetType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipe reset tidak valid.']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $deletedCount = 0;
    $tables = [];
    
    switch ($resetType) {
        case 'accounting':
            // Reset cash_book table ONLY untuk active business
            $stmt = $conn->prepare("SELECT COUNT(*) FROM cash_book WHERE business_id = :business_id");
            $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            $deletedCount = $stmt->fetchColumn();
            
            $stmt = $conn->prepare("DELETE FROM cash_book WHERE business_id = :business_id");
            $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            
            $tables = ['cash_book (bisnis aktif saja)'];
            $message = "Data accounting untuk bisnis ini berhasil direset. {$deletedCount} transaksi dihapus.";
            break;
            
        case 'employees':
            // Reset employees table - ONLY untuk active business jika ada business_id
            $tableInfo = $conn->query("DESCRIBE employees")->fetchAll();
            $hasBusiness = false;
            foreach ($tableInfo as $col) {
                if ($col['Field'] === 'business_id') {
                    $hasBusiness = true;
                    break;
                }
            }
            
            if ($hasBusiness) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $deletedCount = $stmt->fetchColumn();
                
                $stmt = $conn->prepare("DELETE FROM employees WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $tables = ['employees (bisnis aktif saja)'];
                $message = "Data karyawan untuk bisnis ini berhasil direset. {$deletedCount} karyawan dihapus.";
            } else {
                // No business_id column, reset all
                $stmt = $conn->query("SELECT COUNT(*) FROM employees");
                $deletedCount = $stmt->fetchColumn();
                $conn->exec("TRUNCATE TABLE employees");
                $tables = ['employees'];
                $message = "Data karyawan berhasil direset. {$deletedCount} karyawan dihapus.";
            }
            break;
            
        case 'users':
            // Reset users table (except admin)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin'");
            $stmt->execute();
            $deletedCount = $stmt->fetchColumn();
            
            $stmt = $conn->prepare("DELETE FROM users WHERE role != 'admin'");
            $stmt->execute();
            $tables = ['users'];
            $message = "Data user berhasil direset. {$deletedCount} user (selain admin) dihapus.";
            break;
            
        case 'guests':
            // Reset guests table - ONLY untuk active business jika ada business_id
            $tableInfo = $conn->query("DESCRIBE guests")->fetchAll();
            $hasBusiness = false;
            foreach ($tableInfo as $col) {
                if ($col['Field'] === 'business_id') {
                    $hasBusiness = true;
                    break;
                }
            }
            
            if ($hasBusiness) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM guests WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $deletedCount = $stmt->fetchColumn();
                
                $stmt = $conn->prepare("DELETE FROM guests WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $tables = ['guests (bisnis aktif saja)'];
                $message = "Data tamu untuk bisnis ini berhasil direset. {$deletedCount} tamu dihapus.";
            } else {
                // No business_id column, reset all
                $stmt = $conn->query("SELECT COUNT(*) FROM guests");
                $deletedCount = $stmt->fetchColumn();
                $conn->exec("TRUNCATE TABLE guests");
                $tables = ['guests'];
                $message = "Data tamu berhasil direset. {$deletedCount} tamu dihapus.";
            }
            break;
            
        case 'all':
            // Reset everything EXCEPT settings, divisions, categories - ONLY untuk active business
            $deletedCount = 0;
            
            // Reset cash_book untuk active business
            $stmt = $conn->prepare("SELECT COUNT(*) FROM cash_book WHERE business_id = :business_id");
            $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            $deletedCount += $stmt->fetchColumn();
            $stmt = $conn->prepare("DELETE FROM cash_book WHERE business_id = :business_id");
            $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            
            // Reset employees jika ada business_id
            $tableInfo = $conn->query("DESCRIBE employees")->fetchAll();
            $employeeHasBusiness = false;
            foreach ($tableInfo as $col) {
                if ($col['Field'] === 'business_id') {
                    $employeeHasBusiness = true;
                    break;
                }
            }
            if ($employeeHasBusiness) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $deletedCount += $stmt->fetchColumn();
                $stmt = $conn->prepare("DELETE FROM employees WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            }
            
            // Reset guests jika ada business_id
            $tableInfo = $conn->query("DESCRIBE guests")->fetchAll();
            $guestHasBusiness = false;
            foreach ($tableInfo as $col) {
                if ($col['Field'] === 'business_id') {
                    $guestHasBusiness = true;
                    break;
                }
            }
            if ($guestHasBusiness) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM guests WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
                $deletedCount += $stmt->fetchColumn();
                $stmt = $conn->prepare("DELETE FROM guests WHERE business_id = :business_id");
                $stmt->execute(['business_id' => ACTIVE_BUSINESS_ID]);
            }
            
            $tables = ['cash_book', 'employees', 'guests', 'users (non-admin) - GLOBAL'];
            $message = "Semua data untuk bisnis ini berhasil direset. Total {$deletedCount} record dihapus. (Users global tidak dihapus)";
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipe reset tidak valid.']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deletedCount,
        'tables' => $tables
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saat reset data: ' . $e->getMessage()
    ]);
}
