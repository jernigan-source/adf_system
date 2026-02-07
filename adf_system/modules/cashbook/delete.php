<?php
/**
 * NARAYANA HOTEL MANAGEMENT SYSTEM
 * Delete Cash Book Transaction with Audit Log
 */

define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();

$currentUser = $auth->getCurrentUser();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = 'ID transaksi tidak valid';
    header('Location: index.php');
    exit;
}

// Get transaction details before deleting (for audit log)
$transaction = $db->fetchOne(
    "SELECT 
        cb.*,
        d.division_name,
        c.category_name,
        u.full_name as created_by_name
    FROM cash_book cb
    JOIN divisions d ON cb.division_id = d.id
    JOIN categories c ON cb.category_id = c.id
    JOIN users u ON cb.created_by = u.id
    WHERE cb.id = :id",
    ['id' => $id]
);

if (!$transaction) {
    $_SESSION['error'] = 'Transaksi tidak ditemukan';
    header('Location: index.php');
    exit;
}

try {
    $db->beginTransaction();
    
    // Check if transaction is from Purchase Order
    $isPurchaseOrder = false;
    $poNumber = '';
    $poId = null;
    
    if (isset($transaction['source_type']) && $transaction['source_type'] === 'purchase_order' && !empty($transaction['source_id'])) {
        $isPurchaseOrder = true;
        $poId = $transaction['source_id'];
        
        // Get PO number
        $po = $db->fetchOne("SELECT po_number FROM purchase_orders_header WHERE id = ?", [$poId]);
        if ($po) {
            $poNumber = $po['po_number'];
        }
    }
    
    // Create audit log
    $oldData = json_encode([
        'id' => $transaction['id'],
        'transaction_date' => $transaction['transaction_date'],
        'transaction_time' => $transaction['transaction_time'],
        'division' => $transaction['division_name'],
        'category' => $transaction['category_name'],
        'transaction_type' => $transaction['transaction_type'],
        'amount' => $transaction['amount'],
        'payment_method' => $transaction['payment_method'],
        'description' => $transaction['description'],
        'created_by' => $transaction['created_by_name'],
        'source_type' => $transaction['source_type'] ?? 'manual',
        'source_id' => $transaction['source_id'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    
    // Get user IP and browser info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Insert audit log
    $db->insert('audit_logs', [
        'table_name' => 'cash_book',
        'record_id' => $id,
        'action' => 'DELETE',
        'old_data' => $oldData,
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['full_name'],
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ]);
    
    // If from PO, update PO status back to submitted and remove attachment
    if ($isPurchaseOrder && $poId) {
        $db->update('purchase_orders_header', [
            'status' => 'submitted',
            'approved_by' => null,
            'approved_at' => null,
            'attachment_path' => null
        ], 'id = :id', ['id' => $poId]);
    }
    
    // Delete the transaction
    $db->delete('cash_book', 'id = :id', ['id' => $id]);
    
    $db->commit();
    
    if ($isPurchaseOrder) {
        $_SESSION['success'] = '✅ Transaksi pembayaran <strong>PO ' . $poNumber . '</strong> berhasil dihapus dari Buku Kas Besar.<br>⚠️ Status PO dikembalikan ke <strong>"Menunggu Approve"</strong>. Silakan approve ulang jika diperlukan.';
    } else {
        $_SESSION['success'] = '✅ Transaksi berhasil dihapus. Log penghapusan telah dicatat.';
    }
    
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = '❌ Gagal menghapus transaksi: ' . $e->getMessage();
}

header('Location: index.php');
exit;
