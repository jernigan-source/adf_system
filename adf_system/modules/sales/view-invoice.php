<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$print_mode = isset($_GET['print']);

if ($invoice_id <= 0) {
    $_SESSION['error'] = 'Invoice tidak ditemukan';
    redirect(BASE_URL . '/modules/sales/index.php');
}

// Get invoice data
$invoice = $db->fetchOne("
    SELECT 
        si.*,
        d.division_name,
        d.division_code,
        u.full_name as created_by_name
    FROM sales_invoices_header si
    LEFT JOIN divisions d ON si.division_id = d.id
    LEFT JOIN users u ON si.created_by = u.id
    WHERE si.id = ?
", [$invoice_id]);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice tidak ditemukan';
    redirect(BASE_URL . '/modules/sales/index.php');
}

// Get invoice items
$items = $db->fetchAll("
    SELECT * FROM sales_invoices_detail
    WHERE invoice_header_id = ?
    ORDER BY id
", [$invoice_id]);

// Get company/hotel info from active business configuration
$companySettings = [
    'name' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_name'")['setting_value'] ?? BUSINESS_NAME,
    'business_icon' => BUSINESS_ICON,
    'business_color' => BUSINESS_COLOR,
    'address' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_address'")['setting_value'] ?? '',
    'phone' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_phone'")['setting_value'] ?? '',
    'email' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_email'")['setting_value'] ?? '',
    'logo' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'")['setting_value'] ?? null,
    'invoice_logo' => $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'invoice_logo'")['setting_value'] ?? null
];

// Use invoice logo if available, otherwise use company logo
$displayLogo = $companySettings['invoice_logo'] ?? $companySettings['logo'];

// Convert relative path to absolute for PDF export
$absoluteLogoPath = null;
if ($displayLogo) {
    if (strpos($displayLogo, 'http') === 0) {
        $absoluteLogoPath = $displayLogo; // Already absolute URL
    } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $displayLogo)) {
        $absoluteLogoPath = $_SERVER['DOCUMENT_ROOT'] . $displayLogo;
    } elseif (file_exists($displayLogo)) {
        $absoluteLogoPath = $displayLogo; // Already correct path
    }
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Generate invoice content for PDF download
    $invoiceHtml = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Arial", sans-serif; 
                font-size: 12px; 
                color: #2c3e50;
                padding: 20px;
            }
            .invoice-container { 
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                padding: 20mm;
            }
            .header-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid ' . $companySettings['business_color'] . ';
            }
            .company-header {
                display: flex;
                gap: 15px;
                flex: 1;
            }
            .company-logo {
                width: 60px;
                height: 60px;
                flex-shrink: 0;
                overflow: hidden;
            }
            .company-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            .company-info {
                flex: 1;
            }
            .company-name { 
                font-size: 18px; 
                font-weight: bold;
                margin-bottom: 5px;
            }
            .company-details {
                font-size: 10px;
                color: #666;
                line-height: 1.6;
            }
            .invoice-meta {
                text-align: right;
                font-size: 11px;
            }
            .invoice-number {
                font-size: 16px;
                font-weight: bold;
                color: ' . $companySettings['business_color'] . ';
            }
            .status-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 10px;
                margin-top: 5px;
            }
            .status-paid { background: #d4edda; color: #155724; }
            .status-unpaid { background: #f8d7da; color: #721c24; }
            .status-partial { background: #fff3cd; color: #856404; }
            
            .section-title {
                font-size: 10px;
                font-weight: bold;
                text-transform: uppercase;
                color: #666;
                margin: 15px 0 8px 0;
            }
            
            .customer-info {
                font-size: 11px;
                margin-bottom: 15px;
            }
            .customer-name {
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 3px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 10px;
            }
            
            th {
                background: ' . $companySettings['business_color'] . ';
                color: white;
                padding: 8px;
                text-align: left;
                font-weight: bold;
            }
            
            td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            
            tr:nth-child(even) {
                background: #f9f9f9;
            }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            
            .summary {
                width: 300px;
                margin-left: auto;
                margin: 15px 0 0 0;
            }
            
            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                font-size: 11px;
            }
            
            .summary-row.total {
                border-top: 2px solid ' . $companySettings['business_color'] . ';
                padding-top: 8px;
                font-weight: bold;
                font-size: 12px;
                color: ' . $companySettings['business_color'] . ';
            }
            
            .notes {
                margin-top: 15px;
                padding: 10px;
                background: #f5f5f5;
                border-radius: 4px;
                font-size: 10px;
            }
            
            .notes-title {
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
            
            @media print {
                body { padding: 0; }
                .invoice-container { padding: 0; margin: 0; }
            }
        </style>
    </head>
    <body onload="window.print();">
        <div class="invoice-container">
            <div class="header-section">
                <div class="company-header">
                    ' . ($absoluteLogoPath && file_exists($absoluteLogoPath) ? 
                        '<div class="company-logo"><img src="file:///' . str_replace('\\', '/', $absoluteLogoPath) . '" alt="Logo"></div>' : 
                        '') . '
                    <div class="company-info">
                        <div class="company-name">' . htmlspecialchars($companySettings['name']) . '</div>
                        <div class="company-details">
                            ' . ($companySettings['address'] ? htmlspecialchars($companySettings['address']) . '<br>' : '') . '
                            ' . ($companySettings['phone'] ? 'Tel: ' . htmlspecialchars($companySettings['phone']) . '<br>' : '') . '
                            ' . ($companySettings['email'] ? 'Email: ' . htmlspecialchars($companySettings['email']) : '') . '
                        </div>
                    </div>
                </div>
                
                <div class="invoice-meta">
                    <div class="invoice-number">' . htmlspecialchars($invoice['invoice_number']) . '</div>
                    <div>' . date('d M Y', strtotime($invoice['invoice_date'])) . '</div>
                    <span class="status-badge ' . ($invoice['payment_status'] === 'paid' ? 'status-paid' : ($invoice['payment_status'] === 'unpaid' ? 'status-unpaid' : 'status-partial')) . '">
                        ' . ($invoice['payment_status'] === 'paid' ? '✓ LUNAS' : ($invoice['payment_status'] === 'unpaid' ? '⏳ BELUM BAYAR' : '⚠ SEBAGIAN')) . '
                    </span>
                </div>
            </div>
            
            <div class="section-title">Kepada:</div>
            <div class="customer-info">
                <div class="customer-name">' . htmlspecialchars($invoice['customer_name']) . '</div>
                ' . ($invoice['customer_address'] ? '<div>' . htmlspecialchars($invoice['customer_address']) . '</div>' : '') . '
                ' . ($invoice['customer_phone'] ? '<div>Tel: ' . htmlspecialchars($invoice['customer_phone']) . '</div>' : '') . '
                ' . ($invoice['customer_email'] ? '<div>Email: ' . htmlspecialchars($invoice['customer_email']) . '</div>' : '') . '
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>Item / Layanan</th>
                        <th style="width: 60px;" class="text-center">Qty</th>
                        <th style="width: 80px;" class="text-right">Harga</th>
                        <th style="width: 80px;" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
                
    $no = 1;
    foreach ($items as $item) {
        $invoiceHtml .= '
                    <tr>
                        <td class="text-center">' . $no . '</td>
                        <td>
                            <strong>' . htmlspecialchars($item['item_name']) . '</strong>
                            ' . ($item['item_description'] ? '<br><small>' . htmlspecialchars($item['item_description']) . '</small>' : '') . '
                        </td>
                        <td class="text-center">' . number_format($item['quantity'], 0, ',', '.') . '</td>
                        <td class="text-right">Rp ' . number_format($item['unit_price'], 0, ',', '.') . '</td>
                        <td class="text-right"><strong>Rp ' . number_format($item['quantity'] * $item['unit_price'], 0, ',', '.') . '</strong></td>
                    </tr>';
        $no++;
    }
    
    $invoiceHtml .= '
                </tbody>
            </table>
            
            <div class="summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rp ' . number_format($invoice['subtotal'], 0, ',', '.') . '</span>
                </div>
                ' . ($invoice['discount_amount'] > 0 ? '
                <div class="summary-row" style="color: #ef4444;">
                    <span>Diskon</span>
                    <span>- Rp ' . number_format($invoice['discount_amount'], 0, ',', '.') . '</span>
                </div>' : '') . '
                ' . ($invoice['tax_amount'] > 0 ? '
                <div class="summary-row">
                    <span>Pajak</span>
                    <span>Rp ' . number_format($invoice['tax_amount'], 0, ',', '.') . '</span>
                </div>' : '') . '
                <div class="summary-row total">
                    <span>TOTAL</span>
                    <span>Rp ' . number_format($invoice['total_amount'], 0, ',', '.') . '</span>
                </div>
            </div>
            
            ' . ($invoice['notes'] ? '
            <div class="notes">
                <div class="notes-title">Catatan:</div>
                ' . htmlspecialchars($invoice['notes']) . '
            </div>' : '') . '
            
            <div class="footer">
                <strong>' . htmlspecialchars($companySettings['name']) . '</strong><br>
                Terima kasih atas kepercayaan Anda.
            </div>
        </div>
    </body>
    </html>';
    
    // Output as HTML with print dialog
    header('Content-Type: text/html; charset=utf-8');
    echo $invoiceHtml;
    exit;
}

if ($print_mode) {
    // Print layout
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice <?php echo $invoice['invoice_number']; ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            html, body { height: 100%; }
            body { 
                font-family: 'Segoe UI', 'Trebuchet MS', sans-serif; 
                font-size: 13px; 
                color: #2c3e50;
                background: #f5f5f5;
            }
            .invoice-container { 
                max-width: 210mm; 
                height: 297mm;
                margin: 0 auto; 
                padding: 18mm;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                position: relative;
            }
            
            /* Premium Header with Color Gradient */
            .header-section {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 25px;
                padding-bottom: 18px;
                border-bottom: 3px solid;
                border-bottom-color: <?php echo $companySettings['business_color']; ?>;
            }
            
            .company-header {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                flex: 1;
            }
            
            .company-logo {
                width: 70px;
                height: 70px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f5f5f5;
                border-radius: 8px;
                overflow: hidden;
                flex-shrink: 0;
            }
            
            .company-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            
            .company-icon {
                font-size: 48px;
                line-height: 1;
            }
            
            .company-info {
                flex: 1;
            }
            
            .company-name { 
                font-size: 22px; 
                font-weight: 900;
                background: linear-gradient(135deg, <?php echo $companySettings['business_color']; ?>, #000);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 3px;
                letter-spacing: -0.5px;
            }
            
            .company-details {
                font-size: 11px;
                color: #666;
                line-height: 1.7;
                margin: 3px 0 0 0;
            }
            
            .invoice-meta {
                text-align: right;
            }
            
            .invoice-label {
                font-size: 11px;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                font-weight: 700;
                margin-bottom: 3px;
            }
            
            .invoice-number { 
                font-size: 32px; 
                font-weight: 900;
                color: <?php echo $companySettings['business_color']; ?>;
                margin-bottom: 3px;
                letter-spacing: -1px;
            }
            
            .invoice-date { 
                font-size: 12px;
                color: #666;
                margin-bottom: 8px;
            }
            
            .status-badge { 
                display: inline-block; 
                padding: 7px 18px; 
                border-radius: 25px; 
                font-weight: 700; 
                font-size: 11px;
                letter-spacing: 1px;
                text-transform: uppercase;
            }
            
            .status-paid { 
                background: #d4edda; 
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .status-unpaid { 
                background: #f8d7da; 
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .status-partial { 
                background: #fff3cd; 
                color: #856404;
                border: 1px solid #ffeeba;
            }
            
            /* Info Sections */
            .info-section {
                display: flex;
                gap: 30px;
                margin-bottom: 28px;
            }
            
            .info-box {
                flex: 1;
            }
            
            .section-label {
                font-size: 10px;
                font-weight: 900;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 2px;
                margin-bottom: 8px;
                display: block;
            }
            
            .info-content {
                background: #f8f9fa;
                padding: 12px 14px;
                border-radius: 6px;
                border-left: 4px solid <?php echo $companySettings['business_color']; ?>;
            }
            
            .info-name {
                font-size: 14px;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 6px;
            }
            
            .info-detail {
                font-size: 11px;
                color: #666;
                line-height: 1.5;
            }
            
            /* Items Table */
            .items-section {
                margin-bottom: 25px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            table.items-table {
                margin-top: 8px;
            }
            
            table.items-table th {
                background: <?php echo $companySettings['business_color']; ?>;
                color: white;
                padding: 11px 12px;
                text-align: left;
                font-weight: 700;
                font-size: 11px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            
            table.items-table td {
                padding: 11px 12px;
                border-bottom: 1px solid #e9ecef;
                font-size: 12px;
            }
            
            table.items-table tbody tr:last-child td {
                border-bottom: 2px solid <?php echo $companySettings['business_color']; ?>;
            }
            
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            
            /* Summary Section */
            .summary-section {
                display: flex;
                justify-content: flex-end;
                margin-top: 30px;
                margin-bottom: 40px;
            }
            
            .summary-table {
                width: 320px;
            }
            
            .summary-table tr td {
                padding: 9px 12px;
                font-size: 12px;
                border-bottom: 1px solid #e9ecef;
            }
            
            .summary-table .label {
                color: #666;
                font-weight: 600;
            }
            
            .summary-table .value {
                text-align: right;
                color: #2c3e50;
                font-weight: 700;
            }
            
            .summary-row-total {
                background: linear-gradient(135deg, <?php echo $companySettings['business_color']; ?>15, <?php echo $companySettings['business_color']; ?>08);
                border-top: 2px solid <?php echo $companySettings['business_color']; ?> !important;
                border-bottom: none !important;
            }
            
            .summary-row-total .label {
                font-size: 13px;
                font-weight: 900;
                text-transform: uppercase;
                color: <?php echo $companySettings['business_color']; ?>;
                letter-spacing: 1px;
            }
            
            .summary-row-total .value {
                font-size: 28px;
                font-weight: 900;
                color: <?php echo $companySettings['business_color']; ?>;
                letter-spacing: -0.5px;
            }
            
            /* Notes & Footer */
            .notes-section {
                background: #f8f9fa;
                padding: 12px 14px;
                border-radius: 6px;
                margin-bottom: 25px;
                font-size: 11px;
                line-height: 1.6;
                color: #555;
            }
            
            .notes-title {
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 6px;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .footer-section {
                margin-top: auto;
                padding-top: 15px;
                border-top: 2px solid #e9ecef;
                text-align: center;
                font-size: 10px;
                color: #999;
                line-height: 1.6;
            }
            
            .footer-divider {
                height: 1px;
                background: #e9ecef;
                margin: 8px 0;
            }
            
            @media print {
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                body { background: white; }
                .invoice-container { 
                    max-width: 100%;
                    height: auto;
                    margin: 0;
                    padding: 18mm;
                    box-shadow: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <!-- Premium Header -->
            <div class="header-section">
                <div class="company-header">
                    <?php if ($displayLogo && file_exists($displayLogo)): ?>
                        <div class="company-logo">
                            <img src="<?php echo $displayLogo; ?>" alt="Logo">
                        </div>
                    <?php else: ?>
                        <div class="company-logo" style="font-size: 40px; color: <?php echo $companySettings['business_color']; ?>;">
                            <?php echo $companySettings['business_icon']; ?>
                        </div>
                    <?php endif; ?>
                    <div class="company-info">
                        <div class="company-name"><?php echo $companySettings['name']; ?></div>
                        <div class="company-details">
                            <?php if ($companySettings['address']): ?>
                                <?php echo htmlspecialchars($companySettings['address']); ?><br>
                            <?php endif; ?>
                            <?php if ($companySettings['phone']): ?>
                                Tel: <?php echo htmlspecialchars($companySettings['phone']); ?><br>
                            <?php endif; ?>
                            <?php if ($companySettings['email']): ?>
                                Email: <?php echo htmlspecialchars($companySettings['email']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="invoice-meta">
                    <div class="invoice-label">Invoice</div>
                    <div class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                    <div class="invoice-date"><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></div>
                    <div style="margin-top: 8px;">
                        <span class="status-badge <?php 
                            echo ($invoice['payment_status'] === 'paid') ? 'status-paid' : 
                                 (($invoice['payment_status'] === 'unpaid') ? 'status-unpaid' : 'status-partial'); 
                        ?>">
                            <?php 
                                echo ($invoice['payment_status'] === 'paid') ? '✓ LUNAS' : 
                                     (($invoice['payment_status'] === 'unpaid') ? '⏳ BELUM BAYAR' : '⚠ SEBAGIAN'); 
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Customer & Details Info -->
            <div class="info-section">
                <div class="info-box">
                    <span class="section-label">Kepada:</span>
                    <div class="info-content">
                        <div class="info-name"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                        <div class="info-detail">
                            <?php if ($invoice['customer_address']): ?>
                                <?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?><br>
                            <?php endif; ?>
                            <?php if ($invoice['customer_phone']): ?>
                                Tel: <?php echo htmlspecialchars($invoice['customer_phone']); ?><br>
                            <?php endif; ?>
                            <?php if ($invoice['customer_email']): ?>
                                Email: <?php echo htmlspecialchars($invoice['customer_email']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-box">
                    <span class="section-label">Detail Invoice:</span>
                    <div class="info-content">
                        <table style="font-size: 11px;">
                            <tr>
                                <td class="label" style="color: #999;">Divisi:</td>
                                <td style="text-align: right; font-weight: 700;"><?php echo htmlspecialchars($invoice['division_name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="label" style="color: #999;">Metode Bayar:</td>
                                <td style="text-align: right; font-weight: 700;">
                                    <?php 
                                        $payment_methods = [
                                            'cash' => '💵 Cash',
                                            'debit' => '💳 Debit',
                                            'transfer' => '🔄 Transfer',
                                            'qr' => '📱 QR Code',
                                            'other' => '➕ Lainnya'
                                        ];
                                        echo $payment_methods[$invoice['payment_method']] ?? ucfirst($invoice['payment_method']);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="label" style="color: #999;">Dibuat oleh:</td>
                                <td style="text-align: right; font-weight: 700;"><?php echo htmlspecialchars($invoice['created_by_name'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="items-section">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 35%;">Item / Layanan</th>
                            <th style="width: 15%;">Kategori</th>
                            <th style="width: 10%; text-align: right;">Qty</th>
                            <th style="width: 20%; text-align: right;">Harga Satuan</th>
                            <th style="width: 15%; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if ($item['item_description']): ?>
                                        <br><span style="color: #999; font-size: 10px;"><?php echo htmlspecialchars($item['item_description']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #e9ecef; padding: 3px 8px; border-radius: 3px; font-size: 10px;">
                                        <?php echo htmlspecialchars($item['category'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="text-right"><?php echo number_format($item['quantity'], 0, ',', '.'); ?></td>
                                <td class="text-right">Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?></td>
                                <td class="text-right" style="font-weight: 700;">Rp <?php echo number_format($item['quantity'] * $item['unit_price'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary -->
            <div class="summary-section">
                <table class="summary-table">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">Rp <?php echo number_format($invoice['subtotal'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php if ($invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td class="label">Diskon</td>
                            <td class="value">- Rp <?php echo number_format($invoice['discount_amount'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($invoice['tax_amount'] > 0): ?>
                        <tr>
                            <td class="label">Pajak</td>
                            <td class="value">+ Rp <?php echo number_format($invoice['tax_amount'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="summary-row-total">
                        <td class="label">Total</td>
                        <td class="value">Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Notes -->
            <?php if ($invoice['notes']): ?>
                <div class="notes-section">
                    <div class="notes-title">📝 Catatan:</div>
                    <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer-section">
                <strong><?php echo htmlspecialchars($companySettings['name']); ?></strong>
                <div class="footer-divider"></div>
                Terima kasih atas kepercayaan Anda. Invoice ini adalah bukti transaksi yang sah.
                <br>
                Cetak Tanggal: <?php echo date('d M Y H:i'); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Normal view mode
$pageTitle = 'Invoice ' . $invoice['invoice_number'];
include '../../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <!-- Actions -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <a href="index.php" class="btn btn-secondary">
            <i data-feather="arrow-left" style="width: 16px; height: 16px;"></i> Kembali
        </a>
        <div style="display: flex; gap: 0.75rem;">
            <a href="view-invoice.php?id=<?php echo $invoice_id; ?>&export=pdf" class="btn btn-success">
                <i data-feather="download" style="width: 16px; height: 16px;"></i> Export PDF
            </a>
            <a href="view-invoice.php?id=<?php echo $invoice_id; ?>&print=1" target="_blank" class="btn btn-primary">
                <i data-feather="printer" style="width: 16px; height: 16px;"></i> Print Invoice
            </a>
        </div>
    </div>
    
    <!-- Invoice Preview Card -->
    <div class="card" style="padding: 2rem;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 3px solid var(--primary-color);">
            <div>
                <div style="font-size: 2rem; font-weight: 900; background: linear-gradient(135deg, <?php echo BUSINESS_COLOR; ?>, #000); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo BUSINESS_NAME; ?></div>
                <div style="font-size: 3rem; line-height: 1; margin-top: 0.5rem;"><?php echo BUSINESS_ICON; ?></div>
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem; line-height: 1.5;">
                    <?php 
                    $address = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_address'")['setting_value'] ?? '';
                    $phone = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_phone'")['setting_value'] ?? '';
                    $email = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_email'")['setting_value'] ?? '';
                    if ($address) echo nl2br($address) . '<br>';
                    if ($phone) echo 'Tel: ' . $phone . '<br>';
                    if ($email) echo 'Email: ' . $email;
                    ?>
                </div>
            </div>
            
            <div style="text-align: right;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $invoice['invoice_number']; ?></div>
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;"><?php echo date('d F Y', strtotime($invoice['invoice_date'])); ?></div>
                <?php
                $status_colors = ['paid' => 'success', 'unpaid' => 'danger', 'partial' => 'warning'];
                $status_labels = ['paid' => '✓ LUNAS', 'unpaid' => '⏳ BELUM BAYAR', 'partial' => '⏱ SEBAGIAN'];
                ?>
                <span class="badge badge-<?php echo $status_colors[$invoice['payment_status']]; ?>" style="margin-top: 0.75rem; font-size: 0.875rem; padding: 0.5rem 1rem;">
                    <?php echo $status_labels[$invoice['payment_status']]; ?>
                </span>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div style="background: var(--bg-secondary); padding: 1.25rem; border-radius: 0.75rem; margin-bottom: 2rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">Kepada:</div>
            <div style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;"><?php echo $invoice['customer_name']; ?></div>
            <?php if ($invoice['customer_address']): ?>
                <div style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.25rem;"><?php echo nl2br($invoice['customer_address']); ?></div>
            <?php endif; ?>
            <?php if ($invoice['customer_phone']): ?>
                <div style="color: var(--text-muted); font-size: 0.875rem;">Tel: <?php echo $invoice['customer_phone']; ?></div>
            <?php endif; ?>
            <?php if ($invoice['customer_email']): ?>
                <div style="color: var(--text-muted); font-size: 0.875rem;">Email: <?php echo $invoice['customer_email']; ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Items Table -->
        <div class="table-responsive" style="margin-bottom: 2rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Item / Layanan</th>
                        <th style="width: 100px;" class="text-center">Qty</th>
                        <th style="width: 150px;" class="text-right">Harga Satuan</th>
                        <th style="width: 150px;" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td class="text-center"><?php echo $index + 1; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo $item['item_name']; ?></div>
                                <?php if ($item['item_description']): ?>
                                    <div style="font-size: 0.813rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo $item['item_description']; ?></div>
                                <?php endif; ?>
                                <?php if ($item['category']): ?>
                                    <span class="badge badge-secondary" style="margin-top: 0.25rem; font-size: 0.75rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['category'])); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo number_format($item['quantity'], 0, ',', '.'); ?></td>
                            <td class="text-right">Rp <?php echo number_format($item['unit_price'], 0, ',', '.'); ?></td>
                            <td class="text-right" style="font-weight: 700;">Rp <?php echo number_format($item['quantity'] * $item['unit_price'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary -->
        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 350px; background: var(--bg-secondary); padding: 1.5rem; border-radius: 0.75rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                    <span>Subtotal</span>
                    <span>Rp <?php echo number_format($invoice['subtotal'], 0, ',', '.'); ?></span>
                </div>
                <?php if ($invoice['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #ef4444;">
                        <span>Diskon</span>
                        <span>- Rp <?php echo number_format($invoice['discount_amount'], 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($invoice['tax_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span>Pajak</span>
                        <span>Rp <?php echo number_format($invoice['tax_amount'], 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>
                <div style="border-top: 2px solid var(--bg-tertiary); padding-top: 0.75rem; margin-top: 0.75rem; display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">
                    <span>TOTAL</span>
                    <span>Rp <?php echo number_format($invoice['total_amount'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <?php if ($invoice['notes']): ?>
            <div style="margin-top: 2rem; background: var(--bg-secondary); padding: 1.25rem; border-radius: 0.75rem;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem;">Catatan:</div>
                <div style="color: var(--text-muted); font-size: 0.875rem;"><?php echo nl2br($invoice['notes']); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Footer Info -->
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--bg-tertiary); text-align: center; color: var(--text-muted); font-size: 0.875rem;">
            <div>Dibuat oleh: <?php echo $invoice['created_by_name']; ?> | Tanggal: <?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?></div>
            <div style="margin-top: 0.5rem;">
                <strong>Metode Pembayaran:</strong> 
                <?php 
                    $payment_methods = [
                        'cash' => '💵 Cash',
                        'debit' => '💳 Debit Card',
                        'transfer' => '🔄 Transfer',
                        'qr' => '📱 QR Code',
                        'other' => '➕ Lainnya'
                    ];
                    echo $payment_methods[$invoice['payment_method']] ?? ucfirst($invoice['payment_method']);
                ?>
            </div>
        </div>
    </div>
</div>

<script>
feather.replace();
</script>

<?php include '../../includes/footer.php'; ?>
