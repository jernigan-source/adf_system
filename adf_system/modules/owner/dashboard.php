<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Check if user is authorized to view owner dashboard
// Allow admin, manager, and anyone with owner role
$userRole = $_SESSION['role'] ?? '';
if ($userRole !== 'admin' && $userRole !== 'owner' && $userRole !== 'manager') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get company name from settings
$companyNameSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
$displayCompanyName = ($companyNameSetting && $companyNameSetting['setting_value']) 
    ? $companyNameSetting['setting_value'] 
    : 'Narayana';

// Use logo-alt.png which is verified to work
$logoFile = 'logo-alt.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-app-capable" content="yes">
    <meta name="theme-color" content="#1e1b4b">
    <title>Owner Dashboard - <?php echo $displayCompanyName; ?> Monitoring</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Mobile-First Responsive Design */
        * {
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1d3d 50%, #0f1729 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .mobile-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.9) 0%, rgba(139, 92, 246, 0.9) 100%);
            padding: 1rem 1.25rem;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3), 0 2px 8px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .header-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.3;
            letter-spacing: -0.02em;
        }
        
        .header-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .refresh-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            padding: 0.5rem;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .refresh-btn:active {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0.95);
        }
        
        .branch-selector {
            background: white;
            margin: -0.5rem 1rem 1rem;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .branch-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            background: white;
            cursor: pointer;
        }
        
        .content-wrapper {
            padding: 1rem 1rem 6rem;
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.875rem;
            margin-bottom: 1.25rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.95) 100%);
            padding: 1rem;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 0 1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        /* Income Card - Green */
        .stat-card.income-card {
            background: linear-gradient(135deg, rgba(236, 253, 245, 0.98) 0%, rgba(209, 250, 229, 0.98) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .stat-card.income-card::before {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .stat-card.income-card .stat-label {
            color: #065f46;
        }
        
        .stat-card.income-card .stat-value {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Expense Card - Red */
        .stat-card.expense-card {
            background: linear-gradient(135deg, rgba(254, 242, 242, 0.98) 0%, rgba(254, 226, 226, 0.98) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .stat-card.expense-card::before {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }
        
        .stat-card.expense-card .stat-label {
            color: #7f1d1d;
        }
        
        .stat-card.expense-card .stat-value {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-label svg {
            width: 14px;
            height: 14px;
            opacity: 0.7;
        }
        
        .stat-value {
            font-size: 1.375rem;
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, #1e293b 0%, #4f46e5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }
        
        .stat-change {
            font-size: 0.7rem;
            margin-top: 0.35rem;
            font-weight: 600;
        }
        
        .section-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.95) 100%);
            padding: 1.25rem;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 0 1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            margin-bottom: 1.25rem;
        }
        
        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.01em;
        }
        
        .section-title svg {
            width: 18px;
            height: 18px;
            color: #6366f1;
        }
        
        /* Responsive Pie Chart Container */
        .chart-comparison-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (min-width: 768px) {
            .chart-comparison-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .chart-box {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .chart-box h4 {
            margin: 0 0 15px 0;
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
        }
        
        .chart-box canvas {
            max-width: 100%;
            height: auto !important;
        }
        
        .occupancy-bar {
            height: 36px;
            background: #f3f4f6;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            margin-bottom: 0.5rem;
        }
        
        .occupancy-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            transition: width 0.5s ease;
        }
        
        .occupancy-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: #6b7280;
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.875rem 0;
            border-bottom: 1px solid rgba(241, 245, 249, 0.8);
            gap: 0.75rem;
            transition: all 0.2s;
        }
        
        .transaction-item:hover {
            padding-left: 0.5rem;
            background: rgba(248, 250, 252, 0.5);
            margin: 0 -0.5rem;
            padding-right: 0.5rem;
            border-radius: 8px;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info {
            flex: 1;
            min-width: 0;
        }
        
        .transaction-desc {
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .transaction-meta {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .transaction-amount {
            font-size: 1rem;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            color: #6b7280;
            font-size: 0.75rem;
            text-decoration: none;
            padding: 0.25rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-item.active {
            color: #4338ca;
            background: #eef2ff;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #6b7280;
        }
        
        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 260px;
            margin-top: 1rem;
        }
        
        @media (min-width: 768px) {
            .chart-container {
                height: 360px;
            }
        }        
        .period-selector {
            display: flex;
            gap: 0.375rem;
            margin-bottom: 1rem;
            background: rgba(248, 250, 252, 0.8);
            padding: 0.35rem;
            border-radius: 12px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .period-btn {
            flex: 1;
            padding: 0.5rem 0.4rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .period-btn.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
            transform: translateY(-1px);
        }
        
        .period-btn.active {
            background: white;
            color: #4338ca;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }        
        /* Pull to Refresh */
        .pull-to-refresh {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4338ca;
            font-size: 0.875rem;
            transform: translateY(-100%);
            transition: transform 0.3s;
        }
        
        .pull-to-refresh.visible {
            transform: translateY(0);
        }
        
        /* Tablet and Desktop */
        @media (min-width: 768px) {
            .content-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 1rem 2rem 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .bottom-nav {
                display: none;
            }
        }
        
        /* Business Button Styles */
        .business-btn {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 1rem 0.75rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
            min-height: 90px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
        }
        
        .business-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(67, 56, 202, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .business-btn:hover::before {
            opacity: 1;
        }
        
        .business-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.25), 0 0 24px rgba(139, 92, 246, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
        }
        
        .business-btn.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-color: transparent;
            color: white;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.5), 0 0 40px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }
        
        .business-btn.active::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }
        
        .business-btn.all-branches {
            background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #6ee7b7 100%);
            border-color: #10b981;
            color: white;
        }
        
        .business-btn.all-branches.active {
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            box-shadow: 0 8px 28px rgba(16, 185, 129, 0.4), 0 0 30px rgba(52, 211, 153, 0.2);
        }
        
        .business-icon {
            font-size: 26px;
            line-height: 1;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            transition: all 0.3s ease;
        }
        
        .business-btn:hover .business-icon {
            transform: scale(1.1);
            filter: drop-shadow(0 3px 8px rgba(67, 56, 202, 0.3));
        }
        
        .business-btn.active .business-icon {
            filter: drop-shadow(0 2px 8px rgba(255,255,255,0.4));
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Health Indicator Styles */
        .health-indicator {
            background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 0 1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: none;
            animation: fadeIn 0.4s ease-in;
        }
        
        .health-score {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .health-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.7rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease;
        }
        
        .health-badge.excellent {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }
        
        .health-badge.good {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            color: white;
        }
        
        .health-badge.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: white;
        }
        
        .health-badge.critical {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }
        
        .health-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        
        .health-metric {
            text-align: center;
            padding: 0.875rem 0.5rem;
            background: linear-gradient(135deg, rgba(241,245,249,0.8) 0%, rgba(248,250,252,0.8) 100%);
            border-radius: 14px;
            border: 1px solid rgba(226, 232, 240, 0.5);
            transition: all 0.2s;
        }
        
        .health-metric:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        
        .health-metric-label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
        }
        
        .health-metric-value {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .business-name {
            font-size: 0.65rem;
            font-weight: 600;
            line-height: 1.1;
            color: #374151;
        }
        
        .business-btn.active .business-name {
            color: white;
        }
        
        .business-btn.all-branches .business-name {
            color: white;
        }
    </style>
</head>
<body>
    <div class="pull-to-refresh" id="pullToRefresh">
        <div class="loading-spinner"></div>
        <span style="margin-left: 0.5rem;">Menarik untuk refresh...</span>
    </div>
    
    <div class="mobile-header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                <!-- Logo Hotel -->
                <img src="../../uploads/logos/logo-alt.png" 
                     alt="Narayana Hotel" 
                     id="headerLogo"
                     style="height: 45px; width: auto; object-fit: contain; background: white; padding: 0.5rem; border-radius: 8px;"
                     onerror="console.error('Logo error:', this.src); this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                <div id="logoFallback" style="width: 45px; height: 45px; background: white; border-radius: 8px; display: none; align-items: center; justify-content: center; font-size: 1.5rem;">
                    🏨
                </div>
                <div>
                    <div class="header-title"><?php echo htmlspecialchars($displayCompanyName); ?></div>
                    <div class="header-subtitle" id="currentTime">Loading...</div>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="manage-user-access.php" class="refresh-btn" style="text-decoration: none; color: white; display: flex; align-items: center;" title="Manage User Access">
                    <i data-feather="users" style="width: 20px; height: 20px;"></i>
                </a>
                <button class="refresh-btn" onclick="refreshData()" id="refreshBtn">
                    <i data-feather="refresh-cw" style="width: 20px; height: 20px;"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Business Selector - Button Grid -->
    <div class="business-selector" style="padding: 1.25rem; background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 100%); border-radius: 20px; margin: 1rem; box-shadow: 0 4px 24px rgba(0,0,0,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <div style="font-size: 0.9rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem;">
                <i data-feather="briefcase" style="width: 16px; height: 16px; color: #6366f1;"></i>
                Pilih Bisnis Anda
            </div>
            <div id="selectedBusinessName" style="font-size: 0.75rem; color: #6366f1; font-weight: 600; background: rgba(99, 102, 241, 0.1); padding: 0.3rem 0.75rem; border-radius: 8px;">All Businesses</div>
        </div>
        <div id="businessButtons" class="business-buttons-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.875rem;">
            <!-- Buttons will be inserted here by JavaScript -->
        </div>
    </div>
    
    <!-- AI Health Indicator - Otomatis berubah sesuai bisnis -->
    <div id="healthIndicator" class="health-indicator" style="margin: 1rem; display: none;">
        <div class="health-score">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">
                    🤖 AI Health Score
                </div>
                <div id="businessNameHealth" style="font-size: 0.875rem; font-weight: 600; color: #1e293b;"></div>
            </div>
            <div id="healthBadge" class="health-badge excellent">
                <span>●</span>
                <span id="healthStatus">Excellent</span>
            </div>
        </div>
        <div class="health-metrics">
            <div class="health-metric">
                <div class="health-metric-label">Profit</div>
                <div class="health-metric-value" id="healthProfit">0%</div>
            </div>
            <div class="health-metric">
                <div class="health-metric-label">Growth</div>
                <div class="health-metric-value" id="healthGrowth">0%</div>
            </div>
            <div class="health-metric">
                <div class="health-metric-label">Efficiency</div>
                <div class="health-metric-value" id="healthEfficiency">0%</div>
            </div>
        </div>
        <div id="healthRecommendation" style="margin-top: 1rem; padding: 0.875rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%); border-radius: 14px; font-size: 0.8rem; color: #475569; line-height: 1.5; border: 1px solid rgba(99, 102, 241, 0.2); box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);">
            <strong style="color: #4f46e5; font-size: 0.8rem; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <span style="font-size: 1.1rem;">💡</span> Smart AI Insight
            </strong>
            <span id="healthInsight" style="display: block; color: #334155; font-weight: 500;">Loading intelligent analysis...</span>
        </div>
    </div>
    
    <!-- Comparison View - Only visible when All Branches selected -->
    <div id="comparisonView" class="content-wrapper" style="display: none;">
        <div class="section-card">
            <div class="section-title">
                <i data-feather="bar-chart-2" style="width: 20px; height: 20px; color: #4338ca;"></i>
                <span>Business Performance Comparison</span>
            </div>
            <div class="period-selector">
                <button class="period-btn active" onclick="changeComparisonPeriod('today')" data-period="today">
                    Today
                </button>
                <button class="period-btn" onclick="changeComparisonPeriod('this_month')" data-period="this_month">
                    This Month
                </button>
                <button class="period-btn" onclick="changeComparisonPeriod('this_year')" data-period="this_year">
                    This Year
                </button>
            </div>
            
            <!-- Modern Bar Chart -->
            <div style="margin-top: 20px; padding: 1.5rem; background: linear-gradient(135deg, rgba(249, 250, 251, 0.95) 0%, rgba(255, 255, 255, 0.95) 100%); border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
                <div style="position: relative; height: 350px;">
                    <canvas id="comparisonBarChart"></canvas>
                </div>
                
                <!-- Legend Modern -->
                <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 1.5rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #10b981 0%, #059669 100%);"></div>
                        <span style="font-size: 0.875rem; font-weight: 600; color: #065f46;">Income</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);"></div>
                        <span style="font-size: 0.875rem; font-weight: 600; color: #7f1d1d;">Expense</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);"></div>
                        <span style="font-size: 0.875rem; font-weight: 600; color: #312e81;">Net Profit</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Business Cards Grid -->
        <div id="businessCardsGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 20px;">
            <!-- Business cards will be inserted here by JavaScript -->
        </div>
    </div>
    
    <!-- Single Branch View - visible by default -->
    <div id="singleBranchView" class="content-wrapper">
        <!-- Chart Section - MOVED TO TOP -->
        <div class="section-card">
            <div class="section-title">
                <i data-feather="bar-chart-2" style="width: 20px; height: 20px; color: #4338ca;"></i>
                <span id="chartTitle">Trend Chart</span>
            </div>
            <div class="period-selector">
                <button class="period-btn active" onclick="changePeriod('7days')" data-period="7days">
                    7 Days
                </button>
                <button class="period-btn" onclick="changePeriod('30days')" data-period="30days">
                    This Month
                </button>
                <button class="period-btn" onclick="changePeriod('12months')" data-period="12months">
                    This Year
                </button>
            </div>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card income-card">
                <div class="stat-label">
                    <i data-feather="trending-up" style="width: 14px; height: 14px;"></i>
                    Today's Income
                </div>
                <div class="stat-value" style="color: #10b981;" id="todayIncome">Rp 0</div>
                <div class="stat-change" style="color: #6b7280;" id="todayIncomeCount">0 transactions</div>
            </div>
            
            <div class="stat-card expense-card">
                <div class="stat-label">
                    <i data-feather="trending-down" style="width: 14px; height: 14px;"></i>
                    Today's Expense
                </div>
                <div class="stat-value" style="color: #ef4444;" id="todayExpense">Rp 0</div>
                <div class="stat-change" style="color: #6b7280;" id="todayExpenseCount">0 transactions</div>
            </div>
            
            <div class="stat-card income-card">
                <div class="stat-label">
                    <i data-feather="dollar-sign" style="width: 14px; height: 14px;"></i>
                    Monthly Income
                </div>
                <div class="stat-value" style="color: #10b981;" id="monthIncome">Rp 0</div>
                <div class="stat-change" style="color: #6b7280;" id="monthIncomeChange">0%</div>
            </div>
            
            <div class="stat-card expense-card">
                <div class="stat-label">
                    <i data-feather="shopping-cart" style="width: 14px; height: 14px;"></i>
                    Monthly Expense
                </div>
                <div class="stat-value" style="color: #ef4444;" id="monthExpense">Rp 0</div>
                <div class="stat-change" style="color: #6b7280;" id="monthExpenseChange">0%</div>
            </div>
        </div>
        
        <!-- Occupancy Section -->
        <div class="section-card">
            <div class="section-title">
                <i data-feather="home" style="width: 20px; height: 20px; color: #4338ca;"></i>
                Room Occupancy
            </div>
            <div class="occupancy-bar">
                <div class="occupancy-fill" id="occupancyFill" style="width: 0%;">0%</div>
            </div>
            <div class="occupancy-info">
                <span>Occupied: <strong id="occupiedRooms">0</strong></span>
                <span>Total Rooms: <strong id="totalRooms">0</strong></span>
            </div>
        </div>
        
        <!-- Health Report Link -->
        <a href="health-report.php" style="text-decoration: none;">
            <div class="section-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: pointer; transition: transform 0.3s;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem;">
                            🏥 Company Health Report
                        </div>
                        <div style="font-size: 0.875rem; opacity: 0.9;">
                            AI Analysis & Business Recommendations
                        </div>
                    </div>
                    <i data-feather="chevron-right" style="width: 28px; height: 28px;"></i>
                </div>
            </div>
        </a>
        
        <!-- Recent Transactions -->
        <div class="section-card">
            <div class="section-title">
                <i data-feather="list" style="width: 20px; height: 20px; color: #4338ca;"></i>
                Recent Transactions
            </div>
            <div id="recentTransactions">
                <div class="empty-state">
                    <div class="loading-spinner" style="width: 40px; height: 40px; border-width: 4px; border-color: #e5e7eb; border-top-color: #4338ca;"></div>
                    <p style="margin-top: 1rem;">Loading transactions...</p>
                </div>
            </div>
        </div>
    </div>
    <!-- End Single Branch View -->
    
    <div class="bottom-nav">
        <a href="#" class="nav-item active">
            <i data-feather="home" style="width: 20px; height: 20px;"></i>
            Dashboard
        </a>
        <a href="health-report.php" class="nav-item">
            <i data-feather="activity" style="width: 20px; height: 20px;"></i>
            Health
        </a>
        <a href="#" class="nav-item" onclick="showOccupancy(); return false;">
            <i data-feather="grid" style="width: 20px; height: 20px;"></i>
            Occupancy
        </a>
        <a href="../../logout.php" class="nav-item">
            <i data-feather="log-out" style="width: 20px; height: 20px;"></i>
            Logout
        </a>
    </div>
    
    <script>
        let currentBranchId = null;
        let weeklyChart = null;
        let currentPeriod = '7days';
        let currentComparisonPeriod = 'today';
        let comparisonBarChart = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== Owner Dashboard Loaded ===');
            console.log('Chart.js available:', typeof Chart !== 'undefined');
            
            feather.replace();
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            
            // Initialize chart first, then load data
            console.log('Initializing chart...');
            initChart();
            console.log('Chart initialized:', weeklyChart);
            
            console.log('Loading branches...');
            loadBranches(); // This will trigger loadBranchData which loads chart data
            
            // Pull to refresh
            let startY = 0;
            let isPulling = false;
            
            document.addEventListener('touchstart', function(e) {
                if (window.pageYOffset === 0) {
                    startY = e.touches[0].pageY;
                }
            });
            
            document.addEventListener('touchmove', function(e) {
                if (window.pageYOffset === 0) {
                    let currentY = e.touches[0].pageY;
                    let pullDistance = currentY - startY;
                    
                    if (pullDistance > 60 && !isPulling) {
                        document.getElementById('pullToRefresh').classList.add('visible');
                        isPulling = true;
                    }
                }
            });
            
            document.addEventListener('touchend', function() {
                if (isPulling) {
                    refreshData();
                    setTimeout(() => {
                        document.getElementById('pullToRefresh').classList.remove('visible');
                        isPulling = false;
                    }, 1000);
                }
            });
        });
        
        function updateCurrentTime() {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('id-ID', options);
        }
        
        async function loadBranches() {
            try {
                const response = await fetch('../../api/owner-branches.php');
                const data = await response.json();
                
                if (data.success) {
                    const container = document.getElementById('businessButtons');
                    container.innerHTML = '';
                    
                    // Business icons mapping
                    const businessIcons = {
                        'narayana hotel': '🏨',
                        'bens cafe': '☕',
                        'eat & meet': '🍽️',
                        'furniture': '🪑',
                        'karimunjawa': '⛵',
                        'pabrik kapal': '🚢'
                    };
                    
                    // Add "All Businesses" button
                    const allBtn = document.createElement('button');
                    allBtn.className = 'business-btn all-branches active';
                    allBtn.onclick = () => selectBusiness(null, 'All Businesses');
                    allBtn.innerHTML = `
                        <div class="business-icon">🏢</div>
                        <div class="business-name">All<br>Businesses</div>
                    `;
                    container.appendChild(allBtn);
                    
                    // Add individual business buttons
                    data.branches.forEach(branch => {
                        const btn = document.createElement('button');
                        btn.className = 'business-btn';
                        btn.dataset.branchId = branch.id;
                        btn.onclick = () => selectBusiness(branch.id, branch.branch_name);
                        
                        // Find matching icon
                        let icon = '🏢';
                        const nameLower = branch.branch_name.toLowerCase();
                        for (const [key, value] of Object.entries(businessIcons)) {
                            if (nameLower.includes(key)) {
                                icon = value;
                                break;
                            }
                        }
                        
                        // Shorten name if too long
                        let displayName = branch.branch_name;
                        if (displayName.length > 20) {
                            displayName = displayName.substring(0, 18) + '...';
                        }
                        // Add line break for better display
                        displayName = displayName.replace(/ - /g, '<br>').replace(/ /g, ' ');
                        
                        btn.innerHTML = `
                            <div class="business-icon">${icon}</div>
                            <div class="business-name">${displayName}</div>
                        `;
                        container.appendChild(btn);
                    });
                    
                    feather.replace();
                    
                    // Auto-select All Businesses by default
                    selectBusiness(null, 'All Businesses');
                } else {
                    console.error('Failed to load branches:', data);
                    // Even if no branches, try to load data
                    loadBranchData();
                }
            } catch (error) {
                console.error('Error loading branches:', error);
                // Try to load data anyway
                loadBranchData();
            }
        }
        
        function selectBusiness(branchId, branchName) {
            currentBranchId = branchId;
            
            // Update button active states
            document.querySelectorAll('.business-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (branchId === null) {
                document.querySelector('.business-btn.all-branches').classList.add('active');
                // Hide health indicator for All Businesses
                document.getElementById('healthIndicator').style.display = 'none';
            } else {
                const selectedBtn = document.querySelector(`[data-branch-id="${branchId}"]`);
                if (selectedBtn) selectedBtn.classList.add('active');
                // Show and load health indicator for specific business
                loadHealthIndicator(branchId, branchName);
            }
            
            // Update selected business name display
            document.getElementById('selectedBusinessName').textContent = branchName;
            
            // Load data for selected business
            loadBranchData();
        }
        
        async function loadHealthIndicator(branchId, branchName) {
            try {
                // Fetch stats for this business
                const response = await fetch(`../../api/owner-stats.php?branch_id=${branchId}`);
                const data = await response.json();
                
                if (data.success && data.stats) {
                    const stats = data.stats;
                    
                    // Calculate metrics
                    const todayIncome = parseFloat(stats.today_income || 0);
                    const todayExpense = parseFloat(stats.today_expense || 0);
                    const monthIncome = parseFloat(stats.month_income || 0);
                    const monthExpense = parseFloat(stats.month_expense || 0);
                    
                    // Profit Margin (current month)
                    const profitMargin = monthIncome > 0 
                        ? ((monthIncome - monthExpense) / monthIncome * 100) 
                        : 0;
                    
                    // Growth Rate (compare today vs average daily)
                    const today = new Date();
                    const dayOfMonth = today.getDate();
                    const avgDailyIncome = monthIncome / dayOfMonth;
                    const growthRate = avgDailyIncome > 0 
                        ? ((todayIncome - avgDailyIncome) / avgDailyIncome * 100) 
                        : 0;
                    
                    // Efficiency Score (revenue per expense)
                    const efficiency = (monthIncome + monthExpense) > 0 
                        ? (monthIncome / (monthIncome + monthExpense) * 100) 
                        : 50;
                    
                    // Calculate overall health score (weighted average)
                    const healthScore = (profitMargin * 0.5) + (Math.min(Math.max(growthRate, 0), 100) * 0.3) + (efficiency * 0.2);
                    
                    // Determine health status
                    let healthStatus, healthClass;
                    if (healthScore >= 80) {
                        healthStatus = 'Excellent';
                        healthClass = 'excellent';
                    } else if (healthScore >= 60) {
                        healthStatus = 'Good';
                        healthClass = 'good';
                    } else if (healthScore >= 40) {
                        healthStatus = 'Warning';
                        healthClass = 'warning';
                    } else {
                        healthStatus = 'Critical';
                        healthClass = 'critical';
                    }
                    
                    // Generate AI insight
                    let insight = '';
                    if (profitMargin < 20) {
                        insight = '💡 Margin keuntungan rendah. Pertimbangkan optimalisasi biaya operasional atau tingkatkan harga jual.';
                    } else if (profitMargin >= 50) {
                        insight = '🎯 Performa sangat baik! Pertahankan strategi bisnis yang ada dan pertimbangkan ekspansi.';
                    } else if (growthRate < -10) {
                        insight = '📉 Tren penurunan terdeteksi. Fokus pada retensi pelanggan dan strategi marketing.';
                    } else if (efficiency < 60) {
                        insight = '⚠️ Rasio expense tinggi. Review struktur biaya dan identifikasi area penghematan.';
                    } else if (healthScore >= 70) {
                        insight = '✨ Bisnis dalam kondisi sehat. Terus monitor performa dan maintain service quality.';
                    } else {
                        insight = '📊 Performa stabil. Cari peluang untuk meningkatkan efisiensi dan revenue.';
                    }
                    
                    // Update UI
                    document.getElementById('businessNameHealth').textContent = branchName;
                    document.getElementById('healthBadge').className = `health-badge ${healthClass}`;
                    document.getElementById('healthStatus').textContent = healthStatus;
                    document.getElementById('healthProfit').textContent = `${profitMargin.toFixed(1)}%`;
                    document.getElementById('healthGrowth').textContent = `${growthRate.toFixed(1)}%`;
                    document.getElementById('healthEfficiency').textContent = `${efficiency.toFixed(1)}%`;
                    document.getElementById('healthInsight').textContent = insight;
                    
                    // Show the health indicator with animation
                    const healthIndicator = document.getElementById('healthIndicator');
                    healthIndicator.style.display = 'block';
                    healthIndicator.style.animation = 'fadeIn 0.3s ease-in';
                }
            } catch (error) {
                console.error('Error loading health indicator:', error);
            }
        }
        
        async function loadBranchData() {
            // Toggle views based on selection
            if (currentBranchId === null) {
                // Show comparison view for All Branches
                document.getElementById('singleBranchView').style.display = 'none';
                document.getElementById('comparisonView').style.display = 'block';
                loadComparisonData();
            } else {
                // Show single branch view
                document.getElementById('singleBranchView').style.display = 'block';
                document.getElementById('comparisonView').style.display = 'none';
                await Promise.all([
                    loadStats(),
                    loadOccupancy(),
                    loadChartData(),
                    loadRecentTransactions()
                ]);
            }
        }
        
        async function loadStats() {
            try {
                const url = currentBranchId 
                    ? `../../api/owner-stats.php?branch_id=${currentBranchId}`
                    : '../../api/owner-stats.php';
                
                console.log('Loading stats from:', url);
                    
                const response = await fetch(url);
                const data = await response.json();
                
                console.log('Stats response:', data);
                
                if (data.success) {
                    document.getElementById('todayIncome').textContent = formatRupiah(data.today.income);
                    document.getElementById('todayExpense').textContent = formatRupiah(data.today.expense);
                    document.getElementById('todayIncomeCount').textContent = `${data.today.income_count} transactions`;
                    document.getElementById('todayExpenseCount').textContent = `${data.today.expense_count} transactions`;
                    
                    document.getElementById('monthIncome').textContent = formatRupiah(data.month.income);
                    document.getElementById('monthExpense').textContent = formatRupiah(data.month.expense);
                    
                    // Calculate change percentages
                    const incomeChange = data.month.income_change || 0;
                    const expenseChange = data.month.expense_change || 0;
                    
                    document.getElementById('monthIncomeChange').textContent = 
                        `${incomeChange >= 0 ? '+' : ''}${incomeChange.toFixed(1)}% vs last month`;
                    document.getElementById('monthExpenseChange').textContent = 
                        `${expenseChange >= 0 ? '+' : ''}${expenseChange.toFixed(1)}% vs last month`;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        async function loadOccupancy() {
            try {
                const url = currentBranchId 
                    ? `../../api/owner-occupancy.php?branch_id=${currentBranchId}`
                    : '../../api/owner-occupancy.php';
                    
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    const percentage = data.occupancy_rate || 0;
                    document.getElementById('occupancyFill').style.width = percentage + '%';
                    document.getElementById('occupancyFill').textContent = percentage.toFixed(1) + '%';
                    document.getElementById('occupiedRooms').textContent = data.occupied_rooms || 0;
                    document.getElementById('totalRooms').textContent = data.total_rooms || 0;
                }
            } catch (error) {
                console.error('Error loading occupancy:', error);
            }
        }
        
        function changePeriod(period) {
            currentPeriod = period;
            
            // Update button states
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-period="${period}"]`).classList.add('active');
            
            // Update chart title
            const titles = {
                '7days': '7-Day Trend',
                '30days': 'This Month Trend',
                '12months': 'This Year Trend'
            };
            document.getElementById('chartTitle').textContent = titles[period];
            
            // Reload chart data
            loadChartData();
        }
        
        async function loadChartData() {
            try {
                let url = `../../api/owner-chart-data.php?period=${currentPeriod}`;
                if (currentBranchId) {
                    url += `&branch_id=${currentBranchId}`;
                }
                
                console.log('Loading chart data from:', url);
                    
                const response = await fetch(url);
                const result = await response.json();
                
                console.log('Chart data response:', result);
                
                if (result.success && result.data && weeklyChart) {
                    const data = result.data;
                    weeklyChart.data.labels = data.labels;
                    weeklyChart.data.datasets[0].data = data.income;
                    weeklyChart.data.datasets[1].data = data.expense;
                    weeklyChart.update();
                } else {
                    console.error('Failed to load chart data:', result);
                }
            } catch (error) {
                console.error('Error loading chart:', error);
            }
        }
        
        async function loadRecentTransactions() {
            try {
                const url = currentBranchId 
                    ? `../../api/owner-recent-transactions.php?branch_id=${currentBranchId}`
                    : '../../api/owner-recent-transactions.php';
                    
                const response = await fetch(url);
                const data = await response.json();
                
                const container = document.getElementById('recentTransactions');
                
                if (data.success && data.transactions.length > 0) {
                    container.innerHTML = '';
                    data.transactions.forEach(trans => {
                        const item = document.createElement('div');
                        item.className = 'transaction-item';
                        
                        const isIncome = trans.transaction_type === 'income';
                        const color = isIncome ? '#10b981' : '#ef4444';
                        const icon = isIncome ? 'arrow-up' : 'arrow-down';
                        
                        item.innerHTML = `
                            <div class="transaction-info">
                                <div class="transaction-desc">
                                    <i data-feather="${icon}" style="width: 14px; height: 14px; color: ${color};"></i>
                                    ${trans.description || trans.category_name}
                                </div>
                                <div class="transaction-meta">
                                    ${trans.division_name} • ${formatDate(trans.transaction_date)} ${trans.transaction_time}
                                </div>
                            </div>
                            <div class="transaction-amount" style="color: ${color};">
                                ${isIncome ? '+' : '-'} ${formatRupiah(trans.amount)}
                            </div>
                        `;
                        container.appendChild(item);
                    });
                    feather.replace();
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i data-feather="inbox" class="empty-icon"></i>
                            <p>No transactions today</p>
                        </div>
                    `;
                    feather.replace();
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        }
        
        function initChart() {
            const canvas = document.getElementById('weeklyChart');
            if (!canvas) {
                console.error('Canvas element weeklyChart not found!');
                return;
            }
            
            console.log('Initializing chart on canvas:', canvas);
            const ctx = canvas.getContext('2d');
            
            weeklyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Income',
                            data: [],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Expense',
                            data: [],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 11 }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                                },
                                font: { size: 10 }
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        }
        
        async function refreshData() {
            const btn = document.getElementById('refreshBtn');
            btn.innerHTML = '<div class="loading-spinner"></div>';
            btn.disabled = true;
            
            await loadBranchData();
            
            setTimeout(() => {
                btn.innerHTML = '<i data-feather="refresh-cw" style="width: 20px; height: 20px;"></i>';
                feather.replace();
                btn.disabled = false;
            }, 500);
        }
        
        function formatRupiah(number) {
            return 'Rp ' + Number(number).toLocaleString('id-ID');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { day: 'numeric', month: 'short' };
            return date.toLocaleDateString('id-ID', options);
        }
        
        function showReports() {
            alert('Fitur laporan akan tersedia di update berikutnya');
        }
        
        function showOccupancy() {
            alert('Detailed occupancy feature will be available in the next update');
        }
        
        // ===== COMPARISON VIEW FUNCTIONS =====
        async function loadComparisonData() {
            try {
                const response = await fetch(`../../api/owner-comparison.php?period=${currentComparisonPeriod}`);
                const data = await response.json();
                
                console.log('Comparison data:', data);
                
                if (data.success && data.businesses) {
                    renderComparisonCharts(data);
                    renderBusinessCards(data.businesses);
                }
            } catch (error) {
                console.error('Error loading comparison data:', error);
            }
        }
        
        function renderComparisonCharts(data) {
            const businesses = data.businesses;
            const labels = businesses.map(b => b.name);
            const incomeData = businesses.map(b => b.income);
            const expenseData = businesses.map(b => b.expense);
            const netData = businesses.map(b => b.net);
            
            console.log('=== Rendering Comparison Chart ===');
            console.log('Has branch_id:', data.has_branch_id);
            console.log('Businesses:', businesses);
            console.log('Labels:', labels);
            console.log('Income Data:', incomeData);
            console.log('Expense Data:', expenseData);
            console.log('Net Data:', netData);
            
            // Destroy existing chart
            if (comparisonBarChart) {
                comparisonBarChart.destroy();
            }
            
            // Modern Line Chart (Trading Style)
            const ctx = document.getElementById('comparisonBarChart').getContext('2d');
            comparisonBarChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomeData,
                            backgroundColor: (context) => {
                                const ctx = context.chart.ctx;
                                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                                gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
                                gradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');
                                return gradient;
                            },
                            borderColor: '#10b981',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#10b981',
                            pointHoverBorderWidth: 3,
                        },
                        {
                            label: 'Expense',
                            data: expenseData,
                            backgroundColor: (context) => {
                                const ctx = context.chart.ctx;
                                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                                gradient.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
                                gradient.addColorStop(1, 'rgba(239, 68, 68, 0.01)');
                                return gradient;
                            },
                            borderColor: '#ef4444',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#ef4444',
                            pointHoverBorderWidth: 3,
                        },
                        {
                            label: 'Net Profit',
                            data: netData,
                            backgroundColor: (context) => {
                                const ctx = context.chart.ctx;
                                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                                gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
                                gradient.addColorStop(1, 'rgba(99, 102, 241, 0.01)');
                                return gradient;
                            },
                            borderColor: '#6366f1',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#6366f1',
                            pointHoverBorderWidth: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false // Custom legend below
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleColor: '#f1f5f9',
                            bodyColor: '#e2e8f0',
                            padding: 16,
                            cornerRadius: 12,
                            displayColors: true,
                            boxWidth: 12,
                            boxHeight: 12,
                            boxPadding: 6,
                            borderColor: 'rgba(148, 163, 184, 0.2)',
                            borderWidth: 1,
                            titleFont: {
                                size: 13,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 12,
                                weight: '500'
                            },
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = formatRupiah(context.parsed.y);
                                    return ' ' + label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: window.innerWidth < 768 ? 8 : 9,
                                    weight: '500'
                                },
                                color: '#64748b',
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.08)',
                                drawBorder: false,
                                lineWidth: 1
                            },
                            ticks: {
                                font: {
                                    size: 10,
                                    weight: '500'
                                },
                                color: '#94a3b8',
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                    } else if (value >= 1000) {
                                        return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                    }
                                    return 'Rp ' + value;
                                },
                                padding: 10
                            },
                            border: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        function renderBusinessCards(businesses) {
            const container = document.getElementById('businessCardsGrid');
            container.innerHTML = '';
            
            businesses.forEach(business => {
                const card = document.createElement('div');
                card.className = 'stat-card';
                card.style.cursor = 'pointer';
                card.onclick = () => {
                    document.getElementById('branchSelect').value = business.id;
                    loadBranchData();
                };
                
                const net = business.net;
                const netColor = net >= 0 ? '#10b981' : '#ef4444';
                
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1f2937;">${business.name}</h3>
                        <i data-feather="arrow-right" style="width: 16px; height: 16px; color: #6b7280;"></i>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 5px;">Income</div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: #10b981;">${formatRupiah(business.income)}</div>
                            <div style="font-size: 0.7rem; color: #9ca3af;">${business.income_count} transactions</div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 5px;">Expense</div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: #ef4444;">${formatRupiah(business.expense)}</div>
                            <div style="font-size: 0.7rem; color: #9ca3af;">${business.expense_count} transactions</div>
                        </div>
                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 5px;">Net Profit/Loss</div>
                        <div style="font-size: 1rem; font-weight: 700; color: ${netColor};">
                            ${net >= 0 ? '+' : ''}${formatRupiah(net)}
                        </div>
                    </div>
                `;
                
                container.appendChild(card);
            });
            
            feather.replace();
        }
        
        function changeComparisonPeriod(period) {
            currentComparisonPeriod = period;
            
            // Update button states
            document.querySelectorAll('#comparisonView .period-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-period') === period) {
                    btn.classList.add('active');
                }
            });
            
            loadComparisonData();
        }
        
        // Auto refresh every 2 minutes
        setInterval(refreshData, 120000);
    </script>
</body>
</html>
