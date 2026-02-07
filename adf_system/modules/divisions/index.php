<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$pageTitle = 'Analisa Per Divisi';

// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$division_id = isset($_GET['division_id']) ? (int)$_GET['division_id'] : 0;

// Parse month
$filterDate = $month . '-01';
$year = date('Y', strtotime($filterDate));
$monthNum = date('m', strtotime($filterDate));
$monthName = date('F Y', strtotime($filterDate));

// Get all divisions
$divisions = $db->fetchAll("SELECT * FROM divisions ORDER BY division_name");

// Get summary data per division for selected month
$summaryQuery = "
    SELECT 
        d.id as division_id,
        d.division_name,
        d.division_code,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'income' THEN cb.amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'expense' THEN cb.amount ELSE 0 END), 0) as total_expense,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'income' THEN cb.amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'expense' THEN cb.amount ELSE 0 END), 0) as net_balance,
        COUNT(cb.id) as transaction_count
    FROM divisions d
    LEFT JOIN cash_book cb ON d.id = cb.division_id 
        AND YEAR(cb.transaction_date) = :year 
        AND MONTH(cb.transaction_date) = :month
    GROUP BY d.id, d.division_name, d.division_code
    ORDER BY net_balance DESC
";

$divisionSummary = $db->fetchAll($summaryQuery, [
    'year' => $year,
    'month' => $monthNum
]);

// If specific division is selected, get detailed data
$divisionDetail = null;
$divisionTransactions = [];
$categoryBreakdown = [];

if ($division_id > 0) {
    // Get division info
    $divisionDetail = $db->fetchOne("SELECT * FROM divisions WHERE id = ?", [$division_id]);
    
    // Get transactions for this division
    $divisionTransactions = $db->fetchAll("
        SELECT 
            cb.*,
            c.category_name,
            u.full_name as created_by_name
        FROM cash_book cb
        LEFT JOIN categories c ON cb.category_id = c.id
        LEFT JOIN users u ON cb.created_by = u.user_id
        WHERE cb.division_id = :division_id
            AND YEAR(cb.transaction_date) = :year
            AND MONTH(cb.transaction_date) = :month
        ORDER BY cb.transaction_date DESC, cb.created_at DESC
    ", [
        'division_id' => $division_id,
        'year' => $year,
        'month' => $monthNum
    ]);
    
    // Get category breakdown
    $categoryBreakdown = $db->fetchAll("
        SELECT 
            c.category_name,
            cb.transaction_type,
            SUM(cb.amount) as total_amount,
            COUNT(*) as transaction_count
        FROM cash_book cb
        LEFT JOIN categories c ON cb.category_id = c.id
        WHERE cb.division_id = :division_id
            AND YEAR(cb.transaction_date) = :year
            AND MONTH(cb.transaction_date) = :month
        GROUP BY c.category_name, cb.transaction_type
        ORDER BY total_amount DESC
    ", [
        'division_id' => $division_id,
        'year' => $year,
        'month' => $monthNum
    ]);
}

include '../../includes/header.php';
?>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 1.25rem;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Bulan</label>
            <input type="month" name="month" class="form-control" value="<?php echo $month; ?>">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Divisi (Detail)</label>
            <select name="division_id" class="form-control">
                <option value="0">-- Semua Divisi --</option>
                <?php foreach ($divisions as $div): ?>
                    <option value="<?php echo $div['id']; ?>" <?php echo $division_id == $div['id'] ? 'selected' : ''; ?>>
                        <?php echo $div['division_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary" style="height: 42px;">
            <i data-feather="filter" style="width: 16px; height: 16px;"></i> Filter
        </button>
    </form>
</div>

<!-- Division Summary Cards -->
<div style="margin-bottom: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">
            Ringkasan Per Divisi - <?php echo $monthName; ?>
        </h2>
        <span style="font-size: 0.813rem; color: var(--text-muted);">
            Total: <?php echo count($divisionSummary); ?> divisi
        </span>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
        <?php foreach ($divisionSummary as $div): ?>
            <div class="card" style="padding: 1rem; position: relative;">
                <a href="?month=<?php echo $month; ?>&division_id=<?php echo $div['division_id']; ?>" 
                   style="position: absolute; inset: 0; z-index: 1;"></a>
                
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                            <?php echo $div['division_code']; ?>
                        </div>
                        <div style="font-size: 1rem; font-weight: 700; color: var(--text-primary);">
                            <?php echo $div['division_name']; ?>
                        </div>
                    </div>
                    <div style="font-size: 0.75rem; padding: 0.25rem 0.5rem; background: var(--bg-tertiary); border-radius: 4px;">
                        <?php echo $div['transaction_count']; ?> trx
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.688rem; color: var(--text-muted); margin-bottom: 0.25rem;">Pemasukan</div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: var(--success);">
                            <?php echo formatCurrency($div['total_income']); ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.688rem; color: var(--text-muted); margin-bottom: 0.25rem;">Pengeluaran</div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: var(--danger);">
                            <?php echo formatCurrency($div['total_expense']); ?>
                        </div>
                    </div>
                </div>
                
                <div style="padding-top: 0.75rem; border-top: 1px solid var(--bg-tertiary);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Net Balance</span>
                        <span style="font-size: 1.125rem; font-weight: 800; color: <?php echo $div['net_balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo formatCurrency($div['net_balance']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($division_id == $div['division_id']): ?>
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Division Detail Section -->
<?php if ($divisionDetail): ?>
    <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05)); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--bg-tertiary); margin-bottom: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">
                📌 Detail: <?php echo $divisionDetail['division_name']; ?> (<?php echo $divisionDetail['division_code']; ?>)
            </h2>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i data-feather="printer" style="width: 14px; height: 14px;"></i> Cetak
                </button>
                <a href="?month=<?php echo $month; ?>" class="btn btn-secondary btn-sm">
                    <i data-feather="x" style="width: 14px; height: 14px;"></i> Tutup Detail
                </a>
            </div>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <?php if (!empty($categoryBreakdown)): ?>
        <div class="card" style="margin-bottom: 1.25rem;">
            <div style="padding: 0.75rem 0 0.5rem 0; border-bottom: 1px solid var(--bg-tertiary); margin-bottom: 1rem;">
                <h3 style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;">
                    📊 Breakdown Per Kategori
                </h3>
            </div>
            
            <div style="display: grid; gap: 0.5rem;">
                <?php foreach ($categoryBreakdown as $cat): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem; background: var(--bg-tertiary); border-radius: var(--radius-md);">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-primary);">
                                <?php echo $cat['category_name']; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <?php echo $cat['transaction_count']; ?> transaksi • 
                                <?php echo $cat['transaction_type'] === 'income' ? 'Pemasukan' : 'Pengeluaran'; ?>
                            </div>
                        </div>
                        <div style="font-weight: 800; font-size: 1rem; color: <?php echo $cat['transaction_type'] === 'income' ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo $cat['transaction_type'] === 'income' ? '+' : '-'; ?><?php echo formatCurrency($cat['total_amount']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Transactions -->
    <?php if (!empty($divisionTransactions)): ?>
        <div class="card">
            <div style="padding: 0.75rem 0 0.5rem 0; border-bottom: 1px solid var(--bg-tertiary); margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;">
                        📝 Rincian Transaksi
                    </h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                        Total: <?php echo count($divisionTransactions); ?> transaksi
                    </span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Tipe</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisionTransactions as $trans): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.813rem;">
                                    <?php echo date('d/m/Y', strtotime($trans['transaction_date'])); ?>
                                </td>
                                <td style="font-size: 0.813rem;"><?php echo $trans['category_name']; ?></td>
                                <td style="font-size: 0.813rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo $trans['description']; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $trans['transaction_type'] === 'income' ? 'success' : 'danger'; ?>">
                                        <?php echo $trans['transaction_type'] === 'income' ? 'Masuk' : 'Keluar'; ?>
                                    </span>
                                </td>
                                <td class="text-right" style="font-weight: 700; font-size: 0.875rem; color: <?php echo $trans['transaction_type'] === 'income' ? 'var(--success)' : 'var(--danger)'; ?>;">
                                    <?php echo $trans['transaction_type'] === 'income' ? '+' : '-'; ?><?php echo formatCurrency($trans['amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
                Tidak ada transaksi untuk divisi ini di bulan <?php echo $monthName; ?>
            </p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
    feather.replace();
</script>

<style>
    @media print {
        /* Hide navigation and filter */
        .sidebar, .topbar, .btn, form, a[href*="Tutup"] {
            display: none !important;
        }
        
        /* Reset page styles */
        body {
            background: white !important;
            color: black !important;
        }
        
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            page-break-inside: avoid;
            margin-bottom: 1rem !important;
        }
        
        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f0f0f0 !important;
            font-weight: bold;
        }
        
        /* Badge styles */
        .badge {
            border: 1px solid;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-success {
            border-color: #10b981;
            color: #10b981;
        }
        
        .badge-danger {
            border-color: #ef4444;
            color: #ef4444;
        }
        
        /* Color overrides for print */
        .text-right {
            text-align: right !important;
        }
        
        /* Page breaks */
        .card {
            page-break-after: auto;
        }
    }
</style>

<?php include '../../includes/footer.php'; ?>
