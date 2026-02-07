<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$pageTitle = 'Tambah Supplier';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_code = trim($_POST['supplier_code']);
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $tax_number = trim($_POST['tax_number']);
    $payment_terms = $_POST['payment_terms'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate
    if (empty($supplier_code) || empty($supplier_name)) {
        $_SESSION['error'] = 'Kode dan nama supplier wajib diisi';
    } else {
        // Check if code already exists
        $existing = $db->fetchOne("SELECT id FROM suppliers WHERE supplier_code = ?", [$supplier_code]);
        if ($existing) {
            $_SESSION['error'] = 'Kode supplier sudah digunakan';
        } else {
            try {
                $data = [
                    'supplier_code' => $supplier_code,
                    'supplier_name' => $supplier_name,
                    'contact_person' => $contact_person ?: null,
                    'phone' => $phone ?: null,
                    'email' => $email ?: null,
                    'address' => $address ?: null,
                    'tax_number' => $tax_number ?: null,
                    'payment_terms' => $payment_terms,
                    'is_active' => $is_active,
                    'created_by' => $currentUser['id']
                ];
                
                $id = $db->insert('suppliers', $data);
                
                if ($id) {
                    $_SESSION['success'] = 'Supplier berhasil ditambahkan';
                    header('Location: suppliers.php');
                    exit;
                } else {
                    $_SESSION['error'] = 'Gagal menambahkan supplier: Insert returned false';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
                error_log('Supplier insert error: ' . $e->getMessage());
            }
        }
    }
}

include '../../includes/header.php';
?>

<div style="margin-bottom: 1.25rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <a href="suppliers.php" class="btn btn-secondary btn-sm">
            <i data-feather="arrow-left" style="width: 14px; height: 14px;"></i>
        </a>
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                ➕ Tambah Supplier Baru
            </h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Isi form untuk menambahkan supplier</p>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="card" style="margin-bottom: 1.25rem;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary);">
            Informasi Supplier
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Kode Supplier *</label>
                <input type="text" name="supplier_code" class="form-control" placeholder="Contoh: SUP001" required 
                       value="<?php echo isset($_POST['supplier_code']) ? htmlspecialchars($_POST['supplier_code']) : ''; ?>">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Kode unik untuk supplier</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nama Supplier *</label>
                <input type="text" name="supplier_name" class="form-control" placeholder="Nama lengkap supplier" required
                       value="<?php echo isset($_POST['supplier_name']) ? htmlspecialchars($_POST['supplier_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Contact Person</label>
                <input type="text" name="contact_person" class="form-control" placeholder="Nama kontak person"
                       value="<?php echo isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx"
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@supplier.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Tax Number (NPWP)</label>
                <input type="text" name="tax_number" class="form-control" placeholder="XX.XXX.XXX.X-XXX.XXX"
                       value="<?php echo isset($_POST['tax_number']) ? htmlspecialchars($_POST['tax_number']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Terms</label>
                <select name="payment_terms" class="form-control">
                    <option value="cash" <?php echo (isset($_POST['payment_terms']) && $_POST['payment_terms'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="net_7" <?php echo (isset($_POST['payment_terms']) && $_POST['payment_terms'] === 'net_7') ? 'selected' : ''; ?>>Net 7 Days</option>
                    <option value="net_14" <?php echo (isset($_POST['payment_terms']) && $_POST['payment_terms'] === 'net_14') ? 'selected' : ''; ?>>Net 14 Days</option>
                    <option value="net_30" <?php echo (!isset($_POST['payment_terms']) || $_POST['payment_terms'] === 'net_30') ? 'selected' : ''; ?>>Net 30 Days</option>
                    <option value="net_45" <?php echo (isset($_POST['payment_terms']) && $_POST['payment_terms'] === 'net_45') ? 'selected' : ''; ?>>Net 45 Days</option>
                    <option value="net_60" <?php echo (isset($_POST['payment_terms']) && $_POST['payment_terms'] === 'net_60') ? 'selected' : ''; ?>>Net 60 Days</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" 
                           <?php echo (!isset($_POST['is_active']) || isset($_POST['is_active'])) ? 'checked' : ''; ?>
                           style="width: 18px; height: 18px;">
                    <label for="is_active" style="margin: 0; cursor: pointer;">Active</label>
                </div>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Alamat</label>
            <textarea name="address" class="form-control" rows="3" placeholder="Alamat lengkap supplier"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
        </div>
    </div>
    
    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
        <a href="suppliers.php" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn btn-primary">
            <i data-feather="save" style="width: 16px; height: 16px;"></i> Simpan Supplier
        </button>
    </div>
</form>

<script>
    feather.replace();
</script>

<?php include '../../includes/footer.php'; ?>
