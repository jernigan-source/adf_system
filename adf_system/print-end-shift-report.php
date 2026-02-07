<?php
/**
 * End Shift Report - Print PDF
 * Laporan Akhir Shift dengan Detail Transaksi Harian
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/business_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Resolve business info (prefer selected_business_id, fallback to active business config)
$selectedBusinessId = $_SESSION['selected_business_id'] ?? null;
$business = null;

if ($selectedBusinessId) {
    $masterDb = Database::getInstance();
    $businessQuery = "SELECT * FROM businesses WHERE id = ?";
    $business = $masterDb->fetchOne($businessQuery, [$selectedBusinessId]);
}

if (!$business) {
    $activeConfig = getActiveBusinessConfig();
    if (!empty($activeConfig['database'])) {
        $business = [
            'business_name' => $activeConfig['name'] ?? 'Business',
            'database_name' => $activeConfig['database']
        ];
    }
}

if (!$business) {
    header('Location: ' . BASE_URL . '/select-business.php');
    exit();
}

// Switch to business database
$businessDb = Database::switchDatabase($business['database_name']);

// Get today's date
$today = date('Y-m-d');
$todayDisplay = date('d F Y');

// Get today's transactions from cash_book
$transactionsQuery = "
    SELECT 
        cb.id,
        cb.transaction_date,
        cb.transaction_time,
        cb.transaction_type,
        cb.description,
        cb.amount,
        cb.payment_method,
        cb.reference_no,
        cb.created_at,
        c.category_name AS category
    FROM cash_book cb
    LEFT JOIN categories c ON cb.category_id = c.id
    WHERE cb.transaction_date = ?
    ORDER BY cb.transaction_date ASC, cb.transaction_time ASC, cb.id ASC
";

$transactions = $businessDb->fetchAll($transactionsQuery, [$today]);

// Calculate totals
$totalIncome = 0;
$totalExpense = 0;
$incomeTransactions = [];
$expenseTransactions = [];

foreach ($transactions as $trans) {
    if ($trans['transaction_type'] === 'income') {
        $totalIncome += $trans['amount'];
        $incomeTransactions[] = $trans;
    } else {
        $totalExpense += $trans['amount'];
        $expenseTransactions[] = $trans;
    }
}

$netBalance = $totalIncome - $totalExpense;

// Get opening balance (sum of all transactions before today)
$openingBalanceQuery = "
    SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0) as opening_balance
    FROM cash_book
    WHERE transaction_date < ?
";
$openingBalanceResult = $businessDb->fetchOne($openingBalanceQuery, [$today]);
$openingBalance = $openingBalanceResult['opening_balance'] ?? 0;

$closingBalance = $openingBalance + $netBalance;

// Currency format function
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan End Shift - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: white;
        }
        
        .report-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header h2 {
            font-size: 20px;
            color: #666;
            font-weight: normal;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            color: #888;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }
        
        .info-box.expense {
            border-left-color: #f44336;
        }
        
        .info-box.balance {
            border-left-color: #2196F3;
        }
        
        .info-box.net {
            border-left-color: #FF9800;
        }
        
        .info-box h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-box .amount {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table thead {
            background: #333;
            color: white;
        }
        
        table th {
            padding: 12px 10px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }
        
        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        table td {
            padding: 10px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }
        
        table td.amount {
            text-align: right;
            font-weight: 600;
        }
        
        table td.income {
            color: #4CAF50;
        }
        
        table td.expense {
            color: #f44336;
        }
        
        .summary-table {
            width: 100%;
            margin-top: 30px;
            border: 2px solid #333;
        }
        
        .summary-table tr {
            background: white;
        }
        
        .summary-table td {
            padding: 12px 15px;
            font-size: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .summary-table td:first-child {
            font-weight: 600;
            width: 60%;
        }
        
        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }
        
        .summary-table tr.total {
            background: #333;
            color: white;
        }
        
        .summary-table tr.total td {
            border-bottom: none;
            font-size: 18px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .report-container {
                max-width: 100%;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">🖨️ Cetak PDF</button>
    
    <div class="report-container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo htmlspecialchars($business['business_name']); ?></h1>
            <h2>LAPORAN AKHIR SHIFT (END SHIFT)</h2>
            <p><strong>Tanggal:</strong> <?php echo $todayDisplay; ?> | <strong>Waktu Cetak:</strong> <?php echo date('H:i:s'); ?></p>
            <p><strong>Operator:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        
        <!-- Summary Boxes -->
        <div class="info-grid">
            <div class="info-box">
                <h3>Total Pemasukan</h3>
                <div class="amount" style="color: #4CAF50;"><?php echo formatRupiah($totalIncome); ?></div>
                <p style="font-size: 12px; color: #999; margin-top: 5px;"><?php echo count($incomeTransactions); ?> transaksi</p>
            </div>
            
            <div class="info-box expense">
                <h3>Total Pengeluaran</h3>
                <div class="amount" style="color: #f44336;"><?php echo formatRupiah($totalExpense); ?></div>
                <p style="font-size: 12px; color: #999; margin-top: 5px;"><?php echo count($expenseTransactions); ?> transaksi</p>
            </div>
            
            <div class="info-box balance">
                <h3>Saldo Awal</h3>
                <div class="amount" style="color: #2196F3;"><?php echo formatRupiah($openingBalance); ?></div>
            </div>
            
            <div class="info-box net">
                <h3>Saldo Akhir</h3>
                <div class="amount" style="color: <?php echo $closingBalance >= 0 ? '#4CAF50' : '#f44336'; ?>;">
                    <?php echo formatRupiah($closingBalance); ?>
                </div>
            </div>
        </div>
        
        <!-- Income Transactions -->
        <div class="section">
            <h2>📈 Detail Transaksi Pemasukan (<?php echo count($incomeTransactions); ?>)</h2>
            <?php if (count($incomeTransactions) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Waktu</th>
                        <th style="width: 40%;">Keterangan</th>
                        <th style="width: 15%;">Kategori</th>
                        <th style="width: 15%;">Metode</th>
                        <th style="width: 15%;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incomeTransactions as $trans): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($trans['transaction_time'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($trans['description']); ?></td>
                        <td><?php echo htmlspecialchars($trans['category']); ?></td>
                        <td><?php echo htmlspecialchars($trans['payment_method']); ?></td>
                        <td class="amount income"><?php echo formatRupiah($trans['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #e8f5e9; font-weight: bold;">
                        <td colspan="4" style="text-align: right; padding-right: 15px;">TOTAL PEMASUKAN:</td>
                        <td class="amount income" style="font-size: 15px;"><?php echo formatRupiah($totalIncome); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">Tidak ada transaksi pemasukan hari ini</div>
            <?php endif; ?>
        </div>
        
        <!-- Expense Transactions -->
        <div class="section">
            <h2>📉 Detail Transaksi Pengeluaran (<?php echo count($expenseTransactions); ?>)</h2>
            <?php if (count($expenseTransactions) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Waktu</th>
                        <th style="width: 40%;">Keterangan</th>
                        <th style="width: 15%;">Kategori</th>
                        <th style="width: 15%;">Metode</th>
                        <th style="width: 15%;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenseTransactions as $trans): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($trans['transaction_time'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($trans['description']); ?></td>
                        <td><?php echo htmlspecialchars($trans['category']); ?></td>
                        <td><?php echo htmlspecialchars($trans['payment_method']); ?></td>
                        <td class="amount expense"><?php echo formatRupiah($trans['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #ffebee; font-weight: bold;">
                        <td colspan="4" style="text-align: right; padding-right: 15px;">TOTAL PENGELUARAN:</td>
                        <td class="amount expense" style="font-size: 15px;"><?php echo formatRupiah($totalExpense); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">Tidak ada transaksi pengeluaran hari ini</div>
            <?php endif; ?>
        </div>
        
        <!-- Summary -->
        <table class="summary-table">
            <tr>
                <td>Saldo Awal Hari Ini</td>
                <td><?php echo formatRupiah($openingBalance); ?></td>
            </tr>
            <tr>
                <td>Total Pemasukan Hari Ini</td>
                <td style="color: #4CAF50;"><?php echo formatRupiah($totalIncome); ?></td>
            </tr>
            <tr>
                <td>Total Pengeluaran Hari Ini</td>
                <td style="color: #f44336;"><?php echo formatRupiah($totalExpense); ?></td>
            </tr>
            <tr>
                <td>Selisih (Net Income)</td>
                <td style="color: <?php echo $netBalance >= 0 ? '#4CAF50' : '#f44336'; ?>;">
                    <?php echo formatRupiah($netBalance); ?>
                </td>
            </tr>
            <tr class="total">
                <td>SALDO AKHIR</td>
                <td><?php echo formatRupiah($closingBalance); ?></td>
            </tr>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <p>Laporan ini dicetak secara otomatis dari sistem <?php echo APP_NAME; ?></p>
            <p>Dicetak pada: <?php echo date('d F Y, H:i:s'); ?> oleh <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
    </div>
    
    <script>
        // Auto print dialog on load
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });

        // After print (including cancel), notify opener and close
        window.addEventListener('afterprint', function() {
            const logoutUrl = '<?php echo BASE_URL; ?>/logout.php';

            try {
                if (window.opener && window.opener !== window) {
                    window.opener.location.href = logoutUrl;
                } else {
                    window.location.href = logoutUrl;
                    return;
                }
            } catch (e) {
                window.location.href = logoutUrl;
                return;
            }

            setTimeout(function() {
                window.close();
            }, 300);
        });
    </script>
</body>
</html>
