<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/report_helper.php';

// Check if user is logged in
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$pageTitle = 'Laporan Bulanan';

// Get filter parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$division_id = isset($_GET['division_id']) ? (int)$_GET['division_id'] : 0;

// Get all divisions for filter
$divisions = $db->fetchAll("SELECT * FROM divisions ORDER BY division_name");

// Build WHERE clause
$whereConditions = ["YEAR(cb.transaction_date) = :year"];
$params = ['year' => $year];

if ($division_id > 0) {
    $whereConditions[] = "cb.division_id = :division_id";
    $params['division_id'] = $division_id;
}

$whereClause = implode(' AND ', $whereConditions);

// Get monthly summary
$monthlySummary = $db->fetchAll("
    SELECT 
        MONTH(cb.transaction_date) as month,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'income' THEN cb.amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'expense' THEN cb.amount ELSE 0 END), 0) as total_expense,
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'income' THEN cb.amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN cb.transaction_type = 'expense' THEN cb.amount ELSE 0 END), 0) as net_balance,
        COUNT(*) as transaction_count
    FROM cash_book cb
    WHERE $whereClause
    GROUP BY MONTH(cb.transaction_date)
    ORDER BY month
", $params);

// Calculate totals
$grandIncome = 0;
$grandExpense = 0;
$grandNet = 0;
$grandTransactions = 0;

foreach ($monthlySummary as $month) {
    $grandIncome += $month['total_income'];
    $grandExpense += $month['total_expense'];
    $grandNet += $month['net_balance'];
    $grandTransactions += $month['transaction_count'];
}

// Get company info for print
$company = getCompanyInfo();
$dateRangeText = 'Tahun ' . $year;

// Convert logo path to browser-accessible URL
$displayLogo = $company['invoice_logo'] ?? $company['logo'];
$absoluteLogo = null;
if ($displayLogo) {
    if (strpos($displayLogo, 'http') === 0) {
        // Already a URL path
        $absoluteLogo = $displayLogo;
    } else {
        // Convert filename to URL path for browser display
        // Logo filenames are stored in DB, need to build full URL path
        $logoFilename = basename($displayLogo);
        $absoluteLogo = BASE_URL . '/uploads/logos/' . $logoFilename;
    }
}

// Month names in Indonesian
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include '../../includes/header.php';
?>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Tahun</label>
                <select name="year" class="form-control" required>
                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Divisi</label>
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
                <i data-feather="search" style="width: 16px; height: 16px;"></i> Cari
            </button>
        </form>
    </div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">Total Pemasukan</div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">
            <?php echo formatCurrency($grandIncome); ?>
        </div>
    </div>
    
    <div class="card" style="padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">Total Pengeluaran</div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--danger);">
            <?php echo formatCurrency($grandExpense); ?>
        </div>
    </div>
    
    <div class="card" style="padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">Net Balance</div>
        <div style="font-size: 1.5rem; font-weight: 800; color: <?php echo $grandNet >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
            <?php echo formatCurrency($grandNet); ?>
        </div>
    </div>
    
    <div class="card" style="padding: 1rem;">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">Total Transaksi</div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary-color);">
            <?php echo number_format($grandTransactions); ?>
        </div>
    </div>
</div>

<!-- Monthly Chart -->
<div class="card" style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600; margin-bottom: 1rem;">
            📊 Grafik Bulanan <?php echo $year; ?>
        </h3>
        <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Monthly Summary Table -->
    <!-- Monthly Summary Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0 0.5rem 0; border-bottom: 1px solid var(--bg-tertiary); margin-bottom: 1rem;">
            <h3 style="font-size: 0.95rem; color: var(--text-primary); font-weight: 600;">
                📊 Ringkasan Per Bulan
            </h3>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="exportToPDF()" class="btn btn-danger btn-sm">
                    <i data-feather="file-text" style="width: 14px; height: 14px;"></i> Export PDF
                </button>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">
                    <i data-feather="printer" style="width: 14px; height: 14px;"></i> Print
                </button>
                <button onclick="exportToExcel()" class="btn btn-success btn-sm">
                    <i data-feather="download" style="width: 14px; height: 14px;"></i> Export Excel
                </button>
            </div>
        </div>
    
    <?php if (empty($monthlySummary)): ?>
        <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
            Tidak ada data untuk tahun yang dipilih
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="monthlyTable">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="text-right">Pemasukan</th>
                        <th class="text-right">Pengeluaran</th>
                        <th class="text-right">Net Balance</th>
                        <th class="text-center">Transaksi</th>
                        <th class="text-center">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlySummary as $month): ?>
                        <tr>
                            <td style="font-weight: 600; font-size: 0.813rem;">
                                <?php echo $monthNames[$month['month']]; ?> <?php echo $year; ?>
                            </td>
                            <td class="text-right" style="font-weight: 700; font-size: 0.875rem; color: var(--success);">
                                <?php echo formatCurrency($month['total_income']); ?>
                            </td>
                            <td class="text-right" style="font-weight: 700; font-size: 0.875rem; color: var(--danger);">
                                <?php echo formatCurrency($month['total_expense']); ?>
                            </td>
                            <td class="text-right" style="font-weight: 800; font-size: 0.938rem; color: <?php echo $month['net_balance'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo formatCurrency($month['net_balance']); ?>
                            </td>
                            <td class="text-center" style="font-size: 0.813rem;">
                                <span style="padding: 0.25rem 0.5rem; background: var(--bg-tertiary); border-radius: 4px;">
                                    <?php echo $month['transaction_count']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="daily.php?start_date=<?php echo $year . '-' . sprintf('%02d', $month['month']) . '-01'; ?>&end_date=<?php echo date('Y-m-t', strtotime($year . '-' . $month['month'] . '-01')); ?><?php echo $division_id > 0 ? '&division_id=' . $division_id : ''; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i data-feather="eye" style="width: 14px; height: 14px;"></i> Lihat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--bg-tertiary); font-weight: 800;">
                        <td>TOTAL</td>
                        <td class="text-right" style="color: var(--success);">
                            <?php echo formatCurrency($grandIncome); ?>
                        </td>
                        <td class="text-right" style="color: var(--danger);">
                            <?php echo formatCurrency($grandExpense); ?>
                        </td>
                        <td class="text-right" style="color: <?php echo $grandNet >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo formatCurrency($grandNet); ?>
                        </td>
                        <td class="text-center">
                            <?php echo number_format($grandTransactions); ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    feather.replace();
    
    // Prepare chart data
    const chartLabels = <?php echo json_encode(array_map(function($m) use ($monthNames) { return $monthNames[$m['month']]; }, $monthlySummary)); ?>;
    const incomeData = <?php echo json_encode(array_column($monthlySummary, 'total_income')); ?>;
    const expenseData = <?php echo json_encode(array_column($monthlySummary, 'total_expense')); ?>;
    
    // Create chart
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Pemasukan',
                    data: incomeData,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Pengeluaran',
                    data: expenseData,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
    
    // Export to PDF function
    function exportToPDF() {
        const element = document.getElementById('printContent');
        if (!element) {
            alert('Report tidak ditemukan!');
            return;
        }
        showPDFPreview(element);
    }

    function showPDFPreview(element) {
        const modal = document.createElement('div');
        modal.id = 'pdfPreviewModal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px;';
        
        modal.innerHTML = `<div style="background: white; border-radius: 8px; max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column;">
            <div style="padding: 1.5rem; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #333;">Preview PDF</h3>
                <button onclick="closePDFPreview()" style="border: none; background: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            <div id="previewContent" style="flex: 1; overflow-y: auto; padding: 1.5rem; background: #f5f5f5; display: flex; align-items: center; justify-content: center;">
                <div style="color: #999;">Loading PDF...</div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid #ddd; display: flex; gap: 1rem; justify-content: flex-end;">
                <button onclick="closePDFPreview()" class="btn btn-secondary" style="border: 1px solid #ddd; background: white; color: #666; border-radius: 4px; cursor: pointer; padding: 0.75rem 1.5rem;">Cancel</button>
                <button onclick="confirmPDFDownload()" class="btn btn-danger" style="border: none; background: #dc3545; color: white; border-radius: 4px; cursor: pointer; padding: 0.75rem 1.5rem;">Download PDF</button>
            </div>
        </div>`;
        
        document.body.appendChild(modal);
        renderPreview(element);
    }

    function renderPreview(element) {
        // Check if html2canvas is ready, if not wait
        if (typeof html2canvas === 'undefined') {
            setTimeout(() => { renderPreview(element); }, 100);
            return;
        }
        
        const previewContent = document.getElementById('previewContent');
        if (previewContent) previewContent.innerHTML = '<div style="color: #999;">Rendering PDF...</div>';
        
        // Clone and make visible for rendering
        const clone = element.cloneNode(true);
        clone.style.position = 'fixed';
        clone.style.left = '0';
        clone.style.top = '0';
        clone.style.width = '210mm';
        clone.style.height = 'auto';
        clone.style.display = 'block';
        clone.style.opacity = '1';
        clone.style.zIndex = '-999';
        clone.style.visibility = 'hidden';
        
        document.body.appendChild(clone);
        
        // Wait for reflow
        setTimeout(() => {
            html2canvas(clone, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
                windowHeight: clone.scrollHeight,
                windowWidth: clone.scrollWidth,
                onclone: (clonedDoc) => {
                    // Ensure visibility in cloned doc
                    const content = clonedDoc.body.querySelector('#printContent') || clonedDoc.body.firstChild;
                    if (content) {
                        content.style.visibility = 'visible';
                        content.style.opacity = '1';
                    }
                }
            }).then(canvas => {
                document.body.removeChild(clone);
                
                const previewContent = document.getElementById('previewContent');
                if (!previewContent) return;
                
                previewContent.innerHTML = '';
                const img = document.createElement('img');
                img.src = canvas.toDataURL('image/jpeg', 0.95);
                img.style.cssText = 'max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;';
                previewContent.appendChild(img);
                
                window.pdfPreviewCanvas = canvas;
            }).catch(err => {
                document.body.removeChild(clone);
                
                const previewContent = document.getElementById('previewContent');
                if (previewContent) {
                    previewContent.innerHTML = '<div style="color: #d32f2f; text-align: center;"><p>Error: ' + err.message + '</p><p style="font-size: 12px; color: #666;">Gunakan Print Preview</p></div>';
                }
            });
        }, 100);
    }

    function confirmPDFDownload() {
        if (!window.pdfPreviewCanvas) {
            alert('Preview not ready');
            return;
        }
        
        // Check if jsPDF is available (it's in window.jsPDF from html2pdf bundle)
        if (typeof window.jsPDF === 'undefined' && typeof jsPDF === 'undefined') {
            // Fallback: wait for library and retry
            setTimeout(() => { confirmPDFDownload(); }, 500);
            return;
        }
        
        try {
            const canvas = window.pdfPreviewCanvas;
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            const PDF = window.jsPDF || jsPDF;
            const doc = new PDF({orientation: 'portrait', unit: 'mm', format: 'a4'});
            
            const imgWidth = 210;
            const pageHeight = 297;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            let heightLeft = imgHeight;
            let position = 0;
            
            while (heightLeft >= 0) {
                doc.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                if (heightLeft > 0) {
                    doc.addPage();
                    position = heightLeft - imgHeight;
                }
            }
            
            doc.save('Laporan-Bulanan-<?php echo $year; ?>.pdf');
            closePDFPreview();
        } catch (e) {
            console.error('PDF error:', e);
            alert('Error: ' + e.message);
        }
    }

    function closePDFPreview() {
        const modal = document.getElementById('pdfPreviewModal');
        if (modal) modal.remove();
        window.pdfPreviewCanvas = null;
    }


    function closePDFPreview() {
        const modal = document.getElementById('pdfPreviewModal');
        if (modal) {
            modal.remove();
        }
        window.pdfPreviewCanvas = null;
    }
    
    // Export to Excel function
    function exportToExcel() {
        const table = document.getElementById('monthlyTable');
        let html = '<table>';
        
        // Get table HTML
        html += table.outerHTML;
        html += '</table>';
        
        // Create downloadable file
        const blob = new Blob([html], {
            type: 'application/vnd.ms-excel'
        });
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'laporan-bulanan-<?php echo $year; ?>.xls';
        a.click();
        window.URL.revokeObjectURL(url);
    }
</script>

<style>
    /* Hide print content from normal view using position, not display */
    #printContent {
        position: absolute;
        left: -9999px;
        top: -9999px;
        width: 210mm;
        opacity: 0;
        pointer-events: none;
    }
    
    @media print {
        /* Show print content when printing */
        #printContent {
            position: static !important;
            left: auto !important;
            top: auto !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            display: block !important;
            width: 100%;
        }
        
        /* Hide screen elements when printing */
        .sidebar, .top-bar, .top-bar .user-info, .btn, .form-control, form, .filter-section, .no-print, .card, footer, nav, .breadcrumb {
            display: none !important;
        }
        
        main, .main-content {
            margin-left: 0 !important;
            margin-top: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        body {
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            width: 210mm !important;
        }
            padding: 0;
        }
        
        body {
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            margin: 0;
            padding: 0;
        }
        
        canvas {
            max-width: 100%;
        }
        
        table {
            page-break-inside: avoid;
        }
        
        tr {
            page-break-inside: avoid;
        }
    }
</style>

<!-- Print-Only Report Section -->
<div id="printContent">
    <div style="width: 210mm; min-height: 297mm; margin: 0 auto; padding: 8mm 8mm; background: white;">
        <?php echo generateReportHeader('LAPORAN BULANAN', 'Tahun ' . $year . ($division_id > 0 ? ' - ' . end($divisions)['division_name'] : ''), $dateRangeText, $absoluteLogo); ?>
        
        <!-- Summary Cards for Print -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.3rem; margin-bottom: 0.6rem;">
            <?php
            echo generateSummaryCard('💰 TOTAL PEMASUKAN', formatCurrency($grandIncome), '#10b981', '');
            echo generateSummaryCard('💸 TOTAL PENGELUARAN', formatCurrency($grandExpense), '#ef4444', '');
            echo generateSummaryCard('📊 NET BALANCE', formatCurrency($grandNet), $grandNet >= 0 ? '#3b82f6' : '#f59e0b', '');
            echo generateSummaryCard('📈 TOTAL TRANSAKSI', number_format($grandTransactions), '#8b5cf6', '');
            ?>
        </div>
        
        <!-- Monthly Summary Table -->
        <h2 style="font-size: 10px; font-weight: 700; color: #1f2937; margin-bottom: 0.4rem; page-break-after: avoid;">
            Ringkasan Per Bulan
        </h2>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0.6rem; font-size: 8px;">
            <thead>
                <tr style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); font-weight: 700;">
                    <th style="padding: 6px 8px; text-align: left; border-bottom: 1.5px solid #d1d5db;">Bulan</th>
                    <th style="padding: 6px 8px; text-align: right; border-bottom: 1.5px solid #d1d5db;">Pemasukan</th>
                    <th style="padding: 6px 8px; text-align: right; border-bottom: 1.5px solid #d1d5db;">Pengeluaran</th>
                    <th style="padding: 6px 8px; text-align: right; border-bottom: 1.5px solid #d1d5db;">Net Balance</th>
                    <th style="padding: 6px 8px; text-align: center; border-bottom: 1.5px solid #d1d5db;">Transaksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                $rowCount = 0;
                foreach ($monthlySummary as $month): 
                    $bgColor = ($rowCount % 2 === 0) ? '#f9fafb' : '#ffffff';
                    $netColor = ($month['net_balance'] >= 0) ? '#10b981' : '#ef4444';
                    $rowCount++;
                ?>
                    <tr style="background: <?php echo $bgColor; ?>;">
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">
                            <?php echo $monthNames[$month['month']] . ' ' . $year; ?>
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #10b981; font-weight: 600;">
                            Rp <?php echo number_format($month['total_income'], 0, ',', '.'); ?>
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #ef4444; font-weight: 600;">
                            Rp <?php echo number_format($month['total_expense'], 0, ',', '.'); ?>
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color: <?php echo $netColor; ?>; font-weight: 700;">
                            Rp <?php echo number_format($month['net_balance'], 0, ',', '.'); ?>
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;">
                            <?php echo $month['transaction_count']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); font-weight: 800;">
                    <td style="padding: 6px 8px; border-top: 1.5px solid #d1d5db; border-bottom: 1.5px solid #d1d5db;">TOTAL</td>
                    <td style="padding: 6px 8px; text-align: right; border-top: 1.5px solid #d1d5db; border-bottom: 1.5px solid #d1d5db; color: #10b981;">
                        Rp <?php echo number_format($grandIncome, 0, ',', '.'); ?>
                    </td>
                    <td style="padding: 6px 8px; text-align: right; border-top: 1.5px solid #d1d5db; border-bottom: 1.5px solid #d1d5db; color: #ef4444;">
                        Rp <?php echo number_format($grandExpense, 0, ',', '.'); ?>
                    </td>
                    <td style="padding: 6px 8px; text-align: right; border-top: 1.5px solid #d1d5db; border-bottom: 1.5px solid #d1d5db; color: <?php echo $grandNet >= 0 ? '#10b981' : '#ef4444'; ?>;">
                        Rp <?php echo number_format($grandNet, 0, ',', '.'); ?>
                    </td>
                    <td style="padding: 6px 8px; text-align: center; border-top: 1.5px solid #d1d5db; border-bottom: 1.5px solid #d1d5db;">
                        <?php echo $grandTransactions; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <?php echo generateSignatureSection(); ?>
        <?php echo generateReportFooter(); ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
